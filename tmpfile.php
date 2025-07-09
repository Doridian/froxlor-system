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
            try {
                $this->close();
            } catch (Exception $e) {
                echo "Error during shutdown: " . $e->getMessage() . "\n";
            }
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

    public function close() {
        if (empty($this->fh)) {
            return;
        }
        fclose($this->fh);
        unset($this->fh);
        if (!rename($this->tmpname, $this->name)) {
            throw new Exception("Could not rename temporary file to final name: $this->name");
        }
    }
}
