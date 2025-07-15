#!/usr/bin/env php
<?php
declare (strict_types=1);

require_once 'include/shared.php';

require_once 'include/PostfixWriter.php';
require_once 'include/DovecotWriter.php';
require_once 'include/PureFTPDWriter.php';
require_once 'include/TLSConfigurator.php';

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

    $fullChainFile = $ssl_dir . $domain . '_fullchain.pem';
    if (!file_exists($fullChainFile)) {
        echo "Skipping $domain, fullchain file does not exist" . PHP_EOL;
        continue;
    }
    $keyFile = $ssl_dir . $domain . '.key';
    if (!file_exists($keyFile)) {
        echo "Skipping $domain, key file does not exist" . PHP_EOL;
        continue;
    }

    $config = $configurator->addFromData(
        $certRow['ssl_cert_data'],
        $fullChainFile,
        $keyFile
    );

    foreach ($configurator->getWarnings() as $warning) {
        echo "Warning for $domain: $warning";
    }
    $configurator->clearWarnings();

    if ($domainId === 0) {
        $configurator->setDefault($config);
    }
}

$gitRev = shell_exec('git rev-parse HEAD');
$newHash = $configurator->hash() . '|' . $gitRev;

$hashFile = __DIR__ . '/tlsconfig.hash';
if (file_exists($hashFile)) {
    $oldHash = trim(file_get_contents($hashFile));
    if ($oldHash === $newHash) {
        // TODO: Allow force update with cmdline option
        echo 'No changes detected, exiting.' . PHP_EOL;
        exit(0);
    }
    echo 'Changes detected, updating TLS configurations.' . PHP_EOL;
} else {
    $oldHash = '';
    echo 'No previous hash file found, updating config and creating new one.' . PHP_EOL;
}
$configurator->save();
file_put_contents($hashFile, $newHash . PHP_EOL);
