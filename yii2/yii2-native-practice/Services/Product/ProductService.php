<?php

namespace app\modules\itrack\Services\Product;

use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\Product;
use app\modules\itrack\Services\Product\Models\ProductCollection;
use app\modules\itrack\Services\Product\DTO\ProductDataDto;
use app\modules\itrack\Services\Product\Interfaces\ProductServiceInterface;

/**
 * Class ProductService
 */
class ProductService implements ProductServiceInterface
{
    private $productCollection;

    private const GTIN_KEY = 'gtin';
    private const NOMENCLATURE_UID_KEY = 'nomenclature_uid';
    private const SERIES_KEY = 'series';
    private const CDATE_KEY = 'cdate';
    private const EXPDATE_KEY = 'expdate';
    private const EXPDATE_FULL_KEY = 'expdate_full';

    public function __construct(ProductCollection $productCollection)
    {
        $this->productCollection = $productCollection;
    }

    /**
     * @param ProductDataDto $productDataDto
     * @return Product
     * @throws \ErrorException
     */
    public function save(ProductDataDto $productDataDto): Product
    {
        $product = $this->findByProductData($productDataDto);

        if ($product !== null) {
            return $product;
        }

        $nomenclature = Nomenclature::findNomenclatureByGtin($productDataDto->getGtin());

        if ($nomenclature === null) {
            throw new \ErrorException(
                \Yii::t('app', 'Не удалось создать товарную карту, номенклатура не найдена!')
            );
        }

        $product = Product::createProduct(
            [
                self::NOMENCLATURE_UID_KEY => $nomenclature->id,
                self::SERIES_KEY => $productDataDto->getSeries(),
                self::CDATE_KEY => date('m Y',strtotime($productDataDto->getProductionDate())),
                self::EXPDATE_KEY => date('m Y', strtotime($productDataDto->getExpirationDate())),
                self::EXPDATE_FULL_KEY => date('d m Y', strtotime($productDataDto->getExpirationDate()))
            ]
        );

        $this->productCollection->attach($product);

        return $product;
    }

    /**
     * @param ProductDataDto $productDataDto
     * @return Product|null
     */
    public function findByProductData(ProductDataDto $productDataDto): ?Product
    {
        $product = $this->findAtCollection($productDataDto);

        if ($product !== null) {
            return $product;
        }

        return $this->findAtDatabase($productDataDto);
    }

    /**
     * @return ProductCollection
     */
    public function getCachedProducts(): ProductCollection
    {
        return $this->productCollection;
    }

    /**
     * @param ProductDataDto $productDataDto
     * @return Product|null
     */
    private function findAtCollection(ProductDataDto $productDataDto): ?Product
    {
        $product = null;
        $arrayForCheck = [
            self::GTIN_KEY => $productDataDto->getGtin(),
            self::SERIES_KEY => $productDataDto->getSeries(),
            self::CDATE_KEY => date('m Y',strtotime($productDataDto->getProductionDate())),
            self::EXPDATE_KEY => date('m Y', strtotime($productDataDto->getExpirationDate())),
        ];

        foreach ($this->productCollection as $cachedProduct) {
            $cachedProductArray = [
                self::GTIN_KEY => $cachedProduct->nomenclature->gtin,
                self::SERIES_KEY => $cachedProduct->series,
                self::CDATE_KEY => $cachedProduct->cdate,
                self::EXPDATE_KEY => $cachedProduct->expdate
            ];

            if ($arrayForCheck == $cachedProductArray) {
                $product = $cachedProduct;
            }
        }

        return $product;
    }

    /**
     * @param ProductDataDto $productDataDto
     * @return Product|null
     */
    private function findAtDatabase(ProductDataDto $productDataDto): ?Product
    {
        $product = Product::find()
            ->joinWith(['nomenclature'])
            ->where(
                [
                    'product.series' => $productDataDto->getSeries(),
                    'product.cdate' => date('m Y', strtotime($productDataDto->getProductionDate())),
                    'product.expdate' => date('m Y', strtotime($productDataDto->getExpirationDate()))
                ]
            )->andWhere(
                [
                    'nomenclature.gtin' => $productDataDto->getGtin()
                ]
            )->one();

        if ($product !== null) {
            $this->productCollection->attach($product);
        }

        return $product;
    }
}