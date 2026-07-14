#!/bin/sh
set -u

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run this uninstaller as root."
	exit 1
fi

service ttyd onestop >/dev/null 2>&1 || true
service pfsense_ttyd onestop >/dev/null 2>&1 || true
service pfsense_webssh onestop >/dev/null 2>&1 || true

rm -f \
	/etc/rc.conf.d/ttyd \
	/etc/rc.conf.d/pfsense_ttyd \
	/etc/rc.conf.d/pfsense_webssh \
	/usr/local/etc/rc.d/ttyd \
	/usr/local/etc/rc.d/pfsense_ttyd \
	/usr/local/etc/rc.d/pfsense_webssh \
	/usr/local/etc/pfsense_ttyd.credential \
	/usr/local/etc/pfsense_ttyd.crt \
	/usr/local/etc/pfsense_ttyd.key \
	/usr/local/etc/pfsense_webssh.credential \
	/usr/local/etc/pfsense_webssh.crt \
	/usr/local/etc/pfsense_webssh.key \
	/usr/local/pkg/ttyd.inc \
	/usr/local/pkg/ttyd.xml \
	/usr/local/pkg/pfsense_ttyd.inc \
	/usr/local/pkg/pfsense_ttyd.xml \
	/usr/local/pkg/pfsense_webssh.inc \
	/usr/local/pkg/pfsense_webssh.xml \
	/usr/local/share/pfSense-pkg-webshell/info.xml \
	/usr/local/share/pfSense-pkg-pfsense_ttyd/info.xml \
	/usr/local/share/pfSense-pkg-pfsense_webssh/info.xml \
	/usr/local/www/diag_ttyd.php \
	/usr/local/www/diag_ttyd_settings.php \
	/usr/local/www/diag_webshell.php \
	/usr/local/bin/pfsense_ttyd \
	/usr/local/bin/pfsense_webssh \
	/var/log/ttyd.log \
	/usr/local/etc/ttyd.crt \
	/usr/local/etc/ttyd.key \
	/var/log/pfsense_ttyd.log \
	/var/log/pfsense_webssh.log

rm -rf /usr/local/share/ttyd-for-pfsense
rm -rf /usr/local/pfSense-pkg-ttyd

php <<'PHP'
<?php
require_once('/etc/inc/config.inc');

function remove_entries_by_match($path, $matches) {
	$entries = config_get_path($path, []);
	if (!is_array($entries)) {
		return;
	}

	$entries = array_values(array_filter($entries, function($entry) use ($matches) {
		if (!is_array($entry)) {
			return true;
		}

		foreach ($matches as $key => $value) {
			if (($entry[$key] ?? null) !== $value) {
				return true;
			}
		}

		return false;
	}));

	config_set_path($path, $entries);
	write_config('Remove ttyd for pfSense package metadata');
}

remove_entries_by_match('installedpackages/menu', [
	'name' => 'Web SSH Shell',
	'section' => 'Diagnostics',
]);
remove_entries_by_match('installedpackages/menu', [
	'name' => 'ttyd',
	'section' => 'Diagnostics',
]);
remove_entries_by_match('installedpackages/service', [
	'name' => 'pfsense_webssh',
]);
remove_entries_by_match('installedpackages/service', [
	'name' => 'pfsense_ttyd',
]);
remove_entries_by_match('installedpackages/service', [
	'name' => 'ttyd',
]);
remove_entries_by_match('installedpackages/package', [
	'name' => 'pfsensettyd',
]);
remove_entries_by_match('installedpackages/package', [
	'name' => 'pfsensewebssh',
]);
remove_entries_by_match('installedpackages/package', [
	'name' => 'pfsense_webssh',
]);
remove_entries_by_match('installedpackages/package', [
	'name' => 'pfsense_ttyd',
]);
remove_entries_by_match('installedpackages/package', [
	'name' => 'ttyd',
]);
PHP

echo "ttyd for pfSense has been uninstalled."
