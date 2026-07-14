<?php

require_once('config.inc');
require_once('util.inc');
require_once('service-utils.inc');

function ddnsgo_get_config() {
	$defaults = [
		'enable' => 'yes',
		'config_path' => '/usr/local/etc/ddns-go/config.yaml',
		'listen' => ':9876',
		'interval' => '300',
		'extra_args' => '',
	];

	$settings = config_get_path('installedpackages/ddnsgo/config/0', []);
	if (!is_array($settings)) {
		$settings = [];
	}

	return array_merge($defaults, $settings);
}

function ddnsgo_is_running() {
	$pidfile = '/var/run/ddnsgo.pid';
	if (!is_readable($pidfile)) {
		return false;
	}

	$pid = (int)trim(file_get_contents($pidfile));
	if ($pid <= 0) {
		return false;
	}

	if (function_exists('posix_kill')) {
		return posix_kill($pid, 0);
	}

	exec('/bin/kill -0 ' . escapeshellarg((string)$pid) . ' 2>/dev/null', $output, $rc);
	return $rc === 0;
}

function ddnsgo_wait_for_state($want_running, $timeout_usec = 3000000) {
	$deadline = microtime(true) + ($timeout_usec / 1000000);
	do {
		if (ddnsgo_is_running() === $want_running) {
			return true;
		}
		usleep(100000);
	} while (microtime(true) < $deadline);

	return false;
}

function ddnsgo_service_status() {
	return ddnsgo_is_running() ? 'running' : 'stopped';
}

function ddnsgo_rc_quote($value) {
	return '"' . addcslashes($value, "\\\"$`") . '"';
}

function ddnsgo_normalize_listen($value) {
	$value = trim((string)$value);
	if ($value === '') {
		return ':9876';
	}

	if (is_numeric($value)) {
		return ':' . (string)(int)$value;
	}

	return $value;
}

function ddnsgo_resync_config() {
	conf_mount_rw();
	$settings = ddnsgo_get_config();

	$enable = !empty($settings['enable']) && $settings['enable'] !== 'no' ? 'YES' : 'NO';
	$config_path = !empty($settings['config_path']) ? $settings['config_path'] : '/usr/local/etc/ddns-go/config.yaml';
	$listen = ddnsgo_normalize_listen($settings['listen'] ?? ':9876');
	$interval = isset($settings['interval']) && is_numeric($settings['interval']) ? max(1, (int)$settings['interval']) : 300;
	$extra_args = $settings['extra_args'] ?? '';

	$contents = "";
	$contents .= "ddnsgo_enable=\"{$enable}\"\n";
	$contents .= "ddnsgo_config=" . ddnsgo_rc_quote($config_path) . "\n";
	$contents .= "ddnsgo_listen=" . ddnsgo_rc_quote($listen) . "\n";
	$contents .= "ddnsgo_interval=\"{$interval}\"\n";
	$contents .= "ddnsgo_extra_args=" . ddnsgo_rc_quote($extra_args) . "\n";

	file_put_contents('/etc/rc.conf.d/ddnsgo', $contents);
	$config_dir = dirname($config_path);
	if (!is_dir($config_dir)) {
		mkdir($config_dir, 0755, true);
	}
	conf_mount_ro();
}

function ddnsgo_start() {
	ddnsgo_resync_config();
	mwexec('/usr/local/etc/rc.d/ddnsgo onestart');
	ddnsgo_wait_for_state(true);
}

function ddnsgo_stop() {
	mwexec('/usr/local/etc/rc.d/ddnsgo onestop');
	ddnsgo_wait_for_state(false);
}

function ddnsgo_restart() {
	ddnsgo_stop();
	ddnsgo_start();
}

function ddnsgo_php_install_command() {
	ddnsgo_resync_config();
	ddnsgo_start();
}

function ddnsgo_php_deinstall_command() {
	ddnsgo_stop();
	@unlink('/etc/rc.conf.d/ddnsgo');
	@unlink('/var/log/ddnsgo.log');
	@unlink('/var/run/ddnsgo.pid');
	mwexec('/bin/rm -rf /usr/local/etc/ddns-go');
}
