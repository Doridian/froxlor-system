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

$cert_res = $db->query('SELECT d.domain AS domain, s.domainid AS domain_id, s.ssl_cert_file AS ssl_cert_data FROM domain_ssl_settings s LEFT JOIN panel_domains d ON d.id = s.domainid;');
while ($cert_row = $cert_res->fetch_assoc()) {
    $domain_id = (int)$cert_row['domain_id'];
    if ($domain_id === 0) {
        $domain = $fqdn;
    } else {
        $domain = $cert_row['domain'];
    }

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

    $config = $configurator->addFromData(
        $domain,
        $cert_row['ssl_cert_data'],
        $fullchain_file,
        $key_file
    );

    if ($domain_id === 0) {
        $configurator->setDefault($config);
    }
}

$configurator->save();
