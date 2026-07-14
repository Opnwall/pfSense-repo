PKG_NAME?=	pfSense-pkg-arp
OUTPUT_NAME?=	pfSense-pkg-arp.pkg
VERSION?=	1.0
ABI?=		universal

.PHONY: package clean

package:
		ABI="$(ABI)" PKG_NAME="$(PKG_NAME)" VERSION="$(VERSION)" OUTPUT_NAME="$(OUTPUT_NAME)" ./build.sh

clean:
	rm -rf work/freebsd-pkg
