<?php
/*
*
*/
require_once("util.inc");
require_once("globals.inc");
require_once("config.inc");
require_once("interfaces.inc");
require_once("services.inc");
require_once("service-utils.inc");

if (!function_exists('zerotier_translate')) {
    function zerotier_translate($message) {
        return function_exists('gettext') ? gettext($message) : $message;
    }
}

function zerotier_cli_path() {
    $paths = array(
        '/usr/local/bin/zerotier-cli',
        '/usr/local/sbin/zerotier-cli'
    );

    foreach ($paths as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    return '/usr/local/bin/zerotier-cli';
}

function zerotier_service_names() {
    return array('zerotier', 'zerotier-one', 'zerotierone');
}

function zerotier_config_enabled() {
    global $config;

    return isset($config['installedpackages']['zerotier']['config'][0]['enable']) &&
        $config['installedpackages']['zerotier']['config'][0]['enable'] === 'yes';
}

function zerotier_set_boot_enabled($enabled) {
    zerotier_install_boot_wrapper();

    $command = '/usr/sbin/sysrc -q zerotier_enable=' . ($enabled ? 'YES' : 'NO');

    if (function_exists('mwexec')) {
        return mwexec($command) === 0;
    }

    @exec($command . ' 2>/dev/null', $output, $return_var);
    return $return_var === 0;
}

function zerotier_install_boot_wrapper() {
    $wrapper_path = '/usr/local/etc/rc.d/zerotier.sh';
    $wrapper = <<<'EOD'
#!/bin/sh

# pfSense starts package rc scripts from /usr/local/etc/rc.d/*.sh.
# The FreeBSD ZeroTier rc script is named "zerotier", so this wrapper bridges it.

enabled=$(/usr/sbin/sysrc -n zerotier_enable 2>/dev/null || echo NO)

reload_filter()
{
    [ -x /etc/rc.filter_configure ] && /etc/rc.filter_configure >/dev/null 2>&1 || true
}

wait_for_interface()
{
    if ! /usr/bin/find /var/db/zerotier-one /var/lib/zerotier-one -path '*/networks.d/*.conf' -type f -print -quit 2>/dev/null | /usr/bin/grep -q .; then
        return
    fi
    count=0
    while [ "$count" -lt 10 ]; do
        if /sbin/ifconfig -l 2>/dev/null | /usr/bin/tr ' ' '\n' | /usr/bin/grep -Eq '^zt[[:alnum:]]{6,20}$'; then
            break
        fi
        sleep 1
        count=$((count + 1))
    done
}

case "$1" in
    start|onestart)
        case "$enabled" in
            YES|yes|Yes|TRUE|true|True|1)
                if /usr/sbin/service zerotier onestatus >/dev/null 2>&1; then
                    exit 0
                fi
                /usr/sbin/service zerotier onestart
                result=$?
                wait_for_interface
                reload_filter
                exit "$result"
                ;;
        esac
        ;;
    restart|onerestart)
        case "$enabled" in
            YES|yes|Yes|TRUE|true|True|1)
                /usr/sbin/service zerotier onerestart
                result=$?
                wait_for_interface
                reload_filter
                exit "$result"
                ;;
        esac
        ;;
    stop|onestop)
        /usr/sbin/service zerotier onestop
        result=$?
        reload_filter
        exit "$result"
        ;;
    *)
        /usr/sbin/service zerotier "$@"
        ;;
esac

EOD;

    if (@file_get_contents($wrapper_path) !== $wrapper) {
        @file_put_contents($wrapper_path, $wrapper);
        @chmod($wrapper_path, 0755);
    }
}

function zerotier_is_running() {
    foreach (zerotier_service_names() as $service_name) {
        if (is_service_running($service_name)) {
            return true;
        }
    }

    if (function_exists('is_process_running')) {
        return is_process_running('zerotier-one') || is_process_running('zerotierone');
    }

    return false;
}

function zerotier_stop() {
    $stopped = false;

    foreach (zerotier_service_names() as $service_name) {
        if (is_service_running($service_name)) {
            stop_service($service_name);
            $stopped = true;
        }
    }

    // The process detector can see a daemon whose rc service lookup failed.
    if (!$stopped && zerotier_is_running()) {
        @stop_service('zerotier');
    }
}

function zerotier_start_service() {
    foreach (zerotier_service_names() as $service_name) {
        @start_service($service_name);
        if (zerotier_is_running()) {
            return true;
        }
    }

    return zerotier_is_running();
}

