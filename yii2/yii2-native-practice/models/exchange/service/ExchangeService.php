<?php

namespace app\modules\itrack\models\exchange\service;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\exchange\requests\ExchangeRequest;
use app\modules\itrack\models\Facility;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationStatus;
use app\modules\itrack\models\Nomenclature;
use app\modules\itrack\models\Product;
use app\modules\itrack\models\User;
use Yii;
use yii\base\Model;

/**
 * Сервис импорта кодов, получает запрос на импорт в установленном формате
 * и добавляет коды в бд
 * Class ExchangeService
 * @package app\modules\itrack\models\exchange\service
 */
class ExchangeService extends Model
{
    private $exchangeRequest;
    private $object;
    private $user;
    private $products;
    private $generations;
    private $codes;
    private $pallets;
    private $cases;

    const DEFAULT_USER_LOGIN = 'root';

    public function __construct(ExchangeRequest $exchangeRequest)
    {
        $this->exchangeRequest = $exchangeRequest;
        $this->object = null;
        $this->user = null;
        $this->products = [];
        $this->generations = [
            'sscc' => [],
            'serial' => []
        ];
        $this->codes = [];
    }

    /**
     * Устанавливает объект запроса импорта
     * @param array $exchangeRequest
     * @throws \ErrorException
     * @return void
     */
    public function setExchangeRequest(array $exchangeRequest) : void
    {
        $this->exchangeRequest->attributes = $exchangeRequest;

        if (!$this->exchangeRequest->validate()) {
            throw new \ErrorException('Запрос на обмен не прошел валидацию');
        }
    }

    public function import() : void
    {
        $this->setPackData();
        $this->setObject();
        $this->setUser();
        $this->setProducts();
        $this->formatCodes();
        $this->createGenerations();
    }

    /**
     * Выполняет форматирование кодов для сохранения в генерацию
     * @return void
     */
    private function formatCodes() : void
    {
        $sscc = [];
        $serials = [];

        foreach ($this->pallets as $pallet) {
            $sscc[] = $pallet['sscc'];
            $palletProduct = array_key_exists('product', $pallet) ? $pallet['product']['series'] : null;

            foreach ($pallet['content'] as $case) {
                $sscc[] = [
                    'code' => $case['sscc'],
                    'parent_code' => $pallet['sscc']
                ];

                $caseProduct = array_key_exists('product', $case) ? $case['product']['series'] : null;

                foreach ($case['content'] as $item) {
                    if ($caseProduct !== null) {
                        $product = $caseProduct;
                    } else if ($palletProduct !== null) {
                        $product = $palletProduct;
                    } else {
                        $product = $this->exchangeRequest->product['series'];
                    }

                    $serials[$product][] = [
                        'code' => $item,
                        'parent_code' => $case['sscc']
                    ];
                }
            }
        }

        foreach ($this->cases as $case) {
            $sscc[] = [
                'code' => $case['sscc'],
                'parent_code' => null
            ];

            $caseProduct = array_key_exists('product', $case) ? $case['product']['series'] : null;

            foreach ($case['content'] as $item) {
                if ($caseProduct !== null) {
                    $product = $caseProduct;
                } else {
                    $product = $this->exchangeRequest->product['series'];
                }

                $serials[$product][] = [
                    'code' => $item,
                    'parent_code' => $case['sscc']
                ];
            }
        }

        $this->generations['sscc'] = $sscc;
        $this->generations['serial'] = $serials;
    }

    /**
     * Создает генерации и импортирует коды
     * @return void
     */
    private function createGenerations() : void
    {
        $ssccGeneration = new Generation();
        $ssccGeneration->scenario = "external";

        $ssccGeneration->load(
            [
                'codetype_uid' => CodeType::CODE_TYPE_GROUP,
                'status_uid' => GenerationStatus::STATUS_READY,
                'created_by' => $this->user->id,
                'comment' => 'генерация для внешних кодов',
                'object_uid' => $this->object->id,
                'cnt' => count($this->generations['sscc']),
                'capacity' => '0',
                'prefix' => '',
            ], ''
        );

        $ssccGeneration->save();

        $ssccCodes = [];

        foreach ($this->generations['sscc'] as $code) {
            $ssccCodes[] = is_array($code) ? $code['code'] : $code;
        }

        $ssccGeneration->insertCodes($ssccCodes);
        $ssccGeneration->save();

        foreach ($this->products as $product) {
            $serialGeneration = new Generation();
            $serialGeneration->scenario = "external";

            $serialGeneration->load(
                [
                    'codetype_uid' => CodeType::CODE_TYPE_INDIVIDUAL,
                    'status_uid' => GenerationStatus::STATUS_READY,
                    'created_by' => $this->user->id,
                    'comment' => 'генерация для внешних кодов',
                    'object_uid' => $this->object->id,
                    'cnt' => count($this->generations['serial'][$product->series]),
                    'capacity' => '0',
                    'prefix' => '',
                    'product_uid' => $product->id,
                ], ''
            );

            $serialGeneration->save();

            $serialCodes = [];

            foreach ($this->generations['serial'][$product->series] as $code) {
                $serialCodes[] = $code['code'];
            }

            $serialGeneration->insertCodes($ssccCodes);
            $serialGeneration->save();
        }
    }

