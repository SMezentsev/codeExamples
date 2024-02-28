<?php

namespace common\components\order\Model\Query;

use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;

class OrderQuery extends ActiveQuery
{
	
	/**
	 * @param int|int[]|ActiveQueryInterface $orderId
	 *
	 * @return $this
	 */
	public function byId($orderId): self
	{
		return $this->andWhere(['id' => $orderId]);
	}
	
	/**
	 * @param string|string[]|ActiveQueryInterface $reserveId
	 *
	 * @return $this
	 */
	public function byReserveId($reserveId): self
	{
		return $this->andWhere(['code_1c' => $reserveId]);
	}
	
}