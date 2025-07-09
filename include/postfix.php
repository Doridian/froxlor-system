<?php

require_once 'writer.php';

class PostfixWriter extends TLSWriter {
    public function __construct() {
        parent::__construct('/etc/postfix/tls_server_sni_maps', 0640);
    }

    public function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeln($domain . ' ' . $config->key_file . ' ' . $config->fullchain_file);
    }

    public function postSave(): void {
        verbose_run('postmap -F /etc/postfix/tls_server_sni_maps');
        chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
        chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');

        if (!empty($this->default_config)) {
            $escaped = escapeshellarg('smtpd_tls_chain_files=' . $this->default_config->key_file . ',' . $this->default_config->fullchain_file);
            verbose_run('postconf -e ' . $escaped);
        }

        verbose_run('systemctl reload postfix');
    }
}
