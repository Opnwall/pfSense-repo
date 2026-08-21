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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$act = '';
if (isset($_REQUEST['act'])) {
    $act = $_REQUEST['act'];
}

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

$input_errors = array();
$savemsg = null;

if (!empty($_SESSION['zerotier_networks_input_errors']) && is_array($_SESSION['zerotier_networks_input_errors'])) {
    $input_errors = $_SESSION['zerotier_networks_input_errors'];
}
if (isset($_SESSION['zerotier_networks_savemsg'])) {
    $savemsg = $_SESSION['zerotier_networks_savemsg'];
}
unset($_SESSION['zerotier_networks_input_errors'], $_SESSION['zerotier_networks_savemsg']);



function zerotier_value_to_string($value) {
    if (is_object($value) || is_array($value)) {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($json === false) ? '' : $json;
    }

    return (string)$value;
}

function zerotier_network_id_is_valid($network_id) {
    return is_string($network_id) && preg_match('/^[0-9a-fA-F]{16}$/', $network_id);
}

function zerotier_networks_page_fallback() {
    return function_exists('zerotier_listnetworks_from_interfaces') ? zerotier_listnetworks_from_interfaces() : array();
}

function zerotier_networks_interface_names($interfaces) {
    $names = array();

    foreach ((array)$interfaces as $interface) {
        if (!is_object($interface)) {
            continue;
        }

        $name = '';
        if (!empty($interface->portDeviceName)) {
            $name = (string)$interface->portDeviceName;
        } elseif (!empty($interface->name)) {
            $name = (string)$interface->name;
        }

        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

if (isset($_POST['save']) && isset($_POST['Network']) && (!isset($_POST['NetworkAction']) || $_POST['NetworkAction'] !== 'update')) {
    $network_id = is_string($_POST['Network']) ? trim($_POST['Network']) : '';

    if (!zerotier_network_id_is_valid($network_id)) {
        $input_errors[] = zerotier_translate("Network ID must be exactly 16 hexadecimal characters.");
    }
    elseif (!$zerotier_running) {
        $input_errors[] = zerotier_translate("The Zerotier service is not running.");
    }
    else {
        $operation_error = null;
        $out = zerotier_join_network($network_id, $operation_error);
        if ($operation_error !== null) {
            $input_errors[] = $operation_error;
        } elseif (!empty($out)) {
            $savemsg = is_array($out) ? implode("\n", array_map('zerotier_value_to_string', $out)) : zerotier_value_to_string($out);
        }
        else {
            $savemsg = zerotier_translate("Join request submitted for Zerotier network:") . ' ' . $network_id;
        }
    }

    $_SESSION['zerotier_networks_input_errors'] = $input_errors;
    $_SESSION['zerotier_networks_savemsg'] = $savemsg;
    header("Location: zerotier_networks.php");
    exit;
}
if ($act == "del" && isset($_POST['Network'])) {
    $network_id = is_string($_POST['Network']) ? trim($_POST['Network']) : '';

    if (!zerotier_network_id_is_valid($network_id)) {
        $input_errors[] = zerotier_translate("Network ID must be exactly 16 hexadecimal characters.");
    } elseif (!$zerotier_running) {
        $input_errors[] = zerotier_translate("The Zerotier service is not running.");
    }
    else {
        $operation_error = null;
        $out = zerotier_leave_network($network_id, $operation_error);
        if ($operation_error !== null) {
            $input_errors[] = $operation_error;
        } elseif (!empty($out)) {
            $savemsg = is_array($out) ? implode("\n", array_map('zerotier_value_to_string', $out)) : zerotier_value_to_string($out);
        } else {
            $savemsg = zerotier_translate("Leave request submitted.");
        }
    }

    $_SESSION['zerotier_networks_input_errors'] = $input_errors;
    $_SESSION['zerotier_networks_savemsg'] = $savemsg;
    header("Location: zerotier_networks.php");
    exit;
}
if (isset($_POST['save']) && isset($_POST['NetworkAction']) && $_POST['NetworkAction'] === 'update' && isset($_POST['NetworkOriginal']) && isset($_POST['Network'])) {
    $network_original = is_string($_POST['NetworkOriginal']) ? trim($_POST['NetworkOriginal']) : '';
    $network_id = is_string($_POST['Network']) ? trim($_POST['Network']) : '';

    if (!zerotier_network_id_is_valid($network_id) || !zerotier_network_id_is_valid($network_original)) {
        $input_errors[] = zerotier_translate("Network ID must be exactly 16 hexadecimal characters.");
    }
    elseif (!$zerotier_running) {
        $input_errors[] = zerotier_translate("The Zerotier service is not running.");
    }
    else {
        if ($network_original === $network_id) {
            $savemsg = zerotier_translate("No changes were made.");
        } else {
            $operation_error = null;
            $out = zerotier_join_network($network_id, $operation_error);
            if ($operation_error !== null) {
                $input_errors[] = $operation_error;
            } else {
                zerotier_leave_network($network_original, $operation_error);
                if ($operation_error !== null) {
                    $input_errors[] = $operation_error;
                } elseif (!empty($out)) {
                    $savemsg = is_array($out) ? implode("\n", array_map('zerotier_value_to_string', $out)) : zerotier_value_to_string($out);
                } else {
                    $savemsg = zerotier_translate("Update request submitted.");
                }
            }
        }
    }

    $_SESSION['zerotier_networks_input_errors'] = $input_errors;
    $_SESSION['zerotier_networks_savemsg'] = $savemsg;
    header("Location: zerotier_networks.php");
    exit;
}

$pgtitle = array(zerotier_translate("VPN"), zerotier_translate("ZeroTier VPN"), zerotier_translate("Networks"));
$pglinks = array("", "pkg_edit.php?xml=zerotier.xml", "@self");
require("head.inc");

$tab_array = array();
$tab_array[] = array(zerotier_translate("Networks"), true, "zerotier_networks.php");
$tab_array[] = array(zerotier_translate("Peers"), false, "zerotier_peers.php");
$tab_array[] = array(zerotier_translate("Configuration"), false, "zerotier.php");
add_package_tabs(zerotier_translate("Zerotier"), $tab_array);
display_top_tabs($tab_array);

if (!$zerotier_running) {
    print_info_box(zerotier_translate("The Zerotier service is not running."), "warning", false);
}

if ($act=="new" || $act=="edit"):
    $network = isset($_REQUEST['Network']) ? $_REQUEST['Network'] : '';

    $form = new Form();

    $section = new Form_Section($act == "edit" ? zerotier_translate('Update Network') : zerotier_translate('Join Network'));
    $section->addInput(new Form_Input(
        'Network',
        zerotier_translate('Network'),
        'text',
        $network,
        ['min' => '0']
    ))->setHelp(zerotier_translate("A 16-character Zerotier network ID using hexadecimal characters 0-9 and a-f."));
    if ($act=="edit") {
        $form ->addGlobal(new Form_Input(
            'NetworkOriginal',
            zerotier_translate('Network'),
            'hidden',
            $network,
            ['min' => '0']
        ));
        $form ->addGlobal(new Form_Input(
            'NetworkAction',
            zerotier_translate('Action'),
            'hidden',
            'update'
        ));
    }
    $form->add($section);

    print($form);
else:
?>
<?php
if (!empty($input_errors)) {
    print_input_errors($input_errors);
}
$savemsg_text = zerotier_value_to_string($savemsg);
$savemsg_text = trim($savemsg_text);

// If JSON-like output, simplify it
if ($savemsg_text !== '' && strpos($savemsg_text, '{') === 0) {
    $decoded = json_decode($savemsg_text, true);
    if (is_array($decoded)) {
        if (!empty($decoded['status'])) {
            $savemsg_text = sprintf(zerotier_translate('Network status: %s'), $decoded['status']);
        } elseif (!empty($decoded['nwid'])) {
            $savemsg_text = sprintf(zerotier_translate('Network joined: %s'), $decoded['nwid']);
        } else {
            $savemsg_text = zerotier_translate('Operation completed successfully.');
        }
    }
}

if ($savemsg_text !== '') {
    print_info_box(htmlspecialchars($savemsg_text), 'success');
}
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <h2 class="panel-title"><?=zerotier_translate("Zerotier Networks")?></h2>
    </div>
    <div class="table-responsive panel-body">
        <table class="table table-striped table-hover table-condensed">
            <thead>
                <tr>
                    <th><?=zerotier_translate("Status")?></th>
                    <th><?=zerotier_translate("Network")?></th>
                    <th><?=zerotier_translate("Type")?></th>
                    <th><?=zerotier_translate("Addresses")?></th>
                    <th><?=zerotier_translate("Interface")?></th>
                    <th><?=zerotier_translate("Bridged")?></th>
                    <th><?=zerotier_translate("Actions")?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $networks = $zerotier_running ? zerotier_listnetworks() : array();
                    $interface_fallback = array();
                    if (empty($networks) && $zerotier_running) {
                        $interface_fallback = zerotier_networks_page_fallback();
                    }
                    if (empty($networks)) {
                        $interface_names = zerotier_networks_interface_names($interface_fallback);
                        $empty_message = zerotier_translate('No Zerotier networks have been joined yet.');
                        if (!empty($interface_names)) {
                            $empty_message = zerotier_translate('Zerotier interfaces were detected, but the joined network list could not be read from the local Zerotier API or CLI:') . ' ' . implode(', ', $interface_names);
                        }
                ?>
                    <tr>
                        <td colspan="7" class="text-center"><?php print(htmlspecialchars($empty_message)); ?></td>
                    </tr>
                <?php
                    } else {
                        foreach ($networks as $network) {
                ?>
                    <tr>
                        <td>
                            <?php $network_status = zerotier_value_to_string(isset($network->status) ? $network->status : ''); ?>
                            <span class="label label-<?php print(get_status_class($network_status)); ?>"><?php print(htmlspecialchars($network_status)); ?></span>
                        </td>
                        <td>
                            <?php
                                $network_id_display = isset($network->id) ? $network->id : (isset($network->nwid) ? $network->nwid : '');
                                $network_name_display = isset($network->name) ? trim((string)$network->name) : '';
                                print(htmlspecialchars($network_id_display));
                                if ($network_name_display !== '' && $network_name_display !== $network_id_display) {
                                    print("<br /><strong>" . htmlspecialchars($network_name_display) . "</strong>");
                                }
                            ?>
                        </td>
                        <td><?php print(htmlspecialchars(zerotier_value_to_string(isset($network->type) ? $network->type : ''))); ?></td>
                        <td>
                            <?php
                                $addresses = array_reverse((array)$network->assignedAddresses);
                                print(!empty($addresses) ? implode('<br/>', array_map('htmlspecialchars', $addresses)) : '-');
                            ?>
                        </td>
                        <td>
                            <?php
                                $port_device_name = isset($network->portDeviceName) ? (string)$network->portDeviceName : '';
                                if ($port_device_name === '') {
                                    print('-');
                                }
                                else {
                                    print(htmlspecialchars($port_device_name));
                                }
                            ?>
                        </td>
                        <td><?php print($network->bridge ? zerotier_translate("Yes") : zerotier_translate("No")); ?></td>
                        <td>
                            <?php
                                $action_network_id = isset($network->id) ? $network->id : (isset($network->nwid) ? $network->nwid : '');
                                if (zerotier_network_id_is_valid($action_network_id)):
                                    $urlNetworkId = rawurlencode($action_network_id);
                            ?>
                                <a href="?act=edit&amp;Network=<?php print($urlNetworkId); ?>" class="fa fa-pencil" title="<?=zerotier_translate('Edit Network')?>"></a>
                                <a href="?act=del&amp;Network=<?php print($urlNetworkId); ?>" class="fa fa-trash" title="<?=zerotier_translate('Leave Network')?>" usepost></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                        }
                    }
                ?>
            </tbody>
        </table>
    </div>
</div>
<nav class="action-buttons">
    <a href="zerotier_networks.php?act=new" class="btn btn-sm btn-success btn-sm">
        <i class="fa fa-plus icon-embed-btn"></i> <?=zerotier_translate("Join")?>
    </a>
</nav>
<?php
endif;
include("foot.inc");
?>
