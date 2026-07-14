# Static IP Binding for pfSense

Static IP Binding for pfSense adds a Services page for managing ARP IP and MAC bindings.

This project is packaged like a standard pfSense package. It installs its own files under `src/usr/local/...` and does not replace pfSense core WebGUI files such as `head.inc` or `guiconfig.inc`.

The WebGUI page follows the pfSense language setting at `system/language`. It supports English (`en_US`), Simplified Chinese (`zh_CN` or `zh_Hans_CN`), and Traditional Chinese (`zh_TW` or `zh_Hant_TW`). Unsupported language values fall back to English.

## Files

- `src/usr/local/www/services_arp.php`: WebGUI page.
- `src/usr/local/pkg/staticarp.xml`: pfSense package registration and menu metadata.
- `src/usr/local/pkg/staticarp.inc`: package install, deinstall, and resync hooks.
- `src/usr/local/share/pfSense-pkg-staticarp/info.xml`: pfSense package info used during registration.
- `packaging/freebsd`: FreeBSD package metadata scripts.

## Build

Build on FreeBSD or pfSense where the `pkg` command is available:

```sh
make package
```

The package will be written to:

```text
dist/pfSense-pkg-arp.pkg
```

## Install

Install the generated package on pfSense:

```sh
pkg add pfSense-pkg-arp.pkg
```

Refresh the pfSense WebGUI and open:

```text
Services > Static IP Binding
```

Do not copy this repository's files over `/usr/local/www/head.inc`, `/usr/local/www/guiconfig.inc`, or other pfSense system files.