function zerotier_auth_token_path() {
    $paths = array(
        '/var/db/zerotier-one/authtoken.secret',
        '/var/lib/zerotier-one/authtoken.secret'
    );

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function zerotier_home_path() {
    $token_path = zerotier_auth_token_path();
    if (empty($token_path)) {
        return null;
    }

    return dirname($token_path);
}

function zerotier_local_conf_path() {
    $home_path = zerotier_home_path();
    if (!empty($home_path)) {
        return $home_path . '/local.conf';
    }

    $paths = array(
        '/var/db/zerotier-one/local.conf',
        '/var/lib/zerotier-one/local.conf'
    );

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '/var/db/zerotier-one/local.conf';
}

function zerotier_local_conf_config() {
    global $config;

    if (isset($config['installedpackages']['zerotier']['config'][0]['localconf'])) {
        return (string)$config['installedpackages']['zerotier']['config'][0]['localconf'];
    }

    return zerotier_read_local_conf();
}

function zerotier_read_local_conf() {
    $path = zerotier_local_conf_path();

    if (!is_file($path)) {
        return '';
    }

    $contents = @file_get_contents($path);
    return ($contents === false) ? '' : (string)$contents;
}

function zerotier_validate_local_conf($contents, &$error = null) {
    $error = null;
    $contents = trim((string)$contents);

    if ($contents === '') {
        return true;
    }

    json_decode($contents);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = sprintf(zerotier_translate('local.conf must be valid JSON: %s'), json_last_error_msg());
        return false;
    }

    return true;
}

function zerotier_write_local_conf($contents, &$error = null) {
    $error = null;
    $contents = trim((string)$contents);

    if (!zerotier_validate_local_conf($contents, $error)) {
        return false;
    }

    $path = zerotier_local_conf_path();
    if ($contents === '') {
        if (is_file($path) && !@unlink($path)) {
            $error = sprintf(zerotier_translate('Unable to remove %s.'), $path);
            return false;
        }
        return true;
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        $error = sprintf(zerotier_translate('Unable to create %s.'), $dir);
        return false;
    }

    if (@file_put_contents($path, $contents . "\n") === false) {
        $error = sprintf(zerotier_translate('Unable to write %s.'), $path);
        return false;
    }

    @chmod($path, 0600);
    return true;
}

function zerotier_sync_local_conf(&$error = null) {
    return zerotier_write_local_conf(zerotier_local_conf_config(), $error);
}

function zerotier_cli_exec($arguments, &$lines = null, &$return_var = null) {
    $output = array();
    $cmd = escapeshellcmd(zerotier_cli_path());

    $home_path = zerotier_home_path();
    if (!empty($home_path)) {
        $cmd .= ' -D' . escapeshellarg($home_path);
    }

    $cmd .= ' ' . $arguments . ' 2>/dev/null';
    @exec($cmd, $output, $return_var);
    $lines = $output;
    return implode("\n", $output);
}

function zerotier_auth_token() {
    $token_path = zerotier_auth_token_path();
    if (empty($token_path)) {
        return null;
    }

    $auth_secret = trim((string)@file_get_contents($token_path));
    return ($auth_secret === '') ? null : $auth_secret;
}

function zerotier_local_api_request($method, $path, $payload = null, &$error = null, &$http_code = null) {
    $output = array();
    $error = null;
    $http_code = null;

    if (!zerotier_is_running()) {
        $error = zerotier_translate('The Zerotier service is not running.');
        return $output;
    }

    $auth_secret = zerotier_auth_token();
    if (empty($auth_secret)) {
        $error = zerotier_translate('The local Zerotier API token is not available.');
        return $output;
    }

    $curl = curl_init('http://127.0.0.1:9993' . $path);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);

    $headers = array(
        'Content-Type: application/json',
        'X-ZT1-Auth: ' . $auth_secret
    );

    if ($payload !== null) {
        $post_data = json_encode($payload);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        $headers[] = 'Content-Length: ' . strlen($post_data);
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($curl);
    $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        $http_code = null;
        $error = sprintf(zerotier_translate('Unable to contact the local Zerotier API: %s'), $curl_error);
        return $output;
    }

    if ($http_code < 200 || $http_code >= 300) {
        $error = sprintf(zerotier_translate('The local Zerotier API returned HTTP status %d.'), $http_code);
        return $output;
    }

    if ($response === '') {
        return new stdClass();
    }

    $decoded = json_decode($response);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        $error = zerotier_translate('The local Zerotier API returned an invalid response.');
        return $output;
    }

    return $decoded;
}

function zerotier_parse_status_text($line) {
    $line = trim((string)$line);
    if ($line === '' || strpos($line, '200 info ') !== 0) {
        return array();
    }

    $parts = preg_split('/\s+/', $line);
    if (count($parts) < 4) {
        return array();
    }

    $status = new stdClass();
    $status->raw = $line;
    $status->code = $parts[0];
    $status->address = isset($parts[2]) ? $parts[2] : null;
    $status->version = isset($parts[3]) ? $parts[3] : null;
    $status->state = isset($parts[4]) ? $parts[4] : null;

    return $status;
}

