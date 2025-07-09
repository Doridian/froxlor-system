#!/usr/bin/env php
<?php

require_once 'shared.php';

$fqdn = strtolower(trim(get_setting('system', 'hostname')));

function fullchain_from_domain($domain) {
    global $ssl_dir;
    return $ssl_dir . $domain . '_fullchain.pem';
}

function key_from_domain($domain) {
    global $ssl_dir;
    return $ssl_dir . $domain . '.key';
}

function verbose_run($command) {
    echo "Running: $command\n";
    passthru($command);
}

$postfix_map_fh = fopen('/etc/postfix/tls_server_sni_maps', 'w');
chmod('/etc/postfix/tls_server_sni_maps', 0640);

$dovecot_tls_fh = fopen('/etc/dovecot/conf.d/zzz-tls-sni.conf', 'w');

$pureftpd_tls_fh = fopen('/etc/pure-ftpd/certd.sh', 'w');
fwrite($pureftpd_tls_fh, "#!/bin/bash\n");
fwrite($pureftpd_tls_fh, "set -euo pipefail\n");
chmod('/etc/pure-ftpd/certd.sh', 0755);

fwrite($pureftpd_tls_fh, "echo 'action:strict'\n");
fwrite($pureftpd_tls_fh, 'case "$CERTD_SNI_NAME" in' . "\n");

$fqdn_fullchain_file = fullchain_from_domain($fqdn);
$fqdn_key_file = key_from_domain($fqdn);

fwrite($dovecot_tls_fh, "ssl_cert = <$fqdn_fullchain_file\n");
fwrite($dovecot_tls_fh, "ssl_key = <$fqdn_key_file\n");

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
        fwrite($postfix_map_fh, $domain . ' ' . $key_file . ' ' . $fullchain_file . "\n");

        fwrite($dovecot_tls_fh, 'local_name ' . $domain . " {\n");
        fwrite($dovecot_tls_fh, "  ssl_cert = <$fullchain_file\n");
        fwrite($dovecot_tls_fh, "  ssl_key = <$key_file\n");
        fwrite($dovecot_tls_fh, "}\n");
    }

    fwrite($pureftpd_tls_fh, "  '" . implode("'|'", $domains) . "')\n");
    fwrite($pureftpd_tls_fh, "    echo 'cert_file:$fullchain_file'\n");
    fwrite($pureftpd_tls_fh, "    echo 'key_file:$key_file'\n");
    fwrite($pureftpd_tls_fh, "    ;;\n");
}

fwrite($pureftpd_tls_fh, "  *)\n");
fwrite($pureftpd_tls_fh, "    echo 'cert_file:" . $fqdn_fullchain_file . "'\n");
fwrite($pureftpd_tls_fh, "    echo 'key_file:" . $fqdn_key_file . "'\n");
fwrite($pureftpd_tls_fh, "    ;;\n");
fwrite($pureftpd_tls_fh, "esac\n");
fwrite($pureftpd_tls_fh, "echo 'end'\n");

fclose($postfix_map_fh);
fclose($dovecot_tls_fh);
fclose($pureftpd_tls_fh);

verbose_run('postmap -F /etc/postfix/tls_server_sni_maps');
chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');

function postconf($values) {
    $args = [];
    foreach ($values as $key => $value) {
        $key = escapeshellarg($key);
        $escaped_value = escapeshellarg($value);
        $args[] = "'$key=$escaped_value'";
    }
    verbose_run("postconf " . implode(' ', $args));
}

postconf([
    'smtpd_tls_cert_file' => $ssl_dir . $domain . '.crt',
    'smtpd_tls_key_file' => $fqdn_key_file,
    'smtpd_tls_CAfile' => $ssl_dir . $domain . '_chain.pem',
    'smtpd_tls_chain_files' => $fqdn_key_file . ',' . $fqdn_fullchain_file,
    'tls_server_sni_maps' => 'hash:/etc/postfix/tls_server_sni_maps',
]);

verbose_run('systemctl restart dovecot');
verbose_run('systemctl restart postfix');
