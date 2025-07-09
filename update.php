#!/usr/bin/env php
<?php

require_once 'shared.php';
require_once 'tmpfile.php';

// TODO: Detect if certificates were updated since last run
//       and only update if they were changed.

$postfix_map = new SafeTempFile('/etc/postfix/tls_server_sni_maps', 0640);
$dovecot_tls = new SafeTempFile('/etc/dovecot/conf.d/zzz-tls-sni.conf');

$pureftpd_tls = new SafeTempFile('/etc/pure-ftpd/certd.sh', 0755);
$pureftpd_tls->writeln('#!/bin/bash');
$pureftpd_tls->writeln('set -euo pipefail');

$pureftpd_tls->writeln("echo 'action:strict'");
$pureftpd_tls->writeln('case "$CERTD_SNI_NAME" in');

$dovecot_tls->writeln("ssl_cert = <$fqdn_fullchain_file");
$dovecot_tls->writeln("ssl_key = <$fqdn_key_file");

function write_pureftpd_tls($domains, $fullchain_file, $key_file) {
    global $pureftpd_tls;

    if (count($domains) === 1 && $domains[0] === '*') {
        $domains_str = '*';
    } else {
        $domains_escaped = array_map('escapeshellarg', $domains);
        $domains_str = implode('|', $domains_escaped);
    }

    $fullchain_escaped = escapeshellarg("cert_file:$fullchain_file");
    $key_escaped = escapeshellarg("key_file:$key_file");

    $pureftpd_tls->writeln("  $domains_str)");
    $pureftpd_tls->writeln("    echo $fullchain_escaped");
    $pureftpd_tls->writeln("    echo $key_escaped");
    $pureftpd_tls->writeln('    ;;');
}

$cert_res = $db->query('SELECT d.domain AS domain, s.ssl_cert_file AS ssl_cert_file FROM panel_domains d, domain_ssl_settings s WHERE d.id = s.domainid;');
while ($cert_row = $cert_res->fetch_assoc()) {
    $domain_raw = $cert_row['domain'];

    $fullchain_file = fullchain_from_domain($domain_raw);
    if (!file_exists($fullchain_file)) {
        echo "Skipping $domain_raw, fullchain file does not exist\n";
        continue;
    }
    $key_file = key_from_domain($domain_raw);
    if (!file_exists($key_file)) {
        echo "Skipping $domain_raw, key file does not exist\n";
        continue;
    }

    $cert_data = openssl_x509_parse($cert_row['ssl_cert_file']);
    if (!$cert_data) {
        echo "Skipping $domain_raw, cert data could not be parsed\n";
        continue;
    }

    $domains_raw = [];

    if (!empty($cert_data['subject']['CN'])) {
        $domains_raw[] = strtolower(trim($cert_data['subject']['CN']));
    }

    if (!empty($cert_data['extensions']['subjectAltName'])) {
        $san_array = explode(',', $cert_data['extensions']['subjectAltName']);
        foreach ($san_array as $san) {
            $san = strtolower(trim($san));
            if (strpos($san, 'dns:') !== 0) {
                continue;
            }
            $san = substr($san, 4); // Remove 'DNS:' prefix
            if (!empty($san)) {
                $domains_raw[] = $san;
            }
        }
    }

    $domains_raw = array_unique($domains_raw);

    $domains = [];
    foreach ($domains_raw as $domain) {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            echo "Skipping $domain in $domain_raw, invalid hostname\n";
            continue;
        }

        $domains[] = $domain;
    }
    unset($domains_raw);

    if (empty($domains)) {
        echo "Skipping $domain_raw, no valid domains found in cert\n";
        continue;
    }

    foreach ($domains as $domain) {
        $postfix_map->writeln($domain . ' ' . escapeshellarg($key_file) . ' ' . escapeshellarg($fullchain_file));

        $dovecot_tls->writeln('local_name ' . $domain . ' {');
        $dovecot_tls->writeln("  ssl_cert = <$fullchain_file");
        $dovecot_tls->writeln("  ssl_key = <$key_file");
        $dovecot_tls->writeln('}');
    }

    write_pureftpd_tls($domains, $fullchain_file, $key_file);
}

unset($cert_row, $cert_res, $domain_raw, $fullchain_file, $key_file, $cert_data, $domains);

write_pureftpd_tls(['*'], $fqdn_fullchain_file, $fqdn_key_file);
$pureftpd_tls->writeln('esac');
$pureftpd_tls->writeln("echo 'end'");

$postfix_map->save();
$dovecot_tls->save();
$pureftpd_tls->save();

verbose_run('postmap -F /etc/postfix/tls_server_sni_maps');
chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');

function postconf($values) {
    $args = [];
    foreach ($values as $key => $value) {
        $args[] = escapeshellarg("$key=$value");
    }
    verbose_run('postconf ' . implode(' ', $args));
}

postconf([
    'smtpd_tls_cert_file' => sslfile_from_domain($fqdn, '.crt'),
    'smtpd_tls_key_file' => $fqdn_key_file,
    'smtpd_tls_CAfile' => sslfile_from_domain($fqdn, '_chain.pem'),
    'smtpd_tls_chain_files' => $fqdn_key_file . ',' . $fqdn_fullchain_file,
    'tls_server_sni_maps' => 'hash:/etc/postfix/tls_server_sni_maps',
]);

verbose_run('systemctl reload dovecot');
verbose_run('systemctl reload postfix');
