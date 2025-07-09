<?php

class SafeTempFile {
    private $name;
    private $tmpname;
    private $fh;

    public function __construct($name, $chmod = 0644) {
        $this->name = $name;
        $this->tmpname = $name . '.tmp';
        @unlink($this->tmpname);
        $this->fh = fopen($this->tmpname, 'w');
        if (!$this->fh) {
            throw new Exception("Could not open temporary file: $this->tmpname");
        }
        chmod($this->tmpname, $chmod);
    }

    public function write($data) {
        if (fwrite($this->fh, $data) === false) {
            throw new Exception("Could not write to temporary file: $this->tmpname");
        }
    }

    public function writeln($data) {
        $this->write($data . "\n");
    }

    public function close() {
        fclose($this->fh);
        if (!rename($this->tmpname, $this->name)) {
            throw new Exception("Could not rename temporary file to final name: $this->name");
        }
    }
}
