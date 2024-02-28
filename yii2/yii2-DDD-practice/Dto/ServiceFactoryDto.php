<?php

namespace common\components\order\Dto;

use common\components\order\Model\OrderDeliveryType;
use common\models\OptUserAddress;
use DateTimeImmutable;

class ServiceFactoryDto
{
    public string $deliveryType;
    public int $userId;
    public ?string $code1c;
    public array $delivery;
    public array $cart;
    public ?string $shipping_date;
    public ?string $reserve_date;
    public ?int $price;
    public ?string $invoiceNumber;
    public ?string $createdAt;
    public ?string $comment;
    
    public function __construct(array $deliveryData, ?string $code1c, array $cart)
    {
        $this->code1c = $code1c;
        $this->cart   = $cart;
        
        $this->deliveryType = $deliveryData['deliveryType'];
        $this->delivery     = $deliveryData['delivery'];
        
        $this->userId        = $deliveryData['userId'];
        $this->shipping_date = $deliveryData['shippingDate'] ?? '';
        $this->reserve_date  = $deliveryData['reserveDate'] ?? '';
        $this->price         = $deliveryData['price'] ?? 0;
        $this->invoiceNumber = $deliveryData['invoiceNumber'] ?? null;
        $this->createdAt     = $deliveryData['createdAt'] ?? '';
        $this->comment       = $deliveryData['comment'] ?? '';
    }
    
    public function create(): array
    {
        $dto = [];
        switch ($this->deliveryType) {
            case OrderDeliveryType::PICKUP_METHOD:
                $dto = [
                    'shopId'       => (int)$this->delivery['shop'] ?? '',
                    'deliveryDate' => self::prepareShipmentDate($this->delivery['date'] ?? ''),
                    'comment'      => $this->delivery['comment'] ?? '',
                ];
                break;
            
            case OrderDeliveryType::CITY_REGION_METHOD:
                
                /** @var OptUserAddress|null $userAddress */
                $userAddress = $this->delivery['userAddress'] ?? null;
                
                $dto = [
                    'userAddressId' => $userAddress !== null ? $userAddress->id : null,
                    'deliveryDate'  => self::prepareShipmentDate($this->delivery['date'] ?? ''),
                    'comment'       => $this->delivery['comment'] ?? '',
                    'schedule'      => $this->delivery['schedule'] ?? '',
                ];
                break;
            
            case OrderDeliveryType::RUSSIAN_TS_METHOD:
                $dto = [
                    'eng_name'             => $this->delivery['tc'] ?? '',
                    'comment'              => $this->delivery['comment'] ?? '',
                    'userCity'             => $this->delivery['userCity'] ?? '',
                    'payment'              => $this->delivery['payment'] ?? '',
                    'userTransportCompany' => $this->delivery['userTc'] ?? '',
                ];
                break;
            
            default:
                return [];
        }
        
        return $dto;
    }
    
    protected static function prepareShipmentDate(string $date): string
    {
		return DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u\Z', $date)
			->format('Y-m-d H:i:s');
    }
    
}