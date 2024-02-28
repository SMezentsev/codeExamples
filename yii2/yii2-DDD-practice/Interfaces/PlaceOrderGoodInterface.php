<?php

namespace common\components\order\Interfaces;

interface PlaceOrderGoodInterface
{
	
	/**
	 * @return string Тип товара
	 */
	public function getType(): string;
	
	/**
	 * @return string Идентификатор товара
	 */
	public function getIdentity(): string;
	
	/**
	 * @return int Кол-во товара
	 */
	public function getQuantity(): int;
	
	/**
	 * @return int|null Стоимость товара
	 */
	public function getPrice(): ?int;
	
	/**
	 * @return string|null Идентификатор категории авто-товара
	 */
	public function getAutoPartCategoryId(): ?string;
	
}