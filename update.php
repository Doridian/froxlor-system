#!/usr/bin/env php
<?php

require_once 'shared.php';

$ssl_dir = rtrim(get_setting('system', 'customer_ssl_path'), '/') . '/';

$fqdn = strtolower(trim(shell_exec('hostname -f')));

$ips_res = $db->query('SELECT DISTINCT ip FROM panel_ipsandports;');
$ips = [];
while ($ip_row = $ips_res->fetch_assoc()) {
    $ips[] = $ip_row['ip'];
}
$ips_str = implode(' ', $ips);

$postfix_map_fh = fopen('/etc/postfix/tls_server_sni_maps', 'w');
chmod('/etc/postfix/tls_server_sni_maps', 0640);

$dovecot_tls_fh = fopen('/etc/dovecot/conf.d/zzz-2-tls-sni.conf', 'w');

$pureftpd_tls_fh = fopen('/etc/pure-ftpd/certd.sh', 'w');
fwrite($pureftpd_tls_fh, "#!/bin/bash\n");
fwrite($pureftpd_tls_fh, "set -euo pipefail\n");
chmod('/etc/pure-ftpd/certd.sh', 0755);

fwrite($pureftpd_tls_fh, "echo 'action:strict'\n");
fwrite($pureftpd_tls_fh, 'case "$CERTD_SNI_NAME" in' . "\n");

$cert_res = $db->query('SELECT d.domain AS domain, s.ssl_cert_file AS ssl_cert_file FROM panel_domains d, domain_ssl_settings s WHERE d.id = s.domainid;');
while ($cert_row = $cert_res->fetch_assoc()) {
    $domain_raw = $cert_row['domain'];

    $fullchain_file = $ssl_dir . $domain_raw . '_fullchain.pem';
    if (!file_exists($fullchain_file)) {
        echo "Skipping $domain_raw, fullchain file does not exist\n";
        continue;
    }
    $key_file = $ssl_dir . $domain_raw . '.key';
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
            echo "Skipping SAN $domain in $domain_raw, invalid hostname\n";
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
fwrite($pureftpd_tls_fh, "    echo 'cert_file:" . $ssl_dir . $fqdn . "_fullchain.pem'\n");
fwrite($pureftpd_tls_fh, "    echo 'key_file:" . $ssl_dir . $fqdn . ".key'\n");
fwrite($pureftpd_tls_fh, "    ;;\n");
fwrite($pureftpd_tls_fh, "esac\n");
fwrite($pureftpd_tls_fh, "echo 'end'\n");

fclose($postfix_map_fh);
fclose($dovecot_tls_fh);
fclose($pureftpd_tls_fh);

passthru('postmap -F /etc/postfix/tls_server_sni_maps');
chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');

passthru('systemctl restart dovecot');
passthru('systemctl restart postfix');
passthru('systemctl restart pure-ftpd-mysql');
