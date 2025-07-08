#!/bin/bash
set -euo pipefail

chmod 700 /mnt/backups
mkdir -p /mnt/backups/webs /mnt/backups/mail
chmod 700 /mnt/backups/webs /mnt/backups/mail

rsync -rv --delete /var/customers/webs/ /mnt/backups/webs/
rsync -rv --delete /var/customers/mail/ /mnt/backups/mail/
