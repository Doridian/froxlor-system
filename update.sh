#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

php froxconf.php

systemctl restart dovecot
systemctl restart postfix
systemctl restart proftpd
