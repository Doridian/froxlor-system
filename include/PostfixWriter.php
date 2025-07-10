<?php

require_once 'ConfigWriter.php';

class PostfixWriter extends ConfigWriter {
    public function __construct() {
        parent::__construct('/etc/postfix/tls_server_sni_maps', 0640);
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeln($domain . ' ' . $config->key_file . ' ' . $config->fullchain_file);
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verbose_run('postmap -F /etc/postfix/tls_server_sni_maps');
        chmod('/etc/postfix/tls_server_sni_maps.db', 0640);
        chgrp('/etc/postfix/tls_server_sni_maps.db', 'postfix');

        if ($defaultConfig) {
            $escaped = escapeshellarg('smtpd_tls_chain_files=' . $defaultConfig->key_file . ',' . $defaultConfig->fullchain_file);
            verbose_run('postconf -e ' . $escaped);
        }

        verbose_run('systemctl reload postfix');
    }
}
