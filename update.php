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

$certRes = $db->query('SELECT d.domain AS domain, s.domainid AS domain_id, s.ssl_cert_file AS ssl_cert_data FROM domain_ssl_settings s LEFT JOIN panel_domains d ON d.id = s.domainid;');
while ($certRow = $certRes->fetch_assoc()) {
    $domainId = (int)$certRow['domain_id'];
    if ($domainId === 0) {
        $domain = $fqdn;
    } else {
        $domain = $certRow['domain'];
    }

    $fullChainFile = fullchain_from_domain($domain);
    if (!file_exists($fullChainFile)) {
        echo "Skipping $domain, fullchain file does not exist\n";
        continue;
    }
    $keyFile = key_from_domain($domain);
    if (!file_exists($keyFile)) {
        echo "Skipping $domain, key file does not exist\n";
        continue;
    }

    $config = $configurator->addFromData(
        $certRow['ssl_cert_data'],
        $fullChainFile,
        $keyFile
    );

    foreach ($config->getWarnings() as $warning) {
        echo "Warning for $domain: $warning";
    }
    $config->clearWarnings();

    if ($domainId === 0) {
        $configurator->setDefault($config);
    }
}


$newHash = $configurator->hash();

$hashFile = __DIR__ . '/tlsconfig.hash';
if (file_exists($hashFile)) {
    $oldHash = trim(file_get_contents($hashFile));
    if ($oldHash === $newHash) {
        echo "No changes detected, exiting.\n";
        exit(0);
    }
    echo "Changes detected, updating TLS configurations.\n";
} else {
    $oldHash = '';
    echo "No previous hash file found, updating config and creating new one.\n";
}
$configurator->save();
file_put_contents($hashFile, $newHash . PHP_EOL);
