<?php
declare (strict_types=1);

require_once 'ITLSConfigHolder.php';
require_once 'TLSConfig.php';

class TLSConfigurator implements ITLSConfigHolder {
    protected array $configs;
    protected ?TLSConfig $default_config;
    private readonly array $writers;

    public function __construct(array $writers) {
        $this->writers = $writers;
    }

    public function getConfigs(): array {
        return $this->configs;
    }

    public function getDefaultConfig(): ?TLSConfig {
        return $this->default_config;
    }

    public function add(array $domains, string $fullchain_file, string $key_file): TLSConfig {
        $config = new TLSConfig($domains, $fullchain_file, $key_file);
        $old_config = $this->configs[$config->hash()] ?? null;
        if ($old_config) {
            $old_config->append($config);
            return $old_config;
        } else {
            $this->configs[$config->hash()] = $config;
            return $config;
        }
    }

    public function addFromData(string $domain, string $x509_data, string $fullchain_file, string $key_file): ?TLSConfig {
        $domains = [];

        $cert_data = openssl_x509_parse($x509_data);
        if (!empty($cert_data['subject']['CN'])) {
            $domains[] = strtolower(trim($cert_data['subject']['CN']));
        }

        if (!empty($cert_data['extensions']['subjectAltName'])) {
            $san_array = explode(',', $cert_data['extensions']['subjectAltName']);
            foreach ($san_array as $san) {
                $san = strtolower(trim($san));
                if (strpos($san, 'dns:') !== 0) {
                    continue;
                }
                $san = substr($san, 4); // Remove 'DNS:' prefix
                if (!empty($san)) {
                    $domains[] = $san;
                }
            }
        }

        $domains = array_unique($domains);

        if (empty($domains)) {
            echo "Skipping $domain, no valid domains found in cert\n";
            return null;
        }

        return $this->add($domains, $fullchain_file, $key_file);
    }

    public function setDefault(array $domains, string $fullchain_file, string $key_file): TLSConfig {
        $this->default_config = $this->add($domains, $fullchain_file, $key_file);
        return $this->default_config;
    }

    public function save(): void {
        foreach ($this->writers as $writer) {
            $writer->save($this);
        }
    }
}
