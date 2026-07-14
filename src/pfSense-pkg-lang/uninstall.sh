#!/bin/sh
set -eu

if [ "$(id -u)" -ne 0 ]; then
	echo "Please run as root." >&2
	exit 1
fi

pkg delete -y pfSense-pkg-lang
