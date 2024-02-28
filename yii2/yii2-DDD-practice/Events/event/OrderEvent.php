<?php

namespace common\components\order\Events\event;

use common\models\forms\B2BOrderForm;
use domain\entities\service1c\OrderReserve;
use yii\base\Event;

class OrderEvent extends Event
{
	public const ON_ORDER_CREATE = 'new-order';
	
	public B2BOrderForm $orderForm;
	public OrderReserve $reserve;
	
	public function __construct($eventData)
	{
		parent::__construct($eventData);
		$this->orderForm = $eventData['orderForm'];
		$this->reserve   = $eventData['reserve'];
	}
}
