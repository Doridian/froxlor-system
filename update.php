#!/usr/bin/env php
<?php
declare (strict_types=1);

chdir(__DIR__);

require_once 'include/shared.php';

require_once 'include/PostfixWriter.php';
require_once 'include/DovecotWriter.php';
require_once 'include/PureFTPDWriter.php';
require_once 'include/TLSConfigurator.php';

$hashFile = __DIR__ . '/tlsconfig.hash';
$hashFH = fopen($hashFile, 'c+b');
if (!$hashFH) {
    throw new Exception("Could not open hash file: $hashFile");
}
if (!flock($hashFH, LOCK_EX | LOCK_NB)) {
    throw new Exception("Could not lock hash file: $hashFile");
}

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

    $fullChainFile = $sslDir . $domain . '_fullchain.pem';
    if (!file_exists($fullChainFile)) {
        echo "Skipping $domain, fullchain file does not exist" . PHP_EOL;
        continue;
    }
    $keyFile = $sslDir . $domain . '.key';
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

$forceBuild = false;
foreach ($argv as $arg) {
    switch ($arg) {
    case '--force':
    case '-f':
        $forceBuild = true;
        break;
    }
}

if ($forceBuild) {
    echo 'Forcing rebuild of TLS configurations.' . PHP_EOL;
}

$gitRev = trim(shell_exec('git rev-parse HEAD'));
$newHash = $configurator->hash() . '|' . $gitRev;

$oldHash = trim(fgets($hashFH, 65536));

if ($oldHash === $newHash) {
    if ($forceBuild) {
        echo 'Forcing rebuild, but no changes detected.' . PHP_EOL;
    } else {
        echo 'No changes detected, exiting.' . PHP_EOL;
        exit(0);
    }
} else {
    echo 'Changes detected, updating TLS configurations.' . PHP_EOL;
}

$configurator->save();

ftruncate($hashFH, 0) or throw new Exception("Could not truncate hash file: $hashFile");
fseek($hashFH, 0);
fwrite($hashFH, $newHash . PHP_EOL) or throw new Exception("Could not write to hash file: $hashFile");
