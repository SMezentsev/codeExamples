<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderGoodDto;
use common\components\order\Interfaces\OrderGoodRepositoryInterface;
use common\Exceptions\ValidationErrorException;
use common\components\order\Model\GoodCategory;
use common\components\order\Model\OrderGood;
use common\components\order\Model\Order;

class OrderGoodRepository implements OrderGoodRepositoryInterface
{
    /**
     * Возвращает статус тип доставки
     */
    public function getGoodCategoryByEngName(string $eng_name)
    {
        $category = GoodCategory::find();
        
        if (!empty($eng_name)) {
            $category->where(['eng_name' => $eng_name]);
        }
        
        if (!$category = $category->one()) {
            throw new NotFoundHttpException(Yii::t('app', 'Не найдена категория с eng_name={eng_name}', ['eng_name' => $eng_name]));
        }
        
        return $category->id;
    }
    
    /**
     * Сохраняет заказ
     *
     * @param OrderGoodRepositoryInterface $data
     *
     */
    public function create(OrderGoodDto $dto): OrderGood
    {
        $orderGood = new OrderGood(
            [
                'order_id'         => $dto->orderId,
                'good_id'          => $dto->goodId,
                'good_category_id' => $dto->goodCategoryId,
                'quantity'         => $dto->quantity,
                'price'            => $dto->price,
                'created_at'       => $dto->createdAt,
            ]
        );
        
        if (!$orderGood->save()) {
            throw ValidationErrorException::create($orderGood->errors);
        }
        
        return $orderGood;
    }
}