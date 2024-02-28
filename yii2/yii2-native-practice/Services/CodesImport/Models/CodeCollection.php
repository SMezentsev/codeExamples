<?php


namespace app\modules\itrack\Services\CodesImport\Models;

use yii\base\InvalidParamException;
use app\modules\itrack\Services\CodesImport\Models\Code;

/**
 * Class ItemCollection
 */
class CodeCollection extends \SplObjectStorage
{
    /**
     * @param object $code
     * @param null $data
     */
    public function attach($code, $data = null): void
    {
        if (is_a($code, Code::class)) {
            parent::attach($code, $data);
        } else {
            throw new InvalidParamException('Ожидается объект типа Code!');
        }
    }

    /**
     * @param array|null $defaultProductData
     * @return array
     */
    public function getFormattedCodesHierarchy(array $defaultProductData = null): array
    {
        $formattedHierarchy = [];

        foreach ($this as $code) {
            $codeRowData = $code->getFormattedCodesHierarchy($defaultProductData);
            $formattedHierarchy = array_merge($formattedHierarchy, $codeRowData);
        }

        return $formattedHierarchy;
    }

    /**
     * @return array
     */
    public function getCodesProducts(): array
    {
        $products = [];

        foreach ($this as $code) {
            $codeProducts = $code->getProducts();
            $diff = array_udiff_assoc($codeProducts, $products, function ($arr1, $arr2) {
                return ($arr1 === $arr2) ? false : true;
            });
            $products = array_merge($products, $diff);
        }

        return $products;
    }
}