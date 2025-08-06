<?php
declare (strict_types=1);

require_once 'ITLSConfigHolder.php';
require_once 'TLSConfig.php';

class TLSConfigurator implements ITLSConfigHolder {
    protected array $configs;
    protected ?TLSConfig $defaultConfig;
    private readonly array $writers;
    private array $warnings;

    public function __construct(array $writers) {
        $this->writers = $writers;
        $this->defaultConfig = null;
        $this->warnings = [];
    }

    public function getConfigs(): array {
        return $this->configs;
    }

    public function getDefaultConfig(): ?TLSConfig {
        return $this->defaultConfig;
    }

    public function add(TLSconfig $config): TLSConfig {
        $old_config = $this->configs[$config->uniqueKey()] ?? null;
        if ($old_config) {
            $old_config->append($config);
            return $old_config;
        } else {
            $this->configs[$config->uniqueKey()] = $config;
            return $config;
        }
    }

    public function addFromData(string $x509Data, string $fullChainFile, string $keyFile): ?TLSConfig {
        $domains = [];

        $certData = openssl_x509_parse($x509Data);
        if (!empty($certData['subject']['CN'])) {
            $domains[] = $certData['subject']['CN'];
        }

        if (!empty($certData['extensions']['subjectAltName'])) {
            $sanArray = explode(',', $certData['extensions']['subjectAltName']);
            foreach ($sanArray as $sanRaw) {
                $sanSplit = explode(':', strtolower(trim($sanRaw)), 2);
                if (count($sanSplit) !== 2 || trim($sanSplit[0]) !== 'dns') {
                    continue;
                }
                $sanDNS = trim($sanSplit[1]);
                if (!empty($sanDNS)) {
                    $domains[] = $sanDNS;
                }
            }
        }

        if (empty($domains)) {
            $this->warnings[] = "Skipping $keyFile, no valid domains found in certificate";
            return null;
        }

        return $this->add(new TLSConfig($domains, $fullChainFile, $keyFile));
    }

    public function setDefault(?TLSConfig $config): void {
        if (!$config) {
            $this->defaultConfig = null;
            return;
        }
        $this->defaultConfig = $this->add($config);
    }

    public function hash(): string {
        $keys = array_keys($this->configs);
        sort($keys, SORT_STRING);
        $hash = hash_init('sha3-512');
        foreach ($keys as $key) {
            hash_update($hash, $this->configs[$key]->hash() . PHP_EOL);
        }
        return hash_final($hash, false);
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
