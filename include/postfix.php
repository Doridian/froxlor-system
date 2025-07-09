<?php

require_once 'writer.php';

class PostfixWriter implements TLSWriter {
    private $file;

    public function __construct() {
        $this->file = new SafeTempFile('/etc/postfix/tls_server_sni_maps', 0640);
    }

    public function writeConfigDomain(TLSConfig $config, string $domain): void {
        $this->file->writeln($domain . ' ' . $config->key_file . ' ' . $config->fullchain_file);
    }

    public function postSave(): void {
        verbose_run('postmap -F /etc/postfix/tls_server_sni_maps');
        chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
        chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');

        $escaped = escapeshellarg('smtpd_tls_chain_files=' . $fqdn_key_file . ',' . $fqdn_fullchain_file);
        verbose_run('postconf -e ' . $escaped);

        verbose_run('systemctl reload postfix');
    }
}
