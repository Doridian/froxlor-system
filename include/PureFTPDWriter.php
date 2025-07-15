<?php
declare (strict_types=1);

require_once 'ConfigWriter.php';

class PureFTPDWriter extends ConfigWriter {
    private readonly string $baseCode;

    public function __construct() {
        parent::__construct('/etc/pure-ftpd/certd.lua', 0644);
        $this->baseCode = file_get_contents(__DIR__ . '/certd-code.lua');
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        if ($defaultConfig) {
            $fh->writeLine('local DEFAULT_CONFIG = ' . $this->makeConfigInternal($fh, $defaultConfig) . ';');
        } else {
            $fh->writeLine('local DEFAULT_CONFIG = nil');
        }
        $fh->writeLine('local DOMAIN_CONFIGS = {');
    }

    protected function writeFooter(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        $fh->writeLine('}');
        $fh->write($this->baseCode);
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        $fh->writeLine('    [' . escapeLuaString($domain) . '] = ' . $this->makeConfigInternal($fh, $config) . ',');
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        verboseRun('luajit -b /etc/pure-ftpd/certd.lua /etc/pure-ftpd/certd.luac');
    }

    private function makeConfigInternal(SafeTempFile $fh, TLSConfig $config): string {
        $certFile = escapeLuaString($config->fullChainFile);
        $keyFile = escapeLuaString($config->keyFile);
        return '{' . $certFile . ', ' . $keyFile . '}';
    }
}
