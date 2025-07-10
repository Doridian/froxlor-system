<?php
declare (strict_types=1);

require_once 'ITLSConfigHolder.php';
require_once 'TLSConfig.php';

class TLSConfigurator implements ITLSConfigHolder {
    protected array $configs;
    protected ?TLSConfig $default_config;
    private readonly array $writers;
    private array $warnings;

    public function __construct(array $writers) {
        $this->writers = $writers;
        $this->default_config = null;
        $this->warnings = [];
    }

    public function getConfigs(): array {
        return $this->configs;
    }

    public function getDefaultConfig(): ?TLSConfig {
        return $this->default_config;
    }

    public function add(TLSconfig $config): TLSConfig {
        $config = new TLSConfig($domains, $fullchain_file, $key_file);
        $old_config = $this->configs[$config->uniqueKey()] ?? null;
        if ($old_config) {
            $old_config->append($config);
            return $old_config;
        } else {
            $this->configs[$config->uniqueKey()] = $config;
            return $config;
        }
    }

    public function addFromData(string $x509_data, string $fullchain_file, string $key_file): ?TLSConfig {
        $domains = [];

        $cert_data = openssl_x509_parse($x509_data);
        if (!empty($cert_data['subject']['CN'])) {
            $domains[] = $cert_data['subject']['CN'];
        }

        if (!empty($cert_data['extensions']['subjectAltName'])) {
            $san_array = explode(',', $cert_data['extensions']['subjectAltName']);
            foreach ($san_array as $san) {
                $san = trim($san);
                if (stripos($san, 'dns:') !== 0) {
                    continue;
                }
                $san = substr($san, 4); // Remove 'DNS:' prefix
                if (!empty($san)) {
                    $domains[] = trim($san);
                }
            }
        }

        if (empty($domains)) {
            $this->warnings[] = "Skipping $key_file, no valid domains found in certificate";
            return null;
        }

        return $this->add(new TLSConfig($domains, $fullchain_file, $key_file));
    }

    public function setDefault(?TLSConfig $config): void {
        if (!$config) {
            $this->default_config = null;
            return;
        }
        $this->default_config = $this->add($config);
    }

    public function hash(): string {
        asort($this->configs, SORT_STRING);
        $hashes = [];
        foreach ($this->configs as $config) {
            $hashes[] = $config->hash();
        }
        return hash('sha3-512', implode(PHP_EOL, $hashes));
    }

    public function save(): void {
        foreach ($this->writers as $writer) {
            $writer->save($this);
        }
    }

    public function clearWarnings(): void {
        $this->warnings = [];
        foreach ($this->configs as $config) {
            $config->clearWarnings();
        }
    }

    public function getWarnings(): array {
        $warnings = $this->warnings;
        foreach ($this->configs as $config) {
            $warnings = array_merge($warnings, $config->getWarnings());
        }
        return $warnings;
    }
}
