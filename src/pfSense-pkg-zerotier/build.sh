#!/bin/sh

set -eu

VERSION="1.16.2"
PKG_VERSION="${VERSION}_3"
NAME="pfSense-pkg-zerotier"
ORIGIN="pfSense-pkg/zerotier"
ROOT_DIR=$(CDPATH='' cd "$(dirname "$0")" && pwd)
SRC_DIR="$ROOT_DIR/src"
BUILD_DIR="${TMPDIR:-/tmp}/zerotier-universal-pkg-$$"
STAGE_DIR="$BUILD_DIR/stage"
DIST_DIR="$ROOT_DIR/dist"
TARGET_ABI="${TARGET_ABI:-universal}"
OUTPUT_NAME="${OUTPUT_NAME:-${NAME}-${PKG_VERSION}.pkg}"
OUT_PKG="$DIST_DIR/$OUTPUT_NAME"

case "$TARGET_ABI" in
    universal)
        PKG_ABI="FreeBSD:*:amd64"
        PKG_ARCH="freebsd:*:x86:64"
        ;;
    FreeBSD:15:amd64)
        PKG_ABI="$TARGET_ABI"
        PKG_ARCH="freebsd:15:x86:64"
        ;;
    FreeBSD:16:amd64)
        PKG_ABI="$TARGET_ABI"
        PKG_ARCH="freebsd:16:x86:64"
        ;;
    *)
        printf 'Unsupported target ABI: %s\n' "$TARGET_ABI" >&2
        exit 1
        ;;
esac

cleanup() {
    rm -rf "$BUILD_DIR"
}
trap cleanup EXIT INT TERM

require_file() {
    if [ ! -f "$1" ]; then
        printf 'Missing required file: %s\n' "$1" >&2
        exit 1
    fi
}

require_file "$SRC_DIR/usr/local/bin/zerotier-${VERSION}-freebsd15.pkg"
require_file "$SRC_DIR/usr/local/bin/zerotier-${VERSION}-freebsd16.pkg"
require_file "$SRC_DIR/usr/local/etc/rc.d/zerotier.sh"
require_file "$SRC_DIR/usr/local/share/pfSense-pkg-zerotier/info.xml"

rm -rf "$BUILD_DIR"
mkdir -p "$STAGE_DIR/usr/local/share/zerotier-pfsense/payload/freebsd15"
mkdir -p "$STAGE_DIR/usr/local/share/zerotier-pfsense/payload/freebsd16"
mkdir -p "$STAGE_DIR/usr/local/share/zerotier-pfsense"
mkdir -p "$STAGE_DIR/usr/local/share/pfSense-pkg-zerotier"
mkdir -p "$STAGE_DIR/usr/local/www"
mkdir -p "$STAGE_DIR/usr/local/pkg"
mkdir -p "$STAGE_DIR/usr/local/etc/rc.d"

extract_payload() {
    major="$1"
    pkg_file="$2"
    payload_dir="$STAGE_DIR/usr/local/share/zerotier-pfsense/payload/freebsd$major"
    tmp="$BUILD_DIR/extract-freebsd$major"

    mkdir -p "$tmp"
    tar -xf "$pkg_file" -C "$tmp"
    mkdir -p "$payload_dir/usr/local"

    for path in \
        usr/local/bin \
        usr/local/etc/rc.d/zerotier \
        usr/local/sbin \
        usr/local/share/licenses/zerotier-${VERSION}
    do
        if [ -e "$tmp/$path" ] || [ -L "$tmp/$path" ]; then
            mkdir -p "$payload_dir/$(dirname "$path")"
            cp -R "$tmp/$path" "$payload_dir/$path"
        fi
    done
}

extract_payload 15 "$SRC_DIR/usr/local/bin/zerotier-${VERSION}-freebsd15.pkg"
extract_payload 16 "$SRC_DIR/usr/local/bin/zerotier-${VERSION}-freebsd16.pkg"

cp -R "$SRC_DIR/usr/local/www/." "$STAGE_DIR/usr/local/www/"
cp -R "$SRC_DIR/usr/local/pkg/." "$STAGE_DIR/usr/local/pkg/"
cp "$SRC_DIR/usr/local/share/pfSense-pkg-zerotier/info.xml" "$STAGE_DIR/usr/local/share/pfSense-pkg-zerotier/info.xml"
cp "$SRC_DIR/usr/local/etc/rc.d/zerotier.sh" "$STAGE_DIR/usr/local/etc/rc.d/zerotier.sh"
cp "$SRC_DIR/usr/local/etc/rc.d/zerotier.sh" "$STAGE_DIR/usr/local/share/zerotier-pfsense/zerotier.sh"
chmod 755 "$STAGE_DIR/usr/local/etc/rc.d/zerotier.sh"
chmod 755 "$STAGE_DIR/usr/local/share/zerotier-pfsense/zerotier.sh"

