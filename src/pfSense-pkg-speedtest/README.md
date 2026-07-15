# Speedtest for pfSense

`pfSense-pkg-speedtest` adds **Diagnostics > Speedtest** to pfSense. It runs an
Internet speed test from the firewall and reports latency, jitter, packet loss,
download speed, upload speed, ISP, public IP, and the selected test server.

The package bundles the MIT-licensed `speedtest-go` 1.7.10 FreeBSD amd64 binary.
The WebGUI follows the pfSense system language and supports English, Simplified
Chinese, and Traditional Chinese.

Build on FreeBSD or pfSense:

```sh
make package
pkg add -f dist/pfSense-pkg-speedtest.pkg
```
