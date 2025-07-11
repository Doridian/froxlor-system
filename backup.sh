#!/bin/bash
set -euo pipefail

BACKUP_ROOT=/mnt/backups

mkdir_safe() {
    local dir="$1"
    mkdir -p "$dir"
    chmod 700 "$dir"
}

rsync_cmd() {
    local src="$(realpath "$1")/"
    local dest="$BACKUP_ROOT$src"
    mkdir_safe "$dest"
    rsync -avogXAE --delete "$src" "$dest"
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
mysql_dir="$BACKUP_ROOT/var/lib/mysql"
rm -rf "$mysql_dir"
mkdir_safe "$mysql_dir"
mariadb-backup --backup --target-dir "$mysql_dir"
mariadb-backup --prepare --target-dir "$mysql_dir"
