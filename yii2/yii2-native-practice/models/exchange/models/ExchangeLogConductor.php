<?php

namespace app\modules\itrack\models\exchange\models;

use Yii;
use yii\base\Model;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use app\modules\itrack\models\exchange\dao\ExchangeLogGateway;
use app\modules\itrack\models\exchange\requests\ExchangeRequest;

/**
 * Класс отвечает за работу с запросами на импорт
 * Class ExchangeLogConductor
 * @package app\modules\itrack\models\exchange
 */
class ExchangeLogConductor extends Model
{
    /**
     * Проверяет нет ли импорта в системе с выбранным номером заказа
     * @param ExchangeRequest $exchangeRequest
     * @return array
     * @throws \ErrorException
     */
    public function checkQueryExist(ExchangeRequest $exchangeRequest)
    {
        try {
            $exchangeLogGateway = Yii::createObject(ExchangeLogGateway::className());
            $data = $exchangeLogGateway->findByOrderNumber($exchangeRequest->orderNumber);
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось получить данные об обмене.');
        }

        if ($data === []) {
            return [];
        } else {
            return [
                'id' => $data['id'],
                'order_number' => $data['order_number'],
                'status' => $this->statusMutator($data['status'])
            ];
        }
    }

    /**
     * Создает запрос на импорт кодов, который будет обработан по крону exchange/start
     * @param ExchangeRequest $exchangeRequest
     * @return array
     * @throws \ErrorException
     */
    public function createExchangeQuery(ExchangeRequest $exchangeRequest)
    {
        try {
            $exchangeLogGateway = Yii::createObject(ExchangeLogGateway::className());
            $data = $exchangeLogGateway->create(
                [
                    'data' => serialize(ArrayHelper::toArray($exchangeRequest)),
                    'order_number' => $exchangeRequest->orderNumber,
                    'status' => ExchangeLogGateway::STATUS_READY,
                ]
            );
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось сохранить запрос в бд.');
        }

        return [
            'id' => $data['id'],
            'order_number' => $data['order_number'],
            'status' => $this->statusMutator($data['status'])
        ];
    }

    /**
     * Возвращает готовые к обработке записи обмена
     * @return mixed
     * @throws \ErrorException
     */
    public function getExchangeRequests()
    {
        try {
            $exchangeLogGateway = Yii::createObject(ExchangeLogGateway::className());
            $exchangeRequests = $exchangeLogGateway->findAllReadyRequests();

            foreach ($exchangeRequests as $key => $exchangeRequest) {
                $exchangeRequests[$key]['data'] = unserialize($exchangeRequests[$key]['data']);
            }
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось получить данные обмена.');
        }

        return $exchangeRequests;
    }

    /**
     * Выполняет обновление статуса обмена
     * @param int $exchangeId
     * @return int
     * @throws \ErrorException
     */
    public function updateStatus(int $exchangeId) : int
    {
        try {
            $exchangeLogGateway = Yii::createObject(ExchangeLogGateway::className());

            $exchange = $exchangeLogGateway->findById($exchangeId);

            if ($exchange === null) {
                throw new \ErrorException('Не удалось найти запись с идентификатором: ' . $exchangeId);
            }

            if ($exchange['status'] === ExchangeLogGateway::STATUS_SUCCESS) {
                throw new \ErrorException('Запись уже была успешно обработана!');
            }

            $exchangeLogGateway->update(
                $exchange['id'],
                [
                    'status' => $exchange['status']++,
                ]
            );
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось обновить статус для id=' . $exchangeId . ' ' . $e->getMessage());
        }

        return $exchange['status'];
    }

    /**
     * Записывает текст ошибки возникшей при обмене
     * @param int $exchangeId
     * @param string $error
     * @throws \ErrorException
     */
    public function setExchangeFail(int $exchangeId, string $error) : void
    {
        try {
            $exchangeLogGateway = Yii::createObject(ExchangeLogGateway::className());

            $exchange = $exchangeLogGateway->findById($exchangeId);

            if ($exchange === null) {
                throw new \ErrorException('Не удалось найти запись с идентификатором: ' . $exchangeId);
            }

            if ($exchange['status'] === ExchangeLogGateway::STATUS_ERROR) {
                throw new \ErrorException('У записи уже установлена ошибка: ' . $exchange['last_error']);
            }

            $exchangeLogGateway->update(
                $exchange['id'],
                [
                    'status' => ExchangeLogGateway::STATUS_ERROR,
                    'last_error' => $error
                ]
            );
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось записать ошибку для id=' . $exchangeId . ' ' . $e->getMessage());
        }
    }

    /**
     * Преобразует статус в текст
     * @param array $data
     * @return array
     */
    private function statusMutator(int $status) : string
    {
        $statusTxt = '';

        switch ($status) {
            case ExchangeLogGateway::STATUS_ERROR:
                $statusTxt = 'Error';
                break;
            case ExchangeLogGateway::STATUS_READY:
                $statusTxt = 'Ready';
                break;
            case ExchangeLogGateway::STATUS_IN_PROGRESS:
                $statusTxt = 'In progress';
                break;
            case ExchangeLogGateway::STATUS_SUCCESS:
                $statusTxt = 'Success';
                break;
        }

        return $statusTxt;
    }
}