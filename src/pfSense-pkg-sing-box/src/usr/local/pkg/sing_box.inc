<?php

require_once('config.inc');
require_once('util.inc');
require_once('service-utils.inc');

function sing_box_service_status()
{
    exec('/usr/sbin/service sing-box status 2>&1', $output, $rc);
    return ($rc === 0) ? 'running' : 'stopped';
}

function sing_box_start()
{
    mwexec('/usr/sbin/service sing-box start');
}

function sing_box_stop()
{
    mwexec('/usr/sbin/service sing-box stop');
}

function sing_box_restart()
{
    mwexec('/usr/sbin/service sing-box restart');
}

function sing_box_tun_exists()
{
    exec('/sbin/ifconfig tun_singbox >/dev/null 2>&1', $output, $rc);
    return ($rc === 0);
}

function sing_box_generate_rules($type)
{
    if ($type !== 'filter' || !sing_box_tun_exists()) {
        return '';
    }

    $rules = "\n# sing-box TUN runtime pass rules\n";
    $rules .= 'pass on tun_singbox inet from any to any flags S/SA keep state label "sing_box_tun_pass"' . "\n";

    return $rules;
}

function sing_box_resync_config()
{
    if (file_exists('/etc/rc.filter_configure')) {
        mwexec('/etc/rc.filter_configure');
    }
}

function sing_box_php_install_command()
{
    file_put_contents('/etc/rc.conf.d/sing_box', "sing_box_enable=\"YES\"\n");
}

function sing_box_php_deinstall_command()
{
    @unlink('/etc/rc.conf.d/sing_box');
    @unlink('/etc/rc.conf.d/sing-box');
    sing_box_resync_config();
}
