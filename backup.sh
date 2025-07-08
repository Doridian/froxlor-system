#!/bin/bash
set -euo pipefail

mkdir -p /mnt/backups/webs /mnt/backups/mail /mnt/backups/mysql
chmod 700 /mnt/backups/webs /mnt/backups/mail /mnt/backups/mysql

rsync -trv --delete /var/customers/webs/ /mnt/backups/webs/
rsync -trv --delete /var/customers/mail/ /mnt/backups/mail/

DATABASES="$(mysql --batch -e 'SHOW DATABASES;' | grep -v '^Database\|information_schema\|performance_schema\|sys$')"

while IFS= read -r dbname; do
    mysqldump --single-transaction --routines --triggers "$dbname" | gzip > "/mnt/backups/mysql/$dbname.sql.gz"
done <<< "$DATABASES"
