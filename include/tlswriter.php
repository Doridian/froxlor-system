<?php

require_once 'shared.php';
require_once 'tmpfile.php';
require_once 'tlsconfig.php';

abstract class TLSWriter {
    protected array $configs;
    protected ?TLSConfig $default_config;
    protected readonly string $file;
    protected readonly int $mode;
    protected readonly bool $write_default;

    protected function __construct($file, $mode = 0644, $write_default = false) {
        $this->configs = [];
        $this->default_config = null;
        $this->file = $file;
        $this->mode = $mode;
        $this->write_default = $write_default;
    }

    public function add(array $domains, string $fullchain_file, string $key_file): void {
        $config = new TLSConfig($domains, $fullchain_file, $key_file);
        $old_config = $this->configs[$config->hash()] ?? null;
        if ($old_config) {
            $old_config->append($config);
            $config = $old_config;
        } else {
            $this->configs[$config->hash()] = $config;
        }

        if ($config->isDefault()) {
            $this->default_config = $config;
        }
    }

    protected function writeHeader(SafeTempFile $fh): void {
        // Default implementation does nothing
    }
    protected function writeConfig(SafeTempFile $fh, TLSConfig $config): void {
        foreach ($config->getDomains() as $domain) {
            $this->writeConfigDomain($fh, $config, $domain);
        }
    }
    protected function writeConfigDomain(SafeTempFile $fh, TLSConfig $config, string $domain): void {
        throw new BadMethodCallException("writeConfigDomain must be implemented in subclass");
    }
    protected function writeFooter(SafeTempFile $fh): void { 
        // Default implementation does nothing
    }

    public function save(): void {
        $fh = new SafeTempFile($this->file, $this->mode);
        $this->writeHeader($fh);
        foreach ($this->configs as $config) {
            if (!$this->write_default && $config->isDefault()) {
                continue;
            }
            $this->writeConfig($fh, $config);
        }
        $this->writeFooter($fh);
        $fh->save();

        $this->postSave();
    }

    public function postSave(): void {
        // Default implementation does nothing
        // Subclasses can override this method to perform additional actions after saving
    }
}