    /**
     * Получает и создвает все товарные карты для упаковок
     * @throws \ErrorException
     * @return void
     */
    private function setProducts() : void
    {
        $this->products[$this->exchangeRequest->product['series']] = $this->exchangeRequest->product;

        foreach ($this->pallets as $pallet) {
            if (!$this->checkUniformPack($pallet)) {
                if (array_key_exists('product', $pallet)) {
                    $this->products[$pallet['product']['series']] = $pallet['product'];
                }

                foreach ($this->pallet as $box) {
                    if (array_key_exists('product', $box)) {
                        $this->products[$box['product']['series']] = $box['product'];
                    }
                }
            }
        }

        $productObjects = [];

        foreach ($this->products as $product) {
            $productObject = $this->createProduct($product);
            $productObjects[$productObject->series] = $productObject;
        }

        $this->products = $productObjects;
    }

    /**
     * Создает товарную карту для номенклатуры
     * @param array $productData
     * @return Product
     * @throws \ErrorException
     */
    private function createProduct(array $productData)
    {
        $nomenclature = Nomenclature::findOne(['gtin' => $productData['gtin']]);

        if ($nomenclature === null) {
            throw new \ErrorException('Не удалось найти номенклатуру для gtin: ' . $productData['gtin']);
        }

        $product = Product::findOne([
            'series' => $productData['series'],
            'nomenclature_uid' => $nomenclature->id
        ]);

        if ($product === null) {
            $productionDate = new \DateTime($productData['cdate']);
            $expDate = new \DateTime($productData['expDate']);

            $product = new Product();
            $product->nomenclature_uid = $nomenclature->id;
            $product->created_by = $this->user->id;
            $product->series = $productData['series'];
            $product->expdate = $expDate->format('m Y');
            $product->cdate = $productionDate->format('m Y');
            $product->expdate_full = $expDate->format('d m Y');
            $product->components = json_encode([[
                'series' => $productData['series'],
                'cdate' => $productionDate->format('Y-m-d'),
                'expdate' => $expDate->format('Y-m-d')
            ]]);

            if (!$product->save()) {
                throw new \ErrorException('Не удалось создать товарную карту серии: ' . $productData['series']);
            }
        }

        return $product;
    }

    /**
     * Выполняет проверку однородности упаковки
     * @param array $pack
     * @return bool
     */
    private function checkUniformPack(array $pack) : bool
    {
        $isUniformPack = true;

        foreach ($pack['content'] as $item) {
            if (array_key_exists('product', $item)) {
                $isUniformPack = false;
                break;
            }
        }

        return $isUniformPack;
    }

    /**
     * Устанавливает данные упакованных палет и коробов коробов
     * @return void
     */
    private function setPackData() : void
    {
        $this->pallets = $this->exchangeRequest->aggregatedData['pallets'];
        $this->cases = $this->exchangeRequest->aggregatedData['cases'];
    }

    /**
     * Устанавливает объект, на котором будет происходить импорт
     * @throws \ErrorException
     * @return void
     */
    private function setObject() : void
    {
        $this->object = Facility::findOne($this->exchangeRequest->recipientObject);

        if ($this->object === null) {
            throw new \ErrorException('Объект не найден.');
        }
    }

    /**
     * Устанавливает пользователя, от лица которого будет происходить импорт
     * @throws \ErrorException
     * @return void
     */
    private function setUser() : void
    {
        if ($this->exchangeRequest->user !== null) {
            $userLogin = $this->exchangeRequest->user;
        } else {
            $userLogin = self::DEFAULT_USER_LOGIN;
        }

        $this->user = User::findOne(['login' => $userLogin]);

        if ($this->user === null) {
            throw new \ErrorException('Пользователь не найден.');
        }

        \Yii::$app->user->setIdentity($this->user);
    }
}