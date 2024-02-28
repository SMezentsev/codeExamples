<?php

namespace common\components\order\Model\Query;

use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;

class OrderEventQuery extends ActiveQuery
{
	
	/**
	 * @param int|int[]|ActiveQueryInterface $orderId
	 *
	 * @return $this
	 */
	public function byOrderId($orderId): self
	{
		return $this->andWhere(['order_id' => $orderId]);
	}
	
}