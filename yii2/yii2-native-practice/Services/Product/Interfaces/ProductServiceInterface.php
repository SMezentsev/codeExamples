<?php


namespace app\modules\itrack\Services\Product\Interfaces;

use app\modules\itrack\models\Product;
use app\modules\itrack\Services\Product\DTO\ProductDataDto;
use app\modules\itrack\Services\Product\Models\ProductCollection;

/**
 * Interface ProductServiceInterface
 */
interface ProductServiceInterface
{
    /**
     * @param ProductDataDto $productDataDto
     * @return Product
     */
    public function save(ProductDataDto $productDataDto): Product;

    /**
     * @param ProductDataDto $productDataDto
     * @return Product|null
     */
    public function findByProductData(ProductDataDto $productDataDto): ?Product;

    /**
     * @return ProductCollection
     */
    public function getCachedProducts(): ProductCollection;
}