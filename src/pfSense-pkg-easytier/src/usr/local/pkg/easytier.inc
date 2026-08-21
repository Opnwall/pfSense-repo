<?php

require_once('config.inc');
require_once('util.inc');

function easytier_tun_exists()
{
	exec('/sbin/ifconfig easytier0 >/dev/null 2>&1', $output, $status);
	return $status === 0;
}

function easytier_generate_rules($type)
{
	if ($type !== 'filter' || !easytier_tun_exists()) {
		return '';
	}

	$rules = "\n# EasyTier runtime pass rule (IPv4 any to any)\n";
	$rules .= 'pass in quick on easytier0 inet from any to any flags S/SA keep state label "easytier_any_to_any_pass"' . "\n";
	return $rules;
}

function easytier_remove_legacy_interface_assignment()
{
	$interfaces = config_get_path('interfaces', []);
	$removed_keys = [];
	foreach ($interfaces as $key => $interface) {
		if (($interface['if'] ?? '') === 'easytier0') {
			unset($interfaces[$key]);
			$removed_keys[] = $key;
		}
	}
	if (!$removed_keys) {
		return false;
	}

	config_set_path('interfaces', $interfaces);
	$rules = config_get_path('filter/rule', []);
	$rules = array_values(array_filter($rules, function ($rule) use ($removed_keys) {
		return !in_array($rule['interface'] ?? '', $removed_keys, true);
	}));
	config_set_path('filter/rule', $rules);
	write_config('Remove legacy EasyTier interface assignment');
	return true;
}

function easytier_resync_config()
{
	if (file_exists('/etc/rc.filter_configure')) {
		mwexec('/etc/rc.filter_configure');
	}
}

function easytier_php_install_command()
{
	easytier_remove_legacy_interface_assignment();
	easytier_resync_config();
}

function easytier_php_deinstall_command()
{
	easytier_remove_legacy_interface_assignment();
	easytier_resync_config();
}
