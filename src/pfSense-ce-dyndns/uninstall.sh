#!/bin/sh

set -eu

PATCH_NAME="dyndns-aliyun-tencent"
BACKUP_ROOT="${BACKUP_ROOT:-/root}"
BACKUP_DIR="${1:-}"
RC_SCRIPT="/usr/local/etc/rc.d/${PATCH_NAME}_check"

FILES="
/etc/inc/dyndns.class
/etc/inc/globals.inc
/etc/inc/services.inc
/usr/local/www/services_dyndns.php
/usr/local/www/services_dyndns_edit.php
"

if [ "$(id -u)" -ne 0 ]; then
	echo "请使用 root 用户运行卸载脚本。"
	exit 1
fi

if [ -z "${BACKUP_DIR}" ]; then
	BACKUP_DIR=$(ls -dt "${BACKUP_ROOT}/${PATCH_NAME}-backup-"* 2>/dev/null | head -n 1 || true)
fi

if [ -z "${BACKUP_DIR}" ] || [ ! -d "${BACKUP_DIR}" ]; then
	echo "未找到备份目录。可以手动指定：./uninstall.sh /root/${PATCH_NAME}-backup-YYYYmmdd-HHMMSS"
	exit 1
fi

echo "使用备份目录：${BACKUP_DIR}"

for file in ${FILES}; do
	backup="${BACKUP_DIR}${file}"
	if [ ! -f "${backup}" ]; then
		echo "备份中缺少文件：${backup}"
		exit 1
	fi
done

echo "恢复原文件..."
for file in ${FILES}; do
	cp -p "${BACKUP_DIR}${file}" "${file}"
done

echo "检查 PHP 语法..."
php -l /etc/inc/dyndns.class >/dev/null
php -l /etc/inc/globals.inc >/dev/null
php -l /etc/inc/services.inc >/dev/null
php -l /usr/local/www/services_dyndns.php >/dev/null
php -l /usr/local/www/services_dyndns_edit.php >/dev/null

rm -f "${RC_SCRIPT}"

echo "卸载完成，已恢复：${BACKUP_DIR}"
