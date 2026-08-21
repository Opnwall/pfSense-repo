# pfSense-pkg-easytier

EasyTier 2.6.4 integration for pfSense Plus 26.07 (`FreeBSD:16:amd64`). It
installs the core, CLI, rc.d service, and **VPN > EasyTier** WebGUI.

Version 1.0.4 uses a runtime pfSense filter hook for `easytier0`; the dynamic
TUN device is deliberately not added to persistent interface assignments.
WebGUI strings are gettext-ready, with the template and Simplified Chinese
catalog under `translations/`.

The runtime hook installs one stateful IPv4 `any` to `any` pass rule on
`easytier0`, so the package works with arbitrary EasyTier and proxy subnets.

The directory follows the community repository convention: package metadata is
under `packaging/freebsd`, and the installed filesystem tree is under `src`.

## Build

Run on FreeBSD or pfSense:

```sh
make package ABI=native
```

The result is `dist/pfSense-pkg-easytier.pkg`.

## Install

```sh
pkg add -f dist/pfSense-pkg-easytier.pkg
```

Existing `/usr/local/etc/easytier/config.toml` and `/var/log/easytier.log` are
preserved across upgrades and removal. The installer does not open WAN ports or
start EasyTier automatically; configure it in the WebGUI before starting it.

On a fresh installation, `config.toml.sample` is copied to
`/usr/local/etc/easytier/config.toml` with mode `0600`. Existing configurations
are never overwritten during package upgrades or forced reinstalls.
