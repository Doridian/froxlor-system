#!/bin/bash
set -euo pipefail

mkdir_safe() {
    mkdir -p "$1"
    chmod 700 "$1"
}

rsync_cmd() {
    rsync -trv --delete "$1" "$2"
}

echo 'Starting backup process...'

echo 'Backing up system files...'
mkdir_safe /mnt/backups/system
rsync_cmd /etc/ /mnt/backups/system/etc/

echo 'Backing up websites...'
mkdir_safe /mnt/backups/webs
rsync_cmd /var/customers/webs/ /mnt/backups/webs/

echo 'Backing up emails...'
mkdir_safe /mnt/backups/mail
rsync_cmd /var/customers/mail/ /mnt/backups/mail/

echo 'Backing up crontabs...'
mkdir_safe /mnt/backups/crontabs
rsync_cmd /var/spool/cron/crontabs/ /mnt/backups/crontabs/

echo 'Backing up MySQL databases...'
rm -rf /mnt/backups/mysql
mkdir_safe /mnt/backups/mysql
mariadb-backup --backup --target-dir /mnt/backups/mysql
mariadb-backup --prepare --target-dir /mnt/backups/mysql
