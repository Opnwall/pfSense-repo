<?php

require_once('config.inc');
require_once('util.inc');
require_once('service-utils.inc');

function lucky_get_config() {
	$defaults = [
		'enable' => 'yes',
		'conf_dir' => '/usr/local/etc/lucky',
		'extra_args' => '',
		'web_port' => '16601',
	];

	$config = config_get_path('installedpackages/lucky/config/0', []);
	if (!is_array($config)) {
		$config = [];
	}

	return array_merge($defaults, $config);
}

function lucky_is_running() {
	$pidfile = '/var/run/lucky.pid';
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

function lucky_wait_for_state($want_running, $timeout_usec = 3000000) {
	$deadline = microtime(true) + ($timeout_usec / 1000000);
	do {
		if (lucky_is_running() === $want_running) {
			return true;
		}
		usleep(100000);
	} while (microtime(true) < $deadline);

	return false;
}

function lucky_service_status() {
	return lucky_is_running() ? 'running' : 'stopped';
}

function lucky_rc_quote($value) {
	return '"' . addcslashes($value, "\\\"$`") . '"';
}

function lucky_resync_config() {
	conf_mount_rw();
	$config = lucky_get_config();

	$enable = !empty($config['enable']) && $config['enable'] !== 'no' ? 'YES' : 'NO';
	$conf_dir = !empty($config['conf_dir']) ? $config['conf_dir'] : '/usr/local/etc/lucky';
	$web_port = isset($config['web_port']) && is_numeric($config['web_port']) ? (int)$config['web_port'] : 16601;
	$extra_args = $config['extra_args'] ?? '';

	$contents = "";
	$contents .= "lucky_enable=\"{$enable}\"\n";
	$contents .= "lucky_conf_dir=" . lucky_rc_quote($conf_dir) . "\n";
	$contents .= "lucky_http_port=\"{$web_port}\"\n";
	$contents .= "lucky_extra_args=" . lucky_rc_quote($extra_args) . "\n";

	file_put_contents('/etc/rc.conf.d/lucky', $contents);
	if (!is_dir($conf_dir)) {
		mkdir($conf_dir, 0755, true);
	}
	conf_mount_ro();
}

function lucky_start() {
	lucky_resync_config();
	mwexec('/usr/local/etc/rc.d/lucky onestart');
	lucky_wait_for_state(true);
}

function lucky_stop() {
	mwexec('/usr/local/etc/rc.d/lucky onestop');
	lucky_wait_for_state(false);
}

function lucky_restart() {
	lucky_stop();
	lucky_start();
}

function lucky_php_install_command() {
	lucky_resync_config();
	lucky_start();
}

function lucky_php_deinstall_command() {
	lucky_stop();
	@unlink('/etc/rc.conf.d/lucky');
	@unlink('/var/log/lucky.log');
}
