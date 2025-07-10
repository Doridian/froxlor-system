#!/usr/bin/env php
<?php
declare (strict_types=1);

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
    $domain = $cert_row['domain'];

    $fullchain_file = fullchain_from_domain($domain);
    if (!file_exists($fullchain_file)) {
        echo "Skipping $domain, fullchain file does not exist\n";
        continue;
    }
    $key_file = key_from_domain($domain);
    if (!file_exists($key_file)) {
        echo "Skipping $domain, key file does not exist\n";
        continue;
    }

    $cert_data = openssl_x509_parse($cert_row['ssl_cert_file']);
    if (!$cert_data) {
        echo "Skipping $domain, cert data could not be parsed\n";
        continue;
    }

    $domains = [];

    if (!empty($cert_data['subject']['CN'])) {
        $domains[] = strtolower(trim($cert_data['subject']['CN']));
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
                $domains[] = $san;
            }
        }
    }

    $domains = array_unique($domains);

    if (empty($domains)) {
        echo "Skipping $domain, no valid domains found in cert\n";
        continue;
    }

    $configurator->add($domains, $fullchain_file, $key_file);
}

$configurator->save();
