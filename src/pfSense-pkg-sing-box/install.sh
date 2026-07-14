#!/bin/sh
set -u

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this installer as root."
    exit 1
fi

printf '\n'
printf '\033[32m====== sing-box for pfSense Installer ======\033[0m\n'
printf '\n'

GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
RESET="\033[0m"

ROOT="/usr/local"
CONF_DIR="$ROOT/etc"
BIN_DIR="$ROOT/bin"
SING_BOX_ASSET="bsd-box-reF1nd-freebsd-amd64.xz"

log() {
    local color="$1"
    local message="$2"
    printf '%b%s%b\n' "$color" "$message" "$RESET"
}

require_success() {
    local status="$1"
    local message="$2"
    if [ "$status" -ne 0 ]; then
        log "$RED" "$message"
        exit 1
    fi
}

install_pkg_if_missing() {
    local pkg_name="$1"
    local display_name="${2:-$pkg_name}"

    log "$YELLOW" "Checking ${display_name}..."
    if ! pkg info -q "$pkg_name" > /dev/null 2>&1; then
        pkg install -y "$pkg_name" > /dev/null 2>&1
        require_success $? "Failed to install ${display_name}."
    fi
}

mkdir -p "$BIN_DIR" /usr/bin /etc/rc.conf.d "$CONF_DIR/sing-box"
require_success $? "Failed to create directories."

log "$YELLOW" "Copying files..."
cp -R -f src/etc/* /etc/
require_success $? "Failed to copy etc files."
cp -R -f src/usr/* /usr/
require_success $? "Failed to copy usr files."

if [ -f "src/usr/local/bin/$SING_BOX_ASSET" ]; then
    command -v xz >/dev/null 2>&1
    require_success $? "xz is required to unpack $SING_BOX_ASSET."
    xz -dc "src/usr/local/bin/$SING_BOX_ASSET" > "$BIN_DIR/sing-box"
    require_success $? "Failed to unpack the sing-box static binary."
    chmod 0755 "$BIN_DIR/sing-box"
    require_success $? "Failed to set executable permissions on sing-box."
    rm -f "$BIN_DIR/$SING_BOX_ASSET"
fi

chmod 700 "$CONF_DIR/sing-box" "$CONF_DIR/sing-box/sub" 2>/dev/null || true
chmod 644 "$CONF_DIR/sing-box/config.json" "$CONF_DIR/sing-box/sub/template.json" 2>/dev/null || true
chmod 600 "$CONF_DIR/sing-box/sub/env" 2>/dev/null || true
if grep -q '"secret": "CHANGE_ME_ON_INSTALL"' "$CONF_DIR/sing-box/config.json" 2>/dev/null; then
    generated_secret="$(openssl rand -hex 32)"
    sed -i '' "s/\"secret\": \"CHANGE_ME_ON_INSTALL\"/\"secret\": \"${generated_secret}\"/" "$CONF_DIR/sing-box/config.json"
fi
chmod 755 /usr/bin/sub "$CONF_DIR/rc.d/sing-box" "$CONF_DIR/sing-box/sub/sub.sh" 2>/dev/null || true
chmod +x "$CONF_DIR/sing-box/sub/sub.sh"
require_success $? "Failed to set executable permissions on the subscription script."

sleep 1
install_pkg_if_missing jq jq
install_pkg_if_missing curl curl
install_pkg_if_missing perl5 perl
install_pkg_if_missing pfSense-pkg-Cron cron

log "$YELLOW" "Configuring DNS Resolver..."
php <<'PHP'
<?php
require_once('/etc/inc/config.inc');
require_once('/etc/inc/services.inc');

global $config;

if (!is_array($config['unbound'] ?? null)) {
    $config['unbound'] = [];
}

$config['unbound']['enable'] = '';
$config['unbound']['forwarding'] = '';
$config['unbound']['active_interface'] = 'all';
$config['unbound']['outgoing_interface'] = 'all';
$config['unbound']['port'] = '53';
$config['unbound']['custom_options'] = base64_encode(
    "server:\n" .
    "    do-not-query-localhost: no\n" .
    "forward-zone:\n" .
    "    name: \".\"\n" .
    "    forward-addr: 127.0.0.1@5353\n"
);
unset($config['unbound']['dnssec']);
unset($config['unbound']['dnssecstripped']);
unset($config['unbound']['forward_tls_upstream']);

write_config('Forward public DNS from Unbound to sing-box split DNS');

if (function_exists('services_unbound_configure')) {
    services_unbound_configure();
}
PHP
require_success $? "Failed to configure DNS Resolver."

log "$YELLOW" "Registering sing-box startup command..."
php <<'PHP'
<?php
require_once('/etc/inc/config.inc');

$cmd = 'service sing-box start';
$old_cmd = '/usr/local/etc/rc.syshook.d/start/99-sing-box';
$shellcmds = config_get_path('installedpackages/shellcmdsettings/config', []);

if (!is_array($shellcmds)) {
    $shellcmds = [];
}

$shellcmds = array_values(array_filter($shellcmds, function($entry) use ($cmd, $old_cmd) {
    if (!is_array($entry)) {
        return true;
    }
    return ($entry['cmd'] ?? '') !== $cmd && ($entry['cmd'] ?? '') !== $old_cmd;
}));
$shellcmds[] = [
    'cmd' => $cmd,
    'cmdtype' => 'shellcmd',
    'description' => 'Start sing-box on boot',
];

config_set_path('installedpackages/shellcmdsettings/config', $shellcmds);

if (file_exists('/usr/local/pkg/shellcmd.inc')) {
    require_once('/usr/local/pkg/shellcmd.inc');
    if (function_exists('shellcmd_sync_package')) {
        shellcmd_sync_package();
    }
} else {
    config_set_path('system/shellcmd', [$cmd]);
}

write_config('Register sing-box startup shellcmd');
PHP
require_success $? "Failed to register the sing-box startup command."

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
    'name' => 'Sing-Box',
    'section' => 'VPN',
]);
remove_entries_by_match('installedpackages/service', [
    'name' => 'sing-box',
]);
remove_entries_by_match('installedpackages/package', [
    'name' => 'sing_box',
]);

if (!install_package_xml('sing_box')) {
    fwrite(STDERR, "Failed to register sing_box package XML\n");
    exit(1);
}
PHP
require_success $? "Failed to register pfSense package metadata."

log "$YELLOW" "Starting sing-box..."
service sing-box restart > /dev/null 2>&1 || service sing-box restart > /dev/null 2>&1
require_success $? "Failed to start sing-box."

log "$YELLOW" "Reloading firewall rules..."
/etc/rc.filter_configure > /dev/null 2>&1 || log "$RED" "Failed to reload firewall rules. Please run /etc/rc.filter_configure manually."

echo ""
sleep 1
log "$GREEN" "sing-box installation is complete. Refresh the browser and go to VPN > Sing-Box to edit the configuration."
echo ""
