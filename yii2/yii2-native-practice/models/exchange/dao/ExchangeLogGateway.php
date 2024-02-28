<?php

namespace app\modules\itrack\models\exchange\dao;

use app\modules\itrack\models\exchange\entities\ExchangeLog;
use app\modules\itrack\models\exchange\exceptions\SerializeException;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class ExchangeLogGateway extends Model
{
    const STATUS_ERROR = 0;
    const STATUS_READY = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_SUCCESS = 3;

    /**
     * @param array $data
     * @return array
     * @throws SerializeException
     */
    public function create(array $data) :array
    {
        $exchangeLog = new ExchangeLog();
        $exchangeLog->data = $data['data'];
        $exchangeLog->order_number = $data['order_number'];
        $exchangeLog->status = $data['status'];

        if ($exchangeLog->save() === false) {
            throw new SerializeException($exchangeLog->getErrors());
        }

        return ArrayHelper::toArray($exchangeLog);
    }

    /**
     * @param string $oderNumber
     * @return array
     */
    public function findById(int $exchangeId) : array
    {
        $data = ExchangeLog::findOne(['id' => $exchangeId]);
        return ($data === null) ? [] : ArrayHelper::toArray($data);
    }

    /**
     * @param string $oderNumber
     * @return array
     */
    public function findByOrderNumber(string $oderNumber) : array
    {
        $data = ExchangeLog::findOne(['order_number' => $oderNumber]);
        return ($data === null) ? [] : ArrayHelper::toArray($data);
    }

    /**
     * @return array
     */
    public function findAllReadyRequests() : array
    {
        $data = ExchangeLog::find()
            ->where(['status' => self::STATUS_READY])
            ->asArray()
            ->all();

        return $data;
    }

    /**
     * @param int $id
     * @param array $fields
     * @throws \yii\db\Exception
     */
    public function update(int $id, array $fields) : void
    {
        $keysStr =[];

        foreach ($fields as $key => $value) {
            $keysStr[] = $key . '=:' . $key;
        }

        $query = \Yii::$app->db->createCommand('UPDATE exchange_log SET ' . implode(',', $keysStr) . ' WHERE id=:id',
            array_merge(['id' => $id], $fields)
        )->queryScalar();
        var_dump($query->getRawSql());
        exit;
    }


}