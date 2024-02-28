<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderStatusDto;
use common\components\order\Interfaces\OrderStatusRepositoryInterface;
use common\components\order\Model\OrderStatus;
use common\components\order\Model\OrderStatusHistory;
use common\Exceptions\ValidationErrorException;
use Yii;
use yii\web\NotFoundHttpException;

class OrderStatusRepository implements OrderStatusRepositoryInterface
{
	
	/**
	 * Сохраняет дефолтный статус
	 *
	 * @param int   $orderId
	 * @param array $statusCategories
	 *
	 * @throws NotFoundHttpException
	 * @throws ValidationErrorException
	 */
	public function setDefaultStatus(int $orderId, array $statusCategories)
	{
		if ($statusCategories) {
			foreach ($statusCategories as $key => $statusCategory) {
				$this->create(
					new OrderStatusDto($orderId, $this->getDefaultStatus($statusCategory)->id)
				);
			}
		}
	}
	
	/**
	 * Возвращает статус
	 *
	 * @param int $statusId
	 *
	 */
	public function getStatus(int $statusId): OrderStatus
	{
		return OrderStatus::findOne(['id' => $statusId]);
	}
	
	/**
	 * Сохраняет статус
	 *
	 * @param int $orderId
	 * @param int $statusId
	 *
	 * @return OrderStatus
	 * @throws ValidationErrorException
	 */
	public function setStatus(int $orderId, int $statusId): OrderStatus
	{
		$this->create(
			new OrderStatusDto($orderId, $statusId)
		);
		
		return $this->getStatus($statusId);
	}
	
	/**
	 * Сохраняет счет
	 *
	 * @param OrderStatusDto $dto
	 *
	 * @return OrderStatusHistory
	 * @throws ValidationErrorException
	 */
	public function create(OrderStatusDto $dto): OrderStatusHistory
	{
		$status = new OrderStatusHistory(
			[
				'order_id'        => $dto->orderId,
				'order_status_id' => $dto->statusId,
			]
		);
		
		if (!$status->save()) {
			throw ValidationErrorException::create($status->errors);
		}
		
		return $status;
	}
	
	/**
	 * Получение дефолтного статуса
	 *
	 * @param int $statusCategoryId
	 *
	 * @return OrderStatus
	 * @throws NotFoundHttpException
	 */
	public function getDefaultStatus(int $statusCategoryId): OrderStatus
	{
		if (!$model = OrderStatus::find()->where(['status_category_id' => $statusCategoryId, 'is_default' => 1])->one()) {
			throw new NotFoundHttpException(Yii::t('app', 'Не найден дефолтный статус с категорией id={id}', ['id' => $statusCategoryId]));
		}
		
		return $model;
	}
}