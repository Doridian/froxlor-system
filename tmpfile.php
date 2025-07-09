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

        register_shutdown_function(function() {
            $this->remove();
        });
    }

    public function write($data) {
        if (empty($this->fh)) {
            throw new Exception("File not opened: $this->tmpname");
        }
        if (fwrite($this->fh, $data) === false) {
            throw new Exception("Could not write to temporary file: $this->tmpname");
        }
    }

    public function writeln($data) {
        $this->write($data . "\n");
    }

    private function closefh() {
        if (empty($this->fh)) {
            return false;
        }
        fclose($this->fh);
        unset($this->fh);
        return true;
    }

    public function save() {
        if (!$this->closefh()) {
            return;
        }
        if (!rename($this->tmpname, $this->name)) {
            throw new Exception("Could not rename temporary file to final name: $this->name");
        }
    }

    public function remove() {
        $this->closefh();
        @unlink($this->tmpname);
    }
}
