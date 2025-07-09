#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

export FQDN="$(hostname -f)"
export INSTALLDIR="$(pwd)"

set -x

php froxconf.php

systemctl restart dovecot
systemctl restart postfix
systemctl restart pure-ftpd-mysql
