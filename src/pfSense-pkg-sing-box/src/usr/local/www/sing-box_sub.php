<?php
/*
 * sing-box_sub.php
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
##|*IDENT=page-services-sing-box-sub
##|*NAME=Services: Sing-Box Sub
##|*DESCR=Sing-Box Sub service configuration.
##|*MATCH=sing-box_sub.php*
##|-PRIV

require_once("guiconfig.inc");
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$pgtitle = [gettext('VPN'), gettext('Sing-Box'), gettext('Sub')];

// 配置文件路径
const ENV_FILE = '/usr/local/etc/sing-box/sub/env';
const LOG_FILE = '/var/log/sing-box_sub.log';
const SUB_CMD  = '/usr/bin/sub';
const CSRF_SECRET_FILE = '/usr/local/etc/sing-box/sub/.vpn_sub_csrf_secret';

$input_errors = [];
$savemsg = '';

function singbox_sub_system_language(): string
{
    $language = 'en';

    if (function_exists('config_get_path')) {
        $language = (string)config_get_path('system/language', 'en');
    } elseif (isset($GLOBALS['config']['system']['language'])) {
        $language = (string)$GLOBALS['config']['system']['language'];
    }

    return strtolower(str_replace('-', '_', $language));
}

function singbox_sub_language_is_zh(): bool
{
    return in_array(singbox_sub_system_language(), ['zh_cn', 'zh_hans_cn'], true);
}

function singbox_sub_language_is_ru(): bool
{
    return singbox_sub_system_language() === 'ru_ru';
}

function singbox_sub_t(string $text): string
{
    static $zh = [
        'Subscription URL cannot exceed 2048 characters.' => '订阅地址长度不能超过 2048 个字符。',
        'Subscription URL format is invalid.' => '订阅地址格式不正确。',
        'Subscription URL must start with http:// or https://.' => '订阅地址必须以 http:// 或 https:// 开头。',
        'Invalid CSRF token. Please refresh the page and try again.' => '无效的 CSRF Token，请刷新页面后重试。',
        'Subscription URL saved.' => '订阅地址已保存。',
        'Failed to save subscription URL. Please check the configuration file permissions.' => '保存订阅地址失败，请检查配置文件权限。',
        'Log cleared.' => '日志已清空。',
        'Failed to clear the log. Please check the log file permissions.' => '清空日志失败，请检查日志文件权限。',
        'Failed to clear the log file. Please check the log file permissions.' => '清空日志文件失败，请检查日志文件权限。',
        'Subscription command does not exist: %s' => '订阅程序不存在：%s',
        'Subscription command is not executable: %s' => '订阅程序不可执行：%s',
        'Subscription completed successfully.' => '订阅操作执行成功。',
        'Subscription completed successfully, but the log contains warnings. Check the log below.' => '订阅执行成功，但日志中包含 warning，请查看下方日志。',
        'Subscription failed with exit code: %d. Check the log below.' => '订阅执行失败，返回码：%d。请查看下方日志。',
        'Sing-Box Subscription Management' => 'Sing-Box 订阅管理',
        'Subscription URL:' => '订阅地址：',
        'Enter Clash subscription URL' => '输入 Clash 订阅地址',
        'Leave empty to clear the current subscription URL. Non-empty values must be http:// or https:// URLs.' => '留空可清除当前订阅地址；非空时必须为 http:// 或 https:// URL。',
        'Save Settings' => '保存设置',
        'Subscribe Now' => '立即订阅',
        'Clear Log' => '清空日志',
        'Log Viewer' => '日志视图',
    ];
    static $ru = [
        'Subscription URL cannot exceed 2048 characters.' => 'URL подписки не может превышать 2048 символов.',
        'Subscription URL format is invalid.' => 'Неверный формат URL подписки.',
        'Subscription URL must start with http:// or https://.' => 'URL подписки должен начинаться с http:// или https://.',
        'Invalid CSRF token. Please refresh the page and try again.' => 'Недопустимый CSRF-токен. Обновите страницу и повторите попытку.',
        'Subscription URL saved.' => 'URL подписки сохранен.',
        'Failed to save subscription URL. Please check the configuration file permissions.' => 'Не удалось сохранить URL подписки. Проверьте права доступа к файлу конфигурации.',
        'Log cleared.' => 'Журнал очищен.',
        'Failed to clear the log. Please check the log file permissions.' => 'Не удалось очистить журнал. Проверьте права доступа к файлу журнала.',
        'Failed to clear the log file. Please check the log file permissions.' => 'Не удалось очистить файл журнала. Проверьте права доступа к файлу журнала.',
        'Subscription command does not exist: %s' => 'Команда подписки не существует: %s',
        'Subscription command is not executable: %s' => 'Команда подписки не является исполняемой: %s',
        'Subscription completed successfully.' => 'Подписка успешно выполнена.',
        'Subscription completed successfully, but the log contains warnings. Check the log below.' => 'Подписка успешно выполнена, но журнал содержит предупреждения. Проверьте журнал ниже.',
        'Subscription failed with exit code: %d. Check the log below.' => 'Подписка завершилась с кодом: %d. Проверьте журнал ниже.',
        'Sing-Box Subscription Management' => 'Управление подпиской Sing-Box',
        'Subscription URL:' => 'URL подписки:',
        'Enter Clash subscription URL' => 'Введите URL подписки Clash',
        'Leave empty to clear the current subscription URL. Non-empty values must be http:// or https:// URLs.' => 'Оставьте пустым, чтобы очистить текущий URL подписки. Непустое значение должно быть URL с http:// или https://.',
        'Save Settings' => 'Сохранить настройки',
        'Subscribe Now' => 'Подписаться сейчас',
        'Clear Log' => 'Очистить журнал',
        'Log Viewer' => 'Просмотр журнала',
    ];

    if (singbox_sub_language_is_zh()) {
        return $zh[$text] ?? $text;
    }
    if (singbox_sub_language_is_ru()) {
        return $ru[$text] ?? $text;
    }

    return $text;
}

/**
 * 记录日志
 */
