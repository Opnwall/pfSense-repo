#!/bin/sh
set -u

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this uninstaller as root."
    exit 1
fi

printf '\n'
printf '\033[32m======== sing-box for pfSense Uninstaller =========\033[0m\n'
printf '\n'

GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
RESET="\033[0m"

log() {
    local color="$1"
    local message="$2"
    printf '%b%s%b\n' "$color" "$message" "$RESET"
}

log "$YELLOW" "Removing program files and configuration, please wait..."

service sing-box stop > /dev/null 2>&1 || true

log "$YELLOW" "Unregistering pfSense package metadata..."
php <<'PHP' || log "$RED" "Failed to unregister pfSense package metadata. Please check manually."
<?php
require_once('/etc/inc/config.inc');
require_once('/etc/inc/pkg-utils.inc');

$shellcmd = 'service sing-box start';
$old_shellcmd = '/usr/local/etc/rc.syshook.d/start/99-sing-box';
$shellcmds = config_get_path('installedpackages/shellcmdsettings/config', []);

if (!is_array($shellcmds)) {
    $shellcmds = [];
}

$shellcmds = array_values(array_filter($shellcmds, function($entry) use ($shellcmd, $old_shellcmd) {
    if (!is_array($entry)) {
        return true;
    }
    return ($entry['cmd'] ?? '') !== $shellcmd && ($entry['cmd'] ?? '') !== $old_shellcmd;
}));
config_set_path('installedpackages/shellcmdsettings/config', $shellcmds);

if (file_exists('/usr/local/pkg/shellcmd.inc')) {
    require_once('/usr/local/pkg/shellcmd.inc');
    if (function_exists('shellcmd_sync_package')) {
        shellcmd_sync_package();
    }
} else {
    $system_shellcmds = config_get_path('system/shellcmd', []);
    if (!is_array($system_shellcmds)) {
        $system_shellcmds = [];
    }
    $system_shellcmds = array_values(array_filter($system_shellcmds, function($entry) use ($shellcmd, $old_shellcmd) {
        return $entry !== $shellcmd && $entry !== $old_shellcmd;
    }));
    config_set_path('system/shellcmd', $system_shellcmds);
}

delete_package_xml('sing_box');
write_config('Removed sing-box package metadata');
PHP

log "$YELLOW" "Reloading firewall rules..."
/etc/rc.filter_configure > /dev/null 2>&1 || log "$RED" "Failed to reload firewall rules. Please run /etc/rc.filter_configure manually."

rm -rf /usr/local/etc/sing-box
rm -f /usr/local/etc/rc.d/sing-box
rm -f /usr/local/etc/rc.syshook.d/start/99-sing-box
rm -f /etc/rc.conf.d/sing_box
rm -f /etc/rc.conf.d/sing-box
rm -f /usr/local/pkg/sing_box.inc
rm -f /usr/local/pkg/sing_box.xml
rm -rf /usr/local/share/pfSense-pkg-sing_box
rm -f /usr/local/bin/sing-box

rm -f /usr/local/www/sing-box.php
rm -f /usr/local/www/sing-box_sub.php
rm -f /usr/local/www/sing-box_log.php
rm -f /usr/local/www/sing-box_sub_log.php
rm -f /usr/local/www/vpn_sing_box.php
rm -f /usr/local/www/vpn_sub.php
rm -f /usr/local/www/status_sing_box_logs.php
rm -f /usr/local/www/status_sing_box.php
rm -f /usr/bin/sub

echo ""
log "$GREEN" "Uninstall complete. Program files, rc.d startup items, pfSense package metadata, and WebGUI pages have been removed."
echo ""
