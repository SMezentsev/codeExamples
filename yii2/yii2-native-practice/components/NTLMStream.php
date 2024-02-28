<?php

namespace app\modules\itrack\components;

class NTLMStream
{
    
    private $path;
    private $mode;
    private $options;
    private $opened_path;
    private $buffer;
    private $pos;
    
    public function stream_open($path, $mode, $options, $opened_path)
    {
        $this->path = $path;
        $this->mode = $mode;
        $this->options = $options;
        $this->opened_path = $opened_path;
        $this->createBuffer($path);
        
        return true;
    }
    
    public function stream_close()
    {
        curl_close($this->ch);
    }
    
    public function stream_read($count)
    {
        if (strlen($this->buffer) == 0) {
            return false;
        }
        $read = substr($this->buffer, $this->pos, $count);
        $this->pos += $count;
        
        return $read;
    }
    
    public function stream_write($data)
    {
        if (strlen($this->buffer) == 0) {
            return false;
        }
        
        return true;
    }
    
    public function stream_eof()
    {
        return ($this->pos > strlen($this->buffer));
    }
    
    public function stream_tell()
    {
        return $this->pos;
    }
    
    public function stream_flush()
    {
        $this->buffer = null;
        $this->pos = null;
    }
    
    public function stream_stat()
    {
        $this->createBuffer($this->path);
        $stat = [
            'size' => strlen($this->buffer),
        ];
        
        return $stat;
    }
    
    public function url_stat($path, $flags)
    {
        $this->createBuffer($path);
        $stat = [
            'size' => strlen($this->buffer),
        ];
        
        return $stat;
    }
    
    private function createBuffer($path)
    {
        if ($this->buffer) {
            return;
        }
        $this->ch = curl_init($path);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_USERPWD, USERPWD);
        $this->buffer = curl_exec($this->ch);
        $this->pos = 0;
    }
    
}

