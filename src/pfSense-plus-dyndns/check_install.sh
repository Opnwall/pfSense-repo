#!/bin/sh

set -eu

PATCH_NAME="dyndns-plus-aliyun-tencent"
BASE_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
LOG_TAG="${PATCH_NAME}-check"

log_msg() {
	if command -v logger >/dev/null 2>&1; then
		logger -t "${LOG_TAG}" "$*"
	fi
	echo "$*"
}

has_patch() {
	grep -q "aliyun" /etc/inc/services.inc &&
	grep -q "tencentcloud" /etc/inc/services.inc &&
	grep -q "_aliyunRequestURL" /etc/inc/dyndns.class &&
	grep -q "_tencentCloudDNSPodHeaders" /etc/inc/dyndns.class &&
	grep -q "DNSPod's Tencent Cloud API endpoint is IPv4-only" /etc/inc/dyndns.class &&
	grep -q "get_interface_ipv6(\\$dyndns\\['interface'\\])" /usr/local/www/services_dyndns.php &&
	grep -q "service_tencentcloud\\|tencentcloud" /usr/local/www/services_dyndns_edit.php
}

if has_patch; then
	log_msg "补丁已存在，无需处理。"
	exit 0
fi

log_msg "检测到阿里云/腾讯云 DynDNS 补丁缺失，尝试自动恢复。"
exec "${BASE_DIR}/install.sh"
