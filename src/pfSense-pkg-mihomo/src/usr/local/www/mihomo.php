<?php
/*
 * mihomo.php
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
##|*IDENT=page-services-mihomo
##|*NAME=Services: Mihomo
##|*DESCR=Mihomo service configuration.
##|*MATCH=mihomo.php*
##|-PRIV
require_once("guiconfig.inc");
require_once("services.inc");

$pgtitle = [gettext('VPN'), gettext('Mihomo'), gettext('Mihomo')];

$config_file = "/usr/local/etc/mihomo/config.yaml";
$log_file = "/var/log/mihomo.log";
$message = "";
$message_type = "info";
$input_errors = [];
$config_missing = !file_exists($config_file);

$tab_array = [
    1 => [gettext("Mihomo"), true, "mihomo.php"],
    2 => [gettext("Sub"), false, "mihomo_sub.php"],
];

function mihomo_system_language()
{
    $language = 'en';

    if (function_exists('config_get_path')) {
        $language = (string)config_get_path('system/language', 'en');
    } elseif (isset($GLOBALS['config']['system']['language'])) {
        $language = (string)$GLOBALS['config']['system']['language'];
    }

    return strtolower(str_replace('-', '_', $language));
}

function mihomo_language_is_zh()
{
    return in_array(mihomo_system_language(), ['zh_cn', 'zh_hans_cn'], true);
}

function mihomo_language_is_ru()
{
    return mihomo_system_language() === 'ru_ru';
}

function mihomo_t($text)
{
    static $zh = [
        'Invalid action.' => '无效的操作。',
        'start' => '启动',
        'stop' => '停止',
        'restart' => '重启',
        'mihomo %s failed.' => 'mihomo %s失败。',
        'mihomo %s command executed.' => 'mihomo %s命令已执行。',
        'Failed to save configuration: configuration directory does not exist.' => '配置保存失败：配置目录不存在。',
        'Failed to save configuration: configuration file is not writable.' => '配置保存失败：配置文件不可写。',
        'Failed to save configuration: configuration directory is not writable.' => '配置保存失败：配置目录不可写。',
        'Unknown error' => '未知错误',
        'Failed to save configuration: %s' => '配置保存失败：%s',
        'Configuration saved successfully.' => '配置保存成功。',
        'CSRF validation failed. Please refresh the page and try again.' => 'CSRF 校验失败，请刷新页面后重试。',
        'Configuration file exists but cannot be read.' => '配置文件存在，但无法读取。',
        'Configuration file does not exist: %s' => '配置文件不存在：%s',
        'Service Status' => '服务状态',
        'Checking mihomo service status...' => '正在检查 mihomo 服务状态...',
        'Service Control' => '服务控制',
        'Start' => '启动',
        'Stop' => '停止',
        'Restart' => '重启',
        'Configuration Management' => '配置管理',
        'Configuration file content' => '配置文件内容',
        'Save Configuration' => '保存配置',
        'Log Viewer' => '日志查看',
        'mihomo is running' => 'mihomo 正在运行',
        'mihomo is stopped' => 'mihomo 已停止',
        'Status check failed: ' => '状态检查失败：',
        'Failed to load logs: ' => '无法加载日志：',
        'Starting mihomo...' => '正在启动 mihomo...',
        'Stopping mihomo...' => '正在停止 mihomo...',
        'Restarting mihomo...' => '正在重启 mihomo...',
        'Request failed: ' => '请求失败：',
        'Configuration save request failed: ' => '配置保存请求失败：',
        'Error' => '错误',
    ];
    static $ru = [
        'Invalid action.' => 'Недопустимое действие.',
        'start' => 'запуск',
        'stop' => 'остановка',
        'restart' => 'перезапуск',
        'mihomo %s failed.' => 'mihomo: не удалось выполнить %s.',
        'mihomo %s command executed.' => 'Команда mihomo %s выполнена.',
        'Failed to save configuration: configuration directory does not exist.' => 'Не удалось сохранить конфигурацию: каталог конфигурации не существует.',
        'Failed to save configuration: configuration file is not writable.' => 'Не удалось сохранить конфигурацию: файл конфигурации недоступен для записи.',
        'Failed to save configuration: configuration directory is not writable.' => 'Не удалось сохранить конфигурацию: каталог конфигурации недоступен для записи.',
        'Unknown error' => 'Неизвестная ошибка',
        'Failed to save configuration: %s' => 'Не удалось сохранить конфигурацию: %s',
        'Configuration saved successfully.' => 'Конфигурация успешно сохранена.',
        'CSRF validation failed. Please refresh the page and try again.' => 'Проверка CSRF не пройдена. Обновите страницу и повторите попытку.',
        'Configuration file exists but cannot be read.' => 'Файл конфигурации существует, но его невозможно прочитать.',
        'Configuration file does not exist: %s' => 'Файл конфигурации не существует: %s',
        'Service Status' => 'Состояние службы',
        'Checking mihomo service status...' => 'Проверка состояния службы mihomo...',
        'Service Control' => 'Управление службой',
        'Start' => 'Запустить',
        'Stop' => 'Остановить',
        'Restart' => 'Перезапустить',
        'Configuration Management' => 'Управление конфигурацией',
        'Configuration file content' => 'Содержимое файла конфигурации',
        'Save Configuration' => 'Сохранить конфигурацию',
        'Log Viewer' => 'Просмотр журнала',
        'mihomo is running' => 'mihomo запущен',
        'mihomo is stopped' => 'mihomo остановлен',
        'Status check failed: ' => 'Не удалось проверить состояние: ',
        'Failed to load logs: ' => 'Не удалось загрузить журналы: ',
        'Starting mihomo...' => 'Запуск mihomo...',
        'Stopping mihomo...' => 'Остановка mihomo...',
        'Restarting mihomo...' => 'Перезапуск mihomo...',
        'Request failed: ' => 'Запрос не выполнен: ',
        'Configuration save request failed: ' => 'Запрос сохранения конфигурации не выполнен: ',
        'Error' => 'Ошибка',
    ];

    if (mihomo_language_is_zh()) {
        return $zh[$text] ?? $text;
    }
    if (mihomo_language_is_ru()) {
        return $ru[$text] ?? $text;
    }

    return $text;
}

function mihomo_js($text)
{
    return json_encode(mihomo_t($text), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

function mihomo_exec($command, &$output = null, &$return_var = null)
{
    $output = [];
    $return_var = 0;
    exec($command . ' 2>&1', $output, $return_var);
    return implode("\n", $output);
}

function mihomo_exec_background($command)
{
    $nohup = '/usr/bin/nohup';
    $shell = '/bin/sh';

    $background_command = $nohup . ' ' . $shell . ' -c ' . escapeshellarg($command . ' > /dev/null 2>&1') . ' >/dev/null 2>&1 &';

    $output = [];
    $return_var = 0;
    exec($background_command, $output, $return_var);

    return $return_var === 0;
}

function handleServiceAction($action)
{
    $allowedActions = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowedActions, true)) {
        return [
            'text' => mihomo_t('Invalid action.'),
            'type' => 'danger',
        ];
    }

    $messages = [
        'start' => mihomo_t('start'),
        'stop' => mihomo_t('stop'),
        'restart' => mihomo_t('restart'),
    ];

    $output = [];
    $return_var = 0;
    exec('/usr/sbin/service mihomo ' . escapeshellarg($action) . ' 2>&1', $output, $return_var);
    $detail = trim(implode("\n", $output));

    if ($return_var !== 0) {
        return [
            'text' => sprintf(mihomo_t('mihomo %s failed.'), $messages[$action]) . ($detail !== '' ? "\n" . $detail : ''),
            'type' => 'danger',
        ];
    }

    return [
        'text' => sprintf(mihomo_t('mihomo %s command executed.'), $messages[$action]) . ($detail !== '' ? "\n" . $detail : ''),
        'type' => 'success',
    ];
}

function saveConfig($file, $content)
{
    $dir = dirname($file);

    if (trim($content) === '' || strlen($content) > 4 * 1024 * 1024) {
        return ['text' => mihomo_t('Configuration must be non-empty and no larger than 4 MiB.'), 'type' => 'danger'];
    }

    if (!is_dir($dir)) {
        return [
            'text' => mihomo_t('Failed to save configuration: configuration directory does not exist.'),
            'type' => 'danger',
        ];
    }

    if (file_exists($file) && !is_writable($file)) {
        return [
            'text' => mihomo_t('Failed to save configuration: configuration file is not writable.'),
            'type' => 'danger',
        ];
    }

    if (!file_exists($file) && !is_writable($dir)) {
        return [
            'text' => mihomo_t('Failed to save configuration: configuration directory is not writable.'),
            'type' => 'danger',
        ];
    }

    $tempFile = @tempnam($dir, '.mihomo-config-');
    if ($tempFile === false) {
        return ['text' => mihomo_t('Failed to create configuration temporary file.'), 'type' => 'danger'];
    }
    @chmod($tempFile, 0600);
    $bytes = @file_put_contents($tempFile, $content, LOCK_EX);
    if ($bytes === false) {
        $error = error_get_last();
        @unlink($tempFile);
        $detail = !empty($error['message']) ? $error['message'] : mihomo_t('Unknown error');
        return [
            'text' => sprintf(mihomo_t('Failed to save configuration: %s'), $detail),
            'type' => 'danger',
        ];
    }

    $output = [];
    $returnVar = 1;
    exec('/usr/local/bin/mihomo -t -d ' . escapeshellarg($dir) . ' -f ' . escapeshellarg($tempFile) . ' 2>&1', $output, $returnVar);
    if ($returnVar !== 0) {
        @unlink($tempFile);
        return ['text' => mihomo_t('Configuration validation failed: ') . trim(implode("\n", $output)), 'type' => 'danger'];
    }
    if (file_exists($file) && !@copy($file, $file . '.bak')) {
        @unlink($tempFile);
        return ['text' => mihomo_t('Failed to create configuration backup.'), 'type' => 'danger'];
    }
    if (!@rename($tempFile, $file)) {
        @unlink($tempFile);
        return ['text' => mihomo_t('Failed to replace configuration atomically.'), 'type' => 'danger'];
    }
    @chmod($file, 0644);
    clearstatcache(true, $file);

    return [
        'text' => mihomo_t('Configuration saved successfully.'),
        'type' => 'success',
    ];
}

function mihomo_csrf_check()
{
    if (function_exists('csrf_check')) {
        return csrf_check();
    }

    return false;
}

function mihomo_csrf_token_field()
{
    if (function_exists('csrf_token')) {
        csrf_token();
    }
}

if (($_GET['status'] ?? '') === '1') {
    header('Content-Type: application/json');

    $service_status = trim(shell_exec("service mihomo status"));
    if (strpos($service_status, 'is running') !== false) {
        echo json_encode(['status' => 'running']);
    } else {
        echo json_encode(['status' => 'stopped']);
    }
    exit;
}

if ($_POST) {
    if (!mihomo_csrf_check()) {
        $input_errors[] = mihomo_t('CSRF validation failed. Please refresh the page and try again.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_config') {
            $config_content_post = $_POST['config_content'] ?? '';
            $result = saveConfig($config_file, $config_content_post);
            $message = $result['text'];
            $message_type = $result['type'];
            $config_missing = !file_exists($config_file);
        } else {
            $result = handleServiceAction($action);
            $message = $result['text'];
            $message_type = $result['type'];
        }
    }
}

if ($_POST && ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'message' => $message,
        'message_type' => $message_type,
    ]);
    exit;
}

$config_content_raw = '';
if (!$config_missing) {
    $read_result = @file_get_contents($config_file);
    if ($read_result === false) {
        $input_errors[] = mihomo_t('Configuration file exists but cannot be read.');
    } else {
        $config_content_raw = $read_result;
    }
} else {
    $input_errors[] = sprintf(mihomo_t('Configuration file does not exist: %s'), $config_file);
}

include("head.inc");
display_top_tabs($tab_array);
?>

<?php if (!empty($input_errors)): ?>
    <?php print_input_errors($input_errors); ?>
<?php endif; ?>

<?php if (!empty($message) && (($_POST['ajax'] ?? '') !== '1')): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" role="alert">
        <pre style="margin:0; padding:0; border:0; background:transparent; white-space:pre-wrap;"><?php echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
    </div>
<?php endif; ?>

<div id="mihomo-action-message" class="alert alert-info" role="alert" style="display:none;">
    <pre style="margin:0; padding:0; border:0; background:transparent; white-space:pre-wrap;"></pre>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(mihomo_t('Service Status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <div id="mihomo-status" class="alert alert-info" style="margin-bottom: 0;">
            <i class="fa fa-circle-o-notch fa-spin"></i>
            <?=htmlspecialchars(mihomo_t('Checking mihomo service status...'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(mihomo_t('Service Control'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <form method="post" class="form-inline" id="mihomo-service-form" style="margin: 6px 0 10px 10px;">
            <?php mihomo_csrf_token_field(); ?>
            <button type="submit" name="action" value="start" class="btn btn-success" style="margin-right: 8px; margin-bottom: 0;">
                <i class="fa fa-play"></i> <?=htmlspecialchars(mihomo_t('Start'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <button type="submit" name="action" value="stop" class="btn btn-danger" style="margin-right: 8px; margin-bottom: 0;">
                <i class="fa fa-stop"></i> <?=htmlspecialchars(mihomo_t('Stop'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <button type="submit" name="action" value="restart" class="btn btn-warning" style="margin-bottom: 0;">
                <i class="fa fa-refresh"></i> <?=htmlspecialchars(mihomo_t('Restart'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <input type="hidden" name="ajax" value="0" id="mihomo-ajax-flag">
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(mihomo_t('Configuration Management'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <form method="post" id="mihomo-config-form">
            <?php mihomo_csrf_token_field(); ?>
            <div class="form-group">
                <label for="config_content"><?=htmlspecialchars(mihomo_t('Configuration file content'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></label>
                <textarea id="config_content" name="config_content" rows="12" class="form-control" spellcheck="false"><?php echo htmlspecialchars($config_content_raw); ?></textarea>
            </div>
            <button type="submit" name="action" value="save_config" class="btn btn-primary" style="margin: 6px 0 10px 10px;">
                <i class="fa fa-save"></i> <?=htmlspecialchars(mihomo_t('Save Configuration'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <input type="hidden" name="ajax" value="0" id="mihomo-config-ajax-flag">
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading clearfix">
        <h2 class="panel-title" style="line-height: 34px;"><?=htmlspecialchars(mihomo_t('Log Viewer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <div class="form-group" style="margin-bottom: 0;">
            <textarea id="log-viewer" rows="12" class="form-control" readonly="readonly" spellcheck="false"></textarea>
        </div>
    </div>
</div>

<script>
(function() {
    var statusElement = document.getElementById('mihomo-status');
    var logViewer = document.getElementById('log-viewer');
    var logErrorShown = false;
    var serviceForm = document.getElementById('mihomo-service-form');
    var ajaxFlag = document.getElementById('mihomo-ajax-flag');
    var configForm = document.getElementById('mihomo-config-form');
    var configAjaxFlag = document.getElementById('mihomo-config-ajax-flag');
    var actionMessage = document.getElementById('mihomo-action-message');

    function showActionMessage(message, type) {
        if (!actionMessage) {
            return;
        }

        var safeType = ['success', 'info', 'warning', 'danger'].indexOf(type) !== -1 ? type : 'info';
        var pre = actionMessage.querySelector('pre');
        if (pre) {
            pre.textContent = message || '';
        }
        actionMessage.className = 'alert alert-' + safeType;
        actionMessage.style.display = message ? 'block' : 'none';
    }

    function setStatus(html, alertClass) {
        statusElement.innerHTML = html;
        statusElement.className = 'alert ' + alertClass;
    }

    function scheduleStatusRefresh() {
        window.setTimeout(checkMihomoStatus, 1500);
        window.setTimeout(checkMihomoStatus, 3000);
        window.setTimeout(checkMihomoStatus, 5000);
        window.setTimeout(checkMihomoStatus, 8000);
    }

    function checkMihomoStatus() {
        fetch('mihomo.php?status=1', {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.status === 'running') {
                    setStatus('<i class="fa fa-check-circle"></i> ' + <?php echo mihomo_js('mihomo is running'); ?>, 'alert-success');
                } else {
                    setStatus('<i class="fa fa-times-circle"></i> ' + <?php echo mihomo_js('mihomo is stopped'); ?>, 'alert-danger');
                }
            })
            .catch(function(error) {
                setStatus('<i class="fa fa-exclamation-circle"></i> ' + <?php echo mihomo_js('Status check failed: '); ?> + error.message, 'alert-warning');
            });
    }

    function refreshLogs() {
        fetch('mihomo_logs.php', {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function(logContent) {
                logViewer.value = logContent;
                logViewer.scrollTop = logViewer.scrollHeight;
                logErrorShown = false;
            })
            .catch(function(error) {
                if (!logErrorShown) {
                    logViewer.value = '[' + <?php echo mihomo_js('Error'); ?> + '] ' + <?php echo mihomo_js('Failed to load logs: '); ?> + error.message;
                    logErrorShown = true;
                }
            });
    }

    function submitServiceAction(button) {
        if (!serviceForm) {
            return;
        }

        var formData = new FormData(serviceForm);
        formData.set('action', button.value);
        if (ajaxFlag) {
            formData.set('ajax', '1');
        }

        var buttons = serviceForm.querySelectorAll('button[type="submit"]');
        Array.prototype.forEach.call(buttons, function(btn) {
            btn.disabled = true;
        });

        if (button.value === 'start') {
            setStatus('<i class="fa fa-circle-o-notch fa-spin"></i> ' + <?php echo mihomo_js('Starting mihomo...'); ?>, 'alert-info');
        } else if (button.value === 'stop') {
            setStatus('<i class="fa fa-circle-o-notch fa-spin"></i> ' + <?php echo mihomo_js('Stopping mihomo...'); ?>, 'alert-warning');
        } else if (button.value === 'restart') {
            setStatus('<i class="fa fa-circle-o-notch fa-spin"></i> ' + <?php echo mihomo_js('Restarting mihomo...'); ?>, 'alert-info');
        }

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                showActionMessage(data.message, data.message_type);
                scheduleStatusRefresh();
                window.setTimeout(refreshLogs, 1000);
                window.setTimeout(refreshLogs, 3000);
            })
            .catch(function(error) {
                showActionMessage(<?php echo mihomo_js('Request failed: '); ?> + error.message, 'danger');
            })
            .finally(function() {
                Array.prototype.forEach.call(buttons, function(btn) {
                    btn.disabled = false;
                });
            });
    }

    function submitConfigForm() {
        if (!configForm) {
            return;
        }

        var formData = new FormData(configForm);
        if (configAjaxFlag) {
            formData.set('ajax', '1');
        }
        formData.set('action', 'save_config');

        var saveButton = configForm.querySelector('button[type="submit"]');
        if (saveButton) {
            saveButton.disabled = true;
        }

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                showActionMessage(data.message, data.message_type);
            })
            .catch(function(error) {
                showActionMessage(<?php echo mihomo_js('Configuration save request failed: '); ?> + error.message, 'danger');
            })
            .finally(function() {
                if (saveButton) {
                    saveButton.disabled = false;
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (serviceForm) {
            serviceForm.addEventListener('submit', function(event) {
                var button = event.submitter;
                if (!button) {
                    return;
                }
                event.preventDefault();
                submitServiceAction(button);
            });
        }
        if (configForm) {
            configForm.addEventListener('submit', function(event) {
                event.preventDefault();
                submitConfigForm();
            });
        }
        checkMihomoStatus();
        refreshLogs();
        window.setInterval(checkMihomoStatus, 5000);
        window.setInterval(refreshLogs, 3000);
    });
})();
</script>

<?php include("foot.inc"); ?>
