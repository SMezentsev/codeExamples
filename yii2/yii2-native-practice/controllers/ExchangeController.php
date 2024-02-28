<?php


namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\exchange\entities\ExchangeLog;
use app\modules\itrack\models\exchange\exceptions\SerializeException;
use app\modules\itrack\models\exchange\models\ExchangeLogConductor;
use app\modules\itrack\models\exchange\requests\ExchangeRequest;
use yii\base\ErrorException;

class ExchangeController extends ActiveController
{
    use ControllerTrait;

    public $modelClass = ExchangeLog::class;

    public $serializer = [
        'class' => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'exchange'
    ];

    /**
     * Метод выполняет импорт кодов из внешних систем
     * @return array
     */
    public function actionImportData()
    {
        $error = '';
        $payload = [];

        try {
            $exchangeRequest = \Yii::createObject(ExchangeRequest::className());
            $exchangeRequest->attributes = \Yii::$app->request->post();

            if (!$exchangeRequest->validate()) {
                throw new SerializeException($exchangeRequest->getErrors());
            }

            $exchangeLogConductor = \Yii::createObject(ExchangeLogConductor::className());
            $query = $exchangeLogConductor->checkQueryExist($exchangeRequest);

            if (count($query) === 0) {
                $payload = $exchangeLogConductor->createExchangeQuery($exchangeRequest);
            } else {
                $payload = $query;
            }
        } catch (SerializeException $e) {
            $error = unserialize($e->getMessage());
            \Yii::$app->response->statusCode = 400;
        } catch (\Exception $e) {
            $error = ['internal' => $e->getMessage()];
            \Yii::$app->response->statusCode = 400;
        }

        return [
            'success' => ($error === '') ? true : false,
            'payload' => $payload,
            'error' => $error
        ];
    }
}