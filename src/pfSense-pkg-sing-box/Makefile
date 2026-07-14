PKG_NAME?=	pfSense-pkg-sing-box
VERSION?=	1.0.2
ABI?=		universal

.PHONY: package clean

package:
	TARGET_ABI="$(ABI)" PKG_NAME="$(PKG_NAME)" VERSION="$(VERSION)" ./build.sh

clean:
	rm -rf work/freebsd-pkg
