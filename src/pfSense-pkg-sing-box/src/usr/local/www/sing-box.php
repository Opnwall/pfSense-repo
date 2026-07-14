<?php
/*
 * sing-box.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-services-sing-box
##|*NAME=Services: Sing-Box
##|*DESCR=Sing-Box service configuration.
##|*MATCH=sing-box.php*
##|-PRIV
require_once("guiconfig.inc");

$pgtitle = [gettext('VPN'), gettext('Sing-Box'), gettext('Sing-Box')];

// 配置文件路径
$config_file = "/usr/local/etc/sing-box/config.json";
$log_file = "/var/log/sing-box.log";
$singbox_bin = "/usr/local/bin/sing-box";

// 使用 pfSense 的选项卡函数生成菜单
$tab_array = [
    1 => [gettext("Sing-Box"), true, "sing-box.php"],
    2 => [gettext("Sub"), false, "sing-box_sub.php"]
];

// 初始化消息变量
$message = "";
$message_type = "info";

function singbox_system_language()
{
    $language = 'en';

    if (function_exists('config_get_path')) {
        $language = (string)config_get_path('system/language', 'en');
    } elseif (isset($GLOBALS['config']['system']['language'])) {
        $language = (string)$GLOBALS['config']['system']['language'];
    }

    return strtolower(str_replace('-', '_', $language));
}

function singbox_language_is_zh()
{
    return in_array(singbox_system_language(), ['zh_cn', 'zh_hans_cn'], true);
}

function singbox_language_is_ru()
{
    return singbox_system_language() === 'ru_ru';
}

function singbox_t($text)
{
    static $zh = [
        'Invalid action.' => '无效的操作！',
        'Sing-Box binary is missing or not executable: %s' => 'Sing-Box 二进制不存在或不可执行：%s',
        'Warning: failed to clear the log file, but the service action continued.' => '警告：日志文件清空失败，但服务操作仍已继续。',
        'Failed to start the Sing-Box service.' => 'Sing-Box 服务启动失败！',
        'Failed to stop the Sing-Box service.' => 'Sing-Box 服务停止失败！',
        'Failed to restart the Sing-Box service.' => 'Sing-Box 服务重启失败！',
        'Failed to save configuration: configuration content cannot be empty.' => '配置保存失败：配置内容不能为空。',
        'Configuration validation failed: JSON format error, %s.' => '配置校验失败：JSON 格式错误，%s。',
        'Configuration validation failed: Sing-Box binary is missing or not executable: %s' => '配置校验失败：Sing-Box 二进制不存在或不可执行：%s',
        'Configuration validation failed: failed to create a temporary file.' => '配置校验失败：无法创建临时文件。',
        'Configuration validation failed: failed to write the temporary configuration file.' => '配置校验失败：无法写入临时配置文件。',
        'Sing-Box configuration validation failed.' => 'Sing-Box 配置校验未通过。',
        'Configuration validation failed: %s' => '配置校验失败：%s',
        'Failed to save configuration: configuration directory is not writable.' => '配置保存失败：配置目录不可写。',
        'Failed to save configuration: failed to create a save temporary file.' => '配置保存失败：无法创建保存临时文件。',
        'Failed to save configuration: failed to write the save temporary file.' => '配置保存失败：无法写入保存临时文件。',
        'Failed to save configuration: failed to overwrite the configuration file.' => '配置保存失败：无法覆盖配置文件。',
        'Configuration saved successfully and validation passed.' => '配置保存成功，且已通过校验！',
        'Request validation failed. Please refresh the page and try again.' => '请求校验失败，请刷新页面后重试。',
        'Configuration file not found.' => '配置文件未找到！',
        'Service Status' => '服务状态',
        'Checking...' => '检查中...',
        'Service Control' => '服务控制',
        'Start' => '启动',
        'Stop' => '停止',
        'Restart' => '重启',
        'Configuration Management' => '配置管理',
        'Save Configuration' => '保存配置',
        'Log Viewer' => '日志视图',
        'Network response was not OK' => '网络响应不正常',
        'Sing-Box is running' => 'Sing-Box正在运行',
        'Sing-Box is stopped' => 'Sing-Box已停止',
        'Status check failed' => '状态检查失败',
        'Log refresh failed:' => '日志刷新失败:',
        'Error' => '错误',
        'Failed to load logs. Please check the network or server status.' => '无法加载日志，请检查网络或服务器状态。',
    ];
    static $ru = [
        'Invalid action.' => 'Недопустимое действие.',
        'Sing-Box binary is missing or not executable: %s' => 'Бинарный файл Sing-Box отсутствует или не является исполняемым: %s',
        'Warning: failed to clear the log file, but the service action continued.' => 'Предупреждение: не удалось очистить файл журнала, но действие службы продолжено.',
        'Failed to start the Sing-Box service.' => 'Не удалось запустить службу Sing-Box.',
        'Failed to stop the Sing-Box service.' => 'Не удалось остановить службу Sing-Box.',
        'Failed to restart the Sing-Box service.' => 'Не удалось перезапустить службу Sing-Box.',
        'Failed to save configuration: configuration content cannot be empty.' => 'Не удалось сохранить конфигурацию: содержимое конфигурации не может быть пустым.',
        'Configuration validation failed: JSON format error, %s.' => 'Проверка конфигурации не пройдена: ошибка формата JSON, %s.',
        'Configuration validation failed: Sing-Box binary is missing or not executable: %s' => 'Проверка конфигурации не пройдена: бинарный файл Sing-Box отсутствует или не является исполняемым: %s',
        'Configuration validation failed: failed to create a temporary file.' => 'Проверка конфигурации не пройдена: не удалось создать временный файл.',
        'Configuration validation failed: failed to write the temporary configuration file.' => 'Проверка конфигурации не пройдена: не удалось записать временный файл конфигурации.',
        'Sing-Box configuration validation failed.' => 'Проверка конфигурации Sing-Box не пройдена.',
        'Configuration validation failed: %s' => 'Проверка конфигурации не пройдена: %s',
        'Failed to save configuration: configuration directory is not writable.' => 'Не удалось сохранить конфигурацию: каталог конфигурации недоступен для записи.',
        'Failed to save configuration: failed to create a save temporary file.' => 'Не удалось сохранить конфигурацию: не удалось создать временный файл сохранения.',
        'Failed to save configuration: failed to write the save temporary file.' => 'Не удалось сохранить конфигурацию: не удалось записать временный файл сохранения.',
        'Failed to save configuration: failed to overwrite the configuration file.' => 'Не удалось сохранить конфигурацию: не удалось перезаписать файл конфигурации.',
        'Configuration saved successfully and validation passed.' => 'Конфигурация успешно сохранена и прошла проверку.',
        'Request validation failed. Please refresh the page and try again.' => 'Проверка запроса не пройдена. Обновите страницу и повторите попытку.',
        'Configuration file not found.' => 'Файл конфигурации не найден.',
        'Service Status' => 'Состояние службы',
        'Checking...' => 'Проверка...',
        'Service Control' => 'Управление службой',
        'Start' => 'Запустить',
        'Stop' => 'Остановить',
        'Restart' => 'Перезапустить',
        'Configuration Management' => 'Управление конфигурацией',
        'Save Configuration' => 'Сохранить конфигурацию',
        'Log Viewer' => 'Просмотр журнала',
        'Network response was not OK' => 'Некорректный сетевой ответ',
        'Sing-Box is running' => 'Sing-Box запущен',
        'Sing-Box is stopped' => 'Sing-Box остановлен',
        'Status check failed' => 'Не удалось проверить состояние',
        'Log refresh failed:' => 'Не удалось обновить журнал:',
        'Error' => 'Ошибка',
        'Failed to load logs. Please check the network or server status.' => 'Не удалось загрузить журналы. Проверьте сеть или состояние сервера.',
    ];

    if (singbox_language_is_zh()) {
        return $zh[$text] ?? $text;
    }
    if (singbox_language_is_ru()) {
        return $ru[$text] ?? $text;
    }

    return $text;
}

function singbox_js($text)
{
    return json_encode(singbox_t($text), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

function singbox_csrf_check()
{
    if (function_exists('csrf_check')) {
        return csrf_check();
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return hash_equals($_SESSION['singbox_csrf_token'] ?? '', $_POST['singbox_csrf_token'] ?? '');
}

function singbox_csrf_token_field()
{
    if (function_exists('csrf_token')) {
        csrf_token();
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['singbox_csrf_token'])) {
        try {
            $_SESSION['singbox_csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['singbox_csrf_token'] = sha1(uniqid((string)mt_rand(), true));
        }
    }

    echo '<input type="hidden" name="singbox_csrf_token" value="' .
        htmlspecialchars($_SESSION['singbox_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '">';
}

if (($_GET['status'] ?? '') === '1') {
    header('Content-Type: application/json');

    $service_status = trim(shell_exec("service sing-box status"));
    if (strpos($service_status, 'is running') !== false) {
        echo json_encode(['status' => 'running']);
    } else {
        echo json_encode(['status' => 'stopped']);
    }
    exit;
}

// 服务控制函数
function handleServiceAction($action, $logFile, $singboxBin)
{
    $allowedActions = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowedActions, true)) {
        return ['message' => singbox_t('Invalid action.'), 'type' => 'danger'];
    }

    if (!is_executable($singboxBin)) {
        return ['message' => sprintf(singbox_t('Sing-Box binary is missing or not executable: %s'), $singboxBin), 'type' => 'danger'];
    }

    $logWarning = '';
    if (($action === 'start' || $action === 'restart') && file_exists($logFile) && is_writable($logFile)) {
        if (file_put_contents($logFile, '') === false) {
            $logWarning = singbox_t('Warning: failed to clear the log file, but the service action continued.');
        }
    }

    $output = [];
    $returnVar = 1;
    exec('service sing-box ' . escapeshellarg($action) . ' 2>&1', $output, $returnVar);

    if ($returnVar === 0) {
        if ($logWarning !== '') {
            return ['message' => $logWarning, 'type' => 'warning'];
        }
        return ['message' => '', 'type' => 'success'];
    }

    $failureMessages = [
        'start' => singbox_t('Failed to start the Sing-Box service.'),
        'stop' => singbox_t('Failed to stop the Sing-Box service.'),
        'restart' => singbox_t('Failed to restart the Sing-Box service.'),
    ];

    $errorDetail = trim(implode("\n", $output));
    if ($errorDetail !== '') {
        $errorDetail = "\n" . $errorDetail;
    }

    $failureMessage = $failureMessages[$action];
    if ($logWarning !== '') {
        $failureMessage .= "\n" . $logWarning;
    }

    return [
        'message' => $failureMessage . $errorDetail,
        'type' => 'danger'
    ];
}

// 配置保存函数
function saveConfig($file, $content, $singboxBin)
{
    if (trim($content) === '') {
        return ['message' => singbox_t('Failed to save configuration: configuration content cannot be empty.'), 'type' => 'danger'];
    }

    json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['message' => sprintf(singbox_t('Configuration validation failed: JSON format error, %s.'), json_last_error_msg()), 'type' => 'danger'];
    }

    if (!is_executable($singboxBin)) {
        return ['message' => sprintf(singbox_t('Configuration validation failed: Sing-Box binary is missing or not executable: %s'), $singboxBin), 'type' => 'danger'];
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'singbox_cfg_');
    if ($tempFile === false) {
        return ['message' => singbox_t('Configuration validation failed: failed to create a temporary file.'), 'type' => 'danger'];
    }

    if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
        @unlink($tempFile);
        return ['message' => singbox_t('Configuration validation failed: failed to write the temporary configuration file.'), 'type' => 'danger'];
    }

    $checkOutput = [];
    $checkReturnVar = 1;
    exec(escapeshellarg($singboxBin) . ' check -c ' . escapeshellarg($tempFile) . ' 2>&1', $checkOutput, $checkReturnVar);
    @unlink($tempFile);

    if ($checkReturnVar !== 0) {
        $errorMessage = trim(implode("\n", $checkOutput));
        if ($errorMessage === '') {
            $errorMessage = singbox_t('Sing-Box configuration validation failed.');
        }
        return ['message' => sprintf(singbox_t('Configuration validation failed: %s'), $errorMessage), 'type' => 'danger'];
    }

    $targetDir = dirname($file);
    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        return ['message' => singbox_t('Failed to save configuration: configuration directory is not writable.'), 'type' => 'danger'];
    }

    $saveTempFile = tempnam($targetDir, 'singbox_save_');
    if ($saveTempFile === false) {
        return ['message' => singbox_t('Failed to save configuration: failed to create a save temporary file.'), 'type' => 'danger'];
    }

    if (file_put_contents($saveTempFile, $content, LOCK_EX) === false) {
        @unlink($saveTempFile);
        return ['message' => singbox_t('Failed to save configuration: failed to write the save temporary file.'), 'type' => 'danger'];
    }

    $originalPerms = file_exists($file) ? (@fileperms($file) & 0777) : null;
    if (!@rename($saveTempFile, $file)) {
        @unlink($saveTempFile);
        return ['message' => singbox_t('Failed to save configuration: failed to overwrite the configuration file.'), 'type' => 'danger'];
    }

    if ($originalPerms !== null) {
        @chmod($file, $originalPerms);
    }

    return ['message' => singbox_t('Configuration saved successfully and validation passed.'), 'type' => 'success'];
}

// 表单提交处理
if ($_POST) {
    if (!singbox_csrf_check()) {
        $result = ['message' => singbox_t('Request validation failed. Please refresh the page and try again.'), 'type' => 'danger'];
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_config') {
            $config_content = $_POST['config_content'] ?? '';
            $result = saveConfig($config_file, $config_content, $singbox_bin);
        } else {
            $result = handleServiceAction($action, $log_file, $singbox_bin);
        }
    }
    $message = $result['message'];
    $message_type = $result['type'];
}

// 加载配置文件内容
if (isset($config_content)) {
    $config_content = htmlspecialchars($config_content);
} else {
    $config_content = file_exists($config_file) ? htmlspecialchars(file_get_contents($config_file)) : htmlspecialchars(singbox_t('Configuration file not found.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

include("head.inc");
display_top_tabs($tab_array);
?>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type); ?>">
        <?= nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(singbox_t('Service Status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <div id="sing-box-status" class="alert alert-info">
            <i class="fa fa-circle-o-notch fa-spin"></i> <?=htmlspecialchars(singbox_t('Checking...'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(singbox_t('Service Control'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="form-group">
        <form method="post" class="form-inline">
            <?php singbox_csrf_token_field(); ?>
            <button type="submit" name="action" value="start" class="btn btn-success">
                <i class="fa fa-play"></i> <?=htmlspecialchars(singbox_t('Start'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <button type="submit" name="action" value="stop" class="btn btn-danger">
                <i class="fa fa-stop"></i> <?=htmlspecialchars(singbox_t('Stop'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <button type="submit" name="action" value="restart" class="btn btn-warning">
                <i class="fa fa-refresh"></i> <?=htmlspecialchars(singbox_t('Restart'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(singbox_t('Configuration Management'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="form-group">
        <form method="post">
            <?php singbox_csrf_token_field(); ?>
            <textarea name="config_content" rows="10" class="form-control" style="font-family: monospace;"><?= $config_content; ?></textarea>
            <br>
            <button type="submit" name="action" value="save_config" class="btn btn-primary">
                <i class="fa fa-save"></i> <?=htmlspecialchars(singbox_t('Save Configuration'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(singbox_t('Log Viewer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="form-group">
        <textarea id="log-viewer" rows="10" class="form-control" readonly></textarea>
    </div>
</div>

<script>
// 检查服务状态
function checkSingBoxStatus() {
    fetch('sing-box.php?status=1')
        .then(response => {
            if (!response.ok) throw new Error(<?php echo singbox_js('Network response was not OK'); ?>);
            return response.json();
        })
        .then(data => {
            const statusElement = document.getElementById('sing-box-status');
            if (data.status === "running") {
                statusElement.innerHTML = '<i class="fa fa-check-circle text-success"></i> ' + <?php echo singbox_js('Sing-Box is running'); ?>;
                statusElement.className = "alert alert-success";
            } else {
                statusElement.innerHTML = '<i class="fa fa-times-circle text-danger"></i> ' + <?php echo singbox_js('Sing-Box is stopped'); ?>;
                statusElement.className = "alert alert-danger";
            }
        })
        .catch(error => {
            const statusElement = document.getElementById('sing-box-status');
            statusElement.innerHTML = '<i class="fa fa-exclamation-triangle text-warning"></i> ' + <?php echo singbox_js('Status check failed'); ?>;
            statusElement.className = "alert alert-warning";
        });
}

// 实时刷新日志
function refreshLogs() {
    fetch('sing-box_log.php')
        .then(response => {
            if (!response.ok) throw new Error(<?php echo singbox_js('Network response was not OK'); ?>);
            return response.text();
        })
        .then(logContent => {
            const logViewer = document.getElementById('log-viewer');
            const isNearBottom = (logViewer.scrollHeight - logViewer.scrollTop - logViewer.clientHeight) < 20;
            logViewer.value = logContent;
            if (isNearBottom || logViewer.scrollTop === 0) {
                logViewer.scrollTop = logViewer.scrollHeight;
            }
        })
        .catch(error => {
            console.error(<?php echo singbox_js('Log refresh failed:'); ?>, error.message);
            const logViewer = document.getElementById('log-viewer');
            logViewer.value = "[" + <?php echo singbox_js('Error'); ?> + "] " + <?php echo singbox_js('Failed to load logs. Please check the network or server status.'); ?>;
        });
}

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    checkSingBoxStatus();
    refreshLogs();
    setInterval(checkSingBoxStatus, 3000);
    setInterval(refreshLogs, 2000);
});
</script>

<?php include("foot.inc"); ?>
