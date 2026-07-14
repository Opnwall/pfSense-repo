<?php
/*
 * mihomo_sub.php
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
##|*IDENT=page-services-mihomo-sub
##|*NAME=Services: Mihomo Sub
##|*DESCR=Mihomo-sub service configuration.
##|*MATCH=mihomo_sub.php*
##|-PRIV
require_once("guiconfig.inc");
require_once("services.inc");

$pgtitle = [gettext('VPN'), gettext('Mihomo'), gettext('Sub')];

define('ENV_FILE', '/usr/local/etc/mihomo/sub/env');
define('LOG_FILE', '/var/log/mihomo_sub.log');
define('SUB_SCRIPT', '/usr/local/etc/mihomo/sub/sub.sh');
define('LOG_TAIL_LINES', 200);

$message = "";
$message_type = "info";
$input_errors = [];

$env_missing = !file_exists(ENV_FILE);
$env_dir_missing = !is_dir(dirname(ENV_FILE));

$tab_array = [
    1 => [gettext("Mihomo"), false, "mihomo.php"],
    2 => [gettext("Sub"), true, "mihomo_sub.php"],
];

function mihomo_sub_system_language()
{
    $language = 'en';

    if (function_exists('config_get_path')) {
        $language = (string)config_get_path('system/language', 'en');
    } elseif (isset($GLOBALS['config']['system']['language'])) {
        $language = (string)$GLOBALS['config']['system']['language'];
    }

    return strtolower(str_replace('-', '_', $language));
}

function mihomo_sub_language_is_zh()
{
    return in_array(mihomo_sub_system_language(), ['zh_cn', 'zh_hans_cn'], true);
}

function mihomo_sub_language_is_ru()
{
    return mihomo_sub_system_language() === 'ru_ru';
}

function mihomo_sub_t($text)
{
    static $zh = [
        'Failed to clear the log. Make sure the log file is writable.' => '日志清空失败，请确保日志文件可写。',
        'Failed to clear the log.' => '日志清空失败。',
        'Variable name cannot be empty.' => '变量名不能为空。',
        'Directory does not exist: %s' => '目录不存在：%s',
        'Directory is not writable: %s' => '目录不可写：%s',
        'Failed to read the environment file.' => '环境变量文件读取失败。',
        'Failed to write temporary file: %s' => '临时文件写入失败：%s',
        'Failed to replace target file: %s' => '无法替换目标文件：%s',
        'Saved successfully.' => '保存成功。',
        'Subscription URL cannot be empty.' => '订阅地址不能为空。',
        'Failed to save subscription URL: %s' => '保存订阅地址失败：%s',
        'Failed to save access secret: %s' => '保存访问密钥失败：%s',
        'Subscription URL saved.' => '订阅地址已保存。',
        'Access secret saved.' => '访问密钥已保存。',
        'Starting subscription task.' => '开始执行订阅任务。',
        'Subscription task completed successfully.' => '订阅任务执行成功。',
        'Subscription task failed with exit code: $rc.' => '订阅任务执行失败，退出码：$rc。',
        'Failed to start subscription task.' => '订阅任务启动失败。',
        'CSRF validation failed. Please refresh the page and try again.' => 'CSRF 校验失败，请刷新页面后重试。',
        'Invalid action.' => '无效的操作。',
        'Environment directory does not exist: %s' => '环境变量目录不存在：%s',
        'Environment file does not exist: %s' => '环境变量文件不存在：%s',
        'Subscription Management' => '订阅管理',
        'Subscription URL' => '订阅地址',
        'Enter subscription URL' => '输入订阅地址',
        'Access Secret' => '访问密钥',
        'Enter access secret' => '输入访问密钥',
        'Save Settings' => '保存设置',
        'Start Subscription' => '开始订阅',
        'Clear Log' => '清空日志',
        'Log Viewer' => '日志查看',
        'Error' => '错误',
        'The log endpoint returned an HTML page. Check that mihomo_sub_log.php exists and the path is correct.' => '日志接口返回了 HTML 页面，请检查 mihomo_sub_log.php 是否存在且路径正确。',
        'Failed to load logs: ' => '无法加载日志：',
        'Local Notice' => '本地提示',
        'The task was submitted and the subscription is running in the background...' => '任务已提交，正在后台执行订阅...',
        'Settings saved.' => '设置已写入。',
        'Log cleared.' => '日志已清空。',
    ];
    static $ru = [
        'Failed to clear the log. Make sure the log file is writable.' => 'Не удалось очистить журнал. Убедитесь, что файл журнала доступен для записи.',
        'Failed to clear the log.' => 'Не удалось очистить журнал.',
        'Variable name cannot be empty.' => 'Имя переменной не может быть пустым.',
        'Directory does not exist: %s' => 'Каталог не существует: %s',
        'Directory is not writable: %s' => 'Каталог недоступен для записи: %s',
        'Failed to read the environment file.' => 'Не удалось прочитать файл окружения.',
        'Failed to write temporary file: %s' => 'Не удалось записать временный файл: %s',
        'Failed to replace target file: %s' => 'Не удалось заменить целевой файл: %s',
        'Saved successfully.' => 'Успешно сохранено.',
        'Subscription URL cannot be empty.' => 'URL подписки не может быть пустым.',
        'Failed to save subscription URL: %s' => 'Не удалось сохранить URL подписки: %s',
        'Failed to save access secret: %s' => 'Не удалось сохранить секрет доступа: %s',
        'Subscription URL saved.' => 'URL подписки сохранен.',
        'Access secret saved.' => 'Секрет доступа сохранен.',
        'Starting subscription task.' => 'Запуск задачи подписки.',
        'Subscription task completed successfully.' => 'Задача подписки успешно выполнена.',
        'Subscription task failed with exit code: $rc.' => 'Задача подписки завершилась с кодом: $rc.',
        'Failed to start subscription task.' => 'Не удалось запустить задачу подписки.',
        'CSRF validation failed. Please refresh the page and try again.' => 'Проверка CSRF не пройдена. Обновите страницу и повторите попытку.',
        'Invalid action.' => 'Недопустимое действие.',
        'Environment directory does not exist: %s' => 'Каталог окружения не существует: %s',
        'Environment file does not exist: %s' => 'Файл окружения не существует: %s',
        'Subscription Management' => 'Управление подпиской',
        'Subscription URL' => 'URL подписки',
        'Enter subscription URL' => 'Введите URL подписки',
        'Access Secret' => 'Секрет доступа',
        'Enter access secret' => 'Введите секрет доступа',
        'Save Settings' => 'Сохранить настройки',
        'Start Subscription' => 'Запустить подписку',
        'Clear Log' => 'Очистить журнал',
        'Log Viewer' => 'Просмотр журнала',
        'Error' => 'Ошибка',
        'The log endpoint returned an HTML page. Check that mihomo_sub_log.php exists and the path is correct.' => 'Интерфейс журнала вернул HTML-страницу. Проверьте, что mihomo_sub_log.php существует и путь указан правильно.',
        'Failed to load logs: ' => 'Не удалось загрузить журналы: ',
        'Local Notice' => 'Локальное уведомление',
        'The task was submitted and the subscription is running in the background...' => 'Задача отправлена, подписка выполняется в фоне...',
        'Settings saved.' => 'Настройки сохранены.',
        'Log cleared.' => 'Журнал очищен.',
    ];

    if (mihomo_sub_language_is_zh()) {
        return $zh[$text] ?? $text;
    }
    if (mihomo_sub_language_is_ru()) {
        return $ru[$text] ?? $text;
    }

    return $text;
}

function mihomo_sub_js($text)
{
    return json_encode(mihomo_sub_t($text), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

function sub_exec_background($command)
{
    $nohup = '/usr/bin/nohup';
    $shell = '/bin/sh';

    $background_command = $nohup . ' ' . $shell . ' -c ' . escapeshellarg($command) . ' >/dev/null 2>&1 &';

    $output = [];
    $return_var = 0;
    exec($background_command, $output, $return_var);

    return $return_var === 0;
}

function sub_csrf_check_compat()
{
    if (function_exists('csrf_check')) {
        return csrf_check();
    }

    return true;
}

function sub_csrf_token_field_compat()
{
    if (function_exists('csrf_token')) {
        csrf_token();
    }
}

function sub_log_message($message, $log_file = LOG_FILE)
{
    $time = date("Y-m-d H:i:s");
    $log_entry = "[{$time}] {$message}\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function sub_clear_log($log_file = LOG_FILE)
{
    if (!file_exists($log_file)) {
        return [
            'text' => '',
            'type' => 'success',
        ];
    }

    if (!is_writable($log_file)) {
        return [
            'text' => mihomo_sub_t('Failed to clear the log. Make sure the log file is writable.'),
            'type' => 'danger',
        ];
    }

    if (@file_put_contents($log_file, '', LOCK_EX) === false) {
        return [
            'text' => mihomo_sub_t('Failed to clear the log.'),
            'type' => 'danger',
        ];
    }

    return [
        'text' => '',
        'type' => 'success',
    ];
}

function sub_escape_env_value($value)
{
    return str_replace("'", "'\"'\"'", $value);
}

function save_env_variable($key, $value, $env_file = ENV_FILE)
{
    if ($key === '') {
        return [
            'ok' => false,
            'message' => mihomo_sub_t('Variable name cannot be empty.'),
        ];
    }

    $dir = dirname($env_file);
    if (!is_dir($dir)) {
        return [
            'ok' => false,
            'message' => sprintf(mihomo_sub_t('Directory does not exist: %s'), $dir),
        ];
    }

    if (!is_writable($dir)) {
        return [
            'ok' => false,
            'message' => sprintf(mihomo_sub_t('Directory is not writable: %s'), $dir),
        ];
    }

    $lines = file_exists($env_file) ? file($env_file, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        return [
            'ok' => false,
            'message' => mihomo_sub_t('Failed to read the environment file.'),
        ];
    }

    $new_lines = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            $new_lines[] = $line;
            continue;
        }

        $body = (strpos($trimmed, 'export ') === 0) ? substr($trimmed, 7) : $trimmed;

        if (strpos($body, '=') === false) {
            $new_lines[] = $line;
            continue;
        }

        list($existing_key,) = explode('=', $body, 2);
        if (strtoupper(trim($existing_key)) !== strtoupper($key)) {
            $new_lines[] = $line;
        }
    }

    $escaped_value = sub_escape_env_value($value);
    $new_lines[] = "{$key}='{$escaped_value}'";

    $tmp_file = $env_file . '.tmp';
    $content = implode("\n", array_filter($new_lines, static function ($line) {
        return $line !== null;
    })) . "\n";

    if (@file_put_contents($tmp_file, $content, LOCK_EX) === false) {
        @unlink($tmp_file);
        return [
            'ok' => false,
            'message' => sprintf(mihomo_sub_t('Failed to write temporary file: %s'), $tmp_file),
        ];
    }

    if (!@rename($tmp_file, $env_file)) {
        @unlink($tmp_file);
        return [
            'ok' => false,
            'message' => sprintf(mihomo_sub_t('Failed to replace target file: %s'), $env_file),
        ];
    }

    return [
        'ok' => true,
        'message' => mihomo_sub_t('Saved successfully.'),
    ];
}

function load_env_variables($env_file = ENV_FILE)
{
    $env_vars = [];

    if (!file_exists($env_file)) {
        return $env_vars;
    }

    $env_lines = @file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($env_lines === false) {
        return $env_vars;
    }

    foreach ($env_lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (preg_match("/^(?:export\s+)?([A-Za-z0-9_]+)='(.*)'$/", $line, $matches)) {
            $env_vars[strtoupper($matches[1])] = str_replace("'\"'\"'", "'", $matches[2]);
        }
    }

    return $env_vars;
}

function cleanup_temp_files()
{
    $files = [
        "/usr/local/etc/mihomo/sub/temp/mihomo_config.yaml",
        "/usr/local/etc/mihomo/sub/temp/proxies.txt",
        "/usr/local/etc/mihomo/sub/temp/config.yaml",
    ];

    foreach ($files as $file) {
        @unlink($file);
    }
}

function save_sub_settings($url, $secret)
{
    if ($url === '') {
        return [
            'text' => mihomo_sub_t('Subscription URL cannot be empty.'),
            'type' => 'danger',
        ];
    }

    $url_result = save_env_variable('mihomo_URL', $url);
    if (!$url_result['ok']) {
        return [
            'text' => sprintf(mihomo_sub_t('Failed to save subscription URL: %s'), $url_result['message']),
            'type' => 'danger',
        ];
    }

    $secret_result = save_env_variable('mihomo_secret', $secret);
    if (!$secret_result['ok']) {
        return [
            'text' => sprintf(mihomo_sub_t('Failed to save access secret: %s'), $secret_result['message']),
            'type' => 'danger',
        ];
    }

    sub_log_message(mihomo_sub_t('Subscription URL saved.'));
    sub_log_message(mihomo_sub_t('Access secret saved.'));

    return [
        'text' => '',
        'type' => 'success',
    ];
}

function run_subscription_now()
{
    cleanup_temp_files();

    @file_put_contents(LOG_FILE, '', LOCK_EX);
    sub_log_message(mihomo_sub_t('Starting subscription task.'));

    $command =
        '/bin/sh ' . escapeshellarg(SUB_SCRIPT) .
        ' >> ' . escapeshellarg(LOG_FILE) . ' 2>&1; ' .
        'rc=$?; ' .
        'if [ "$rc" -eq 0 ]; then ' .
        'echo "[$(date \'+%Y-%m-%d %H:%M:%S\')] ' . mihomo_sub_t('Subscription task completed successfully.') . '" >> ' . escapeshellarg(LOG_FILE) . '; ' .
        'else ' .
        'echo "[$(date \'+%Y-%m-%d %H:%M:%S\')] ' . mihomo_sub_t('Subscription task failed with exit code: $rc.') . '" >> ' . escapeshellarg(LOG_FILE) . '; ' .
        'fi';

    $ok = sub_exec_background($command);

    if (!$ok) {
        sub_log_message(mihomo_sub_t('Failed to start subscription task.'));
        return [
            'text' => mihomo_sub_t('Failed to start subscription task.'),
            'type' => 'danger',
        ];
    }

    return [
        'text' => '',
        'type' => 'success',
    ];
}

$env_vars = load_env_variables();
$current_url = $env_vars['MIHOMO_URL'] ?? '';
$current_secret = $env_vars['MIHOMO_SECRET'] ?? '';

if ($_POST) {
    if (!sub_csrf_check_compat()) {
        $input_errors[] = mihomo_sub_t('CSRF validation failed. Please refresh the page and try again.');
    } else {
        $action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

        if ($action === 'save_settings') {
            $url = isset($_POST['subscribe_url']) ? trim((string)$_POST['subscribe_url']) : '';
            $secret = isset($_POST['mihomo_secret']) ? trim((string)$_POST['mihomo_secret']) : '';
            $result = save_sub_settings($url, $secret);
            $message = $result['text'];
            $message_type = $result['type'];
        } elseif ($action === 'subscribe_now') {
            $result = run_subscription_now();
            $message = $result['text'];
            $message_type = $result['type'];
        } elseif ($action === 'clear_log') {
            $result = sub_clear_log();
            $message = $result['text'];
            $message_type = $result['type'];
        } else {
            $message = mihomo_sub_t('Invalid action.');
            $message_type = 'danger';
        }
    }
}

if ($_POST && (($_POST['ajax'] ?? '') === '1')) {
    header('Content-Type: application/json');
    echo json_encode([
        'message' => $message,
        'message_type' => $message_type,
    ]);
    exit;
}

$env_vars = load_env_variables();
$current_url = $env_vars['MIHOMO_URL'] ?? '';
$current_secret = $env_vars['MIHOMO_SECRET'] ?? '';

if ($env_dir_missing) {
    $input_errors[] = sprintf(mihomo_sub_t('Environment directory does not exist: %s'), dirname(ENV_FILE));
} elseif ($env_missing) {
    $input_errors[] = sprintf(mihomo_sub_t('Environment file does not exist: %s'), ENV_FILE);
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

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=htmlspecialchars(mihomo_sub_t('Subscription Management'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <form method="post" id="sub-settings-form">
            <?php sub_csrf_token_field_compat(); ?>

            <div class="form-group" style="margin-left: 10px;">
                <label for="subscribe_url"><?=htmlspecialchars(mihomo_sub_t('Subscription URL'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></label>
                <input
                    type="text"
                    id="subscribe_url"
                    name="subscribe_url"
                    value="<?php echo htmlspecialchars($current_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    class="form-control"
                    placeholder="<?=htmlspecialchars(mihomo_sub_t('Enter subscription URL'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"
                    autocomplete="off"
                    spellcheck="false"
                />
            </div>

            <div class="form-group" style="margin-left: 10px;">
                <label for="mihomo_secret"><?=htmlspecialchars(mihomo_sub_t('Access Secret'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></label>
                <input
                    type="text"
                    id="mihomo_secret"
                    name="mihomo_secret"
                    value="<?php echo htmlspecialchars($current_secret, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                    class="form-control"
                    placeholder="<?=htmlspecialchars(mihomo_sub_t('Enter access secret'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>"
                    autocomplete="off"
                    spellcheck="false"
                />
            </div>

            <button type="submit" name="action" value="save_settings" class="btn btn-primary" id="save-settings-btn" style="margin: 6px 8px 10px 10px;">
                <i class="fa fa-save"></i> <?=htmlspecialchars(mihomo_sub_t('Save Settings'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <button type="submit" name="action" value="subscribe_now" class="btn btn-success" id="subscribe-now-btn" style="margin: 6px 8px 10px 0;">
                <i class="fa fa-refresh"></i> <?=htmlspecialchars(mihomo_sub_t('Start Subscription'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>
            <button type="submit" name="action" value="clear_log" class="btn btn-default" id="clear-log-btn" style="margin: 6px 0 10px 0;">
                <i class="fa fa-trash"></i> <?=htmlspecialchars(mihomo_sub_t('Clear Log'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?>
            </button>

            <div id="sub-light-tip" style="display:none; margin: 0 0 10px 10px; color: #666;"></div>
            <input type="hidden" name="ajax" value="0" id="sub-settings-ajax-flag">
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading clearfix">
        <h2 class="panel-title" style="line-height: 34px;"><?=htmlspecialchars(mihomo_sub_t('Log Viewer'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></h2>
    </div>
    <div class="panel-body">
        <div class="form-group" style="margin-bottom: 0;">
            <textarea
                id="log-viewer"
                rows="20"
                class="form-control"
                readonly="readonly"
                spellcheck="false"
                style="max-width:none;font-family:monospace;"
            ></textarea>
        </div>
    </div>
</div>

<style>
#sub-settings-form button.is-busy {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
}

#sub-settings-form button.is-busy i {
    opacity: 0.8;
}
</style>

<script>
(function() {
    var logViewer = document.getElementById('log-viewer');
    var logErrorShown = false;
    var settingsForm = document.getElementById('sub-settings-form');
    var settingsAjaxFlag = document.getElementById('sub-settings-ajax-flag');
    var lightTip = document.getElementById('sub-light-tip');
    var maxClientLines = <?php echo (int)LOG_TAIL_LINES; ?>;

    function showLightTip(text) {
        if (!lightTip) {
            return;
        }

        lightTip.textContent = text;
        lightTip.style.display = 'block';

        window.clearTimeout(showLightTip._timer);
        showLightTip._timer = window.setTimeout(function() {
            lightTip.style.display = 'none';
            lightTip.textContent = '';
        }, 2200);
    }

    function trimLogLines(text, maxLines) {
        var lines = String(text || '').split('\n');
        if (lines.length <= maxLines) {
            return String(text || '');
        }
        return lines.slice(lines.length - maxLines).join('\n');
    }

    function prependLogMessage(text) {
        if (!logViewer) {
            return;
        }

        var current = logViewer.value || '';
        var merged = text + '\n' + current;
        logViewer.value = trimLogLines(merged, maxClientLines);
        logViewer.scrollTop = 0;
    }

    function refreshLogs() {
        fetch('mihomo_sub_log.php', {
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
                if (!logViewer) {
                    return;
                }

                var trimmed = logContent.trim().toLowerCase();
                if (
                    trimmed.indexOf('<!doctype html') === 0 ||
                    trimmed.indexOf('<html') === 0 ||
                    logContent.indexOf('<title>') !== -1
                ) {
                    logViewer.value = '[' + <?php echo mihomo_sub_js('Error'); ?> + '] ' + <?php echo mihomo_sub_js('The log endpoint returned an HTML page. Check that mihomo_sub_log.php exists and the path is correct.'); ?>;
                    return;
                }

                logViewer.value = trimLogLines(logContent, maxClientLines);
                logViewer.scrollTop = logViewer.scrollHeight;
                logErrorShown = false;
            })
            .catch(function(error) {
                if (!logViewer) {
                    return;
                }

                if (!logErrorShown) {
                    logViewer.value = '[' + <?php echo mihomo_sub_js('Error'); ?> + '] ' + <?php echo mihomo_sub_js('Failed to load logs: '); ?> + error.message;
                    logErrorShown = true;
                }
            });
    }

    function setButtonsBusy(busy, activeButton) {
        if (!settingsForm) {
            return;
        }

        var buttons = settingsForm.querySelectorAll('button[type="submit"]');
        Array.prototype.forEach.call(buttons, function(btn) {
            btn.disabled = busy;
            if (busy) {
                btn.classList.add('is-busy');
            } else {
                btn.classList.remove('is-busy');
                btn.style.opacity = '';
            }
        });

        if (busy && activeButton) {
            activeButton.style.opacity = '1';
        }
    }

    function submitSettingsForm(button) {
        if (!settingsForm) {
            return;
        }

        var action = button.value;
        var formData = new FormData(settingsForm);
        formData.set('action', action);

        if (settingsAjaxFlag) {
            formData.set('ajax', '1');
        }

        if (action === 'subscribe_now') {
            prependLogMessage('[' + <?php echo mihomo_sub_js('Local Notice'); ?> + '] ' + <?php echo mihomo_sub_js('The task was submitted and the subscription is running in the background...'); ?>);
        }

        setButtonsBusy(true, button);

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
            .then(function() {
                if (action === 'save_settings') {
                    showLightTip(<?php echo mihomo_sub_js('Settings saved.'); ?>);
                } else if (action === 'clear_log') {
                    refreshLogs();
                    showLightTip(<?php echo mihomo_sub_js('Log cleared.'); ?>);
                } else if (action === 'subscribe_now') {
                    window.setTimeout(refreshLogs, 800);
                    window.setTimeout(refreshLogs, 2000);
                    window.setTimeout(refreshLogs, 5000);
                }
            })
            .catch(function() {
            })
            .finally(function() {
                setButtonsBusy(false);
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (settingsForm) {
            settingsForm.addEventListener('submit', function(event) {
                var button = event.submitter;
                if (!button) {
                    return;
                }
                event.preventDefault();
                submitSettingsForm(button);
            });
        }

        refreshLogs();
        window.setInterval(refreshLogs, 3000);
    });
})();
</script>

<?php include("foot.inc"); ?>