function zerotier_parse_listnetworks_text($lines) {
    $networks = array();

    foreach ((array)$lines as $line) {
        $line = trim((string)$line);

        if ($line === '' || strpos($line, '200 listnetworks <') === 0) {
            continue;
        }
        if (strpos($line, '200 listnetworks ') !== 0) {
            continue;
        }

        $rest = substr($line, strlen('200 listnetworks '));
        $parts = preg_split('/\s+/', $rest, 7);

        if (count($parts) < 6) {
            continue;
        }

        $network = new stdClass();
        $network->nwid = $parts[0];
        $network->id = $parts[0];
        $network->name = $parts[1];
        $network->mac = $parts[2];
        $network->status = $parts[3];
        $network->type = $parts[4];
        $network->portDeviceName = $parts[5];
        $network->mtu = null;
        $network->dhcp = null;
        $network->bridge = false;
        $network->assignedAddresses = array();

        if (isset($parts[6]) && preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\/\d+\b|\b[0-9a-fA-F:]+\/\d+\b/', $parts[6], $matches)) {
            $network->assignedAddresses = $matches[0];
        }

        $networks[] = $network;
    }

    return $networks;
}

function zerotier_interface_details($ifname) {
    $details = array(
        'portDeviceName' => '',
        'assignedAddresses' => array(),
        'mac' => '',
        'mtu' => null
    );

    if (!is_string($ifname) || $ifname === '') {
        return $details;
    }

    $details['portDeviceName'] = $ifname;

    if (function_exists('get_interfaces_info')) {
        $ifinfo = get_interfaces_info();
        if (is_array($ifinfo) && isset($ifinfo[$ifname]) && is_array($ifinfo[$ifname])) {
            $ifdata = $ifinfo[$ifname];

            if (!empty($ifdata['macaddr'])) {
                $details['mac'] = (string)$ifdata['macaddr'];
            }
            if (isset($ifdata['mtu']) && !is_array($ifdata['mtu']) && !is_object($ifdata['mtu'])) {
                $details['mtu'] = $ifdata['mtu'];
            }
            if (!empty($ifdata['ipv4']) && !empty($ifdata['subnet'])) {
                $details['assignedAddresses'][] = $ifdata['ipv4'] . '/' . $ifdata['subnet'];
            }
            if (!empty($ifdata['ipv6'])) {
                $ipv6_list = is_array($ifdata['ipv6']) ? $ifdata['ipv6'] : array($ifdata['ipv6']);
                foreach ($ipv6_list as $ipv6_item) {
                    if (is_array($ipv6_item) && !empty($ipv6_item['ipaddr']) && isset($ipv6_item['subnet'])) {
                        if (strpos($ipv6_item['ipaddr'], 'fe80:') !== 0) {
                            $details['assignedAddresses'][] = $ipv6_item['ipaddr'] . '/' . $ipv6_item['subnet'];
                        }
                    }
                }
            }

            $details['assignedAddresses'] = array_values(array_unique($details['assignedAddresses']));
            return $details;
        }
    }

    $output = array();
    @exec('/sbin/ifconfig ' . escapeshellarg($ifname) . ' 2>/dev/null', $output, $rc);
    if ($rc !== 0 || empty($output)) {
        return $details;
    }

    foreach ($output as $line) {
        $line = trim($line);
        if (preg_match('/^ether\s+([0-9a-fA-F:]+)/', $line, $m)) {
            $details['mac'] = $m[1];
        } elseif (preg_match('/\bmtu\s+(\d+)/', $line, $m)) {
            $details['mtu'] = $m[1];
        } elseif (preg_match('/^inet\s+(\d+\.\d+\.\d+\.\d+)\s+netmask\s+0x([0-9a-fA-F]+)/', $line, $m)) {
            $mask_long = hexdec($m[2]);
            if ($mask_long > 0x7fffffff) {
                $mask_long -= 0x100000000;
            }
            $mask = long2ip($mask_long);
            $bits = 0;
            foreach (explode('.', $mask) as $octet) {
                $bits += substr_count(decbin((int)$octet), '1');
            }
            $details['assignedAddresses'][] = $m[1] . '/' . $bits;
        } elseif (preg_match('/^inet6\s+([^\s%]+)(?:%\S+)?\s+prefixlen\s+(\d+)/', $line, $m)) {
            if (strpos($m[1], 'fe80:') !== 0) {
                $details['assignedAddresses'][] = $m[1] . '/' . $m[2];
            }
        }
    }

    $details['assignedAddresses'] = array_values(array_unique($details['assignedAddresses']));
    return $details;
}