function log_message(string $message, string $log_file = LOG_FILE): bool
{
    $time = date("Y-m-d H:i:s");
    $log_entry = "[{$time}] {$message}\n";
    return file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * 清空日志文件
 */
function clear_log(string $log_file = LOG_FILE): bool
{
    return file_put_contents($log_file, '', LOCK_EX) !== false;
}

/**
 * shell 单引号安全转义
 */
function shell_single_quote_escape(string $value): string
{
    return str_replace("'", "'\\''", $value);
}

/**
 * 保存环境变量到文件
 */
function save_env_variable(string $key, string $value, string $env_file = ENV_FILE): bool
{
    if ($key === '' || !preg_match('/^[A-Z0-9_]+$/', $key)) {
        return false;
    }

    $lines = file_exists($env_file) ? file($env_file, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }

    $new_lines = [];
    foreach ($lines as $line) {
        if (!preg_match('/^export\s+' . preg_quote($key, '/') . '=/', $line)) {
            $new_lines[] = $line;
        }
    }

    $escaped_value = shell_single_quote_escape($value);
    $new_lines[] = "export {$key}='{$escaped_value}'";

    return file_put_contents($env_file, implode("\n", $new_lines) . "\n", LOCK_EX) !== false;
}

/**
 * 加载环境变量
 */
function load_env_variables(string $env_file = ENV_FILE): array
{
    $env_vars = [];

    if (!file_exists($env_file)) {
        return $env_vars;
    }

    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($env_lines === false) {
        return $env_vars;
    }

    foreach ($env_lines as $line) {
        if (!preg_match('/^export\s+([A-Z0-9_]+)=(.*)$/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];
        $value = trim($matches[2]);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = substr($value, -1);
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                $value = substr($value, 1, -1);
            }
        }

        $value = str_replace("'\\''", "'", $value);
        $env_vars[$key] = $value;
    }

    return $env_vars;
}

/**
 * 订阅地址校验：允许为空；非空时必须为 http/https URL
 */
function validate_subscribe_url(string $url): ?string
{
    if ($url === '') {
        return null;
    }

    if (strlen($url) > 2048) {
        return singbox_sub_t('Subscription URL cannot exceed 2048 characters.');
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return singbox_sub_t('Subscription URL format is invalid.');
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
        return singbox_sub_t('Subscription URL must start with http:// or https://.');
    }

    return null;
}

/**
 * 页面跳转，避免重复提交
 */
function redirect_self(): void
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/**
 * HTML 转义输出
 */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 读取或创建本页面 CSRF 密钥。
 * 使用文件密钥而不是 PHP Session，避免 pfSense WebGUI 环境中 Session 不一致导致误判。
 */
function sub_csrf_secret(string $secret_file = CSRF_SECRET_FILE): string
{
    $dir = dirname($secret_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    if (is_readable($secret_file)) {
        $secret = trim((string)file_get_contents($secret_file));
        if ($secret !== '') {
            return $secret;
        }
    }

    $secret = bin2hex(random_bytes(32));
    @file_put_contents($secret_file, $secret . "\n", LOCK_EX);
    @chmod($secret_file, 0600);

    return $secret;
}

/**
 * 生成无 Session 依赖的 CSRF Token。
 * 格式：timestamp:hmac
 */
function sub_csrf_token(): string
{
    $ts = (string)time();
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $data = $ts . '|' . $remote . '|' . $ua . '|vpn_sub';
    $mac = hash_hmac('sha256', $data, sub_csrf_secret());

    return $ts . ':' . $mac;
}

/**
 * 输出 CSRF 隐藏字段
 */
function sub_csrf_field(): string
{
    return '<input type="hidden" name="sub_csrf_token" value="' . h(sub_csrf_token()) . '" />';
}

/**
 * 校验 CSRF Token。
 * Token 有效期 6 小时，避免长时间打开页面后误判。
 */
function sub_csrf_check(): bool
{
    $token = (string)($_POST['sub_csrf_token'] ?? '');
    if ($token === '' || strpos($token, ':') === false) {
        return false;
    }

    [$ts, $mac] = explode(':', $token, 2);
    if (!ctype_digit($ts)) {
        return false;
    }

    $timestamp = (int)$ts;
    if ($timestamp < time() - 21600 || $timestamp > time() + 300) {
        return false;
    }

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $data = $ts . '|' . $remote . '|' . $ua . '|vpn_sub';
    $expected = hash_hmac('sha256', $data, sub_csrf_secret());

    return hash_equals($expected, $mac);
}

// 使用 pfSense 的选项卡函数生成菜单
$tab_array = [
    0 => [gettext("Sing-Box"), false, "sing-box.php"],
    1 => [gettext("Sub"), true, "sing-box_sub.php"],
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sub_csrf_check()) {
        $input_errors[] = singbox_sub_t('Invalid CSRF token. Please refresh the page and try again.');
    } else {
        if (isset($_POST['save'])) {
            $url = trim((string)($_POST['subscribe_url'] ?? ''));
            $url_error = validate_subscribe_url($url);

            if ($url_error !== null) {
                $input_errors[] = $url_error;
            } else {
                if (empty($input_errors)) {
                    if (save_env_variable('SING_BOX_URL', $url)) {
                        $_SESSION['sub_notice'] = ['type' => 'success', 'text' => singbox_sub_t('Subscription URL saved.')];
                        redirect_self();
                    } else {
                        $input_errors[] = singbox_sub_t('Failed to save subscription URL. Please check the configuration file permissions.');
                    }
                }
            }
        }

        if (isset($_POST['action']) && $_POST['action'] === 'clear_log') {
            if (clear_log()) {
                $_SESSION['sub_notice'] = ['type' => 'success', 'text' => singbox_sub_t('Log cleared.')];
            } else {
                $_SESSION['sub_notice'] = ['type' => 'error', 'text' => singbox_sub_t('Failed to clear the log. Please check the log file permissions.')];
            }
            redirect_self();
        }

        if (isset($_POST['action']) && $_POST['action'] === 'run_sub') {
            if (!clear_log()) {
                $input_errors[] = singbox_sub_t('Failed to clear the log file. Please check the log file permissions.');
            }

            if (!file_exists(SUB_CMD)) {
                $input_errors[] = sprintf(singbox_sub_t('Subscription command does not exist: %s'), SUB_CMD);
            } elseif (!is_executable(SUB_CMD)) {
                $input_errors[] = sprintf(singbox_sub_t('Subscription command is not executable: %s'), SUB_CMD);
            }

            if (empty($input_errors)) {
                $dummy_output = [];
                $return_var = 1;
                $cmd = escapeshellarg(SUB_CMD) . ' >> ' . escapeshellarg(LOG_FILE) . ' 2>&1';
                exec($cmd, $dummy_output, $return_var);

                if ($return_var === 0) {
                    log_message(singbox_sub_t('Subscription completed successfully.'));

                    $warning_detected = false;
                    if (file_exists(LOG_FILE)) {
                        $log_after_run = file_get_contents(LOG_FILE);
                        if ($log_after_run !== false && preg_match('/\b(warn|warning)\b/i', $log_after_run)) {
                            $warning_detected = true;
                        }
                    }

                    if ($warning_detected) {
                        $_SESSION['sub_notice'] = ['type' => 'success', 'text' => singbox_sub_t('Subscription completed successfully, but the log contains warnings. Check the log below.')];
                    } else {
                        $_SESSION['sub_notice'] = ['type' => 'success', 'text' => singbox_sub_t('Subscription completed successfully.')];
                    }
                } else {
                    log_message('订阅操作执行失败，返回码：' . $return_var);
                    $_SESSION['sub_notice'] = [
                        'type' => 'error',
                        'text' => sprintf(singbox_sub_t('Subscription failed with exit code: %d. Check the log below.'), $return_var),
                    ];
                }

                redirect_self();
            }
        }
    }
}

if (!empty($_SESSION['sub_notice']) && is_array($_SESSION['sub_notice'])) {
    $notice = $_SESSION['sub_notice'];
    unset($_SESSION['sub_notice']);

    if (($notice['type'] ?? '') === 'success') {
        $savemsg = $notice['text'] ?? '';
    } elseif (($notice['type'] ?? '') === 'error' && !empty($notice['text'])) {
        $input_errors[] = $notice['text'];
    }
}

// 加载当前订阅地址
$env_vars = load_env_variables();
$current_url = $env_vars['SING_BOX_URL'] ?? ($env_vars['CLASH_URL'] ?? '');

$log_content = '';
if (file_exists(LOG_FILE)) {
    $log_raw = file_get_contents(LOG_FILE);
    if ($log_raw !== false) {
        $log_content = h($log_raw);
    }
}

include('head.inc');
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=h(singbox_sub_t('Sing-Box Subscription Management'));?></h2>
    </div>
    <div class="panel-body">
        <?php
        if (!empty($input_errors)) {
            print_input_errors($input_errors);
        }
        if (!empty($savemsg)) {
            print_info_box($savemsg, 'success');
        }
        ?>

        <form method="post">
            <?= sub_csrf_field(); ?>

            <div class="form-group">
                <label for="subscribe_url"><?=h(singbox_sub_t('Subscription URL:'));?></label>
                <input
                    type="url"
                    id="subscribe_url"
                    name="subscribe_url"
                    value="<?=h($current_url);?>"
                    class="form-control"
                    placeholder="<?=h(singbox_sub_t('Enter Clash subscription URL'));?>"
                    autocomplete="off"
                />
                <span class="help-block"><?=h(singbox_sub_t('Leave empty to clear the current subscription URL. Non-empty values must be http:// or https:// URLs.'));?></span>
            </div>

            <div class="form-group">
                <button type="submit" name="save" value="1" class="btn btn-primary">
                    <i class="fa fa-save"></i> <?=h(singbox_sub_t('Save Settings'));?>
                </button>
                <button type="submit" name="action" value="run_sub" class="btn btn-success">
                    <i class="fa fa-refresh"></i> <?=h(singbox_sub_t('Subscribe Now'));?>
                </button>
                <button type="submit" name="action" value="clear_log" class="btn btn-danger">
                    <i class="fa fa-trash"></i> <?=h(singbox_sub_t('Clear Log'));?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=h(singbox_sub_t('Log Viewer'));?></h2>
    </div>
    <div class="panel-body">
        <div class="form-group">
            <textarea readonly="readonly" rows="20" class="form-control"><?= $log_content; ?></textarea>
        </div>
    </div>
</div>

<?php include('foot.inc'); ?>
