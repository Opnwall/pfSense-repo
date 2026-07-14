<?php
/*
 * lang_update.php
 *
 * Chinese localization updater for pfSense.
 */

require_once("guiconfig.inc");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const LANGTOOL_LIST_FILE = '/var/lang/list';
const LANGTOOL_LIST_URL = 'https://cloud.pfchina.org/index.php/s/CYFKMKGY7spK7mj/download?path=%2FpfSense&files=list';
const LANGTOOL_TRUSTED_HOST = 'cloud.pfchina.org';

function langtool_system_language()
{
    if (function_exists('config_get_path')) {
        $language = (string)config_get_path('system/language', 'en');
    } elseif (isset($GLOBALS['config']['system']['language'])) {
        $language = (string)$GLOBALS['config']['system']['language'];
    } else {
        $language = 'en';
    }
    return strtolower(str_replace('-', '_', trim($language)));
}

function langtool_is_simplified_chinese()
{
    $language = langtool_system_language();
    return $language === 'zh' ||
        strpos($language, 'zh_cn') === 0 ||
        strpos($language, 'zh_sg') === 0 ||
        strpos($language, 'zh_hans') === 0;
}

function langtool_t($key)
{
    static $messages = array(
        'System' => '系统',
        'Localization Patch' => '汉化补丁',
        'Version Information' => '版本信息',
        'Current version' => '当前版本：',
        'Localization Actions' => '汉化列表',
        'Update List' => '更新列表',
        'Download address' => '下载地址：',
        'Select a localization package' => '请选择一个汉化包',
        'Start Localization' => '开始汉化',
        'Updating...' => '更新中...',
        'Localizing...' => '汉化中...',
        'Log' => '执行日志',
        'Readme' => '说明文档',
        'Unknown' => '未知',
        'The selected package is downloaded from the trusted pfchina.org source. Its version must match the running pfSense version before files are installed.' => '所选语言包会从受信任的 pfchina.org 下载地址获取，版本匹配当前 pfSense 后才会安装。',
        'Invalid form token, please refresh and try again.' => '表单令牌无效，请刷新页面后重试。',
        'No localization package selected.' => '未选择汉化包。',
        'Downloading language list...' => '正在下载语言列表...',
        'Language list updated.' => '列表更新完成。',
        'Language list update failed.' => '更新列表失败。',
        'Language list has no valid entries.' => '语言列表没有有效条目。',
        'Download URL is not trusted or is not in the current language list.' => '下载地址不在当前语言列表或来源不可信。',
        'Unable to create temporary directory.' => '无法创建临时目录。',
        'Downloading localization package...' => '正在下载语言包...',
        'Download failed.' => '下载失败。',
        'Download completed.' => '下载完成。',
        'Checksum verification failed.' => '压缩包校验失败。',
        'Unable to read archive file list.' => '无法读取语言包文件列表。',
        'Archive is empty.' => '语言包为空。',
        'Archive contains unsupported path' => '语言包包含不允许的路径',
        'Archive is missing etc/version.' => '语言包缺少 etc/version，无法校验版本。',
        'Extracting localization package...' => '正在解压语言包...',
        'Extraction failed.' => '解压失败。',
        'System version' => '系统版本',
        'Localization version' => '汉化版本',
        'Version mismatch, installation aborted.' => '版本不匹配，中止汉化。',
        'Version matched.' => '版本匹配，执行汉化。',
        'Installing localization files...' => '正在安装汉化文件...',
        'Installation failed.' => '汉化文件安装失败。',
        'Localization completed.' => '汉化完成。',
        'No readme.md found in the package.' => '语言包中未找到 readme.md。',
        'Cleaning temporary files...' => '清理临时文件。',
        'Restarting PHP-FPM...' => '正在重启 PHP-FPM...',
    );

    if (!langtool_is_simplified_chinese()) {
        return $key;
    }
    return $messages[$key] ?? $key;
}

function langtool_run($command)
{
    exec($command . ' 2>&1', $output, $status);
    return array('status' => $status, 'output' => (array)$output);
}

function langtool_log(&$log, $message)
{
    $log[] = $message;
}

function langtool_system_version()
{
    $version = is_readable('/etc/version') ? trim((string)file_get_contents('/etc/version')) : '';
    return $version !== '' ? $version : langtool_t('Unknown');
}

function langtool_system_type($version)
{
    return preg_match('/^2\./', $version) ? 'pfSense CE' : 'pfSense Plus';
}