function zerotier_normalize_network($network) {
    if (is_array($network)) {
        $network = json_decode(json_encode($network));
    }
    if (!is_object($network)) {
        return null;
    }

    if (!isset($network->id) && isset($network->nwid)) {
        $network->id = $network->nwid;
    }
    if (!isset($network->nwid) && isset($network->id)) {
        $network->nwid = $network->id;
    }

    if (isset($network->status) && is_object($network->status)) {
        if (isset($network->status->status)) {
            $network->status = (string)$network->status->status;
        } elseif (isset($network->status->type)) {
            $network->status = (string)$network->status->type;
        } else {
            $network->status = '';
        }
    } elseif (isset($network->status) && is_array($network->status)) {
        if (isset($network->status['status'])) {
            $network->status = (string)$network->status['status'];
        } elseif (isset($network->status['type'])) {
            $network->status = (string)$network->status['type'];
        } else {
            $network->status = '';
        }
    }

    if (isset($network->type) && is_object($network->type)) {
        if (isset($network->type->type)) {
            $network->type = (string)$network->type->type;
        } elseif (isset($network->type->name)) {
            $network->type = (string)$network->type->name;
        } else {
            $network->type = '';
        }
    } elseif (isset($network->type) && is_array($network->type)) {
        if (isset($network->type['type'])) {
            $network->type = (string)$network->type['type'];
        } elseif (isset($network->type['name'])) {
            $network->type = (string)$network->type['name'];
        } else {
            $network->type = '';
        }
    }

    if (!isset($network->name) || is_object($network->name) || is_array($network->name)) {
        $network->name = '';
    } else {
        $network->name = trim((string)$network->name);
    }

    if (!isset($network->status)) {
        $network->status = '';
    } else {
        $network->status = trim((string)$network->status);
    }

    if (!isset($network->type)) {
        $network->type = '';
    } else {
        $network->type = trim((string)$network->type);
    }

    if (empty($network->portDeviceName)) {
        if (!empty($network->portDevice) && is_string($network->portDevice)) {
            $network->portDeviceName = $network->portDevice;
        } elseif (!empty($network->device) && is_string($network->device)) {
            $network->portDeviceName = $network->device;
        } elseif (!empty($network->dev) && is_string($network->dev)) {
            $network->portDeviceName = $network->dev;
        } else {
            $network->portDeviceName = '';
        }
    } else {
        $network->portDeviceName = (string)$network->portDeviceName;
    }

    $interface_details = zerotier_interface_details($network->portDeviceName);

    if (!isset($network->assignedAddresses)) {
        $network->assignedAddresses = array();
    } elseif (is_object($network->assignedAddresses)) {
        $network->assignedAddresses = array_values(get_object_vars($network->assignedAddresses));
    } elseif (!is_array($network->assignedAddresses)) {
        $network->assignedAddresses = array($network->assignedAddresses);
    }

    $normalized_addresses = array();
    foreach ($network->assignedAddresses as $address) {
        if (is_scalar($address)) {
            $address = trim((string)$address);
            if ($address !== '') {
                $normalized_addresses[] = $address;
            }
            continue;
        }

        if (is_object($address)) {
            if (isset($address->address)) {
                $candidate = trim((string)$address->address);
                if ($candidate !== '') {
                    $normalized_addresses[] = $candidate;
                }
                continue;
            }
            if (isset($address->ip)) {
                $candidate = trim((string)$address->ip);
                if (isset($address->prefixLength)) {
                    $candidate .= '/' . (string)$address->prefixLength;
                } elseif (isset($address->prefix)) {
                    $candidate .= '/' . (string)$address->prefix;
                } elseif (isset($address->subnet)) {
                    $candidate .= '/' . (string)$address->subnet;
                }
                if ($candidate !== '') {
                    $normalized_addresses[] = $candidate;
                }
                continue;
            }
        }

        if (is_array($address)) {
            if (isset($address['address'])) {
                $candidate = trim((string)$address['address']);
                if ($candidate !== '') {
                    $normalized_addresses[] = $candidate;
                }
                continue;
            }
            if (isset($address['ip'])) {
                $candidate = trim((string)$address['ip']);
                if (isset($address['prefixLength'])) {
                    $candidate .= '/' . (string)$address['prefixLength'];
                } elseif (isset($address['prefix'])) {
                    $candidate .= '/' . (string)$address['prefix'];
                } elseif (isset($address['subnet'])) {
                    $candidate .= '/' . (string)$address['subnet'];
                }
                if ($candidate !== '') {
                    $normalized_addresses[] = $candidate;
                }
                continue;
            }
        }
    }
    if (empty($normalized_addresses) && !empty($interface_details['assignedAddresses'])) {
        $normalized_addresses = $interface_details['assignedAddresses'];
    }
    $network->assignedAddresses = array_values(array_unique($normalized_addresses));

    if (!isset($network->bridge)) {
        $network->bridge = false;
    } else {
        $network->bridge = (bool)$network->bridge;
    }
    if (!isset($network->mac) || is_object($network->mac) || is_array($network->mac) || $network->mac === '') {
        $network->mac = $interface_details['mac'];
    } else {
        $network->mac = (string)$network->mac;
    }
    if (!isset($network->mtu) || is_object($network->mtu) || is_array($network->mtu) || $network->mtu === null || $network->mtu === '') {
        $network->mtu = $interface_details['mtu'];
    }
    if (!isset($network->dhcp) || is_object($network->dhcp) || is_array($network->dhcp)) {
        $network->dhcp = null;
    }

    return $network;
}

