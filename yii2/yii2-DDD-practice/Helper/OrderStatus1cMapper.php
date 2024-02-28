<?php

namespace common\components\order\Helper;

class OrderStatus1cMapper
{
	
	protected const STATUS_RESERVED         = 'Зарезервирован';
	protected const STATUS_ASSEMBLE         = 'Собирается';
	protected const STATUS_ON_DELIVERY      = 'Передан в службу доставки';
	protected const STATUS_DELIVERED        = 'Доставлен';
	protected const STATUS_DONE             = 'Отгружен';
	protected const STATUS_RESERVE_CANCELED = 'Снят с резерва';
	protected const STATUS_READY_DELIVERY   = 'Подготовлен к доставке';
	protected const STATUS_COURIER_TAKE     = 'Передан курьеру';
	
	protected static function getOptions(): array
	{
		return [
			self::STATUS_RESERVED         => 'reserved',
			self::STATUS_ASSEMBLE         => 'assemble',
			self::STATUS_ON_DELIVERY      => 'on_delivery',
			self::STATUS_DELIVERED        => 'delivered',
			self::STATUS_DONE             => 'shipped',
			self::STATUS_RESERVE_CANCELED => 'canceled',
			self::STATUS_READY_DELIVERY   => 'ready_delivery',
			self::STATUS_COURIER_TAKE     => 'courier_take',
		];
	}
	
	public static function getBy1cCode(string $status): ?string
	{
		return self::getOptions()[$status] ?? null;
	}
	
	
}