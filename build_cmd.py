#!/usr/bin/env python3
from subprocess import check_output
from socket import getfqdn

fqdn = getfqdn()
domains = [fqdn, f"www.{fqdn}", "mysql.doridian.net"]

sql_domains = check_output(["mysql", "--batch", "-e", "select domain, wwwserveralias from froxlor.panel_domains;"], encoding="utf-8").splitlines()
sql_columns = None
for line in sql_domains:
	lsplit = line.split("\t")
	if not sql_columns:
		sql_columns = {}
		for i, c in enumerate(lsplit):
			sql_columns[c] = i
		continue

	domain = lsplit[sql_columns["domain"]]
	has_www = int(lsplit[sql_columns["wwwserveralias"]], 10) != 0

	domains.append(domain)
	if has_www:
		domains.append(f"www.{domain}")

cmd = [
	"certbot",
	"certonly",
	"--non-interactive",
	"--expand",
	"--webroot",
	"-w",
	"/var/www/html/froxlor",
	"--deploy-hook",
	"/root/froxlor-letsencrypt-system/renew.sh",
]

for dom in domains:
	cmd += ["-d", dom]

print(" ".join(cmd))
