<?php

require_once('service-utils.inc');

function ttyd_get_config() {
	$defaults = [
		'interface' => '0.0.0.0',
		'port' => '7681',
		'base_path' => '/',
		'ssh_host' => '127.0.0.1',
		'ssh_port' => '22',
	];

	$config = config_get_path('installedpackages/ttyd/config/0', []);
	if (empty($config)) {
		$config = config_get_path('installedpackages/pfsense_ttyd/config/0', []);
	}
	if (!is_array($config)) {
		$config = [];
	}

	return array_merge($defaults, $config);
}

function ttyd_is_running() {
	$pidfile = '/var/run/ttyd.pid';
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

function ttyd_wait_for_state($want_running, $timeout_usec = 2000000) {
	$deadline = microtime(true) + ($timeout_usec / 1000000);
	do {
		if (ttyd_is_running() === $want_running) {
			return true;
		}
		usleep(100000);
	} while (microtime(true) < $deadline);

	return false;
}

function ttyd_start() {
	mwexec('/usr/local/etc/rc.d/ttyd onestart');
	ttyd_wait_for_state(true);
}

function ttyd_stop() {
	mwexec('/usr/local/etc/rc.d/ttyd onestop');
	ttyd_wait_for_state(false);
}

function ttyd_rc_shell_quote($value) {
	return "'" . str_replace("'", "'\\''", $value) . "'";
}

function ttyd_resync_config() {
	conf_mount_rw();
	$config = ttyd_get_config();

	$port = isset($config['port']) && is_numeric($config['port']) ? (int)$config['port'] : 7681;
	$interface = !empty($config['interface']) ? $config['interface'] : '0.0.0.0';
	$base_path = !empty($config['base_path']) ? $config['base_path'] : '/';
	$ssh_host = '127.0.0.1';
	$ssh_port = 22;
	$ssh_command = 'printf "login: "; read -r ttyd_login; if [ -z "$ttyd_login" ]; then echo "login is required"; exit 1; fi; exec /usr/bin/ssh -tt -p ' . $ssh_port . ' -o PreferredAuthentications=keyboard-interactive,password -o PubkeyAuthentication=no -o ServerAliveInterval=30 -o StrictHostKeyChecking=accept-new "$ttyd_login@' . $ssh_host . '"';

	$contents = "";
	$contents .= "ttyd_enable=\"YES\"\n";
	$contents .= "ttyd_port=\"" . $port . "\"\n";
	$contents .= "ttyd_interface=\"" . addcslashes($interface, "\\\"") . "\"\n";
	$contents .= "ttyd_base_path=\"" . addcslashes($base_path, "\\\"") . "\"\n";
	$contents .= "ttyd_command=" . ttyd_rc_shell_quote($ssh_command) . "\n";

	file_put_contents('/etc/rc.conf.d/ttyd', $contents);
	conf_mount_ro();

	ttyd_start();
}

function ttyd_php_install_command() {
	ttyd_resync_config();
}

function ttyd_php_deinstall_command() {
	ttyd_stop();
	@unlink('/usr/local/etc/pfsense_ttyd.credential');
	@unlink('/usr/local/etc/pfsense_ttyd.crt');
	@unlink('/usr/local/etc/pfsense_ttyd.key');
	@unlink('/var/log/pfsense_ttyd.log');
	@unlink('/usr/local/etc/ttyd.crt');
	@unlink('/usr/local/etc/ttyd.key');
	@unlink('/var/log/ttyd.log');
}