function zerotier_listnetworks_from_api() {
    $networks = array();
    $network_list = zerotier_local_api_request('GET', '/network');

    if (empty($network_list)) {
        return $networks;
    }

    if (is_object($network_list)) {
        $network_list = get_object_vars($network_list);
    }

    if (!is_array($network_list)) {
        return $networks;
    }

    foreach ($network_list as $key => $value) {
        $network = null;
        $network_id = null;

        if (is_object($value)) {
            $network = $value;
            if (!empty($value->id) && is_string($value->id)) {
                $network_id = $value->id;
            } elseif (!empty($value->nwid) && is_string($value->nwid)) {
                $network_id = $value->nwid;
            } elseif (is_string($key)) {
                $network_id = $key;
            }
        } elseif (is_array($value)) {
            $network = json_decode(json_encode($value));
            if (!empty($value['id']) && is_string($value['id'])) {
                $network_id = $value['id'];
            } elseif (!empty($value['nwid']) && is_string($value['nwid'])) {
                $network_id = $value['nwid'];
            } elseif (is_string($key)) {
                $network_id = $key;
            }
        } else {
            if (is_string($value) && $value !== '') {
                $network_id = $value;
            } elseif (is_string($key)) {
                $network_id = $key;
            }
        }

        if (!empty($network_id)) {
            $network_detail = zerotier_local_api_request('GET', '/network/' . rawurlencode($network_id));
            if (is_object($network_detail)) {
                $network = $network_detail;
            } elseif (is_array($network_detail)) {
                $network = json_decode(json_encode($network_detail));
            }
        }

        if (is_object($network) && empty($network->id) && !empty($network_id)) {
            $network->id = $network_id;
        }
        if (is_object($network) && empty($network->nwid) && !empty($network_id)) {
            $network->nwid = $network_id;
        }

        $network = zerotier_normalize_network($network);
        if ($network !== null) {
            $networks[] = $network;
        }
    }

    return $networks;
}

function zerotier_parse_listpeers_text($lines) {
    $peers = array();

    foreach ((array)$lines as $line) {
        $line = trim((string)$line);

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
        $peer->paths = array();

        if (!empty($peer->path)) {
            $path_obj = new stdClass();
            $path_obj->address = $peer->path;
            $peer->paths[] = $path_obj;
        }

        $peers[] = $peer;
    }

    return $peers;
}

function zerotier_interface_name_is_candidate($ifname) {
    return is_string($ifname) &&
        (strpos($ifname, 'zt') === 0 || strpos($ifname, 'tap') === 0);
}

function zerotier_interface_name_is_managed($ifname) {
    return is_string($ifname) && preg_match('/^zt[0-9a-z]{6,20}$/i', $ifname) === 1;
}

function zerotier_runtime_interfaces() {
    $interfaces = array();

    @exec('/sbin/ifconfig -l 2>/dev/null', $output, $return_var);
    if ($return_var === 0) {
        foreach (preg_split('/\s+/', trim(implode(' ', $output))) as $ifname) {
            if (zerotier_interface_name_is_managed($ifname)) {
                $interfaces[] = $ifname;
            }
        }
    }

    return array_values(array_unique($interfaces));
}

function zerotier_generate_rules($type) {
    if ($type !== 'filter') {
        return '';
    }

    $rules = '';
    foreach (zerotier_runtime_interfaces() as $ifname) {
        $rules .= "\n# ZeroTier runtime pass rule for {$ifname} (IPv4 any to any)\n";
        $rules .= 'pass in quick on ' . $ifname . ' inet from any to any flags S/SA keep state label "zerotier_any_to_any_pass"' . "\n";
    }

    return $rules;
}

function zerotier_remove_legacy_interface_assignments() {
    $interfaces = config_get_path('interfaces', array());
    $removed_keys = array();

    foreach ($interfaces as $key => $interface) {
        if (zerotier_interface_name_is_managed(isset($interface['if']) ? $interface['if'] : '')) {
            unset($interfaces[$key]);
            $removed_keys[] = $key;
        }
    }

    if (empty($removed_keys)) {
        return false;
    }

    config_set_path('interfaces', $interfaces);
    $filter_rules = config_get_path('filter/rule', array());
    $filter_rules = array_values(array_filter($filter_rules, function ($rule) use ($removed_keys) {
        return !in_array(isset($rule['interface']) ? $rule['interface'] : '', $removed_keys, true);
    }));
    config_set_path('filter/rule', $filter_rules);
    write_config(zerotier_translate('Remove legacy Zerotier interface assignments.'));
    return true;
}

