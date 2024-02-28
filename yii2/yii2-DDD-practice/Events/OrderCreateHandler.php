<?php

namespace common\components\order\Events;

use common\components\notify\OrderNotify;
use common\components\order\Events\event\OrderEvent;
use Exception;

class OrderCreateHandler
{
	
	/**
	 * @param OrderEvent $event
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function handle(OrderEvent $event): void
	{
		$notify = new OrderNotify();
		$notify->orderCreate($event->orderForm, $event->reserve);
	}
}