function langtool_validate_url($url)
{
    $parts = parse_url($url);
    return is_array($parts) &&
        ($parts['scheme'] ?? '') === 'https' &&
        strcasecmp($parts['host'] ?? '', LANGTOOL_TRUSTED_HOST) === 0;
}

function langtool_valid_sha256($hash)
{
    return is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
}

function langtool_parse_list_line($line)
{
    $line = trim((string)$line);
    if ($line === '' || $line[0] === '#') {
        return false;
    }

    $parts = explode('=', $line, 2);
    if (count($parts) !== 2) {
        return false;
    }

    $label = trim($parts[0]);
    $value = trim($parts[1]);
    $url = '';
    $sha256 = '';

    if (preg_match('/^([\'"])(.*?)\1(?:\s+sha256=([a-f0-9]{64}))?$/i', $value, $matches) === 1) {
        $url = $matches[2];
        $sha256 = $matches[3] ?? '';
    } elseif (preg_match('/^(.+?)\|([a-f0-9]{64})$/i', $value, $matches) === 1) {
        $url = trim(trim($matches[1]), "' \"");
        $sha256 = $matches[2];
    }

    if ($label === '' || $url === '' || !langtool_validate_url($url)) {
        return false;
    }

    return array(
        'label' => $label,
        'url' => $url,
        'sha256' => langtool_valid_sha256($sha256) ? strtolower($sha256) : '',
    );
}

function langtool_update_list(&$log)
{
    if (!is_dir(dirname(LANGTOOL_LIST_FILE))) {
        mkdir(dirname(LANGTOOL_LIST_FILE), 0755, true);
    }
    langtool_log($log, langtool_t('Downloading language list...'));
    $tmp_list = LANGTOOL_LIST_FILE . '.tmp';
    $result = langtool_run('fetch -o ' . escapeshellarg($tmp_list) . ' ' . escapeshellarg(LANGTOOL_LIST_URL));
    if ($result['status'] !== 0 || !is_readable($tmp_list)) {
        @unlink($tmp_list);
        langtool_log($log, langtool_t('Language list update failed.'));
        $log = array_merge($log, $result['output']);
        return false;
    }
    if (count(langtool_parse_list_file($tmp_list)) === 0) {
        @unlink($tmp_list);
        langtool_log($log, langtool_t('Language list has no valid entries.'));
        return false;
    }
    rename($tmp_list, LANGTOOL_LIST_FILE);
    chmod(LANGTOOL_LIST_FILE, 0644);
    langtool_log($log, langtool_t('Language list updated.'));
    return true;
}

function langtool_parse_list_file($file)
{
    $entries = array();
    if (!is_readable($file)) {
        return $entries;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = langtool_parse_list_line($line);
        if ($entry !== false) {
            $entries[$entry['url']] = $entry;
        }
    }
    return $entries;
}

function langtool_list_entries()
{
    return langtool_parse_list_file(LANGTOOL_LIST_FILE);
}

function langtool_tmpdir(&$log)
{
    $tmp_base = tempnam(sys_get_temp_dir(), 'lang_update_');
    if ($tmp_base === false) {
        langtool_log($log, langtool_t('Unable to create temporary directory.'));
        return false;
    }
    unlink($tmp_base);
    if (!mkdir($tmp_base, 0700)) {
        langtool_log($log, langtool_t('Unable to create temporary directory.'));
        return false;
    }
    return $tmp_base;
}

function langtool_cleanup($tmp_dir, &$log)
{
    if (!is_string($tmp_dir) || $tmp_dir === '' || !is_dir($tmp_dir)) {
        return;
    }
    $real_tmp_dir = realpath($tmp_dir);
    $real_system_tmp = realpath(sys_get_temp_dir());
    if ($real_tmp_dir === false || $real_system_tmp === false || strpos($real_tmp_dir, $real_system_tmp . DIRECTORY_SEPARATOR . 'lang_update_') !== 0) {
        return;
    }
    langtool_run('rm -rf ' . escapeshellarg($real_tmp_dir));
    langtool_log($log, langtool_t('Cleaning temporary files...'));
}

function langtool_archive_entries($archive, &$log)
{
    $result = langtool_run('unzip -Z1 ' . escapeshellarg($archive));
    if ($result['status'] !== 0) {
        langtool_log($log, langtool_t('Unable to read archive file list.'));
        $log = array_merge($log, $result['output']);
        return false;
    }
    $entries = array_values(array_filter(array_map('trim', $result['output']), function ($entry) {
        return $entry !== '';
    }));
    if (empty($entries)) {
        langtool_log($log, langtool_t('Archive is empty.'));
        return false;
    }
    return $entries;
}

