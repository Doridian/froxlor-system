#!/bin/bash
set -euo pipefail

rsync -av --delete /var/customers/webs/ /mnt/backups/webs/
rsync -av --delete /var/customers/mail/ /mnt/backups/mail/
