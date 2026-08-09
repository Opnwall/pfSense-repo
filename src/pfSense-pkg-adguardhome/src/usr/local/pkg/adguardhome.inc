<?php

require_once('config.inc');
require_once('util.inc');
require_once('service-utils.inc');

function adguardhome_text($key, $language = null)
{
    static $translations = [
        'zh_CN' => [
            'Invalid service action.' => '无效的服务操作。',
            'AdGuard Home binary is missing or not executable:' => 'AdGuard Home 二进制文件不存在或不可执行：',
            'Service command completed.' => '服务命令执行成功。',
            'Service command failed.' => '服务命令执行失败。',
            'Configuration cannot be empty.' => '配置内容不能为空。',
            'Cannot create configuration directory.' => '无法创建配置目录。',
            'Configuration directory is not writable.' => '配置目录不可写。',
            'Cannot create temporary configuration file.' => '无法创建临时配置文件。',
            'Cannot write temporary configuration file.' => '无法写入临时配置文件。',
            'Cannot replace configuration file.' => '无法替换配置文件。',
            'Configuration saved.' => '配置已保存。',
            'Request validation failed. Refresh the page and try again.' => '请求验证失败，请刷新页面后重试。',
            'AdGuard Home binary is not installed.' => 'AdGuard Home 二进制文件尚未安装。',
            'Service Status' => '服务状态',
            'Checking...' => '正在检查……',
            'Version' => '版本',
            'Links' => '访问地址',
            'Recommended DNS Flow' => '推荐的 DNS 流程',
            'Client -> AdGuard Home:53 -> Unbound:5353 -> Upstream DNS' => '客户端 -> AdGuard Home:53 -> Unbound:5353 -> 上游 DNS',
            'Service Control' => '服务控制',
            'Start' => '启动',
            'Stop' => '停止',
            'Restart' => '重启',
            'Configuration File' => '配置文件',
            'Save Configuration' => '保存配置',
            'Log' => '日志',
            'AdGuard Home is running' => 'AdGuard Home 正在运行',
            'AdGuard Home is stopped' => 'AdGuard Home 已停止',
            'Status check failed' => '状态检查失败',
            'Cannot load log.' => '无法加载日志。',
            'Error' => '错误',
            'No log file found.' => '未找到日志文件。',
        ],
        'zh_TW' => [
            'Invalid service action.' => '無效的服務操作。',
            'AdGuard Home binary is missing or not executable:' => 'AdGuard Home 二進位檔不存在或無法執行：',
            'Service command completed.' => '服務命令執行成功。',
            'Service command failed.' => '服務命令執行失敗。',
            'Configuration cannot be empty.' => '設定內容不能為空。',
            'Cannot create configuration directory.' => '無法建立設定目錄。',
            'Configuration directory is not writable.' => '設定目錄無法寫入。',
            'Cannot create temporary configuration file.' => '無法建立暫存設定檔。',
            'Cannot write temporary configuration file.' => '無法寫入暫存設定檔。',
            'Cannot replace configuration file.' => '無法取代設定檔。',
            'Configuration saved.' => '設定已儲存。',
            'Request validation failed. Refresh the page and try again.' => '請求驗證失敗，請重新整理頁面後再試。',
            'AdGuard Home binary is not installed.' => 'AdGuard Home 二進位檔尚未安裝。',
            'Service Status' => '服務狀態',
            'Checking...' => '正在檢查……',
            'Version' => '版本',
            'Links' => '存取位址',
            'Recommended DNS Flow' => '建議的 DNS 流程',
            'Client -> AdGuard Home:53 -> Unbound:5353 -> Upstream DNS' => '用戶端 -> AdGuard Home:53 -> Unbound:5353 -> 上游 DNS',
            'Service Control' => '服務控制',
            'Start' => '啟動',
            'Stop' => '停止',
            'Restart' => '重新啟動',
            'Configuration File' => '設定檔',
            'Save Configuration' => '儲存設定',
            'Log' => '日誌',
            'AdGuard Home is running' => 'AdGuard Home 正在執行',
            'AdGuard Home is stopped' => 'AdGuard Home 已停止',
            'Status check failed' => '狀態檢查失敗',
            'Cannot load log.' => '無法載入日誌。',
            'Error' => '錯誤',
            'No log file found.' => '找不到日誌檔案。',
        ],
    ];

	if ($language === null) {
		$language = function_exists('config_get_path') ? (string)config_get_path('system/language', 'en') : 'en';
	}
    if (in_array($language, ['zh_CN', 'zh_Hans_CN'], true)) {
        $language = 'zh_CN';
    } elseif (in_array($language, ['zh_TW', 'zh_Hant_TW'], true)) {
        $language = 'zh_TW';
    } else {
        return $key;
    }

    return $translations[$language][$key] ?? $key;
}

function adguardhome_service_status()
{
    exec('/usr/sbin/service adguardhome status 2>&1', $output, $rc);
    return ($rc === 0) ? 'running' : 'stopped';
}

function adguardhome_start()
{
    mwexec('/usr/sbin/service adguardhome start');
}

function adguardhome_stop()
{
    mwexec('/usr/sbin/service adguardhome stop');
}

function adguardhome_restart()
{
    mwexec('/usr/sbin/service adguardhome restart');
}

function adguardhome_php_install_command()
{
    file_put_contents('/etc/rc.conf.d/adguardhome', "adguardhome_enable=\"YES\"\n");
}

function adguardhome_php_deinstall_command()
{
    @unlink('/etc/rc.conf.d/adguardhome');
}
