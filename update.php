#!/usr/bin/env php
<?php

require_once 'include/shared.php';

require_once 'include/PostfixWriter.php';
require_once 'include/DovecotWriter.php';
require_once 'include/PureFTPDWriter.php';
require_once 'include/TLSConfigurator.php';

// TODO: Detect if certificates were updated since last run
//       and only update if they were changed.

$configurator = new TLSConfigurator([
    new PostfixWriter(),
    new DovecotWriter(),
    new PureFTPDWriter(),
]);

$configurator->setDefault([$fqdn], $fqdn_fullchain_file, $fqdn_key_file);

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

    if (empty($domains)) {
        echo "Skipping $domain_raw, no valid domains found in cert\n";
        continue;
    }

    $configurator->add($domains, $fullchain_file, $key_file);
}

$configurator->save();
