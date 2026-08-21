<?php

require_once("config.inc");
require_once("guiconfig.inc");

if (!function_exists('zerotier_translate')) {
    function zerotier_translate($message) {
        return function_exists('gettext') ? gettext($message) : $message;
    }
}

$zerotier_inc_candidates = array(
    '/usr/local/pkg/zerotier.inc',
    '/etc/inc/pkg/zerotier.inc',
    __DIR__ . '/zerotier.inc'
);

$zerotier_inc_loaded = false;
foreach ($zerotier_inc_candidates as $zerotier_inc) {
    if (is_file($zerotier_inc)) {
        require_once($zerotier_inc);
        $zerotier_inc_loaded = true;
        break;
    }
}


if (!$zerotier_inc_loaded) {
    $include_path = get_include_path();
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
    print(zerotier_translate("Missing required file: zerotier.inc") . "\\n");
    print(zerotier_translate("Checked paths:") . "\\n - " . implode("\\n - ", $zerotier_inc_candidates) . "\\n");
    print(zerotier_translate("include_path:") . " " . $include_path . "\\n");
    exit;
}

function zerotier_status_fallback() {
    $cli_candidates = array(
        '/usr/local/bin/zerotier-cli',
        '/usr/local/sbin/zerotier-cli',
        'zerotier-cli'
    );

    foreach ($cli_candidates as $cli) {
        $output = array();
        $return_var = 1;
        @exec(escapeshellcmd($cli) . ' info 2>/dev/null', $output, $return_var);
        if ($return_var !== 0 || empty($output)) {
            continue;
        }

        $line = trim(implode("\n", $output));
        if ($line === '') {
            continue;
        }

        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 4) {
            continue;
        }

        $status = new stdClass();
        $status->raw = $line;
        $status->code = $parts[0];
        $status->address = $parts[2];
        $status->version = $parts[3];
        $status->state = isset($parts[4]) ? $parts[4] : null;
        return $status;
    }

    return null;
}

function zerotier_run_service_command($action) {
    if (!in_array($action, array('start', 'stop', 'restart'), true)) {
        return false;
    }

    $command = '/usr/sbin/service zerotier ' . $action;
    if (function_exists('mwexec_bg')) {
        mwexec_bg($command);
        return true;
    }

    @exec($command . ' >/dev/null 2>&1 &');
    return true;
}

if (!isset($config['installedpackages']['zerotier']) || !is_array($config['installedpackages']['zerotier'])) {
    $config['installedpackages']['zerotier'] = array();
}

if (!isset($config['installedpackages']['zerotier']['config']) || !is_array($config['installedpackages']['zerotier']['config'])) {
    $config['installedpackages']['zerotier']['config'] = array();
}

$input_errors = array();
$local_conf_requested = null;

if (isset($_POST['save'])) {
    $enable_requested = isset($_POST['enable']);
    $local_conf_requested = isset($_POST['localconf']) && is_string($_POST['localconf'])
        ? trim(str_replace("\r\n", "\n", $_POST['localconf']))
        : '';
    $local_conf_previous = isset($config['installedpackages']['zerotier']['config'][0]['localconf'])
        ? (string)$config['installedpackages']['zerotier']['config'][0]['localconf']
        : '';
    $local_conf_error = null;

    if (!zerotier_validate_local_conf($local_conf_requested, $local_conf_error)) {
        $input_errors[] = $local_conf_error;
    }

    if (empty($input_errors)) {
        if ($enable_requested) {
            $config['installedpackages']['zerotier']['config'][0]['enable'] = 'yes';
        }
        else {
            $config['installedpackages']['zerotier']['config'][0]['enable'] = null;
        }

        $config['installedpackages']['zerotier']['config'][0]['localconf'] = $local_conf_requested;

        if (!zerotier_write_local_conf($local_conf_requested, $local_conf_error)) {
            $input_errors[] = $local_conf_error;
        }
    }

    if (empty($input_errors)) {
        $service_running_before_save = zerotier_is_running();
        $service_action = $enable_requested ? 'start' : 'stop';
        if ($enable_requested && $service_running_before_save && $local_conf_previous !== $local_conf_requested) {
            $service_action = 'restart';
        }

        write_config(zerotier_translate("Update Zerotier configuration."));
        zerotier_set_boot_enabled($enable_requested);
        zerotier_run_service_command($service_action);

        header("Location: zerotier.php");
        exit;
    }
}

