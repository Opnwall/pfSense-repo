PKG_NAME?=	pfSense-pkg-mihomo
VERSION?=	1.0.1
ABI?=		universal

.PHONY: package clean

package:
	ABI="$(ABI)" PKG_NAME="$(PKG_NAME)" VERSION="$(VERSION)" ./build.sh

clean:
	rm -rf work/freebsd-pkg
