#!/bin/sh
set -u

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run this installer as root."
	exit 1
fi

GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
RESET="\033[0m"

log() {
	printf '%b%s%b\n' "$1" "$2" "$RESET"
}

find_freebsd_pkg_repopath() {
	local index_file="$1"
	local package_name="$2"

	tar -xOf "$index_file" packagesite.yaml | awk -F'"' -v n="$package_name" '
		$0 ~ "\"name\":\"" n "\"" {
			for (i = 1; i <= NF; i++) {
				if ($i == "repopath") {
					print $(i + 2)
					exit
				}
			}
		}
	'
}

detect_bundle_name() {
	local version major

	version="$(freebsd-version -u 2>/dev/null || freebsd-version 2>/dev/null || uname -r)"
	major="$(printf '%s\n' "$version" | awk -F. '{print $1}' | sed 's/[^0-9].*$//')"

	case "$major" in
		15) echo "freebsd15" ;;
		16) echo "freebsd16" ;;
		*) return 1 ;;
	esac
}

install_freebsd14_ttyd() {
	local repo="https://pkg.freebsd.org/FreeBSD:14:amd64/quarterly"
	local workdir="/tmp/pfsense-ttyd-pkg"
	local repopath=""

	if [ "$(uname -m)" != "amd64" ] || [ ! -e /lib/libcrypto.so.30 ]; then
		return 1
	fi

	rm -rf "$workdir"
	mkdir -p "$workdir"

	if command -v fetch >/dev/null 2>&1; then
		fetch -q -o "$workdir/packagesite.pkg" "$repo/packagesite.pkg" || return 1
	else
		curl -fsSL -o "$workdir/packagesite.pkg" "$repo/packagesite.pkg" || return 1
	fi

	for dep in libwebsockets ttyd; do
		repopath="$(find_freebsd_pkg_repopath "$workdir/packagesite.pkg" "$dep")"
		[ -n "$repopath" ] || return 1
		log "$YELLOW" "Installing FreeBSD 14 compatible package $dep..."
		pkg add -M -f "$repo/$repopath" || return 1
	done

	rm -rf "$workdir"
	command -v ttyd >/dev/null 2>&1
}

install_bundled_ttyd() {
	local bundle_name bundle_dir bundle_archive runtime_dir workdir src_bin
	local dst_root="/usr/local/pfSense-pkg-ttyd"
	local dst_bin="$dst_root/bin/ttyd"

	bundle_name="$(detect_bundle_name)" || return 1
	bundle_dir="src/usr/local/share/ttyd-for-pfsense/${bundle_name}"
	bundle_archive="${bundle_dir}.tar.gz"
	runtime_dir="$bundle_dir"

	if [ -f "$bundle_archive" ]; then
		workdir="$(mktemp -d /tmp/ttyd-runtime.XXXXXX)" || return 1
		tar -xzf "$bundle_archive" -C "$workdir" || {
			rm -rf "$workdir"
			return 1
		}
		runtime_dir="$workdir"
	elif [ ! -d "$runtime_dir" ]; then
		return 1
	fi

	src_bin="${runtime_dir}/usr/local/bin/ttyd"
	[ -f "$src_bin" ] || {
		[ -n "${workdir:-}" ] && rm -rf "$workdir"
		return 1
	}

	log "$YELLOW" "Installing bundled ttyd runtime for ${bundle_name}..."
	rm -rf "$dst_root"
	mkdir -p "$dst_root/bin" "$dst_root/lib"
	if [ -d "${runtime_dir}/usr/local/lib" ]; then
		cp -R -P -f "${runtime_dir}/usr/local/lib/." "$dst_root/lib/" || {
			[ -n "${workdir:-}" ] && rm -rf "$workdir"
			return 1
		}
	fi
	chmod 0755 "$src_bin" || return 1
	cp -f "$src_bin" "$dst_bin" || return 1
	chmod 0755 "$dst_bin" || return 1
	env LD_LIBRARY_PATH="$dst_root/lib:/usr/local/lib" "$dst_bin" --version >/dev/null 2>&1 || {
		[ -n "${workdir:-}" ] && rm -rf "$workdir"
		return 1
	}
	[ -n "${workdir:-}" ] && rm -rf "$workdir"
}

