#!/bin/sh
systemctl restart dovecot
systemctl restart postfix
systemctl restart proftpd
