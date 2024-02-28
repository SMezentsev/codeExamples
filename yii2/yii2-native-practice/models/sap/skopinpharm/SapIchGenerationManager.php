<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationStatus;
use yii\base\Model;
use yii\db\Exception;

/**
 * Класс для получения кодов из sap и загрузки в генерацию
 * Class SapIchGenerationManager
 *
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class SapIchGenerationManager extends Model
{
    public $sapIchConnector;
    public $sapCodesFile;
    public $generation;
    
    public function __construct(SapIchConnector $sapIchConnector, SapCodesFile $sapCodesFile)
    {
        $this->sapIchConnector = $sapIchConnector;
        $this->sapCodesFile = $sapCodesFile;
        $this->generation = null;
    }
    
    /**
     * @param array $generationData
     *
     * @return void
     * @throws \ErrorException
     */
    public function setGeneration(array $generationData): void
    {
        $this->generation = Generation::findOne(['id' => $generationData['id']]);
        
        if ($this->generation === null) {
            throw new \ErrorException('Не удалось найти генерацию с id=' . $generationData['id'] . ' в бд.');
        }
    }
    
    /**
     * @return Generation
     */
    public function getGeneration(): Generation
    {
        return $this->generation;
    }
    
    /**
     * Загружет коды из sap ich и импортирует их в установленную генерацию
     *
     * @return void
     * @throws \yii\db\Exception
     * @throws \ErrorException
     */
    public function importCodes(): void
    {
        $transaction = \Yii::$app->db->beginTransaction();
        
        try {
            //            $ichResponse = $this->sapIchConnector->serialNumberRequest(
            //                $this->generation->cnt,
            //                $this->generation->product->nomenclature->gtin
            //            );
            //            $codes = $ichResponse->getSerialNumbers();
            //            if (empty($codes)) {
            $codes = $this->sapCodesFile->checkCodes($this->generation);
            if (empty($codes)) {
                throw new Exception('Коды от САП не получены');
            }
            //            }
            $this->generation->insertCodes($codes);
            $this->setGenerationStatusProcessing();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new \ErrorException($e->getMessage());
        }
    }
    
    /**
     * Отмечает генерацию как готовую
     *
     * @return void
     * @throws \ErrorException
     */
    private function setGenerationStatusProcessing(): void
    {
        $this->generation->scenario = "external";
        $this->generation->status_uid = GenerationStatus::STATUS_PROCESSING;
        
        if (!$this->generation->save()) {
            throw new \ErrorException('Не удалось обновить статус генерации.');
        }
    }
}