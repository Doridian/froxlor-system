#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

set -x

php froxconf.php

systemctl restart dovecot
systemctl restart postfix
systemctl restart pure-ftpd
