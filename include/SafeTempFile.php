<?php
declare (strict_types=1);

class SafeTempFile {
    private readonly string $name;
    private readonly string $tmpname;
    private mixed $fh;

    public function __construct(string $name, int $chmod = 0644) {
        $this->name = $name;
        $this->tmpname = $name . '.tmp';
        @unlink($this->tmpname);
        $this->fh = fopen($this->tmpname, 'w');
        if (!$this->fh) {
            throw new Exception("Could not open temporary file: $this->tmpname");
        }
        chmod($this->tmpname, $chmod);
    }

    public function __destruct() {
        $this->remove();
    }

    public function write(string $data): void {
        if (!$this->fh) {
            throw new Exception("File not opened: $this->tmpname");
        }
        if (fwrite($this->fh, $data) === false) {
            throw new Exception("Could not write to temporary file: $this->tmpname");
        }
    }

    public function writeln(string $data): void {
        $this->write($data . "\n");
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
            throw new Exception("Could not close temporary file (already removed?): $this->tmpname");
        }
        if (!rename($this->tmpname, $this->name)) {
            throw new Exception("Could not rename temporary file to final name: $this->name");
        }
    }

    public function remove(): void {
        $this->close();
        @unlink($this->tmpname);
    }
}
