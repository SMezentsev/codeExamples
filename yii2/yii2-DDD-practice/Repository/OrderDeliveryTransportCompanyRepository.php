<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderDeliveryTransportCompanyDto;
use common\components\order\Interfaces\OrderDeliveryTransportCompanyRepositoryInterface;
use common\components\order\Order;
use common\Exceptions\ValidationErrorException;
use common\models\DeliveryCompany;
use common\components\order\Model\OrderDeliveryTransportCompany;


class OrderDeliveryTransportCompanyRepository implements OrderDeliveryTransportCompanyRepositoryInterface
{
    /**
     * Возвращает заказ по его ID
     *
     * @param $id integer ID заказа
     *
     * @return OrderRepositoryInterface
     */
    public function findById($id)
    {
    }
    
    /**
     * Сохраняет заказ
     *
     * @param OrderDeliveryTransportCompanyDto $dto
     *
     */
    public function save(OrderDeliveryTransportCompanyDto $dto): OrderDeliveryTransportCompany
    {
        $deliveryCompany = DeliveryCompany::getCompanyByName($dto->engName);
        $delivery        = new OrderDeliveryTransportCompany(
            [
                'order_id'               => $dto->orderId,
                'delivery_company_id'    => $deliveryCompany->id,
                'comment'                => $dto->comment,
                'city'                   => $dto->city,
                'user_transport_company' => $dto->userTransportCompany,
            ]
        );
        
        if (!$delivery->save()) {
            throw ValidationErrorException::create($delivery->errors);
        }
        
        return $delivery;
    }
}