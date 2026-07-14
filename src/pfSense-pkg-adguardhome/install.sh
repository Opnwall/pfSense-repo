#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PKG_FILE="${PKG_FILE:-"$SCRIPT_DIR/dist/pfSense-pkg-adguardhome.pkg"}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this installer as root." >&2
    exit 1
fi

if [ ! -f "$PKG_FILE" ]; then
    echo "Package not found: $PKG_FILE" >&2
    echo "Run ./build.sh on FreeBSD/pfSense first." >&2
    exit 1
fi

pkg add -f "$PKG_FILE"
