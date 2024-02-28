<?php

namespace common\components\order\Model\Query;

use common\components\order\Model\OrderStatusCategory;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;

class OrderStatusQuery extends ActiveQuery
{
	
	/**
	 * @return $this
	 */
	public function byStatusError(): self
	{
		return $this->byCategoryId(OrderStatusCategory::CATEGORY_DELIVERY)->byCode('error');
	}
	
	/**
	 * @param int|int[]|ActiveQueryInterface $categoryId
	 *
	 * @return $this
	 */
	public function byCategoryId($categoryId): self
	{
		return $this->andWhere(['status_category_id' => $categoryId]);
	}
	
	/**
	 * @param string|string[]|ActiveQueryInterface $statusCode
	 *
	 * @return $this
	 */
	public function byCode($statusCode): self
	{
		return $this->andWhere(['code' => $statusCode]);
	}
	
}