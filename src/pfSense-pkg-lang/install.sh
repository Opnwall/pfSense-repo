#!/bin/sh
set -eu

PKG_FILE="${1:-dist/pfSense-pkg-lang.pkg}"

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root." >&2
	exit 1
fi

if [ ! -f "$PKG_FILE" ]; then
	echo "Package not found: $PKG_FILE" >&2
	echo "Build it first with: make package" >&2
	exit 1
fi

pkg add -f "$PKG_FILE"
