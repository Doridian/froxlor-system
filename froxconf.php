<?php

define('SSL_DIR', '/etc/ssl/froxlor-custom/');

require_once '/var/www/html/froxlor/lib/userdata.inc.php';

$db = new mysqli(
    $sql['host'],
    $sql['user'],
    $sql['password'],
    $sql['db'],
) or die('Database connection error '. mysqli_connect_error());

$ips_res = $db->query("SELECT DISTINCT ip FROM panel_ipsandports;");
$ips = [];
while ($ip_row = $ips_res->fetch_assoc()) {
    $ips[] = $ip_row['ip'];
}

$postfix_map_fh = fopen('/etc/postfix/tls_server_sni_maps', 'w');
chmod('/etc/postfix/tls_server_sni_maps', 0640);
$dovecot_tls_fh = fopen('/etc/dovecot/conf.d/zzz-2-tls-sni.conf', 'w');
$proftpd_tls_fh = fopen('/etc/proftpd/conf.d/tls_sni.conf', 'w');

function add_domain($domain, $key_file, $fullchain_file, $cert_file, $chain_file) {
    global $postfix_map_fh, $dovecot_tls_fh, $proftpd_tls_fh, $ips;

    fwrite($postfix_map_fh, $domain . ' ' . $key_file . ' ' . $fullchain_file . "\n");

    fwrite($dovecot_tls_fh, 'local_name ' . $domain . " {\n");
    fwrite($dovecot_tls_fh, "  ssl_cert = <$fullchain_file\n");
    fwrite($dovecot_tls_fh, "  ssl_key = <$key_file\n");
    fwrite($dovecot_tls_fh, "}\n");

    $ip_str = implode(' ', $ips);
    fwrite($proftpd_tls_fh, "<VirtualHost $ip_str>\n");
    fwrite($proftpd_tls_fh, "  ServerAlias $domain\n");
    // TODO: Detect if we are EC or RSA and use the appropriate directives
    fwrite($proftpd_tls_fh, "  TLSRSACertificateFile $cert_file\n");
    fwrite($proftpd_tls_fh, "  TLSCertificateChainFile $chain_file\n");
    fwrite($proftpd_tls_fh, "  TLSRSACertificateKeyFile $key_file\n");
    fwrite($proftpd_tls_fh, "</VirtualHost>\n");
}

$cert_res = $db->query("SELECT domain, wwwserveralias FROM panel_domains;");
while ($cert_row = $cert_res->fetch_assoc()) {
    $domain = $cert_row['domain'];

    $fullchain_file = SSL_DIR . $domain . '_fullchain.pem';
    if (!file_exists($fullchain_file)) {
        echo "Skipping $domain, fullchain file does not exist\n";
        continue;
    }
    $key_file = SSL_DIR . $domain . '.key';
    if (!file_exists($key_file)) {
        echo "Skipping $domain, key file does not exist\n";
        continue;
    }
    $cert_file = SSL_DIR . $domain . '.crt';
    if (!file_exists($cert_file)) {
        echo "Skipping $domain, cert file does not exist\n";
        continue;
    }
    $chain_file = SSL_DIR . $domain . '_chain.pem';
    if (!file_exists($chain_file)) {
        echo "Skipping $domain, chain file does not exist\n";
        continue;
    }

    add_domain($domain, $key_file, $fullchain_file, $cert_file, $chain_file);
    if ($cert_row['wwwserveralias']) {
        add_domain('www.' . $domain, $key_file, $fullchain_file, $cert_file, $chain_file);
    }
}

fclose($postfix_map_fh);
fclose($dovecot_tls_fh);

passthru('postmap -F /etc/postfix/tls_server_sni_maps');
chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');
