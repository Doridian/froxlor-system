<?php

require_once 'shared.php';
require_once 'tmpfile.php';

class TLSConfig {
    private array $domains;
    private bool $is_default;
    public readonly string $fullchain_file;
    public readonly string $key_file;

    public function __construct(array $domains, string $fullchain_file, string $key_file) {
        $this->domains = [];
        $this->is_default = false;
        $this->fullchain_file = $fullchain_file;
        $this->key_file = $key_file;

        foreach ($domains as $domain_raw) {
            $domain = strtolower(trim($domain_raw));
            if ($domain === '*') {
                $this->is_default = true;
                continue;
            }
            if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                $this->domains[] = $domain;
            } else {
                echo "Skipping invalid domain: $domain_raw\n";
            }
        }
    }

    public function hash(): string {
        return $this->fullchain_file . '|' . $this->key_file;
    }

    public function append(TLSConfig $other): void {
        if ($this->fullchain_file !== $other->fullchain_file || $this->key_file !== $other->key_file) {
            throw new InvalidArgumentException("Cannot append TLSConfig with different files");
        }

        foreach ($other->domains as $domain) {
            if (!in_array($domain, $this->domains, true)) {
                $this->domains[] = $domain;
            }
        }

        if ($other->is_default) {
            $this->is_default = true;
        }
    }

    public function getDomains(): array {
        return $this->domains;
    }

    public function isDefault(): bool {
        return $this->is_default;
    }
}

abstract class TLSWriter {
    protected array $configs;
    protected ?TLSConfig $default_config;
    protected SafeTempFile $file;
    protected readonly bool $write_default;

    protected function __construct($file, $mode = 0644, $write_default = false) {
        $this->configs = [];
        $this->default_config = null;
        $this->file = new SafeTempFile($file, $mode);
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

    protected function writeHeader(): void {
        // Default implementation does nothing
    }
    protected function writeConfig(TLSConfig $config): void {
        foreach ($config->getDomains() as $domain) {
            $this->writeConfigDomain($config, $domain);
        }
    }
    protected function writeConfigDomain(TLSConfig $config, string $domain): void {
        throw new BadMethodCallException("writeConfigDomain must be implemented in subclass");
    }
    protected function writeFooter(): void { 
        // Default implementation does nothing
    }

    public function save(): void {
        $this->writeHeader();
        foreach ($this->configs as $config) {
            if (!$this->write_default && $config->isDefault()) {
                continue;
            }
            $this->writeConfig($config);
        }
        $this->writeFooter();
        $this->file->save();

        $this->postSave();
    }

    public function remove(): void {
        $this->file->remove();
        $this->configs = [];
        $this->default_config = null;
    }

    public function postSave(): void {
        // Default implementation does nothing
        // Subclasses can override this method to perform additional actions after saving
    }
}
