<?php
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
    print(zerotier_translate("Missing required file: zerotier.inc") . "\n");
    print(zerotier_translate("Checked paths:") . "\n - " . implode("\n - ", $zerotier_inc_candidates) . "\n");
    print(zerotier_translate("include_path:") . " " . $include_path . "\n");
    exit;
}


function sort_roles($a, $b) {
    if($a->role == $b->role){ return 0 ; }
	return ($a->role < $b->role) ? 1 : -1;
}

function zerotier_peer_latency_class($latency) {
    $latency = is_numeric($latency) ? (int)$latency : -1;

    if ($latency < 0) {
        return 'default';
    }
    if ($latency <= 50) {
        return 'success';
    }
    if ($latency <= 150) {
        return 'info';
    }
    if ($latency <= 300) {
        return 'warning';
    }
    return 'danger';
}

function zerotier_peer_path_type($path) {
    $path = trim((string)$path);
    if ($path === '' || $path === '-') {
        return 'Unknown';
    }
    if (stripos($path, 'relay') !== false) {
        return 'Relay';
    }
    if (strpos($path, ';') !== false) {
        return 'Direct';
    }
    return 'Direct';
}

function zerotier_peer_path_type_label($path_type) {
    switch ($path_type) {
        case 'Direct':
            return zerotier_translate('Direct');
        case 'Relay':
            return zerotier_translate('Relay');
        case 'Unknown':
        default:
            return zerotier_translate('Unknown');
    }
}

function zerotier_peer_link_type($peer, $path) {
    if (isset($peer->role) && strtoupper((string)$peer->role) === 'PLANET') {
        return 'Relay';
    }
    if (isset($peer->tunneled) && $peer->tunneled) {
        return 'Relay';
    }
    if (stripos((string)$path, 'relay') !== false) {
        return 'Relay';
    }
    return 'Direct';
}

function zerotier_peer_link_type_label($link_type) {
    switch ($link_type) {
        case 'Direct':
            return zerotier_translate('Direct');
        case 'Relay':
        default:
            return zerotier_translate('Relay');
    }
}

function zerotier_peer_role_label($role) {
    $role = strtoupper((string)$role);
    switch ($role) {
        case 'PLANET':
            return zerotier_translate('PLANET (root)');
        case 'LEAF':
            return zerotier_translate('LEAF (peer)');
        case 'MOON':
            return zerotier_translate('MOON');
        default:
            return $role;
    }
}

function zerotier_listpeers_fallback() {
    $cli_candidates = array(
        '/usr/local/bin/zerotier-cli',
        '/usr/local/sbin/zerotier-cli',
        'zerotier-cli'
    );

    foreach ($cli_candidates as $cli) {
        $output = array();
        $return_var = 1;
        @exec(escapeshellcmd($cli) . ' listpeers 2>/dev/null', $output, $return_var);
        if ($return_var !== 0 || empty($output)) {
            continue;
        }

        $peers = array();
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '200 listpeers <ztaddr> <path> <latency> <version> <role>') === 0) {
                continue;
            }
            if (strpos($line, '200 listpeers ') !== 0) {
                continue;
            }

            $rest = substr($line, strlen('200 listpeers '));
            $parts = preg_split('/\s+/', $rest, 5);
            if (count($parts) < 5) {
                continue;
            }

            $peer = new stdClass();
            $peer->address = $parts[0];
            $peer->path = $parts[1];
            $peer->latency = $parts[2];
            $peer->version = $parts[3];
            $peer->role = $parts[4];
            $peers[] = $peer;
        }

        if (!empty($peers)) {
            return $peers;
        }
    }

    return array();
}

$pgtitle = array(zerotier_translate("VPN"), zerotier_translate("ZeroTier VPN"), zerotier_translate("Peers"));
$pglinks = array("", "pkg_edit.php?xml=zerotier.xml", "@self");
require("head.inc");

$tab_array = array();
$tab_array[] = array(zerotier_translate("Networks"), false, "zerotier_networks.php");
$tab_array[] = array(zerotier_translate("Peers"), true, "zerotier_peers.php");
$tab_array[] = array(zerotier_translate("Configuration"), false, "zerotier.php");
add_package_tabs(zerotier_translate("Zerotier"), $tab_array);
display_top_tabs($tab_array);

// Robust zerotier running detection
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

if (!$zerotier_running) {
    print_info_box(zerotier_translate("The Zerotier service is not running."), "warning", false);
}

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=zerotier_translate('Zerotier Peers');?></h2>
    </div>
    <div class="table-responsive panel-body">
        <table class="table table-striped table-hover table-condensed">
            <thead>
                <tr>
                    <th><?=zerotier_translate('Peer Node ID');?></th>
                    <th><?=zerotier_translate('Path');?></th>
                    <th><?=zerotier_translate('Path Type');?></th>
                    <th><?=zerotier_translate('Link');?></th>
                    <th><?=zerotier_translate('Latency');?></th>
                    <th><?=zerotier_translate('Version');?></th>
                    <th><?=zerotier_translate('Role');?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $peers = $zerotier_running ? zerotier_listpeers() : array();
                    if (empty($peers) && $zerotier_running) {
                        $peers = zerotier_listpeers_fallback();
                    }
                    if (!empty($peers)) {
                        usort($peers, 'sort_roles');
                        foreach($peers as $peer) {
                            $path = '';
                            if (!empty($peer->paths) && isset($peer->paths[0]->address)) {
                                $path = $peer->paths[0]->address;
                            } elseif (isset($peer->path)) {
                                $path = $peer->path;
                            }
                ?>
                    <tr>
                        <td><?php print(htmlspecialchars(isset($peer->address) ? $peer->address : '-')); ?></td>
                        <td><?php print(htmlspecialchars($path !== '' ? $path : '-')); ?></td>
                        <td><?php print(htmlspecialchars(zerotier_peer_path_type_label(zerotier_peer_path_type($path)))); ?></td>
                        <td>
                            <?php $link_type = zerotier_peer_link_type($peer, $path); ?>
                            <span class="label label-<?php print($link_type === 'Direct' ? 'success' : 'warning'); ?>"><?php print(htmlspecialchars(zerotier_peer_link_type_label($link_type))); ?></span>
                        </td>
                        <td>
                            <?php $latency_value = isset($peer->latency) ? $peer->latency : -1; ?>
                            <span class="label label-<?php print(zerotier_peer_latency_class($latency_value)); ?>"><?php print(htmlspecialchars(sprintf(zerotier_translate('%s ms'), (string)$latency_value))); ?></span>
                        </td>
                        <td><?php print(htmlspecialchars(isset($peer->version) ? $peer->version : '-')); ?></td>
                        <td>
                            <?php print(htmlspecialchars(zerotier_peer_role_label(isset($peer->role) ? $peer->role : ''))); ?>
                        </td>
                    </tr>
                <?php
                        }
                    } else {
                ?>
                    <tr>
                        <td colspan="7" class="text-center"><?=zerotier_translate('No Zerotier peers are available.');?></td>
                    </tr>
                <?php
                    }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php include("foot.inc"); ?>