$enable_mode = isset($config['installedpackages']['zerotier']['config'][0]['enable'])
    ? $config['installedpackages']['zerotier']['config'][0]['enable']
    : null;

$zerotier_running = false;
$zerotier_service_names = array('zerotier', 'zerotier-one', 'zerotierone');
foreach ($zerotier_service_names as $zerotier_service_name) {
    if (is_service_running($zerotier_service_name)) {
        $zerotier_running = true;
        break;
    }
}

if (!$zerotier_running && function_exists('is_process_running')) {
    $zerotier_running = is_process_running('zerotier-one') || is_process_running('zerotierone');
}

$pgtitle = array(zerotier_translate("VPN"), zerotier_translate("ZeroTier VPN"), zerotier_translate("Configuration"));
$pglinks = array("", "pkg_edit.php?xml=zerotier.xml", "@self");
require("head.inc");

$tab_array = array();
$tab_array[] = array(zerotier_translate("Networks"), false, "zerotier_networks.php");
$tab_array[] = array(zerotier_translate("Peers"), false, "zerotier_peers.php");
$tab_array[] = array(zerotier_translate("Configuration"), true, "zerotier.php");
add_package_tabs(zerotier_translate("Zerotier"), $tab_array);
display_top_tabs($tab_array);

if ($enable_mode != 'yes' || !$zerotier_running) {
    print_info_box(zerotier_translate("The Zerotier service is not running."), "warning", false);
}


$enable['mode'] = $enable_mode;
$local_conf_value = isset($config['installedpackages']['zerotier']['config'][0]['localconf'])
    ? (string)$config['installedpackages']['zerotier']['config'][0]['localconf']
    : zerotier_read_local_conf();
if ($local_conf_requested !== null) {
    $local_conf_value = $local_conf_requested;
}

$status = null;
$status_error = null;
if ($enable_mode == 'yes' && $zerotier_running) {
    $status = zerotier_status();
    if (!$status || !isset($status->address)) {
        $status = zerotier_status_fallback();
    }
    if (!$status || !isset($status->address)) {
        $status_error = zerotier_translate("Zerotier appears to be running, but status data could not be retrieved yet.");
    }
}
?>
<div class="panel panel-default">
    <div class="panel-heading"><h2 class="panel-title"><?php print(zerotier_translate("Zerotier Status")); ?></h2></div>
    <div class="panel-body">
        <?php if ($status && isset($status->address)): ?>
            <dl class="dl-horizontal">
                <dt><?php print(zerotier_translate("Address")); ?></dt><dd><?php print(htmlspecialchars($status->address)); ?></dd>
                <dt><?php print(zerotier_translate("Version")); ?></dt><dd><?php print(htmlspecialchars($status->version)); ?></dd>
                <?php if (isset($status->state) && $status->state !== null): ?>
                    <dt><?php print(zerotier_translate("State")); ?></dt><dd><?php print(htmlspecialchars($status->state)); ?></dd>
                <?php endif; ?>
            </dl>
        <?php elseif ($status_error): ?>
            <p><?php print(htmlspecialchars($status_error)); ?></p>
        <?php else: ?>
            <p><?php print(zerotier_translate("Zerotier service is not running or no status data is available.")); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php
if (!empty($input_errors)) {
    print_input_errors($input_errors);
}

$form = new Form();
$section = new Form_Section(zerotier_translate('Enable Zerotier'));
$section->addInput(new Form_Checkbox(
                'enable',
                zerotier_translate('Enable'),
                zerotier_translate('Enable zerotier client.'),
                $enable['mode']
            ));
$form->add($section);

$section = new Form_Section(zerotier_translate('local.conf settings'));
$section->addInput(new Form_Textarea(
                'localconf',
                zerotier_translate('local.conf'),
                $local_conf_value
            ))->setHelp(zerotier_translate('Optional ZeroTier local.conf JSON. Leave empty to remove local.conf.'));
$form->add($section);

print($form);
include("foot.inc");
?>