function langtool_normalize_entry($entry)
{
    $entry = str_replace('\\', '/', trim($entry));
    $entry = preg_replace('#^\./+#', '', $entry);
    if ($entry === '' || $entry[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $entry) || preg_match('#^[A-Za-z]:/#', $entry)) {
        return false;
    }
    return $entry;
}

function langtool_validate_entries($entries, &$log)
{
    $has_version = false;
    foreach ($entries as $entry) {
        $normalized = langtool_normalize_entry($entry);
        $trimmed = $normalized === false ? '' : rtrim($normalized, '/');
        $allowed = $trimmed === 'readme.md' ||
            $trimmed === 'etc' || strpos($trimmed, 'etc/') === 0 ||
            $trimmed === 'usr' || strpos($trimmed, 'usr/') === 0;
        if ($normalized === false || !$allowed) {
            langtool_log($log, langtool_t('Archive contains unsupported path') . ': ' . $entry);
            return false;
        }
        if ($trimmed === 'etc/version') {
            $has_version = true;
        }
    }
    if (!$has_version) {
        langtool_log($log, langtool_t('Archive is missing etc/version.'));
        return false;
    }
    return true;
}

function langtool_validate_checksum($archive, $expected_hash, &$log)
{
    if (!langtool_valid_sha256($expected_hash)) {
        return true;
    }
    $actual_hash = hash_file('sha256', $archive);
    if (!hash_equals(strtolower($expected_hash), strtolower((string)$actual_hash))) {
        langtool_log($log, langtool_t('Checksum verification failed.'));
        return false;
    }
    return true;
}

function langtool_version_token($version)
{
    if (preg_match('/[0-9]+(?:\.[0-9]+){1,3}/', (string)$version, $matches) === 1) {
        return $matches[0];
    }
    return trim((string)$version);
}

function langtool_version_matches($system_version, $package_version)
{
    $system_token = langtool_version_token($system_version);
    $package_token = langtool_version_token($package_version);
    return $system_token !== '' && hash_equals($system_token, $package_token);
}

function langtool_readme($staging)
{
    $readme = $staging . '/readme.md';
    if (is_readable($readme)) {
        $content = file_get_contents($readme);
        return is_string($content) ? $content : langtool_t('No readme.md found in the package.');
    }
    return langtool_t('No readme.md found in the package.');
}

function langtool_restart_php(&$log)
{
    langtool_run("nohup sh -c 'sleep 2 && /etc/rc.php-fpm_restart' >/dev/null 2>&1 &");
    langtool_log($log, langtool_t('Restarting PHP-FPM...'));
}

function langtool_install_package($selected_url, $system_version, &$log, &$readme)
{
    $allowed_entries = langtool_list_entries();
    if (!isset($allowed_entries[$selected_url]) || !langtool_validate_url($selected_url)) {
        langtool_log($log, langtool_t('Download URL is not trusted or is not in the current language list.'));
        return false;
    }
    $expected_hash = $allowed_entries[$selected_url]['sha256'] ?? '';

    $tmp_dir = langtool_tmpdir($log);
    if ($tmp_dir === false) {
        return false;
    }

    $archive = $tmp_dir . '/lang.zip';
    $staging = $tmp_dir . '/staging';

    try {
        langtool_log($log, langtool_t('Downloading localization package...'));
        $download = langtool_run('fetch -o ' . escapeshellarg($archive) . ' ' . escapeshellarg($selected_url));
        if ($download['status'] !== 0 || !is_readable($archive)) {
            langtool_log($log, langtool_t('Download failed.'));
            $log = array_merge($log, $download['output']);
            return false;
        }
        langtool_log($log, langtool_t('Download completed.'));

        if (!langtool_validate_checksum($archive, $expected_hash, $log)) {
            return false;
        }
        $entries = langtool_archive_entries($archive, $log);
        if ($entries === false || !langtool_validate_entries($entries, $log)) {
            return false;
        }

        mkdir($staging, 0700);
        langtool_log($log, langtool_t('Extracting localization package...'));
        $extract = langtool_run('unzip -q -o ' . escapeshellarg($archive) . ' -d ' . escapeshellarg($staging));
        if ($extract['status'] !== 0) {
            langtool_log($log, langtool_t('Extraction failed.'));
            $log = array_merge($log, $extract['output']);
            return false;
        }

        $package_version = is_readable($staging . '/etc/version') ? trim((string)file_get_contents($staging . '/etc/version')) : '';
        langtool_log($log, langtool_t('System version') . ': ' . $system_version);
        langtool_log($log, langtool_t('Localization version') . ': ' . $package_version);
        if ($package_version === '' || !langtool_version_matches($system_version, $package_version)) {
            langtool_log($log, langtool_t('Version mismatch, installation aborted.'));
            return false;
        }
        langtool_log($log, langtool_t('Version matched.'));

        $readme = langtool_readme($staging);
        @unlink($staging . '/readme.md');

        langtool_log($log, langtool_t('Installing localization files...'));
        $install = langtool_run('tar -C ' . escapeshellarg($staging) . ' -cf - etc usr | tar -C / -xf -');
        if ($install['status'] !== 0) {
            langtool_log($log, langtool_t('Installation failed.'));
            $log = array_merge($log, $install['output']);
            return false;
        }

        $auth_file = '/etc/inc/auth.inc';
        if (is_writable($auth_file)) {
            $auth_content = file_get_contents($auth_file);
            if (is_string($auth_content)) {
                $auth_content = str_replace('View license.', '【汉化】：鉄血男兒。', $auth_content);
                $auth_content = str_replace('https://pfsense.org/license', 'https://pfchina.org/', $auth_content);
                file_put_contents($auth_file, $auth_content);
            }
        }

        langtool_log($log, langtool_t('Localization completed.'));
        langtool_restart_php($log);
        return true;
    } finally {
        langtool_cleanup($tmp_dir, $log);
    }
}

