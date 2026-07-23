<div align="center">
  <a href="README.md">中文</a> |
  <a href="README.US.md">English</a>
</div>

# pfSense Community Repository

![pfSense CE](https://img.shields.io/badge/pfSense_CE-005AA0?logo=pfsense&logoColor=white)
![pfSense Plus](https://img.shields.io/badge/pfSense_Plus-00A86B)
![FreeBSD](https://img.shields.io/badge/FreeBSD-15%20%7C%2016-red?logo=freebsd)
![amd64](https://img.shields.io/badge/Architecture-amd64-success)

An unofficial community package repository for **pfSense CE** and **pfSense Plus**, providing high-quality open-source plugins that can be installed through the native `pkg` package manager.

## Supported Platforms

| System | Version | ABI | PHP | Status |
| --- | --- | --- | --- | --- |
| pfSense CE | 2.8.1 | `FreeBSD:15:amd64` | 8.3 | Tested |
| pfSense Plus | 26.03.1 | `FreeBSD:16:amd64` | 8.5 | Tested |

## Installation

### pfSense CE

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-ce-opnwall.conf
pkg update -f
```

### pfSense Plus

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-plus-opnwall.conf
pkg update -f
```

List available packages:

```sh
pkg search pfSense-pkg-
```

Install a package:

```sh
pkg install <package-name>
```

### Install the Community Repository Plugin

Install the persistent Package Manager integration:

```sh
pkg install pfSense-pkg-community-repo
```

The plugin adds a **Community Packages** tab. Repository enable/disable and
catalog refresh operations are available in the WebGUI, and the integration is
automatically reapplied at boot after pfSense upgrades.

## Packages

| Package | Version | Description |
| --- | --- | --- |
| `pfSense-pkg-adguardhome` | 1.0.1 | AdGuard Home DNS integration |
| `pfSense-pkg-arp` | 1.0.1 | Static IP/MAC binding |
| `pfSense-pkg-community-repo` | 1.1.4 | Community repository and Package Manager integration |
| `pfSense-pkg-ddns-go` | 1.0.1 | DDNS-Go integration |
| `pfSense-pkg-lang` | 1.0.1 | Chinese localization |
| `pfSense-pkg-lantest` | 1.0.1 | LAN speed test |
| `pfSense-pkg-lucky` | 1.0.1 | Lucky network toolbox |
| `pfSense-pkg-mihomo` | 1.0.1 | Mihomo integration |
| `pfSense-pkg-sing-box` | 1.0.1 | sing-box integration |
| `pfSense-pkg-speedtest` | 1.0.1 | Speedtest internet speed test |
| `pfSense-pkg-ttyd` | 1.0.1 | ttyd web terminal |

## Installing Packages

After installing the community repository plugin, all community packages can
be installed from **System > Package Manager > Community Packages** or directly
using the `pkg` command.

## Remove the Repository

```sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

Removing the repository does **not** uninstall packages that are already installed.

## Source Code

All source code is available under the `src/` directory. Each `pfSense-pkg-*` directory is an independent plugin project and can be built on pfSense/FreeBSD using the included `build.sh` script.

## Disclaimer

This repository is **not affiliated with or endorsed by Netgate or the pfSense project**. Third-party packages may affect system stability or future upgrades. Always back up your firewall configuration and test in a non-production environment before deployment. Each bundled project remains subject to its own license and notices.
