<?php

namespace app\modules\itrack\components;

use yii\base\Component;

class TQSClient extends Component
{
    
    static public $TIMEOUT  = 30;
    protected     $remote_addr;
    protected     $filedesc = null;
    
    public function __construct($remote_ip, $remote_port)
    {
        $this->remote_addr = $remote_ip . ":" . $remote_port;
        
        $this->filedesc = stream_socket_client("tcp://" . $remote_ip . ":" . $remote_port, $errno, $errstr, 5);
        
        if (!$this->filedesc) {
            throw new Exception("Ошибка подключения: $errstr ($errno)");
        }
        
        stream_set_blocking($this->filedesc, true);
    }
    
    
    public function send($xml)
    {
        for ($written = 0; $written < strlen($xml); $written += $fwrite) {
            $fwrite = fwrite($this->filedesc, substr($xml, $written));
            if ($fwrite === false) {
                return false;
            }
        }
        
        return true;
    }
    
    public function receive()
    {
        $buf = '';
        $trys = 0;
        
        do {
            stream_set_timeout($this->filedesc, self::$TIMEOUT);
            $res = fread($this->filedesc, 655350);
            $buf .= $res;
            try {
                $xml = new \SimpleXMLElement($buf);
                
                return $buf;
            } catch (\Exception $ex) {
                //ошибочный $xml  пробуем еще
                if (empty($res)) {
                    $trys++;
                }
            }
        } while ($trys < 5);
        
        throw new \Exception('Ошибка получения данных');
    }
    
    public function disconnect()
    {
        fclose($this->filedesc);
    }
}
