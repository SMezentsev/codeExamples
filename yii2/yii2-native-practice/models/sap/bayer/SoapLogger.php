<?php


namespace app\modules\itrack\models\sap\bayer;

/**
 * Класс ведет лог soap запросов
 * Class SoapLogger
 *
 * @package app\modules\itrack\models
 */
class SoapLogger
{
    const LOG_FOLDER = 'logs';
    const LOG_FILENAME = 'soap.log';
    
    private $logPath;
    private $logFile;
    private $data;
    
    public function __construct()
    {
        $this->logPath = \Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . self::LOG_FOLDER
            . DIRECTORY_SEPARATOR . self::LOG_FILENAME;
        $this->buildLog();
    }
    
    public function writeLog()
    {
        $this->data .= file_get_contents("php://output") . PHP_EOL;
        $this->data .= PHP_EOL;
        
        $this->logFile = fopen($this->logPath, 'a+');
        flock($this->logFile, LOCK_EX);
        fwrite($this->logFile, $this->data);
        fclose($this->logFile);
    }
    
    private function buildLog()
    {
        $this->data = date('d.m.Y H:i:s', time()) . PHP_EOL;
        $this->data .= file_get_contents("php://input") . PHP_EOL;
        $this->data .= PHP_EOL;
    }
}