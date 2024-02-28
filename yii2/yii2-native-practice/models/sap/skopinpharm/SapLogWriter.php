<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use Yii;
use yii\base\Model;
use app\modules\itrack\models\FnsOcs;

/**
 * Класс выполняет функции логирования запросов и ответов sap
 * Class SapLogWriter
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class SapLogWriter extends Model
{
    private $operation;

    const MODE_WITHOUT_OCS = 0;
    const MODE_WITH_OCS = 1;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->operation = null;
    }

    /**
     * @param array $operation
     * @return void
     */
    public function setOperation(array $operation) :void
    {
        $this->operation = $operation;
    }

    /**
     * @param string $request
     * @param string $response
     * @param int $mode
     * @throws \ErrorException
     */
    public function saveLog(string $request, string $response, $mode = self::MODE_WITH_OCS)
    {
        $log = $this->buildLog($request, $response);
        $this->writeLogToDisc($log);

        if ($mode) {
            $this->writeOperationOcs($log);
        }
    }

    /**
     * Пишет лог операции в бд
     * @param string $log
     * @throws \ErrorException
     */
    private function writeOperationOcs(string $log) :void
    {
        if ($this->operation === null) {
            throw new \ErrorException('Не установлена операция по которой будет записан лог');
        }

        $fnsOcs = new FnsOcs();
        $fnsOcs->attributes = $this->operation;
        $fnsOcs->codes = '{}'; //бага
        $fnsOcs->fns_log = $log;

        if ($fnsOcs->save(false) === false) {
            throw new \ErrorException('Не удалось записать лог по отправке отчета в sap.');
        }
    }

    /**
     * Строит лог в читаемом виде с данными запроса, ответа и времени операции
     * @param string $request
     * @param string $response
     * @return string
     */
    private function buildLog(string $request, string $response) :string
    {
        $log = '--------------------------------------' . PHP_EOL;
        $log .= 'RequestDate: ' . date('d.m.Y H:i:s', time()) . PHP_EOL;
        $log .= 'RequestData: ' . $request . PHP_EOL;
        $log .= 'ResponseData: ' . $response . PHP_EOL;
        $log .= '--------------------------------------' . PHP_EOL;

        return $log;
    }

    /**
     * логирует запросы
     * @param string $data
     * @return void
     */
    private function writeLogToDisc(string $log): void
    {
        $file = fopen(\Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . '.sap_ich_log','a');
        fwrite($file, $log);
        fclose($file);
    }
}