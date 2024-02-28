<?php

namespace common\components\order\Repository;

use common\components\order\Interfaces\OrderEventInterface;
use common\components\order\Interfaces\OrderEventRepositoryInterface;
use yii\db\ActiveRecord;
use yii\db\ActiveRecordInterface;

class OrderEventRepository implements OrderEventRepositoryInterface
{
	
	/**
	 * @var ActiveRecordInterface|ActiveRecord|string
	 */
	private $modelClass;
	
	/**
	 * @param string|ActiveRecord|ActiveRecordInterface $modelClass
	 */
	public function __construct(string $modelClass)
	{
		$this->modelClass = $modelClass;
	}
	
	public function event(OrderEventInterface $event): void
	{
		/** @var ActiveRecord $model */
		$model = new $this->modelClass;
		$model->setAttributes(
			[
				'order_id'   => $event->getOrderId(),
				'event'      => $event->getEvent(),
				'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
				'payload'    => $event->getPayload(),
			]
			,
			false
		);
		$model->save(false);
	}
	
}