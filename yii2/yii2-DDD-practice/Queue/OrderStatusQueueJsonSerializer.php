<?php

namespace common\components\order\Queue;

use common\components\order\Queue\jobs\OrderStatusJob;
use Yii;
use yii\queue\serializers\JsonSerializer;

class OrderStatusQueueJsonSerializer extends JsonSerializer
{
	
	protected function fromArray($data)
	{
		if (is_array($data)) {
			$jobType   = $data['type'] ?? null;
			$jobAction = $data['action'] ?? null;
			
			switch (true) {
				case ($jobType === 'order_status' && $jobAction === 'update'):
					return Yii::createObject(
						[
							'class'  => OrderStatusJob::class,
							'orders' => $data['order_status_data'] ?? [],
						]
					);
			}
		}
		
		return parent::fromArray($data);
	}
	
}