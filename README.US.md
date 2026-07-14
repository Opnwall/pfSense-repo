# Opnwall pfSense Community Repository

An unofficial community plugin repository for pfSense CE and pfSense Plus on amd64.

## Supported platforms

| System | Version | ABI | PHP | Status |
| --- | --- | --- | --- | --- |
| pfSense CE | 2.8.1 | `FreeBSD:15:amd64` | 8.3 | Tested |
| pfSense Plus | 26.03.1 | `FreeBSD:16:amd64` | 8.5 | Tested |

## Installation

pfSense CE:

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-ce-opnwall.conf
pkg update -f
```

pfSense Plus:

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-plus-opnwall.conf
pkg update -f
```

Use `pkg search pfSense-pkg-` to list plugins and `pkg install <package-name>` to install one.

### Show community plugins in the WebGUI package list

pfSense normally queries only its official repository for `pfSense-pkg-*`. Run this single command to make System > Package Manager > Available Packages query all enabled repositories:

```sh
fetch -qo - https://opnwall.github.io/pfSense-repo/enable-opnwall-gui.sh | sh
```

The script backs up `/etc/inc/pkg-utils.inc`, validates the result and refreshes the WebGUI. A pfSense firmware upgrade may overwrite this change; run the same command again after upgrading.

## Packages

| Package | Version | Description |
| --- | --- | --- |
| `pfSense-pkg-adguardhome` | 1.0.1 | AdGuard Home DNS filtering integration |
| `pfSense-pkg-arp` | 1.0.1 | Static IP and MAC binding integration |
| `pfSense-pkg-ddns-go` | 1.0.1 | DDNS-Go dynamic DNS integration |
| `pfSense-pkg-lang` | 1.0.1 | Chinese localization updater |
| `pfSense-pkg-lucky` | 1.0.1 | Lucky network toolbox integration |
| `pfSense-pkg-mihomo` | 1.0.1 | Mihomo proxy integration |
| `pfSense-pkg-sing-box` | 1.0.1 | sing-box proxy integration |
| `pfSense-pkg-ttyd` | 1.0.1 | ttyd web terminal integration |

## Remove the repository

```sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

This does not uninstall packages that are already installed.

## Source code

Complete source trees are stored under [`src/`](src/). Each `pfSense-pkg-*` directory is an independent plugin project. `pfSense-ce-dyndns` and `pfSense-plus-dyndns` are platform-specific system patch sources and are not published as ordinary repository plugins.

## Disclaimer

This repository is not affiliated with or supported by Netgate or the pfSense project. Third-party packages may affect upgrades and system stability. Back up the firewall configuration and test in a non-production environment first.

Each project and bundled component remains subject to its included license and notices.
