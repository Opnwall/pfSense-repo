<?php
require_once("guiconfig.inc");

$pgtitle = [gettext('Services'), gettext('AdGuard Home')];

$service_name = "adguardhome";
$config_file = "/usr/local/etc/adguardhome/AdGuardHome.yaml";
$log_file = "/var/log/adguardhome.log";
$adguardhome_bin = "/usr/local/bin/AdGuardHome";

$message = "";
$message_type = "info";

function adguardhome_csrf_check()
{
    if (function_exists('csrf_check')) {
        return csrf_check();
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return hash_equals($_SESSION['adguardhome_csrf_token'] ?? '', $_POST['adguardhome_csrf_token'] ?? '');
}

function adguardhome_csrf_token_field()
{
    if (function_exists('csrf_token')) {
        csrf_token();
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['adguardhome_csrf_token'])) {
        try {
            $_SESSION['adguardhome_csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['adguardhome_csrf_token'] = sha1(uniqid((string)mt_rand(), true));
        }
    }

    echo '<input type="hidden" name="adguardhome_csrf_token" value="' .
        htmlspecialchars($_SESSION['adguardhome_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .
        '">';
}

function adguardhome_service_running()
{
    exec('/usr/sbin/service adguardhome status 2>&1', $output, $rc);
    return $rc === 0;
}

function adguardhome_service_action($action, $binary)
{
    $allowed = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowed, true)) {
        return ['message' => gettext('Invalid service action.'), 'type' => 'danger'];
    }

    if (($action === 'start' || $action === 'restart') && !is_executable($binary)) {
        return ['message' => gettext('AdGuard Home binary is missing or not executable:') . ' ' . $binary, 'type' => 'danger'];
    }

    $output = [];
    $return_var = 1;
    exec('/usr/sbin/service adguardhome ' . escapeshellarg($action) . ' 2>&1', $output, $return_var);

    if ($return_var === 0) {
        return ['message' => gettext('Service command completed.'), 'type' => 'success'];
    }

    $detail = trim(implode("\n", $output));
    if ($detail !== '') {
        $detail = "\n" . $detail;
    }

    return ['message' => gettext('Service command failed.') . $detail, 'type' => 'danger'];
}

function adguardhome_save_config($file, $content)
{
    if (trim($content) === '') {
        return ['message' => gettext('Configuration cannot be empty.'), 'type' => 'danger'];
    }

    $target_dir = dirname($file);
    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
        return ['message' => gettext('Cannot create configuration directory.'), 'type' => 'danger'];
    }

    if (!is_writable($target_dir)) {
        return ['message' => gettext('Configuration directory is not writable.'), 'type' => 'danger'];
    }

    $tmp_file = tempnam($target_dir, 'adguardhome_save_');
    if ($tmp_file === false) {
        return ['message' => gettext('Cannot create temporary configuration file.'), 'type' => 'danger'];
    }

    if (file_put_contents($tmp_file, $content, LOCK_EX) === false) {
        @unlink($tmp_file);
        return ['message' => gettext('Cannot write temporary configuration file.'), 'type' => 'danger'];
    }

    $original_perms = file_exists($file) ? (@fileperms($file) & 0777) : 0600;
    if (!@rename($tmp_file, $file)) {
        @unlink($tmp_file);
        return ['message' => gettext('Cannot replace configuration file.'), 'type' => 'danger'];
    }

    @chmod($file, $original_perms);
    return ['message' => gettext('Configuration saved.'), 'type' => 'success'];
}

