#!/bin/sh
set -u

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this uninstaller as root."
    exit 1
fi

printf '\n'
printf '\033[32m======== Mihomo for pfSense Uninstaller =========\033[0m\n'
printf '\n'

# Color settings
GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
RESET="\033[0m"

# Logging helper
log() {
    local color="$1"
    local message="$2"
    printf '%b%s%b\n' "$color" "$message" "$RESET"
}

# Remove program and configuration.
log "$YELLOW" "Removing program files and configuration, please wait..."

# Stop service.
service mihomo stop > /dev/null 2>&1 || true

# Remove pfSense package metadata.
log "$YELLOW" "Unregistering pfSense package metadata..."
php <<'PHP' || log "$RED" "Failed to unregister pfSense package metadata. Please check manually."
<?php
require_once('/etc/inc/config.inc');
require_once('/etc/inc/pkg-utils.inc');

delete_package_xml('mihomo');
PHP

log "$YELLOW" "Reloading firewall rules..."
/etc/rc.filter_configure > /dev/null 2>&1 || log "$RED" "Failed to reload firewall rules. Please run /etc/rc.filter_configure manually."

# Remove configuration.
rm -rf /usr/local/etc/mihomo

# Remove rc.d file.
rm -f /usr/local/etc/rc.d/mihomo

# Remove rc.conf file.
rm -f /etc/rc.conf.d/mihomo

# Remove package files.
rm -f /usr/local/pkg/mihomo.inc
rm -f /usr/local/pkg/mihomo.xml
rm -rf /usr/local/share/pfSense-pkg-mihomo

# Remove WebGUI files.
rm -f /usr/local/www/mihomo.php
rm -f /usr/local/www/mihomo_sub.php
rm -f /usr/local/www/mihomo_logs.php
rm -f /usr/local/www/mihomo_sub_log.php
rm -f /usr/local/www/vpn_mihomo.php
rm -f /usr/local/www/vpn_sub.php
rm -f /usr/local/www/status_mihomo_logs.php
rm -f /usr/local/www/status_sub_logs.php
rm -f /usr/local/www/status_mihomo.php
rm -f /usr/bin/mihomo_sub

# Remove binary.
rm -f /usr/local/bin/mihomo
echo ""

# Done.
log "$GREEN" "Uninstall complete. Program files, rc.d startup items, pfSense package metadata, and WebGUI pages have been removed."
echo ""