if (empty($_SESSION['lang_update_csrf'])) {
    $_SESSION['lang_update_csrf'] = bin2hex(random_bytes(32));
}

$system_version = langtool_system_version();
$system_type = langtool_system_type($system_version);
$log_output = array();
$readme_output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['lang_update_csrf'], $csrf_token)) {
        langtool_log($log_output, langtool_t('Invalid form token, please refresh and try again.'));
    } elseif ($action === 'update_list') {
        langtool_update_list($log_output);
    } elseif ($action === 'start_localization') {
        $selected_lang = $_POST['selected_lang'] ?? '';
        if ($selected_lang === '') {
            langtool_log($log_output, langtool_t('No localization package selected.'));
        } else {
            langtool_install_package($selected_lang, $system_version, $log_output, $readme_output);
        }
    } else {
        langtool_log($log_output, langtool_t('No localization package selected.'));
    }
}

$lang_entries = langtool_list_entries();
$pgtitle = array(langtool_t('System'), langtool_t('Localization Patch'));
include("head.inc");
?>

<style>
    :root {
        --langtool-content-offset: 130px;
    }
    .langtool-panel.panel {
        margin-bottom: 10px;
    }
    .langtool-panel .panel-heading {
        padding-left: 12px;
        padding-right: 12px;
    }
    .langtool-panel .panel-body {
        padding: 12px 14px;
    }
    .langtool-summary {
        margin: 0 0 8px var(--langtool-content-offset);
        max-width: 820px;
    }
    .langtool-summary > tbody > tr > th,
    .langtool-summary > tbody > tr > td {
        border-top: 0;
        line-height: 20px;
        padding: 3px 10px 3px 0;
        vertical-align: middle;
    }
    .langtool-summary > tbody > tr > th {
        color: #555;
        font-weight: 600;
        width: 120px;
        white-space: nowrap;
    }
    .langtool-help {
        color: #777;
        margin: 6px 0 0 var(--langtool-content-offset);
    }
    .langtool-actions {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-left: var(--langtool-content-offset);
        margin-top: 8px;
        max-width: 980px;
        padding-bottom: 8px;
    }
    .langtool-select-wrap {
        flex: 0 1 260px;
        margin: 0;
        max-width: 260px;
        min-width: 260px;
    }
    .langtool-select-wrap .form-control {
        width: 100%;
    }
    .langtool-action-buttons {
        align-items: center;
        display: flex;
        flex: 0 0 auto;
        gap: 8px;
    }
    .langtool-action-buttons form {
        margin: 0;
    }
    .langtool-log {
        resize: vertical;
    }
    @media (max-width: 767px) {
        :root {
            --langtool-content-offset: 0;
        }
        .langtool-summary > tbody > tr > th,
        .langtool-summary > tbody > tr > td {
            display: block;
            width: auto;
        }
        .langtool-action-buttons {
            width: 100%;
        }
        .langtool-action-buttons .btn {
            flex: 1 1 auto;
        }
    }
