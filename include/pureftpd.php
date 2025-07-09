<?php

require_once 'tmpfile.php';
require_once 'writer.php';

class PureFTPDWriter implements TLSWriter {
    private $file;

    public function __construct() {
        $this->file = new SafeTempFile('/etc/pure-ftpd/certd.sh', 0755);
        $this->file->writeln('#!/bin/bash');
        $this->file->writeln('set -euo pipefail');
        $this->file->writeln("echo 'action:strict'");
        $this->file->writeln('case "$CERTD_SNI_NAME" in');
    }

    public function write(array $domains, string $fullchain_file, string $key_file): void {
        if (count($domains) === 1 && $domains[0] === '*') {
            $domains_str = '*';
        } else {
            $domains_str = implode('|', array_map('escapeshellarg', $domains));
        }

        $fullchain_escaped = escapeshellarg("cert_file:$fullchain_file");
        $key_escaped = escapeshellarg("key_file:$key_file");

        $this->file->writeln("  $domains_str)");
        $this->file->writeln("    echo $fullchain_escaped");
        $this->file->writeln("    echo $key_escaped");
        $this->file->writeln('    ;;');
    }

    public function save(): void {
        $this->file->writeln('esac');
        $this->file->writeln("echo 'end'");
        $this->file->save();
    }
}