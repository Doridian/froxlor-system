<?php
declare (strict_types=1);

require_once 'shared.php';
require_once 'SafeTempFile.php';
require_once 'TLSConfig.php';
require_once 'ITLSConfigHolder.php';

abstract class ConfigWriter {
    protected readonly string $file;
    protected readonly int $mode;
    protected readonly bool $writeDefault;

    protected function __construct(string $file, int $mode = 0644, bool $writeDefault = false) {
        $this->file = $file;
        $this->mode = $mode;
        $this->writeDefault = $writeDefault;
    }

    public function save(ITLSConfigHolder $configHolder): void {
        $fh = new SafeTempFile($this->file, $this->mode);
        $defaultConfig = $configHolder->getDefaultConfig();
        $this->writeHeader($fh, $defaultConfig);
        foreach ($configHolder->getConfigs() as $config) {
            if (!$this->writeDefault && $config === $defaultConfig) {
                continue;
            }
            $this->writeConfig($fh, $config);
        }
        $this->writeFooter($fh, $defaultConfig);
        $fh->save();

        $this->postSave($defaultConfig);
    }

    protected function writeHeader(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        // Default implementation does nothing
    }

    protected function writeConfig(SafeTempFile $fh, TLSConfig $config): void {
        foreach ($config->getDomains() as $domain) {
            $this->writeConfigDomain($fh, $config, $domain);
        }
    }

    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        throw new BadMethodCallException("writeConfigDomain or writeConfig must be implemented in subclass");
    }

    protected function writeFooter(SafeTempFile $fh, ?TLSConfig $defaultConfig): void {
        // Default implementation does nothing
    }

    protected function postSave(?TLSConfig $defaultConfig): void {
        // Default implementation does nothing
    }
}