ensure_ttyd() {
	if install_bundled_ttyd; then
		return 0
	fi

	if command -v ttyd >/dev/null 2>&1; then
		ttyd --version >/dev/null 2>&1 && return 0
	fi

	log "$YELLOW" "Checking ttyd package availability..."
	if pkg install -y ttyd; then
		return 0
	fi

	log "$YELLOW" "pfSense repositories do not provide ttyd; trying FreeBSD 14 compatible packages..."
	install_freebsd14_ttyd
}

require_success() {
	status="$1"
	message="$2"
	if [ "$status" -ne 0 ]; then
		log "$RED" "$message"
		exit 1
	fi
}

log "$GREEN" "====== ttyd for pfSense installer ======"

service pfsense_ttyd onestop >/dev/null 2>&1 || true
service pfsense_webssh onestop >/dev/null 2>&1 || true
service ttyd onestop >/dev/null 2>&1 || true

ensure_ttyd
require_success $? "Failed to install ttyd."

log "$YELLOW" "Copying files..."
cp -R -f src/etc/* /etc/
require_success $? "Failed to copy /etc files."
cp -R -f src/usr/* /usr/
require_success $? "Failed to copy /usr files."

rm -f \
	/etc/rc.conf.d/pfsense_ttyd \
	/etc/rc.conf.d/pfsense_webssh \
	/usr/local/etc/rc.d/pfsense_ttyd \
	/usr/local/etc/rc.d/pfsense_webssh \
	/usr/local/etc/pfsense_webssh.credential \
	/usr/local/etc/pfsense_webssh.crt \
	/usr/local/etc/pfsense_webssh.key \
	/usr/local/pkg/pfsense_ttyd.inc \
	/usr/local/pkg/pfsense_ttyd.xml \
	/usr/local/pkg/pfsense_webssh.inc \
	/usr/local/pkg/pfsense_webssh.xml \
	/usr/local/share/pfSense-pkg-webshell/info.xml \
	/usr/local/share/pfSense-pkg-pfsense_ttyd/info.xml \
	/usr/local/share/pfSense-pkg-pfsense_webssh/info.xml \
	/usr/local/www/diag_ttyd_settings.php \
	/usr/local/www/diag_webshell.php \
	/usr/local/bin/pfsense_ttyd \
	/usr/local/bin/pfsense_webssh \
	/var/log/pfsense_ttyd.log \
	/var/log/pfsense_webssh.log

chmod 0644 /etc/rc.conf.d/ttyd 2>/dev/null || true
chmod 0755 /usr/local/etc/rc.d/ttyd
chmod 0755 /usr/local/pfSense-pkg-ttyd/bin/ttyd 2>/dev/null || true
install_bundled_ttyd
require_success $? "Failed to install the bundled ttyd runtime for this FreeBSD version."

log "$YELLOW" "Registering pfSense menu and service..."
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
	'name' => 'WebShell',
	'section' => 'Diagnostics',
]);
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

if (!install_package_xml('ttyd')) {
	fwrite(STDERR, "Failed to register ttyd package XML\n");
	exit(1);
}
PHP
require_success $? "Failed to register pfSense package metadata."

log "$YELLOW" "Starting service..."
service ttyd onerestart >/dev/null 2>&1 || service ttyd onestart >/dev/null 2>&1 || true

if service ttyd onestatus >/dev/null 2>&1; then
	log "$GREEN" "Installation complete. Refresh the browser and open Diagnostics > ttyd."
else
	log "$RED" "Installation completed, but the service did not start. Ensure SSH is enabled under System > Advanced > Admin Access."
fi