function zerotier_reload_filter($wait_for_interface = false) {
    if ($wait_for_interface) {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (!empty(zerotier_runtime_interfaces())) {
                break;
            }
            sleep(1);
        }
    }

    if (is_executable('/etc/rc.filter_configure')) {
        mwexec('/etc/rc.filter_configure');
    }
}

function zerotier_network_from_interface_info($ifname, $ifdata = array()) {
    $network = new stdClass();
    $network->nwid = $ifname;
    $network->id = $ifname;
    $network->name = $ifname;
    $network->mac = isset($ifdata['macaddr']) ? $ifdata['macaddr'] : '';
    $network->status = 'OK';
    $network->type = 'VIRTUAL';
    $network->portDeviceName = $ifname;
    $network->mtu = isset($ifdata['mtu']) ? $ifdata['mtu'] : null;
    $network->dhcp = null;
    $network->bridge = false;
    $network->assignedAddresses = array();

    if (!empty($ifdata['ipv4']) && !empty($ifdata['subnet'])) {
        $network->assignedAddresses[] = $ifdata['ipv4'] . '/' . $ifdata['subnet'];
    }

    if (!empty($ifdata['ipv6'])) {
        $ipv6_list = is_array($ifdata['ipv6']) ? $ifdata['ipv6'] : array($ifdata['ipv6']);
        foreach ($ipv6_list as $ipv6_item) {
            if (is_array($ipv6_item) && !empty($ipv6_item['ipaddr']) && isset($ipv6_item['subnet'])) {
                if (strpos($ipv6_item['ipaddr'], 'fe80:') !== 0) {
                    $network->assignedAddresses[] = $ipv6_item['ipaddr'] . '/' . $ipv6_item['subnet'];
                }
            }
        }
    }

    return $network;
}

function zerotier_network_from_ifconfig($ifname) {
    $output = array();
    @exec('/sbin/ifconfig ' . escapeshellarg($ifname) . ' 2>/dev/null', $output, $rc);
    if ($rc !== 0 || empty($output)) {
        return null;
    }

    $network = zerotier_network_from_interface_info($ifname);

    foreach ($output as $line) {
        $line = trim($line);
        if (preg_match('/^ether\s+([0-9a-fA-F:]+)/', $line, $m)) {
            $network->mac = $m[1];
        } elseif (preg_match('/\bmtu\s+(\d+)/', $line, $m)) {
            $network->mtu = $m[1];
        } elseif (preg_match('/^inet\s+(\d+\.\d+\.\d+\.\d+)\s+netmask\s+0x([0-9a-fA-F]+)/', $line, $m)) {
            $mask_long = hexdec($m[2]);
            if ($mask_long > 0x7fffffff) {
                $mask_long -= 0x100000000;
            }
            $mask = long2ip($mask_long);
            $bits = 0;
            foreach (explode('.', $mask) as $octet) {
                $bits += substr_count(decbin((int)$octet), '1');
            }
            $network->assignedAddresses[] = $m[1] . '/' . $bits;
        } elseif (preg_match('/^inet6\s+([^\s%]+)(?:%\S+)?\s+prefixlen\s+(\d+)/', $line, $m)) {
            if (strpos($m[1], 'fe80:') !== 0) {
                $network->assignedAddresses[] = $m[1] . '/' . $m[2];
            }
        }
    }

    $network->assignedAddresses = array_values(array_unique($network->assignedAddresses));
    return $network;
}

function zerotier_sync() {
    $enabled = zerotier_config_enabled();
    zerotier_set_boot_enabled($enabled);
    zerotier_sync_local_conf();

    if (!$enabled) {
        zerotier_stop();
        zerotier_reload_filter();
        return;
    }

    if (!zerotier_is_running()) {
        zerotier_start_service();
    }
    zerotier_reload_filter();
}

function zerotier_install() {
    global $config;

    if (!isset($config['installedpackages']['zerotier']) || !is_array($config['installedpackages']['zerotier'])) {
        $config['installedpackages']['zerotier'] = array();
    }

    if (!isset($config['installedpackages']['zerotier']['config']) || !is_array($config['installedpackages']['zerotier']['config'])) {
        $config['installedpackages']['zerotier']['config'] = array();
    }

    zerotier_remove_legacy_interface_assignments();
    zerotier_set_boot_enabled(zerotier_config_enabled());
    zerotier_sync_local_conf();
    write_config("[zerotier] Package installed.");
    zerotier_reload_filter();
}

