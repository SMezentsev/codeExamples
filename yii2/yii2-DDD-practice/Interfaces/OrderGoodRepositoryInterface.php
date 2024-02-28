<?php

namespace common\components\order\Interfaces;

use common\components\order\Dto\OrderDeliveryStatusDto;
use common\components\order\Dto\OrderGoodDto;
use common\components\order\Model\Order;
use common\components\order\Model\GoodCategory;
use common\components\order\Model\OrderGood;

interface OrderGoodRepositoryInterface
{
    /**
     * Возвращает категорию товара
     *
     * @param string $eng_name
     *
     */
    
    public function getGoodCategoryByEngName(string $eng_name);
    
    /**
     * Сохраняет товар
     *
     * @param OrderGoodDto $dto
     *
     * @return OrderGood
     */
    
    public function create(OrderGoodDto $dto): OrderGood;
}