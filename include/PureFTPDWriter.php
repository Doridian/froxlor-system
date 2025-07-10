<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

class PureFTPDWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/pure-ftpd/certd.sh', 0755);
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        $fh->writeln('#!/bin/bash');
        $fh->writeln('set -euo pipefail');
        $fh->writeln("echo 'action:strict'");
        $fh->writeln('case "$CERTD_SNI_NAME" in');
    }

    protected function writeFooter(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $this->writeConfigInternal($fh, $defaultConfig, '*');
        }
        $fh->writeln('esac');
        $fh->writeln("echo 'end'");
    }

    protected function writeConfig(SafeTempFile $fh, TLSConfig $config): void {
        $domainsStr = implode('|', array_map('escapeshellarg', $config->getDomains()));
        $this->writeConfigInternal($fh, $config, $domainsStr);
    }

    private function writeConfigInternal(SafeTempFile $fh, TLSConfig $config, string $domainsStr): void {
        $fullChainEscaped = escapeshellarg("cert_file:{$config->fullChainFile}");
        $keyEscaped = escapeshellarg("keyFile:{$config->keyFile}");

        $fh->writeln("  $domainsStr)");
        $fh->writeln("    echo $fullChainEscaped");
        $fh->writeln("    echo $keyEscaped");
        $fh->writeln('    ;;');

    }
}
