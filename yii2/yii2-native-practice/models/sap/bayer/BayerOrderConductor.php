<?php


namespace app\modules\itrack\models\sap\bayer;

use app\modules\itrack\models\Generation;
use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\Product;
use app\modules\itrack\models\User;
use yii\base\Model;

class BayerOrderConductor extends Model
{
    private $gtin;
    private $itemsCount;
    private $user;
    private $nomenclature;
    private $product;
    private $generation;
    private $productionDate;
    private $expDate;
    private $series;
    
    public function __construct(SerialNumberRequestMessage $serialNumberRequestMessage)
    {
        $this->user = User::findOne(['login' => 'root']);
        $this->itemsCount = $serialNumberRequestMessage->size;
        $this->gtin = $serialNumberRequestMessage->gtin;
        $this->series = time() . $this->user->id;
        $this->productionDate = new \DateTime('11.12.2019');
        $this->expDate = new \DateTime('11.12.2022');
        $this->nomenclature = null;
        $this->product = null;
        $this->generation = null;
    }
    
    /**
     * Создает заказ в системе itrack
     *
     * @return int
     * @throws \Exception
     */
    public function createOrder()
    : string
    {
        $this->nomenclature = $this->getNomenclature();
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
        $product = Product::find()->andWhere(
            [
                'series' => $this->series,
            ])
            ->one();
        
        if ($product instanceof Product) {
            throw new \Exception('Товарная карта (' . $this->series . ') уже создана');
        }
        
        $product = new Product();
        $product->nomenclature_uid = $this->nomenclature->id;
        $product->created_by = $this->user->id;
        $product->series = $this->series;
        $product->expdate = $this->expDate->format('m Y');
        $product->cdate = $this->productionDate->format('m Y');
        $product->expdate_full = $this->expDate->format('d m Y');
        $product->components = json_encode([[
            'series'  => $this->series,
            'cdate'   => $this->productionDate->format('Y-m-d'),
            'expdate' => $this->expDate->format('Y-m-d'),
        ]]);
        
        if (!$product->save()) {
            throw new \Exception('Ошибка создания товарной карты. ' . $this->series);
        }
        
        return $product;
    }
    
    private function createGeneration()
    : Generation
    {
        $generation = new Generation();
        
        $generation->load(
            [
                'cnt'          => $this->itemsCount,
                'codetype_uid' => \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL,
                'status_uid'   => \app\modules\itrack\models\GenerationStatus::STATUS_WAITING_CHECK_GTIN,
                'created_by'   => $this->user->id,
                'product_uid'  => $this->product->id,
            ],
            ''
        );
        
        if (!$generation->save()) {
            throw new \Exception('Ошибка создания заказа');
        }
        
        return $generation;
    }
}