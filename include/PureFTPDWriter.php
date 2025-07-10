<?php

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
        if (!empty($defaultConfig)) {
            $this->writeConfigInternal($fh, $defaultConfig, '*');
        }
        $fh->writeln('esac');
        $fh->writeln("echo 'end'");
    }

    protected function writeConfig(SafeTempFile $fh, TLSConfig $config): void {
        $domains_str = implode('|', array_map('escapeshellarg', $config->getDomains()));
        $this->writeConfigInternal($fh, $config, $domains_str);
    }

    private function writeConfigInternal(SafeTempFile $fh, TLSConfig $config, string $domains_str): void {
        $fullchain_escaped = escapeshellarg("cert_file:{$config->fullchain_file}");
        $key_escaped = escapeshellarg("key_file:{$config->key_file}");

        $fh->writeln("  $domains_str)");
        $fh->writeln("    echo $fullchain_escaped");
        $fh->writeln("    echo $key_escaped");
        $fh->writeln('    ;;');

    }
}
