<?php
declare (strict_types=1);

class SafeTempFile {
    private readonly string $name;
    private readonly string $tmpName;
    private mixed $fh;

    public function __construct(string $name, int $chmod = 0644) {
        $this->name = $name;
        $this->tmpName = $name . '.tmp';
        @unlink($this->tmpName);
        $this->fh = fopen($this->tmpName, 'w');
        if (!$this->fh) {
            throw new Exception("Could not open temporary file: $this->tmpName");
        }
        chmod($this->tmpName, $chmod);
    }

    public function __destruct() {
        $this->remove();
    }

    public function write(string $data): void {
        if (!$this->fh) {
            throw new Exception("File not opened: $this->tmpName");
        }
        if (fwrite($this->fh, $data) === false) {
            throw new Exception("Could not write to temporary file: $this->tmpName");
        }
    }

    public function writeLine(string $data): void {
        $this->write($data . PHP_EOL);
    }

    private function close(): bool {
        if (!$this->fh) {
            return false;
        }
        fclose($this->fh);
        $this->fh = false;
        return true;
    }

    public function save(): void {
        if (!$this->close()) {
            throw new Exception("Could not close temporary file (already removed?): $this->tmpName");
        }
        if (!rename($this->tmpName, $this->name)) {
            throw new Exception("Could not rename temporary file to final name: $this->name");
        }
        echo "Saved: $this->name" . PHP_EOL;
    }

    public function remove(): void {
        $this->close();
        @unlink($this->tmpName);
    }
}