function zerotier_deinstall() {
    global $config;

    zerotier_stop();
    zerotier_remove_legacy_interface_assignments();

    unset($config['installedpackages']['zerotier']['config'][0]);
    write_config("[zerotier] Package uninstalled.");

    unlink_if_exists('/usr/local/etc/rc.d/zerotier.sh');
    zerotier_reload_filter();
}

function zerotier_kill() {
    zerotier_stop();
    zerotier_reload_filter();
}

function zerotier_start() {
    if (zerotier_config_enabled() && !zerotier_is_running()) {
        zerotier_set_boot_enabled(true);
        zerotier_sync_local_conf();
        zerotier_start_service();
        zerotier_reload_filter();
    }
}

function zerotier_status() {
    $status = array();

    if (!zerotier_is_running()) {
        return $status;
    }

    $raw = zerotier_cli_exec('info -j', $json, $return_var);
    if ($return_var === 0 && $raw !== '') {
        $status = json_decode($raw);
        if ($status && isset($status->address)) {
            return $status;
        }
    }

    $raw = zerotier_cli_exec('info', $lines, $return_var);
    if ($return_var === 0) {
        $status = zerotier_parse_status_text($raw);
    }

    return $status;
}

function zerotier_listnetworks() {
    $networks = array();

    if (!zerotier_is_running()) {
        return $networks;
    }

    $networks = zerotier_listnetworks_from_api();
    if (!empty($networks)) {
        return $networks;
    }

    $raw = zerotier_cli_exec('listnetworks -j', $json, $return_var);
    if ($return_var === 0 && $raw !== '') {
        $networks = json_decode($raw);
        if (is_array($networks) && !empty($networks)) {
            $normalized = array();
            foreach ($networks as $network) {
                $network = zerotier_normalize_network($network);
                if ($network !== null) {
                    $normalized[] = $network;
                }
            }
            if (!empty($normalized)) {
                return $normalized;
            }
        }
    }

    zerotier_cli_exec('listnetworks', $lines, $return_var);
    if ($return_var === 0) {
        $networks = zerotier_parse_listnetworks_text($lines);
        if (!empty($networks)) {
            return $networks;
        }
    }

    return $networks;
}

function zerotier_listnetworks_from_interfaces() {
    $networks = array();

    if (function_exists('get_interfaces_info')) {
        $ifinfo = get_interfaces_info();
        if (is_array($ifinfo)) {
            foreach ($ifinfo as $ifname => $ifdata) {
                if (!zerotier_interface_name_is_candidate($ifname)) {
                    continue;
                }

                $networks[] = zerotier_network_from_interface_info($ifname, is_array($ifdata) ? $ifdata : array());
            }
        }

        if (!empty($networks)) {
            return $networks;
        }
    }

    @exec('/sbin/ifconfig -l 2>/dev/null', $ifconfig_list, $ifconfig_rc);
    if ($ifconfig_rc !== 0 || empty($ifconfig_list)) {
        return $networks;
    }

    $interfaces = preg_split('/\s+/', trim(implode(' ', $ifconfig_list)));
    foreach ($interfaces as $ifname) {
        if (!zerotier_interface_name_is_candidate($ifname)) {
            continue;
        }

        $network = zerotier_network_from_ifconfig($ifname);
        if ($network !== null) {
            $networks[] = $network;
        }
    }

    return $networks;
}

function zerotier_listpeers() {
    $peers = array();

    if (!zerotier_is_running()) {
        return $peers;
    }

    $raw = zerotier_cli_exec('peers -j', $json, $return_var);
    if ($return_var === 0 && $raw !== '') {
        $peers = json_decode($raw);
        if (is_array($peers) && !empty($peers)) {
            return $peers;
        }
    }

    $raw = zerotier_cli_exec('listpeers', $lines, $return_var);
    if ($return_var === 0) {
        $peers = zerotier_parse_listpeers_text($lines);
    }

    return is_array($peers) ? $peers : array();
}

function zerotier_join_network($network, &$error = null) {
    $network = trim((string)$network);
    $error = null;

    if ($network === '') {
        $error = zerotier_translate('A Zerotier network ID is required.');
        return array();
    }

    $api_error = null;
    $http_code = null;
    $output = zerotier_local_api_request('POST', '/network/' . rawurlencode($network), new stdClass(), $api_error, $http_code);
    if ($http_code !== null && $http_code >= 200 && $http_code < 300) {
        zerotier_reload_filter(true);
        return $output;
    }

    $raw = zerotier_cli_exec('join ' . escapeshellarg($network), $lines, $return_var);
    if ($return_var !== 0 || $raw === '') {
        $error = $api_error ?: zerotier_translate('The Zerotier join command failed.');
        return array();
    }

    zerotier_reload_filter(true);
    return array($raw);
}

