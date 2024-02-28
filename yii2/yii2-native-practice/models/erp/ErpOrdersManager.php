<?php

namespace app\modules\itrack\models\erp;

use app\modules\itrack\models\Generation;
use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\Product;
use yii\base\Model;

/**
 * Класс для интеграции с erp системами
 * Class ErpOrdersManager
 *
 * @package app\modules\itrack\models\erp
 */
class ErpOrdersManager extends Model
{
    private $orderNumber;
    private $orderDate;
    private $nomenclature;
    private $gtin;
    private $serialProductNumber;
    private $productionDate;
    private $expDate;
    private $productionLine;
    private $itemsCount;
    private $user;
    private $product;
    private $generation;
    private $dateFormat;
    
    public function __construct($config = [])
    {
        $this->user = \Yii::$app->user->getIdentity();
        $this->product = null;
        $this->generation = null;
    }
    
    public function setData(array $data)
    {
        $this->orderNumber = $data['order_number'];
        $this->orderDate = $data['order_date'];
        $this->gtin = $data['gtin'];
        $this->nomenclature = $this->getNomenclature();
        $this->serialProductNumber = $data['serial_product_number'];
        $this->productionDate = new \DateTime($data['production_date']);
        $this->expDate = new \DateTime($data['exp_date']);
        $this->productionLine = $data['production_line'];
        $this->itemsCount = $data['items_count'];
        $this->dateFormat = $data['date_format'];
    }
    
    /**
     * Создает заказ в системе itrack
     *
     * @return int
     * @throws \Exception
     */
    public function create()
    : string
    {
        $this->product = $this->createProduct();
        $this->generation = $this->createGeneration();
        
        return $this->generation->id;
    }
    
    /**
     * Ищет номенклатуру по установленному в SerialNumberRequestMessage GTIN
     *
     * @throws \ErrorException
     */
    private function getNomenclature()
    : Nomenclature
    {
        $nomenclature = Nomenclature::findOne(['gtin' => $this->gtin]);
        
        if ($nomenclature === null) {
            throw new \ErrorException('Номенклатура по переданному GTIN не найдена.');
        }
        
        return $nomenclature;
    }
    
    /**
     * Создает карточку партии
     *
     * @param array $params
     *
     * @return Product
     * @throws \Exception
     */
    private function createProduct()
    : Product
    {
        $product = \app\modules\itrack\models\Product::find()->andWhere(
            [
                'series'           => $this->serialProductNumber,
                'nomenclature_uid' => $this->nomenclature->id,
            ])
            ->one();

        $updateProduct = false;

        if ($product !== null) {
            if ($product->expdate != $this->expDate->format('m Y') ||
                $product->cdate != $this->productionDate->format('m Y') ||
                $product->nomenclature_uid != $this->nomenclature->id
            ) {
                $updateProduct = true;
            }
        }

        if ($product === null || $updateProduct) {
            if ($updateProduct === false) {
                $product = new \app\modules\itrack\models\Product();
            }

            $product->nomenclature_uid = $this->nomenclature->id;
            $product->created_by = $this->user->id;
            $product->series = $this->serialProductNumber;
            $product->expdate = $this->expDate->format($this->dateFormat);
            $product->cdate = $this->productionDate->format($this->dateFormat);
            $product->expdate_full = $this->expDate->format('d m Y');
            $product->components = json_encode([[
                'series'  => $this->serialProductNumber,
                'cdate'   => $this->productionDate->format('Y-m-d'),
                'expdate' => $this->expDate->format('Y-m-d'),
            ]]);
            
            if (!$product->save()) {
                throw new \Exception('Ошибка создания товарной карты. ' . $this->serialProductNumber);
            }
        }
        
        return $product;
    }
    
    private function createGeneration()
    : Generation
    {
        $generation = new \app\modules\itrack\models\Generation();
        
        $generation->load(
            [
                'cnt'            => $this->itemsCount,
                'codetype_uid'   => \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL,
                'status_uid'     => \app\modules\itrack\models\GenerationStatus::STATUS_CREATED,
                'created_by'     => $this->user->id,
                'product_uid'    => $this->product->id,
                'packing_status' => 'В работе',
                'equip_uid'      => $this->productionLine,
            ],
            ''
        );
        
        if (!$generation->save()) {
            throw new \Exception('Ошибка создания заказа');
        }
        
        return $generation;
    }
}