#!/bin/bash
set -euo pipefail

rsync -rv --delete /var/customers/webs/ /mnt/backups/webs/
rsync -rv --delete /var/customers/mail/ /mnt/backups/mail/
