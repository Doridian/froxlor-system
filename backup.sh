#!/bin/bash
set -euo pipefail

echo 'Starting backup process...'
rm -rf /mnt/backups/mysql
mkdir -p /mnt/backups/webs /mnt/backups/mail /mnt/backups/mysql /mnt/backups/crontabs
chmod 700 /mnt/backups/webs /mnt/backups/mail /mnt/backups/mysql /mnt/backups/crontabs

echo 'Backing up websites...'
rsync -trv --delete /var/customers/webs/ /mnt/backups/webs/

echo 'Backing up emails...'
rsync -trv --delete /var/customers/mail/ /mnt/backups/mail/

echo 'Backing up crontabs...'
rsync -trv --delete /var/spool/cron/crontabs/ /mnt/backups/crontabs/

echo 'Backing up MySQL databases...'
mariadb-backup --backup --target-dir /mnt/backups/mysql
mariadb-backup --prepare --target-dir /mnt/backups/mysql
