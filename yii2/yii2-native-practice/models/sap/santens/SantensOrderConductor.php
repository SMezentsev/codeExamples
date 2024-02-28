<?php


namespace app\modules\itrack\models\sap\santens;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\Equip;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationStatus;
use app\modules\itrack\models\Manufacturer;
use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\Product;
use app\modules\itrack\models\User;
use yii\base\Model;

class SantensOrderConductor extends Model
{
    private $data;
    private $gtin;
    private $itemsCount;
    private $user;
    private $nomenclature;
    private $product;
    private $generation;
    private $productionDate;
    private $manufacturer;
    private $expDate;
    private $series;
    private $equip;
    private $boxCodes;
    private $palletCodes;
    private $id;

    public function __construct()
    {
        $this->user = User::findOne(['login' => 'root']);
        \Yii::$app->user->setIdentity($this->user);
        $this->nomenclature = null;
        $this->product = null;
        $this->generation = null;
        $this->data = null;
    }
    
    public function parseIncome(string $data)
    {
        $data = str_replace('\\', '/', $data);

        $this->data = json_decode($data, true);
        
        if ($this->data === null) {
            throw new \ErrorException('Не удалось получить данные из файла.');
        }

        $this->id = $this->data['id'];
        $this->gtin = $this->data['gtin'];
        $this->itemsCount = $this->data['qty'];
        $this->series = $this->data['batch'];
        $this->setFilesData();
        $this->productionDate = new \DateTime($this->data['mandate']);
        $this->expDate = new \DateTime($this->data['expdate']);
        
        return $this->createOrder();
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
        try {
            $this->equip = Equip::find()->where(['login' => $this->data['line']])->one();
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось получить оборудование для выбранной линии.');
        }
        
        $this->manufacturer = $this->findFirstManufacturer();
        $this->nomenclature = $this->getNomenclature();
        $this->product = $this->createProduct();
        $this->generation = $this->createGeneration();
        
        if ($this->palletCodes !== null && $this->boxCodes !== null) {
            $this->importGroupCodes();
        }
        
        return $this->generation->id;
    }
    
    /**
     * Получает данные о сторонних групповых кодах
     *
     * @return boolean
     * @throws \ErrorException
     */
    private function setFilesData()
    : bool
    {
        if (!isset($this->data['aggrl2file']) || !isset($this->data['aggrl3file'])) {
            $this->boxCodes = null;
            $this->palletCodes = null;
            
            return true;
        }
        
        try {
            $this->boxCodes = $this->getFileDataByRemotePath($this->data['aggrl2file']);
            $this->palletCodes = $this->getFileDataByRemotePath($this->data['aggrl3file']);
        } catch (\Exception $e) {
            throw new \ErrorException(
                'Не удалось получить данные из файлов кодов, возможно указан неправильный путь или '
                . 'возникли проблемы с синхронизацией удаленной папки.'
            );
        }
        
        return true;
    }
    
    /**
     * Получает данные файла по пути из расшаренной папки и возвращает массив кодов
     *
     * @param string $path
     *
     * @return array
     */
    private function getFileDataByRemotePath(string $fileName): array
    {
        $fileData = file_get_contents(\Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR
            . \Yii::$app->params['santensFtpConfig']['codesDir'] . DIRECTORY_SEPARATOR . $fileName);

        $fileArray = explode(PHP_EOL, trim($fileData));

        foreach ($fileArray as $key=>$value) {
            $fileArray[$key] = trim($value);
        }

        return $fileArray;
    }


    /**
     * Создает заказ в системе itrack
     * @return int
     * @throws \Exception
     */
    public function createOrder(): string
    {
        $this->equip = Equip::find()->where(['login' => $this->data['line']])->one();

        if ($this->equip === null) {
            throw new \ErrorException('Не удалось получить оборудование для выбранной линии.');
        }

        $this->manufacturer = $this->findFirstManufacturer();
        $this->nomenclature = $this->getNomenclature();

        $this->product = $this->createProduct();
        $this->generation = $this->createGeneration();

        if ($this->palletCodes !== null && $this->boxCodes !== null) {
            $this->savePalletCodes();
            $this->importGroupCodes();
        }

        return $this->generation->id;
    }

    /**
     * Возвращает первого производителя в справочнике
     *
     * @return Manufacturer
     * @throws \ErrorException
     */
    private function findFirstManufacturer()
    : Manufacturer
    {
        $manufacturer = Manufacturer::find()->one();
        
        if ($manufacturer === null) {
            throw new \ErrorException('Не удалось получить производителя.');
        }
        
        return $manufacturer;
    }
    
