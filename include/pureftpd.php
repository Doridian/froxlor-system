<?php

require_once 'writer.php';

class PureFTPDWriter extends TLSWriter {
    private $file;

    public function __construct() {
        parent::__construct('/etc/pure-ftpd/certd.sh', 0755);
        $this->file = new SafeTempFile('/etc/pure-ftpd/certd.sh', 0755);
    }

    protected function writeHeader(): void {
        $this->file->writeln('#!/bin/bash');
        $this->file->writeln('set -euo pipefail');
        $this->file->writeln("echo 'action:strict'");
        $this->file->writeln('case "$CERTD_SNI_NAME" in');
    }

    protected function writeFooter(): void {
        if (!empty($this->default_config)) {
            $this->writeConfigInternal($this->default_config, '*');
        }
        $this->file->writeln('esac');
        $this->file->writeln("echo 'end'");
    }

    protected function writeConfig(TLSConfig $config): void {
        $domains_str = implode('|', array_map('escapeshellarg', $config->getDomains()));
        $this->writeConfigInternal($config, $domains_str);
    }

    private function writeConfigInternal(TLSconfig $config, string $domains_str): void {
        $fullchain_escaped = escapeshellarg("cert_file:{$config->fullchain_file}");
        $key_escaped = escapeshellarg("key_file:{$config->key_file}");

        $this->file->writeln("  $domains_str)");
        $this->file->writeln("    echo $fullchain_escaped");
        $this->file->writeln("    echo $key_escaped");
        $this->file->writeln('    ;;');

    }
}
