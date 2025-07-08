#!/bin/bash
set -euo pipefail

mkdir -p /mnt/backups/webs /mnt/backups/mail /mnt/backups/mysql
chmod 700 /mnt/backups/webs /mnt/backups/mail /mnt/backups/mysql

rsync -trv --delete /var/customers/webs/ /mnt/backups/webs/
rsync -trv --delete /var/customers/mail/ /mnt/backups/mail/

mariadb-backup --backup --innobackupex --target-dir /mnt/backups/mysql
mariadb-backup --prepare --target-dir /mnt/backups/mysql
