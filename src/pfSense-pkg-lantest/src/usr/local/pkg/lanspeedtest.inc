<?php

require_once('config.inc');
require_once('interfaces.inc');
require_once('util.inc');

function lanspeedtest_get_config(): array {
	$defaults = ['enable' => 'yes', 'interface' => 'lan', 'port' => '3300'];
	$settings = config_get_path('installedpackages/lanspeedtest/config/0', []);
	return array_merge($defaults, is_array($settings) ? $settings : []);
}

function lanspeedtest_interface_address(string $interface): string {
	$interfaces = get_configured_interface_with_descr();
	if (!array_key_exists($interface, $interfaces)) {
		$interface = 'lan';
	}
	$address = get_interface_ip($interface);
	return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $address : '127.0.0.1';
}

function lanspeedtest_is_running(): bool {
	$pidfile = '/var/run/lanspeedtest.pid';
	if (!is_readable($pidfile)) {
		return false;
	}
	$pid = (int)trim((string)file_get_contents($pidfile));
	return $pid > 0 && posix_kill($pid, 0);
}

function lanspeedtest_service_status(): string {
	return lanspeedtest_is_running() ? 'running' : 'stopped';
}

function lanspeedtest_resync_config(): void {
	$settings = lanspeedtest_get_config();
	$enabled = ($settings['enable'] ?? 'yes') === 'yes' ? 'YES' : 'NO';
	$host = lanspeedtest_interface_address((string)($settings['interface'] ?? 'lan'));
	$port = (int)($settings['port'] ?? 3300);
	if ($port < 1024 || $port > 65535) {
		$port = 3300;
	}
	$contents = "lanspeedtest_enable=\"{$enabled}\"\n";
	$contents .= "lanspeedtest_host=\"{$host}\"\n";
	$contents .= "lanspeedtest_port=\"{$port}\"\n";
	conf_mount_rw();
	file_put_contents('/etc/rc.conf.d/lanspeedtest', $contents);
	conf_mount_ro();
}

function lanspeedtest_start(): void {
	lanspeedtest_resync_config();
	mwexec('/usr/local/etc/rc.d/lanspeedtest onestart');
	usleep(300000);
}

function lanspeedtest_stop(): void {
	mwexec('/usr/local/etc/rc.d/lanspeedtest onestop');
	usleep(200000);
}

function lanspeedtest_restart(): void {
	lanspeedtest_stop();
	lanspeedtest_start();
}

function lanspeedtest_php_install_command(): void {
	lanspeedtest_resync_config();
	lanspeedtest_start();
}

function lanspeedtest_php_deinstall_command(): void {
	lanspeedtest_stop();
	@unlink('/etc/rc.conf.d/lanspeedtest');
	@unlink('/var/log/lanspeedtest.log');
}
