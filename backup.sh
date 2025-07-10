#!/bin/bash
set -euo pipefail

mkdir_safe() {
    local dir="$1"
    mkdir -p "$dir"
    chmod 700 "$dir"
}

rsync_cmd() {
    local src="$1"
    local dest="/mnt/backups$src"
    mkdir_safe "$dest"
    rsync -tlrv --delete "$src" "$dest"
}

echo 'Starting backup process...'

echo 'Backing up system files...'
rsync_cmd /etc/

echo 'Backing up websites...'
rsync_cmd /var/customers/webs/

echo 'Backing up emails...'
rsync_cmd /var/customers/mail/

echo 'Backing up crontabs...'
rsync_cmd /var/spool/cron/crontabs/

echo 'Backing up MySQL databases...'
mysql_dir=/mnt/backups/var/lib/mysql
rm -rf "$mysql_dir"
mkdir_safe "$mysql_dir"
mariadb-backup --backup --target-dir "$mysql_dir"
mariadb-backup --prepare --target-dir "$mysql_dir"
