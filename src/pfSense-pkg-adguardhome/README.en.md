<p align="center">
  <img src="https://cdn.jsdelivr.net/gh/selfhst/icons/svg/adguard-home.svg"
       width="96"
       height="96"
       alt="AdGuard Home Logo">
</p>
<h1 align="center">AdGuard Home for pfSense</h1>
<div align="center">
  English | <a href="README.md">中文</a><br><br>
</div>
<p align="center">
  <img src="https://img.shields.io/badge/pfSense-CE%202.8.1%20%7C%20Plus%2026.03-212121?logo=pfsense&logoColor=white">
  <img src="https://img.shields.io/badge/Platform-amd64-blue?logo=amd">
  <img src="https://img.shields.io/badge/AdGuard%20Home-Supported-67b279?logo=adguard">
</p>

AdGuard Home is a DNS-based network-wide ad blocking and privacy protection solution. It provides centralized DNS filtering for all devices in a home or business network, including phones, computers, smart TVs, and IoT devices. DNS queries from clients are processed by AdGuard Home first, allowing it to block ads, trackers, malicious domains, and provide a safer and more controllable DNS experience.

This project integrates AdGuard Home with pfSense firewalls. It provides one-step deployment and management through a standard pfSense package, including a service script, Web UI menu entry, management page, and persistent configuration directories. The goal is to make AdGuard Home feel like a native pfSense component that can be installed, configured, and maintained directly from the firewall.

Tested on:

- pfSense CE 2.8.1
- pfSense Plus 26.03

![](image/AdGuardHome.png)

## Recommended Setup

pfSense uses Unbound DNS Resolver on port `53` by default. To make AdGuard Home effective for LAN clients, the recommended DNS flow is:

```text
Client -> AdGuard Home:53 -> Unbound DNS Resolver:5353 -> Upstream DNS
```

This provides:

- LAN clients continue to use pfSense port `53` as their DNS server.
- AdGuard Home listens on `0.0.0.0:53` and handles ad blocking, query logs, and statistics.
- Unbound is moved to `127.0.0.1:5353` and continues to provide pfSense DNS Resolver functionality.
- AdGuard Home uses `127.0.0.1:5353` as its upstream DNS server.

The package does not automatically change the Unbound port, so existing DNS behavior is not changed during installation. Switch the DNS flow manually after confirming that AdGuard Home has initialized correctly.

## Build

Build on FreeBSD or pfSense:

```sh
sh build.sh
```

The default output is:

```text
dist/pfSense-pkg-adguardhome.pkg
```

The build script first looks for:

```text
src/usr/local/bin/AdGuardHome_freebsd_amd64.tar.gz
```

If the local asset is missing, it downloads the official AdGuard Home release:

```text
https://static.adguard.com/adguardhome/release/AdGuardHome_freebsd_amd64.tar.gz
```

## Install

```sh
pkg add -f dist/pfSense-pkg-adguardhome.pkg
```

After the first start, open:

```text
http://<pfsense-host>:3000/
```

If Unbound is still using port `53`, set the AdGuard Home DNS listen port to `5353` or another free port during the first-run wizard. After initialization is complete, switch to the production DNS flow.

## Take Over Port 53

Stop AdGuard Home first, then open the pfSense Web UI:

```text
Services > DNS Resolver > General Settings
```

Change the DNS Resolver listen port from `53` to `5353`, then save and apply. It is recommended to use Unbound only as the local upstream resolver for AdGuard Home, or at least make sure `127.0.0.1:5353` is available.

Then configure AdGuard Home in its Web UI:

```yaml
dns:
  bind_hosts:
    - 0.0.0.0
  port: 53
  upstream_dns:
    - 127.0.0.1:5353
```

Restart the services after changing the settings.

## Verify

Check listening ports:

```sh
sockstat -4 -l | egrep ':(53|5353|3000)'
```

Expected result:

- `AdGuardHome` listens on `*:53`
- `unbound` listens on `127.0.0.1:5353`
- The AdGuard Home Web UI listens on `*:3000`

Test DNS:

```sh
dig @127.0.0.1 -p 53 bing.com
dig @127.0.0.1 -p 5353 bing.com
```

## Roll Back

To restore the default pfSense DNS behavior, stop the `adguardhome` service first. Then change the DNS Resolver port back to `53` in the pfSense Web UI and apply the changes. Finally, restart Unbound.

## Uninstall

```sh
pkg delete -y pfSense-pkg-adguardhome
```

## Disclaimer

This is an unofficial community project and is not affiliated with, endorsed by, or supported by the pfSense team. Please review the source code before deployment and use it at your own risk.