python3 - "$STAGE_DIR" "$VERSION" "$PKG_VERSION" "$NAME" "$ORIGIN" "$PKG_ABI" "$PKG_ARCH" > "$STAGE_DIR/+MANIFEST" <<'PY'
import hashlib
import json
import os
import stat
import sys

stage, version, pkg_version, name, origin, pkg_abi, pkg_arch = sys.argv[1:]

def sha256(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return "1$" + h.hexdigest()

files = {}
flatsize = 0
for root, dirs, names in os.walk(stage):
    dirs.sort()
    names.sort()
    for filename in names:
        rel = os.path.relpath(os.path.join(root, filename), stage)
        if rel in {"+MANIFEST", "+COMPACT_MANIFEST"}:
            continue
        full = os.path.join(stage, rel)
        st = os.lstat(full)
        path = "/" + rel
        entry = {
            "uname": "root",
            "gname": "wheel",
            "perm": format(stat.S_IMODE(st.st_mode), "04o"),
            "fflags": 0,
        }
        if stat.S_ISLNK(st.st_mode):
            entry["sum"] = "1$" + hashlib.sha256(os.readlink(full).encode()).hexdigest()
            entry["symlink_target"] = os.readlink(full)
        else:
            entry["sum"] = sha256(full)
            flatsize += st.st_size
        files[path] = entry

post_install = r'''#!/bin/sh
set -e

ROOT="/usr/local"
BASE="$ROOT/share/zerotier-pfsense"
WAS_RUNNING="NO"

if /usr/sbin/service zerotier onestatus >/dev/null 2>&1; then
    WAS_RUNNING="YES"
    /usr/sbin/service zerotier onestop >/dev/null 2>&1 || true
fi

log() {
    message="$2"
    echo "$message"
}

version_file="/tmp/zerotier_freebsd_version"
if freebsd-version -u > "$version_file" 2>/dev/null; then
    :
elif freebsd-version > "$version_file" 2>/dev/null; then
    :
else
    uname -r > "$version_file"
fi

read version < "$version_file"
rm -f "$version_file"

FREEBSD_MAJOR=""
case "$version" in
    15|15.*|15-*)
        FREEBSD_MAJOR="15"
        PAYLOAD="$BASE/payload/freebsd15/usr/local"
        ;;
    16|16.*|16-*)
        FREEBSD_MAJOR="16"
        PAYLOAD="$BASE/payload/freebsd16/usr/local"
        ;;
    *)
        log "" "Unsupported FreeBSD version: $version. This package supports FreeBSD 15 and FreeBSD 16."
        exit 1
        ;;
esac

log "" "Detected FreeBSD $FREEBSD_MAJOR. Installing the matching ZeroTier binaries..."
mkdir -p "$ROOT/bin" "$ROOT/sbin" "$ROOT/etc/rc.d" "$ROOT/share/licenses"
cp -R "$PAYLOAD/bin/." "$ROOT/bin/"
cp -R "$PAYLOAD/sbin/." "$ROOT/sbin/"
cp "$PAYLOAD/etc/rc.d/zerotier" "$ROOT/etc/rc.d/zerotier"
chmod 755 "$ROOT/etc/rc.d/zerotier"
cp -R "$PAYLOAD/share/licenses/zerotier-1.16.2" "$ROOT/share/licenses/"
cp "$BASE/zerotier.sh" "$ROOT/etc/rc.d/zerotier.sh"
chmod 755 "$ROOT/etc/rc.d/zerotier.sh"

if ! sysrc -q -n zerotier_enable >/dev/null 2>&1; then
    sysrc -q zerotier_enable=NO >/dev/null 2>&1 || \
        log "" "Unable to initialize zerotier_enable. Please enable ZeroTier from the WebGUI after installation."
fi

if ! grep -Eq '^[[:space:]]*net\.link\.tap\.up_on_open[[:space:]]*=' /etc/sysctl.conf 2>/dev/null; then
    echo "" >> /etc/sysctl.conf
    echo "# Required for ZeroTier TAP interfaces during pfSense startup." >> /etc/sysctl.conf
    echo "net.link.tap.up_on_open=1" >> /etc/sysctl.conf
fi
sysctl net.link.tap.up_on_open=1 >/dev/null 2>&1 || \
    log "" "The sysctl setting was written to /etc/sysctl.conf and will take effect after TAP support is loaded."

if command -v php >/dev/null 2>&1 && [ -f /etc/inc/pkg-utils.inc ]; then
    php <<'PHP'
<?php
require_once('/etc/inc/config.inc');
require_once('/etc/inc/pkg-utils.inc');
foreach (array('menu', 'service', 'package') as $type) {
    $entries = config_get_path("installedpackages/{$type}", array());
    $entries = array_values(array_filter($entries, function ($entry) use ($type) {
        return !(($type === 'menu' && in_array(($entry['name'] ?? ''), array('Zerotier', 'ZeroTier VPN'), true)) ||
            ($type === 'service' && ($entry['name'] ?? '') === 'zerotier') ||
            ($type === 'package' && (($entry['internal_name'] ?? '') === 'zerotier' ||
                ($entry['name'] ?? '') === 'Zerotier')));
    }));
    config_set_path("installedpackages/{$type}", $entries);
}
install_package_xml('zerotier');
PHP
fi

rm -f /usr/local/share/pfSense/menu/pfSense-VPN_ZeroTier.xml

if [ "$WAS_RUNNING" = "YES" ]; then
    /usr/sbin/service zerotier onestart >/dev/null 2>&1 || \
        log "" "ZeroTier was running before the upgrade but could not be restarted automatically."
fi

log "" "ZeroTier for pfSense installation completed."
log "" "Enable the service from VPN > ZeroTier VPN > Configuration, then join a network from the Networks page."
exit 0
'''

pre_deinstall = r'''#!/bin/sh
service zerotier stop >/dev/null 2>&1 || true
pkill -f zerotier-one >/dev/null 2>&1 || true

if command -v php >/dev/null 2>&1 && [ -f /etc/inc/config.inc ]; then
    php <<'PHP'
<?php
require_once('/etc/inc/config.inc');
foreach (array('menu', 'service', 'package') as $type) {
    $entries = config_get_path("installedpackages/{$type}", array());
    $entries = array_values(array_filter($entries, function ($entry) use ($type) {
        return !(($type === 'menu' && in_array(($entry['name'] ?? ''), array('Zerotier', 'ZeroTier VPN'), true)) ||
            ($type === 'service' && ($entry['name'] ?? '') === 'zerotier') ||
            ($type === 'package' && (($entry['internal_name'] ?? '') === 'zerotier' ||
                in_array(($entry['name'] ?? ''), array('zerotier', 'Zerotier', 'ZeroTier VPN'), true))));
    }));
    config_set_path("installedpackages/{$type}", $entries);
}
write_config('Removed ZeroTier package metadata.');
PHP
fi
exit 0
'''

post_deinstall = r'''#!/bin/sh
ROOT="/usr/local"
rm -f "$ROOT/bin/zerotier-cli" "$ROOT/bin/zerotier-idtool" "$ROOT/sbin/zerotier-one"
rm -f "$ROOT/etc/rc.d/zerotier" "$ROOT/etc/rc.d/zerotier.sh"
rm -f "$ROOT/share/pfSense/menu/pfSense-VPN_ZeroTier.xml"
rm -rf "$ROOT/share/licenses/zerotier-1.16.2"
sysrc -q -x zerotier_enable >/dev/null 2>&1 || true
exit 0
'''

manifest = {
    "name": name,
    "origin": origin,
    "version": pkg_version,
    "comment": "Universal ZeroTier package for pfSense FreeBSD 15 and 16",
    "maintainer": "pfchina.org",
    "www": "https://www.zerotier.com/",
    "abi": pkg_abi,
    "arch": pkg_arch,
    "prefix": "/usr/local",
    "flatsize": flatsize,
    "licenselogic": "single",
    "licenses": ["MPL-2.0"],
    "desc": "ZeroTier for pfSense with FreeBSD 15 and FreeBSD 16 payloads. The post-install script detects the host FreeBSD major version and installs the matching binaries.",
    "categories": ["net", "pfSense"],
    "annotations": {
        "FreeBSD_major_supported": "15,16",
        "built_by": "codex",
        "zerotier_version": version,
        "universal_payload": "true",
    },
    "files": files,
    "scripts": {
        "post-install": post_install,
        "pre-deinstall": pre_deinstall,
        "post-deinstall": post_deinstall,
    },
    "messages": [{
        "type": "install",
        "message": "ZeroTier for pfSense has been installed. Enable it from VPN > ZeroTier VPN > Configuration.",
    }],
}

json.dump(manifest, sys.stdout, separators=(",", ":"))
PY

python3 - "$STAGE_DIR/+MANIFEST" > "$STAGE_DIR/+COMPACT_MANIFEST" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as f:
    manifest = json.load(f)

for key in ("files", "directories", "scripts"):
    manifest.pop(key, None)

json.dump(manifest, sys.stdout, separators=(",", ":"))
PY

mkdir -p "$DIST_DIR"
(
    cd "$STAGE_DIR"
    find usr \( -type f -o -type l \) -print | sort > "$BUILD_DIR/tarfiles"
    # shellcheck disable=SC2046
    tar -P --format=ustar -s ',^usr,/usr,' -cf - +COMPACT_MANIFEST +MANIFEST $(cat "$BUILD_DIR/tarfiles") | zstd -q -19 -f -o "$OUT_PKG"
)

printf 'Built %s\n' "$OUT_PKG"
