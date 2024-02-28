<?php

namespace common\components\order\Factory;

use common\components\order\Interfaces\OrderInterface;
use common\models\forms\B2BOrderForm;

interface B2BOrderFormFactoryByOrderInterface
{
	
	public function createByOrder(OrderInterface $order): B2BOrderForm;
	
}