    /**
     * Ищет номенклатуру по установленному в SerialNumberRequestMessage GTIN
     *
     * @throws \ErrorException
     */
    private function getNomenclature(): Nomenclature
    {
        $nomenclature = Nomenclature::findOne(['gtin' => $this->gtin]);
        $isNeedSave = false;

        if ($nomenclature === null) {
            $isNeedSave = true;

            $nomenclature = new Nomenclature();
            $nomenclature->gtin = $this->gtin;
            $nomenclature->object_uid = $this->equip->object_uid;
            $nomenclature->code1c = $this->id;
            $nomenclature->cnt = $this->data['itemsperbox'];
            $nomenclature->cnt_on_pallet = $this->data['boxesperpallet'];
            $nomenclature->name = $this->data['prodname'];
            $nomenclature->ean13 = null;
            $nomenclature->manufacturer_uid = $this->manufacturer->id;
        } elseif (
            $nomenclature->object_uid != $this->equip->object_uid ||
            $nomenclature->name != $this->data['prodname'] ||
            $nomenclature->cnt != $this->data['itemsperbox'] ||
            $nomenclature->cnt_on_pallet != $this->data['boxesperpallet']
        ) {
            $isNeedSave = true;

            $nomenclature->object_uid = $this->equip->object_uid;
            $nomenclature->name = $this->data['prodname'];
            $nomenclature->cnt = $this->data['itemsperbox'];
            $nomenclature->cnt_on_pallet = $this->data['boxesperpallet'];
        }

        if ($isNeedSave) {
            if (!$nomenclature->save()) {
                throw new \ErrorException(\Yii::t('app', 'Не удалось сохранить номенклатуру.'));
            }
        }
        
        if ($nomenclature === null) {
            throw new \ErrorException(\Yii::t('app', 'Номенклатура по переданному GTIN не найдена.'));
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
                'series'           => $this->series,
                'nomenclature_uid' => $this->nomenclature->id,
            ])
            ->one();
        
        if ($product instanceof Product) {
            return $product;
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
    
    /**
     * Создает пустой заказ в который будет производиться импорт
     *
     * @return Generation
     * @throws \Exception
     */
    private function createGeneration()
    : Generation
    {
        $generation = new Generation();
        
        if ($this->palletCodes !== null && $this->boxCodes !== null) {
            $generation->scenario = "external";
            $generation->load(
                [
                    'codetype_uid' => CodeType::CODE_TYPE_GROUP,
                    'status_uid'   => GenerationStatus::STATUS_READY,
                    'created_by'   => $this->user->id,
                    'comment'      => 'генерация для внешних кодов',
                    'object_uid'   => $this->nomenclature->object_uid,
                    'cnt'          => 0,
                    'capacity'     => '0',
                    'prefix'       => '',
                    'product_uid'  => $this->product->id,
                ], ''
            );
            
            if (!$generation->save(false)) {
                throw new \Exception('Ошибка создания заказа');
            }
            
            $generation->refresh();
        } else {
            $generation->load(
                [
                    'cnt'          => $this->itemsCount,
                    'codetype_uid' => \app\modules\itrack\models\CodeType::CODE_TYPE_GROUP,
                    'status_uid'   => \app\modules\itrack\models\GenerationStatus::STATUS_CREATED,
                    'product_uid'  => $this->product->id,
                    'created_by'   => $this->user->id,
                    'object_uid'   => $this->nomenclature->object_uid,
                ]
            );
            
            if (!$generation->save()) {
                throw new \Exception('Ошибка создания заказа');
            }
        }
        
        return $generation;
    }
    
    /**
     * Сохраняет на диск коды палет из заказа
     * @throws \ErrorException
     */
    private function savePalletCodes(): void
    {
        $runtime = \Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR;
        $palletCodesDir = \Yii::$app->params['santensFtpConfig']['palletCodesDir'];
        $fileName = 'codes-' . $this->id . '.csv';
        $filePath = $runtime . $palletCodesDir . DIRECTORY_SEPARATOR . $fileName;
        if (!is_dir($runtime . $palletCodesDir)) {
            throw new \ErrorException('Не найдена директория для сохранения sscc для палет.');
        }

        try {
            $fp = fopen($filePath, 'w+');

            foreach ($this->palletCodes as $code) {
                fputcsv($fp, [trim($code)]);
            }

            fclose($fp);
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось записать sscc палет в файл: ' . $filePath);
        }
    }

    /**
     * Выполняет иморт сторонних кодов
     *
     * @throws \ErrorException
     * @throws \yii\db\Exception
     */
    private function importGroupCodes()
    : void
    {
        $allCodes = $this->boxCodes;
        $transaction = \Yii::$app->db->beginTransaction();

        try {
            foreach ($allCodes as $k => $code) {
                $code = trim($code);
                if (!empty($code)) {
                    $allCodes[$k] = $code;
                } else {
                    unset($allCodes[$k]);
                }
            }

            $this->generation->object_uid = $this->nomenclature->object_uid;
            $this->generation->product_uid = $this->product->id;
            $this->generation->insertCodes($allCodes);
            $this->generation->cnt = count($allCodes);
            $this->generation->third_party_order_id = $this->id;
            $this->generation->equip_uid = $this->equip->id;
            $this->generation->save();

            $palletCodesGeneration = new Generation();
            $palletCodesGeneration->scenario = "external";

            $palletCodesGeneration->load(
                [
                    'codetype_uid' => CodeType::CODE_TYPE_GROUP,
                    'status_uid' => GenerationStatus::STATUS_READY,
                    'created_by' => $this->user->id,
                    'comment' => 'генерация для внешних кодов',
                    'object_uid' => $this->nomenclature->object_uid,
                    'cnt' => count($this->palletCodes),
                    'capacity' => '0',
                    'prefix' => '',
                    'product_uid' => $this->product->id,
                ], ''
            );

            $palletCodesGeneration->save();
            $palletCodesGeneration->insertCodes($this->palletCodes);
            $palletCodesGeneration->third_party_order_id = $this->id;
            $palletCodesGeneration->is_closed = true;
            $palletCodesGeneration->equip_uid = $this->equip->id;
            $palletCodesGeneration->save();

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new \ErrorException('Не удалось импортировать сторонние коды в базу данных.');
        }
    }
}