function zerotier_leave_network($network, &$error = null) {
    $network = trim((string)$network);
    $error = null;

    if ($network === '') {
        $error = zerotier_translate('A Zerotier network ID is required.');
        return array();
    }

    $api_error = null;
    $http_code = null;
    $output = zerotier_local_api_request('DELETE', '/network/' . rawurlencode($network), null, $api_error, $http_code);
    if ($http_code !== null && $http_code >= 200 && $http_code < 300) {
        zerotier_reload_filter();
        return $output;
    }

    $raw = zerotier_cli_exec('leave ' . escapeshellarg($network), $lines, $return_var);
    if ($return_var !== 0 || $raw === '') {
        $error = $api_error ?: zerotier_translate('The Zerotier leave command failed.');
        return array();
    }

    zerotier_reload_filter();
    return array($raw);
}

function zerotier_controller_listnetworks() {
    $networks = array();
    $network_list = array();

    if (!zerotier_is_running()) {
        return $networks;
    }

    $raw = zerotier_local_api_request('GET', '/controller/network');
    if (is_array($raw) || is_object($raw)) {
        $network_list = $raw;
    } else {
        $network_list = array();
    }

    if (!is_array($network_list) && !is_object($network_list)) {
        $network_list = array();
    }

    foreach ($network_list as $network) {
        $decoded = zerotier_local_api_request('GET', '/controller/network/' . rawurlencode((string)$network));
        if (!empty($decoded)) {
            $networks[] = $decoded;
        }
    }

    return $networks;
}

function zerotier_controller_network($network) {
    return zerotier_local_api_request('GET', '/controller/network/' . rawurlencode($network));
}

function zerotier_controller_createnetwork($network, $networkID) {
    $output = array();

    if (!zerotier_is_running()) {
        return $output;
    }

    $status = zerotier_status();
    if (!$status || !isset($status->address)) {
        return $output;
    }

    $controller_id = $status->address;
    $network_id = $controller_id . $networkID;

    $output = zerotier_local_api_request(
        'POST',
        '/controller/network/' . rawurlencode($network_id),
        $network
    );

    return $output;
}

function zerotier_controller_deletenetwork($id) {
    return zerotier_local_api_request('DELETE', '/controller/network/' . rawurlencode($id));
}

function zerotier_controller_network_members($network) {
    $output = array();

    $members = zerotier_local_api_request(
        'GET',
        '/controller/network/' . rawurlencode($network) . '/member'
    );

    if (!is_object($members) && !is_array($members)) {
        return $output;
    }

    foreach ($members as $key => $value) {
        $response = zerotier_local_api_request(
            'GET',
            '/controller/network/' . rawurlencode($network) . '/member/' . rawurlencode($key)
        );

        if (!empty($response)) {
            $output[] = $response;
        }
    }

    return $output;
}

function zerotier_controller_member_toggle($network, $member, $key) {
    $output = array();

    $current = zerotier_local_api_request(
        'GET',
        '/controller/network/' . rawurlencode($network) . '/member/' . rawurlencode($member)
    );

    if (!is_object($current) || !isset($current->{$key})) {
        return $output;
    }

    $new = !((bool)$current->{$key});

    $response = zerotier_local_api_request(
        'POST',
        '/controller/network/' . rawurlencode($network) . '/member/' . rawurlencode($member),
        array($key => $new)
    );

    if (!empty($response)) {
        $output[] = $response;
    }

    return $output;
}

// Interface Functions

function zt_get_pfsense_interface_info($portDeviceName) {
    $interface = '';

    if (function_exists('convert_real_interface_to_friendly_interface_name')) {
        $interface = (string)convert_real_interface_to_friendly_interface_name($portDeviceName);
    } elseif (function_exists('get_configured_interface_list_by_realif')) {
        $if_list = get_configured_interface_list_by_realif(true);
        if (is_array($if_list) && isset($if_list[$portDeviceName])) {
            $interface = (string)$if_list[$portDeviceName];
        }
    }

    if ($interface !== '') {
        $if_desc = get_configured_interface_with_descr(true);
        $description = (is_array($if_desc) && isset($if_desc[$interface])) ? (string)$if_desc[$interface] : '';
        return new pfInterface($interface, $description);
    }

    return new pfInterface();
}

class pfInterface {
    public $interface;
    public $description;

    public function __construct($interface = "", $description = "") {
        $this->interface = $interface;
        $this->description = $description;
    }
}

// Helper Functions

function get_status_class($status) {
    $label = '';

    switch ($status) {
        case 'OK':
            $label = 'success';
            break;
        case 'ACCESS_DENIED':
        case 'NOT_FOUND':
            $label = 'danger';
            break;
        case 'REQUESTING_CONFIGURATION':
            $label = 'info';
            break;
        default:
            $label = 'default';
            break;
    }

    return $label;
}
?>
