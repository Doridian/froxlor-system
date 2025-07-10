<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

class PureFTPDWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/pure-ftpd/certd.sh', 0755);
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        $fh->writeLine('#!/bin/bash');
        $fh->writeLine('set -euo pipefail');
        $fh->writeLine("echo 'action:strict'");
        $fh->writeLine('case "$CERTD_SNI_NAME" in');
    }

    protected function writeFooter(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $this->writeConfigInternal($fh, $defaultConfig, '*');
        }
        $fh->writeLine('esac');
        $fh->writeLine("echo 'end'");
    }

    protected function writeConfig(SafeTempFile $fh, TLSConfig $config): void {
        $domainsStr = implode('|', array_map('escapeshellarg', $config->getDomains()));
        $this->writeConfigInternal($fh, $config, $domainsStr);
    }

    private function writeConfigInternal(SafeTempFile $fh, TLSConfig $config, string $domainsStr): void {
        $fullChainEscaped = escapeshellarg("cert_file:{$config->fullChainFile}");
        $keyEscaped = escapeshellarg("keyFile:{$config->keyFile}");

        $fh->writeLine("  $domainsStr)");
        $fh->writeLine("    echo $fullChainEscaped");
        $fh->writeLine("    echo $keyEscaped");
        $fh->writeLine('    ;;');

    }
}
