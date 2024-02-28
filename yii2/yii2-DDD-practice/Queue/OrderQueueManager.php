<?php

namespace common\components\Order\Queue;

use common\components\order\Queue\jobs\PlaceOrderJob;
use yii\queue\Queue;

class OrderQueueManager implements OrderQueueManagerInterface
{
	/**
	 * @var Queue Очередь для заказов (исходящие)
	 */
	private $queueOrderOut;
	
	/**
	 * @param Queue $queueOrderOut
	 */
	public function __construct(Queue $queueOrderOut)
	{
		$this->queueOrderOut = $queueOrderOut;
	}
	
	/**
	 * @param int $orderId
	 *
	 * @return string|null
	 */
	public function placeOrder(int $orderId): ?string
	{
		$job = new PlaceOrderJob(
			[
				'orderId' => $orderId,
			]
		);
		
		return $this->queueOrderOut->push($job);
	}
	
}
