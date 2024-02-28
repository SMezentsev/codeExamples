<?php


namespace app\modules\itrack\Services\Product\Models;

use app\modules\itrack\models\Product;
use yii\base\InvalidParamException;

/**
 * Class ProductCollection
 */
class ProductCollection extends \SplObjectStorage
{
    /**
     * @param object $product
     * @param null $data
     */
    public function attach($product, $data = null): void
    {
        if (is_a($product, Product::class)) {
            parent::attach($product, $data);
        } else {
            throw new InvalidParamException('Ожидается объект типа Product!');
        }
    }
}