if (($_GET['status'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode(['status' => adguardhome_service_running() ? 'running' : 'stopped']);
    exit;
}

if ($_POST) {
    if (!adguardhome_csrf_check()) {
        $result = ['message' => gettext('Request validation failed. Refresh the page and try again.'), 'type' => 'danger'];
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_config') {
            $result = adguardhome_save_config($config_file, $_POST['config_content'] ?? '');
        } else {
            $result = adguardhome_service_action($action, $adguardhome_bin);
        }
    }

    $message = $result['message'];
    $message_type = $result['type'];
}

$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$host = preg_replace('/:\d+$/', '', $host);
$web_url = 'http://' . ($host ?: '127.0.0.1') . ':3000/';
$version = is_executable($adguardhome_bin) ? trim(shell_exec(escapeshellarg($adguardhome_bin) . ' --version 2>&1')) : gettext('AdGuard Home binary is not installed.');
$config_content = file_exists($config_file) ? htmlspecialchars(file_get_contents($config_file), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';

include("head.inc");
?>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type); ?>">
        <?= nl2br(htmlspecialchars($message)); ?>
    </div>
<?php endif; ?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?= htmlspecialchars(gettext('Service Status')); ?></h2>
    </div>
    <div class="panel-body">
        <div id="adguardhome-status" class="alert alert-info">
            <i class="fa fa-circle-o-notch fa-spin"></i> <?= htmlspecialchars(gettext('Checking...')); ?>
        </div>
        <table class="table table-striped table-condensed">
            <tbody>
                <tr>
                    <th style="width: 180px;"><?= htmlspecialchars(gettext('Version')); ?></th>
                    <td><pre style="margin:0; white-space:pre-wrap;"><?= htmlspecialchars($version); ?></pre></td>
                </tr>
                <tr>
                    <th><?= htmlspecialchars(gettext('Links')); ?></th>
                    <td><a href="<?= htmlspecialchars($web_url); ?>" target="_blank"><?= htmlspecialchars($web_url); ?></a></td>
                </tr>
                <tr>
                    <th><?= htmlspecialchars(gettext('Recommended DNS Flow')); ?></th>
                    <td><code><?= htmlspecialchars(gettext('Client -> AdGuard Home:53 -> Unbound:5353 -> Upstream DNS')); ?></code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?= htmlspecialchars(gettext('Service Control')); ?></h2>
    </div>
    <div class="panel-body">
        <form method="post" class="form-inline">
            <?php adguardhome_csrf_token_field(); ?>
            <button type="submit" name="action" value="start" class="btn btn-success">
                <i class="fa fa-play"></i> <?= htmlspecialchars(gettext('Start')); ?>
            </button>
            <button type="submit" name="action" value="stop" class="btn btn-danger">
                <i class="fa fa-stop"></i> <?= htmlspecialchars(gettext('Stop')); ?>
            </button>
            <button type="submit" name="action" value="restart" class="btn btn-warning">
                <i class="fa fa-refresh"></i> <?= htmlspecialchars(gettext('Restart')); ?>
            </button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?= htmlspecialchars(gettext('Configuration File')); ?></h2>
    </div>
    <div class="panel-body">
        <form method="post">
            <?php adguardhome_csrf_token_field(); ?>
            <textarea name="config_content" rows="14" class="form-control" style="font-family: monospace;"><?= $config_content; ?></textarea>
            <br>
            <button type="submit" name="action" value="save_config" class="btn btn-primary">
                <i class="fa fa-save"></i> <?= htmlspecialchars(gettext('Save Configuration')); ?>
            </button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?= htmlspecialchars(gettext('Log')); ?></h2>
    </div>
    <div class="panel-body">
        <textarea id="log-viewer" rows="10" class="form-control" readonly></textarea>
    </div>
</div>

<script>
const adguardHomeI18n = <?= json_encode([
    'running' => gettext('AdGuard Home is running'),
    'stopped' => gettext('AdGuard Home is stopped'),
    'status_failed' => gettext('Status check failed'),
    'log_failed' => gettext('Cannot load log.'),
    'error' => gettext('Error'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function checkAdGuardHomeStatus() {
    fetch('adguardhome.php?status=1')
        .then(response => {
            if (!response.ok) throw new Error('bad status response');
            return response.json();
        })
        .then(data => {
            const statusElement = document.getElementById('adguardhome-status');
            if (data.status === 'running') {
                statusElement.innerHTML = '<i class="fa fa-check-circle text-success"></i> ' + adguardHomeI18n.running;
                statusElement.className = 'alert alert-success';
            } else {
                statusElement.innerHTML = '<i class="fa fa-times-circle text-danger"></i> ' + adguardHomeI18n.stopped;
                statusElement.className = 'alert alert-danger';
            }
        })
        .catch(() => {
            const statusElement = document.getElementById('adguardhome-status');
            statusElement.innerHTML = '<i class="fa fa-exclamation-triangle text-warning"></i> ' + adguardHomeI18n.status_failed;
            statusElement.className = 'alert alert-warning';
        });
}

function refreshLogs() {
    fetch('adguardhome_log.php')
        .then(response => {
            if (!response.ok) throw new Error('bad log response');
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
        .catch(() => {
            document.getElementById('log-viewer').value = '[' + adguardHomeI18n.error + '] ' + adguardHomeI18n.log_failed;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    checkAdGuardHomeStatus();
    refreshLogs();
    setInterval(checkAdGuardHomeStatus, 3000);
    setInterval(refreshLogs, 3000);
});
</script>

<?php include("foot.inc"); ?>
