#!/bin/sh

# PROVIDE: ddnsgo
# REQUIRE: NETWORKING LOGIN
# KEYWORD: shutdown

exec /usr/local/etc/rc.d/ddnsgo "$@"
