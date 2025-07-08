#!/bin/bash
set -euo pipefail

mkdir -p /mnt/backups/webs /mnt/backups/mail
chmod 700 /mnt/backups/webs /mnt/backups/mail

rsync -trv --delete /var/customers/webs/ /mnt/backups/webs/
rsync -trv --delete /var/customers/mail/ /mnt/backups/mail/
