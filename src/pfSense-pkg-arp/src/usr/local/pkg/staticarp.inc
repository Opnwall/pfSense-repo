<?php

require_once('config.inc');
require_once('util.inc');

function staticarp_interface_has_upstream_gateway($interface)
{
	$gateway = strtolower((string)($interface['gateway'] ?? ''));
	return $gateway !== '' && !in_array($gateway, ['none', 'dynamic'], true);
}

function staticarp_interface_is_protected($interfacename, $interface)
{
	if (empty($interface['if']) || empty($interface['ipaddr']) || !is_ipaddr($interface['ipaddr'])) {
		return false;
	}
	if (staticarp_interface_has_upstream_gateway($interface)) {
		return false;
	}
	return ($interfacename === 'lan' || isset($interface['enable']));
}

function staticarp_local_ipv4_addresses()
{
	global $config;

	$addresses = [];
	foreach (($config['interfaces'] ?? []) as $interface) {
		if (!empty($interface['ipaddr']) && is_ipaddr($interface['ipaddr'])) {
			$addresses[$interface['ipaddr']] = true;
		}
	}
	return $addresses;
}

function staticarp_config_dir()
{
	return '/usr/local/etc/staticarp';
}

function staticarp_ensure_config_dir()
{
	if (!is_dir(staticarp_config_dir())) {
		mkdir(staticarp_config_dir(), 0755, true);
	}
}

function staticarp_binding_entries()
{
	global $config;

	$entries = [];
	$local_addresses = staticarp_local_ipv4_addresses();
	$arplist = str_replace(',', "\n", $config['security']['arp']['list'] ?? '');
	foreach (preg_split('/\r?\n/', trim($arplist)) as $line) {
		$parts = preg_split('/\s+/', trim($line));
		if (!isset($parts[0], $parts[1]) || !is_ipaddr($parts[0]) || !is_macaddr($parts[1])) {
			continue;
		}
		if (isset($local_addresses[$parts[0]])) {
			continue;
		}
		$entries[$parts[0]] = strtolower($parts[1]);
	}
	ksort($entries, SORT_NATURAL);
	return $entries;
}

function staticarp_write_arp_file($arplist)
{
	$tmpfile = tempnam('/tmp', 'staticarp_');
	$local_addresses = staticarp_local_ipv4_addresses();
	$lines = [];
	foreach (preg_split('/\r?\n/', trim(str_replace(',', "\n", $arplist))) as $line) {
		$parts = preg_split('/\s+/', trim($line));
		if (!isset($parts[0], $parts[1]) || !is_ipaddr($parts[0]) || !is_macaddr($parts[1])) {
			continue;
		}
		if (isset($local_addresses[$parts[0]])) {
			continue;
		}
		$lines[] = $parts[0] . ' ' . strtolower($parts[1]);
	}
	file_put_contents($tmpfile, implode("\n", $lines) . "\n");
	return $tmpfile;
}

function staticarp_write_runtime_config($enabled)
{
	global $config;

	staticarp_ensure_config_dir();
	file_put_contents(staticarp_config_dir() . '/settings.conf', 'enabled=' . ($enabled ? 'YES' : 'NO') . "\n", LOCK_EX);

	$lines = [];
	foreach (staticarp_binding_entries() as $ip => $mac) {
		$lines[] = $ip . ' ' . $mac;
	}
	file_put_contents(staticarp_config_dir() . '/entries.conf', implode("\n", $lines) . (empty($lines) ? '' : "\n"), LOCK_EX);

	$interfaces = [];
	foreach (($config['interfaces'] ?? []) as $interfacename => $interface) {
		if (!staticarp_interface_is_protected($interfacename, $interface)) {
			continue;
		}
		$mode = $interface['arp'] ?? 'normal';
		$interfaces[] = $interfacename . ' ' . $interface['if'] . ' ' . $mode;
	}
	file_put_contents(staticarp_config_dir() . '/interfaces.conf', implode("\n", $interfaces) . (empty($interfaces) ? '' : "\n"), LOCK_EX);
}

function staticarp_run_control($action)
{
	if (file_exists('/usr/local/sbin/staticarpctl')) {
		mwexec_bg('/usr/local/sbin/staticarpctl ' . escapeshellarg($action));
	}
}

function staticarp_resync_config()
{
	global $config;

	$interfaces = $config['interfaces'] ?? [];
	$arpcfg = $config['security']['arp'] ?? [];

	if (isset($arpcfg['enable'])) {
		staticarp_write_runtime_config(true);
		staticarp_run_control('apply');
		return;
	}

	staticarp_write_runtime_config(false);
	staticarp_run_control('reset');
}

function staticarp_php_install_command()
{
	staticarp_ensure_config_dir();
	if (!file_exists(staticarp_config_dir() . '/settings.conf')) {
		file_put_contents(staticarp_config_dir() . '/settings.conf', "enabled=NO\n", LOCK_EX);
	}
	if (!file_exists(staticarp_config_dir() . '/entries.conf')) {
		file_put_contents(staticarp_config_dir() . '/entries.conf', '', LOCK_EX);
	}
	if (!file_exists(staticarp_config_dir() . '/interfaces.conf')) {
		file_put_contents(staticarp_config_dir() . '/interfaces.conf', '', LOCK_EX);
	}
}

function staticarp_php_deinstall_command()
{
	global $config;

	unset($config['security']['arp']);
	if (is_array($config['interfaces'])) {
		foreach (array_keys($config['interfaces']) as $interfacename) {
			unset($config['interfaces'][$interfacename]['arp']);
		}
	}
	write_config('Remove Static IP Binding package configuration');
}
