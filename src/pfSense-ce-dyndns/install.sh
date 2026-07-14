#!/bin/sh

set -eu

PATCH_NAME="dyndns-aliyun-tencent"
BASE_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
SRC_DIR="${BASE_DIR}/src"
INSTALL_DIR="${INSTALL_DIR:-/root/${PATCH_NAME}}"
BACKUP_ROOT="${BACKUP_ROOT:-/root}"
BACKUP_DIR="${BACKUP_ROOT}/${PATCH_NAME}-backup-$(date +%Y%m%d-%H%M%S)"
RC_SCRIPT="/usr/local/etc/rc.d/${PATCH_NAME}_check"

FILES="
/etc/inc/dyndns.class
/etc/inc/globals.inc
/etc/inc/services.inc
/usr/local/www/services_dyndns.php
/usr/local/www/services_dyndns_edit.php
"

if [ "$(id -u)" -ne 0 ]; then
	echo "请使用 root 用户运行安装脚本。"
	exit 1
fi

if [ ! -d "${SRC_DIR}" ]; then
	echo "未找到 src 目录：${SRC_DIR}"
	exit 1
fi

check_structure() {
	if [ ! -f /etc/inc/services.inc ] ||
	    ! grep -q "DYNDNS_PROVIDER_VALUES" /etc/inc/services.inc ||
	    ! grep -q "DYNDNS_PROVIDER_DESCRIPTIONS" /etc/inc/services.inc; then
		echo "目标 /etc/inc/services.inc 结构异常，停止安装。"
		exit 1
	fi
	if [ ! -f /etc/inc/globals.inc ] ||
	    ! grep -q "dyndns_split_domain_types" /etc/inc/globals.inc; then
		echo "目标 /etc/inc/globals.inc 结构异常，停止安装。"
		exit 1
	fi
	if [ ! -f /etc/inc/dyndns.class ] ||
	    ! grep -q "function _update" /etc/inc/dyndns.class ||
	    ! grep -q "function _checkStatus" /etc/inc/dyndns.class; then
		echo "目标 /etc/inc/dyndns.class 结构异常，停止安装。"
		exit 1
	fi
	if [ ! -f /usr/local/www/services_dyndns_edit.php ] ||
	    ! grep -q "build_type_list" /usr/local/www/services_dyndns_edit.php ||
	    ! grep -q "setVisible" /usr/local/www/services_dyndns_edit.php; then
		echo "目标 services_dyndns_edit.php 结构异常，停止安装。"
		exit 1
	fi
	if [ ! -f /usr/local/www/services_dyndns.php ] ||
	    ! grep -q "DYNDNS_PROVIDER_DESCRIPTIONS" /usr/local/www/services_dyndns.php; then
		echo "目标 services_dyndns.php 结构异常，停止安装。"
		exit 1
	fi
}

already_patched() {
	grep -q "aliyun" /etc/inc/services.inc &&
	grep -q "tencentcloud" /etc/inc/services.inc &&
	grep -q "_aliyunRequestURL" /etc/inc/dyndns.class &&
	grep -q "_tencentCloudDNSPodHeaders" /etc/inc/dyndns.class &&
	grep -q "DNSPod's Tencent Cloud API endpoint is IPv4-only" /etc/inc/dyndns.class &&
	grep -q "get_interface_ipv6(\\$dyndns\\['interface'\\])" /usr/local/www/services_dyndns.php &&
	grep -q "tencentcloud" /usr/local/www/services_dyndns_edit.php
}

install_runtime_copy() {
	if [ "${BASE_DIR}" != "${INSTALL_DIR}" ]; then
		echo "复制补丁包到持久目录：${INSTALL_DIR}"
		mkdir -p "${INSTALL_DIR}"
		cp -Rp "${BASE_DIR}/src" "${INSTALL_DIR}/"
		cp -p "${BASE_DIR}/install.sh" "${BASE_DIR}/uninstall.sh" "${BASE_DIR}/check_install.sh" "${INSTALL_DIR}/"
		[ -f "${BASE_DIR}/readme.me" ] && cp -p "${BASE_DIR}/readme.me" "${INSTALL_DIR}/"
		chmod +x "${INSTALL_DIR}/install.sh" "${INSTALL_DIR}/uninstall.sh" "${INSTALL_DIR}/check_install.sh"
	fi
}

install_boot_hook() {
	echo "安装开机自愈脚本：${RC_SCRIPT}"
	cat > "${RC_SCRIPT}" <<EOF
#!/bin/sh
#
# PROVIDE: ${PATCH_NAME}_check
# REQUIRE: FILESYSTEMS
# KEYWORD: nojail

case "\$1" in
	start)
		${INSTALL_DIR}/check_install.sh >/dev/null 2>&1 || true
		;;
esac
EOF
	chmod +x "${RC_SCRIPT}"
}

check_structure

if already_patched; then
	echo "检测到补丁已存在，本次不创建系统文件备份。"
	BACKUP_DIR=""
else
	echo "创建备份目录：${BACKUP_DIR}"
	mkdir -p "${BACKUP_DIR}/etc/inc" "${BACKUP_DIR}/usr/local/www"
fi

for file in ${FILES}; do
	src="${SRC_DIR}${file}"
	if [ ! -f "${src}" ]; then
		echo "缺少补丁文件：${src}"
		exit 1
	fi
	if [ -n "${BACKUP_DIR}" ] && [ -f "${file}" ]; then
		cp -p "${file}" "${BACKUP_DIR}${file}"
	fi
done

echo "安装补丁文件..."
for file in ${FILES}; do
	cp -p "${SRC_DIR}${file}" "${file}"
done

echo "检查 PHP 语法..."
php -l /etc/inc/dyndns.class >/dev/null
php -l /etc/inc/globals.inc >/dev/null
php -l /etc/inc/services.inc >/dev/null
php -l /usr/local/www/services_dyndns.php >/dev/null
php -l /usr/local/www/services_dyndns_edit.php >/dev/null

install_runtime_copy
install_boot_hook

if [ -n "${BACKUP_DIR}" ]; then
	echo "安装完成。备份目录：${BACKUP_DIR}"
else
	echo "安装完成。"
fi