</style>

<div class="panel panel-default langtool-list-panel">
  <div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars(langtool_t('Version Information'), ENT_QUOTES, 'UTF-8')?></h2></div>
  <div class="panel-body">
    <table class="table langtool-summary">
      <tbody>
        <tr>
          <th><?=htmlspecialchars(langtool_t('Current version'), ENT_QUOTES, 'UTF-8')?></th>
          <td><?=htmlspecialchars($system_type . ' ' . $system_version, ENT_QUOTES, 'UTF-8')?></td>
        </tr>
        <tr>
          <th><?=htmlspecialchars(langtool_t('Download address'), ENT_QUOTES, 'UTF-8')?></th>
          <td><?=htmlspecialchars(LANGTOOL_TRUSTED_HOST, ENT_QUOTES, 'UTF-8')?></td>
        </tr>
      </tbody>
    </table>
    <p class="langtool-help"><?=htmlspecialchars(langtool_t('The selected package is downloaded from the trusted pfchina.org source. Its version must match the running pfSense version before files are installed.'), ENT_QUOTES, 'UTF-8')?></p>
  </div>
</div>

<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title"><?=htmlspecialchars(langtool_t('Localization Actions'), ENT_QUOTES, 'UTF-8')?></h2></div>
  <div class="panel-body">
    <div class="langtool-actions">
      <form method="post" id="updateForm" class="langtool-select-wrap">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['lang_update_csrf'], ENT_QUOTES, 'UTF-8')?>">
        <input type="hidden" name="action" value="start_localization">
        <select name="selected_lang" id="lang_selection" class="form-control" required>
            <option value=""><?=htmlspecialchars(langtool_t('Select a localization package'), ENT_QUOTES, 'UTF-8')?></option>
            <?php foreach ($lang_entries as $url => $entry): ?>
              <option value="<?=htmlspecialchars($url, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8')?></option>
            <?php endforeach; ?>
          </select>
      </form>
      <div class="langtool-action-buttons">
        <form method="post" id="updateListForm">
          <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['lang_update_csrf'], ENT_QUOTES, 'UTF-8')?>">
          <input type="hidden" name="action" value="update_list">
          <button type="submit" class="btn btn-info" id="updateListBtn">
            <i class="fa fa-refresh"></i> <?=htmlspecialchars(langtool_t('Update List'), ENT_QUOTES, 'UTF-8')?>
          </button>
        </form>
        <button type="submit" form="updateForm" class="btn btn-primary" id="updateBtn">
          <i class="fa fa-language"></i> <?=htmlspecialchars(langtool_t('Start Localization'), ENT_QUOTES, 'UTF-8')?>
        </button>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($log_output)): ?>
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title"><i class="fa fa-list-alt"></i> <?=htmlspecialchars(langtool_t('Log'), ENT_QUOTES, 'UTF-8')?></h2></div>
  <div class="panel-body">
    <textarea rows="10" class="form-control langtool-log" readonly><?=htmlspecialchars(implode("\n", $log_output), ENT_QUOTES, 'UTF-8')?></textarea>
  </div>
</div>
<?php endif; ?>

<?php if ($readme_output !== ''): ?>
<div class="panel panel-default">
  <div class="panel-heading"><h2 class="panel-title"><i class="fa fa-file-text-o"></i> <?=htmlspecialchars(langtool_t('Readme'), ENT_QUOTES, 'UTF-8')?></h2></div>
  <div class="panel-body">
    <pre class="pre-scrollable" style="max-height: 300px;"><?=htmlspecialchars($readme_output, ENT_QUOTES, 'UTF-8')?></pre>
  </div>
</div>
<?php endif; ?>

<script>
//<![CDATA[
document.addEventListener("DOMContentLoaded", function() {
    var listForm = document.getElementById("updateListForm");
    var updateForm = document.getElementById("updateForm");
    var listBtn = document.getElementById("updateListBtn");
    var updateBtn = document.getElementById("updateBtn");

    if (listForm && listBtn) {
        listForm.addEventListener("submit", function() {
            listBtn.disabled = true;
            listBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?=htmlspecialchars(langtool_t('Updating...'), ENT_QUOTES, 'UTF-8')?>';
        });
    }
    if (updateForm && updateBtn) {
        updateForm.addEventListener("submit", function() {
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?=htmlspecialchars(langtool_t('Localizing...'), ENT_QUOTES, 'UTF-8')?>';
        });
    }
});
//]]>
</script>

<?php include("foot.inc"); ?>
