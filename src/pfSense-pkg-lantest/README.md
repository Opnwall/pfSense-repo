# LanTest for pfSense

`pfSense-pkg-lantest` adds a browser-based local throughput test to
**Diagnostics > LanTest**. It measures latency, jitter, download, and
upload between a client and pfSense without an external speed-test service.

## Defaults

- Listen interface: LAN
- TCP port: 3300
- Test data: generated and discarded in memory
- Supported systems: pfSense CE 2.8.x and pfSense Plus 26.x on amd64

## Build

Run on pfSense or FreeBSD with `pkg` installed:

```sh
make package
pkg add -f dist/pfSense-pkg-lantest.pkg
```

The package uses a universal `FreeBSD:*:amd64` ABI because it contains no
architecture-specific binary.
