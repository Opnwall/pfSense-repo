#!/bin/sh
set -u

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this installer as root."
    exit 1
fi

printf '\n'
printf '\033[32m====== Mihomo for pfSense Installer ======\033[0m\n'
printf '\n'

# Color settings
GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
RESET="\033[0m"

ROOT="/usr/local"
CONF_DIR="$ROOT/etc"
BIN_DIR="$ROOT/bin"
MIHOMO_ASSET="clash-meta-freebsd-amd64.xz"

# Logging helper
log() {
    local color="$1"
    local message="$2"
    printf '%b%s%b\n' "$color" "$message" "$RESET"
}

wait_for_interface() {
    local ifname="$1"
    local timeout="${2:-20}"
    local i=0

    while [ "$i" -lt "$timeout" ]; do
        if ifconfig "$ifname" >/dev/null 2>&1; then
            log "$GREEN" "Detected interface $ifname"
            return 0
        fi
        sleep 1
        i=$((i + 1))
    done

    log "$RED" "Timed out waiting for interface $ifname."
    return 1
}

require_success() {
    local status="$1"
    local message="$2"
    if [ "$status" -ne 0 ]; then
        log "$RED" "$message"
        exit 1
    fi
}

mkdir -p "$BIN_DIR" /usr/bin /etc/rc.conf.d "$CONF_DIR/mihomo"
require_success $? "Failed to create directories."

log "$YELLOW" "Copying files..."
cp -R -f src/etc/* /etc/
require_success $? "Failed to copy etc files."
cp -R -f src/usr/* /usr/
require_success $? "Failed to copy usr files."

if [ -f "src/usr/local/bin/$MIHOMO_ASSET" ]; then
    command -v xz >/dev/null 2>&1
    require_success $? "xz is required to unpack $MIHOMO_ASSET."
    xz -dc "src/usr/local/bin/$MIHOMO_ASSET" > "$BIN_DIR/mihomo"
    require_success $? "Failed to unpack the mihomo static binary."
    chmod 0755 "$BIN_DIR/mihomo"
    require_success $? "Failed to set executable permissions on mihomo."
    rm -f "$BIN_DIR/$MIHOMO_ASSET"
fi

chmod 755 /usr/bin/mihomo_sub "$CONF_DIR/rc.d/mihomo" "$CONF_DIR/mihomo/sub/sub.sh" 2>/dev/null || true

# Install Cron package when missing.
log "$YELLOW" "Installing cron package if needed..."
if ! pkg info -q pfSense-pkg-Cron > /dev/null 2>&1; then
  pkg install -y pfSense-pkg-Cron > /dev/null 2>&1
  require_success $? "Failed to install cron."
fi

log "$YELLOW" "Checking subscription dependencies..."
for dep in jq curl openssl; do
  if ! command -v "$dep" > /dev/null 2>&1; then
    pkg install -y "$dep" > /dev/null 2>&1
    require_success $? "Failed to install $dep."
  fi
done

if [ -f "$CONF_DIR/mihomo/config.yaml" ] && grep -qE "^secret:[[:space:]]*(''|\"\"|123456)?[[:space:]]*$" "$CONF_DIR/mihomo/config.yaml"; then
    generated_secret="$(openssl rand -hex 32)"
    sed -i '' "s/^secret:.*/secret: ${generated_secret}/" "$CONF_DIR/mihomo/config.yaml"
fi

chmod 0644 "$CONF_DIR/mihomo/config.yaml" 2>/dev/null || true
chmod 0600 "$CONF_DIR/mihomo/sub/env" 2>/dev/null || true

log "$YELLOW" "Registering pfSense package metadata..."
php <<'PHP'
<?php
require_once('/etc/inc/config.inc');
require_once('/etc/inc/pkg-utils.inc');

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
}

remove_entries_by_match('installedpackages/menu', [
    'name' => 'Mihomo',
    'section' => 'VPN',
]);
remove_entries_by_match('installedpackages/service', [
    'name' => 'mihomo',
]);
remove_entries_by_match('installedpackages/package', [
    'name' => 'mihomo',
]);

if (!install_package_xml('mihomo')) {
    fwrite(STDERR, "Failed to register mihomo package XML\n");
    exit(1);
}
PHP
require_success $? "Failed to register pfSense package metadata."

# Start service.
log "$YELLOW" "Starting mihomo..."
service mihomo restart > /dev/null 2>&1 || service mihomo restart > /dev/null 2>&1
require_success $? "Failed to start mihomo."
log "$YELLOW" "Waiting for the tun interface..."
wait_for_interface tun_mihomo 20 || log "$RED" "The tun interface did not appear. Firewall rules will take effect after the next filter reload once the interface exists."
log "$YELLOW" "Reloading firewall rules..."
/etc/rc.filter_configure > /dev/null 2>&1 || log "$RED" "Failed to reload firewall rules. Please run /etc/rc.filter_configure manually."
echo ""

# Done.
sleep 1
log "$GREEN" "Mihomo installation is complete. Refresh the browser and go to VPN > Mihomo to edit the configuration."
echo ""
