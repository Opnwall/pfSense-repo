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
