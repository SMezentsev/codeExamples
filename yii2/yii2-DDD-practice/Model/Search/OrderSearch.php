<?php

namespace common\components\order\Model\Search;

use common\components\order\Model\GoodCategory;
use common\components\webService\response\GetListOrders;
use common\models\Autopart;
use common\models\DiskBrand;
use common\models\DiskGood;
use common\models\DiskModel;
use common\models\TyreBrand;
use common\models\TyreGood;
use common\models\TyreModel;
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\WorkDay;
use Yii;
use api\Exceptions\ValidationErrorException;
use common\components\order\Model\Invoice;
use common\components\order\Model\Order;
use common\components\order\Model\OrderInvoice;
use common\Helper\DateTimeConstant;
use phpDocumentor\Reflection\DocBlock\Tags\Formatter\AlignFormatter;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\data\ArrayDataProvider;
use yii\data\Sort;
use common\components\order\Model\OrderStatus;
use common\components\order\Model\OrderStatusHistory;
use yii\helpers\ArrayHelper;
use common\components\order\Model\OrderGood;

class OrderSearch extends Model
{
    public ?int $id = null;
    public ?int $price = null;
    public ?string $code_1c = null;
    public ?int $opt_user_id = null;
    public ?int $order_delivery_type_id = null;
    public ?int $order_payment_method_id = null;
    public ?int $payment_status_id = null;
    public ?int $delivery_status_id = null;
    public ?int $shipping_date = null;
    public ?string $reserve_date = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $created_at = null;
    public ?array $filter = null;
    public ?string $comment = null;
    public array $pagination = [];
    public ?string $expand = null;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['payment_status_id', 'delivery_status_id', 'order_delivery_type_id', 'opt_user_id', 'order_payment_method_id', 'price'], 'integer'],
            [['created_at', 'date_from', 'date_to', 'code_1c'], 'string'],
            [['id', 'expand', 'shipping_date', 'reserve_date', 'date_from', 'date_from', 'filter', 'pagination', 'comment'], 'safe'],
            [
                'filter',
                'filter',
                'filter' => function ($filter) {
                    if (!empty($filter['shop_id'])) {
                        $filter['shop_id'] = array_map('intval', explode(',', $filter['shop_id']));
                    }
                    if (!empty($filter['user_address_id'])) {
                        $ids                       = [];
                        $filter['user_address_id'] = array_map('intval', explode(',', $filter['user_address_id']));
                        foreach ($filter['user_address_id'] as $key => $item) {
                            if ($item) {
                                $ids[] = $item;
                            }
                        }
                        $filter['user_address_id'] = $ids;
                    }
                    
                    return $filter;
                },
            
            ],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'                      => 'ID',
            'order_delivery_type_id'  => 'ID типа получения заказа',
            'opt_user_id'             => 'ID пользователя',
            'order_payment_method_id' => 'ID метода платежа',
            'price'                   => 'Стоимость заказа',
            'created_at'              => 'Дата создания заказа',
            'payment_status_id'       => 'Статус оплаты',
            'delivery_status_id'      => 'Статус доставки',
        ];
    }
    
    //TODO переделать весь метод
    public function searchByOrderNomenclature(array $params = []): ArrayDataProvider
    {
        $user = Yii::$app->user->getIdentity();
        
        //выбираем уникальные позиции товаров во всех заказах
        $goodsGroup = OrderGood::find()
                               ->select(
                                   [
                                       'DISTINCT(order_good.good_id) as good_id',
                                       'order_good.*',
                                   ]
                               )
                               ->leftJoin(Order::tableName(), 'order_good.order_id = order.id')
                               ->where(['order.opt_user_id' => $user->id])
                               ->groupBy(['order_good.good_id'])
                               ->all();
        
        $orderGoods = [];
        foreach ($goodsGroup as $goodGroup) {
            //находим все заказы с определенным товаром
            $orders = Order::find()
                           ->leftJoin(OrderGood::tableName(), 'order_good.order_id = order.id')
                           ->where(['order_good.good_id' => $goodGroup->good_id])
                           ->andWhere(['order.opt_user_id' => $user->id])
                           ->asArray()
                           ->all();
            
            $ordersWithGood = [];
            //добавляем во все заказы только один товар $order['good'] по которому идет группировка
            foreach ($orders as $order) {
                $order['good'] = ArrayHelper::toArray($goodGroup);
                
                $order['good']['price']      = $order['good']['price'] / 100;
                $order['good']['totalPrice'] = $order['good']['price'] * $order['good']['quantity'];
                
                $ordersWithGood[] = array_merge(
                    ArrayHelper::toArray(Order::find()->where(['id' => $order['id']])->one()),
                    [
                        'good' => $order['good'],
                    ]
                );
            }
            
            $good = ArrayHelper::toArray($goodGroup->getGood(), true);
            
            $title = ($good['brand']['title'] ?? '') . ' ' . ($good['model'] ?? '') . ' ' . ($good['title'] ?? '');
            
            $orderGoods[] = [
                'title'  => $title,
                'sku'    => $good['sku'] ?? '',
                'orders' => $ordersWithGood,
            ];
        };
        
        return new ArrayDataProvider(
            [
                'allModels'  => $orderGoods,
                'pagination' => $this->pagination,
                'sort'       => [
                    'defaultOrder' => [
                        'title' => SORT_DESC,
                    ],
                    'attributes'   => [
                        'title',
                    ],
                ],
            ]
        );
    }
    
    public function searchByOrderDate(): ArrayDataProvider
    {
        $user   = Yii::$app->user->getIdentity();
        $orders = Order::find()
                       ->where(['order.opt_user_id' => $user->id])
                       ->groupBy('date(created_at)')
                       ->orderBy('created_at DESC')
                       ->all();
        
        $orderGoods = [];
        foreach ($orders as $key => $order) {
            $date  = date('Y-m-d', strtotime($order->created_at));
            $goods = OrderGood::find()
                              ->where(
                                  [
                                      'between',
                                      'created_at',
                                      $date . ' 00:00:00',
                                      $date . ' 23:23:59',
                                  ]
                              )
                              ->andWhere(['order_id' => $order->id])
                              ->all();
            
            if ($goods) {
                $orderGoods[$key] = [
                    'created_at' => $date,
                    'totalCount' => count($goods),
                    'price'      => array_sum(array_column($goods, 'price')) / 100,
                    'goods'      => [],
                ];
                
                foreach ($goods as $good) {
                    $good->price = $good->price / 100;
                    
                    $orderGoods[$key]['goods'][] = array_merge(
                        ArrayHelper::toArray($good->getGood()),
                        ArrayHelper::toArray($good),
                        [
                            'totalPrice' => ($good->price * $good->quantity),
                            'order'      => $order,
                        ]
                    );
                };
            }
        }
        
        return new ArrayDataProvider(
            [
                'allModels'  => $orderGoods,
                'pagination' => [
                    'pageSize' => 20,
                ],
                'sort'       => [
                    'defaultOrder' => [
                        'created_at' => SORT_DESC,
                    ],
                    'attributes'   => [
                        'price',
                        'totalPriceRub',
                        'created_at',
                        'totalCount',
                    ],
                ],
            
            ]
        );
    }
    
    public function search($params): ActiveDataProvider
    {
        if (!empty($params) && (!$this->load($params) || !$this->validate())) {
            throw ValidationErrorException::create($this->errors);
        }
        
        $query = Order::find();
        $user  = Yii::$app->user->getIdentity();
        $query->andWhere(['order.opt_user_id' => $user->id]);
        $query->andFilterWhere(
            [
                'id'                      => $this->id,
                'order_delivery_type_id'  => $this->order_delivery_type_id,
                'opt_user_id'             => $this->opt_user_id,
                'order_payment_method_id' => $this->order_payment_method_id,
                'payment_status_id'       => $this->payment_status_id,
                'delivery_status_id'      => $this->delivery_status_id,
                'price'                   => $this->price,
            ]
        );
        
        $search = $this->filter['search'] ?? null;
        if ($search) {
            $query->leftJoin(OrderInvoice::tableName(), 'order_invoice.order_id = order.id')
                  ->leftJoin(Invoice::tableName(), 'invoice.id = order_invoice.invoice_id')
                //джоин промежуточной таблицы товаров заказа
                  ->leftJoin(OrderGood::tableName(), 'order_good.order_id = order.id')
                //поиск по автозапчастей, таблица autoparts
                  ->leftJoin(Autopart::tableName(), 'autoparts.autopart_id = order_good.good_id')
                //поиск по шинам, таблица assort
                  ->leftJoin(TyreGood::tableName(), 'assort.idx = order_good.good_id')
                //поиск по дискам, таблица disks
                  ->leftJoin(DiskGood::tableName(), 'disks.disk_id = order_good.good_id')
                //поиск по дискам, таблица d_model
                  ->leftJoin(DiskModel::tableName(), 'd_model.id = disks.model_id')
                //таблица d_producer
                  ->leftJoin(DiskBrand::tableName(), 'd_producer.d_producer_id = d_model.brand_id')
                //таблица producer
                  ->leftJoin(TyreBrand::tableName(), 'producer.code = assort.prod_code')
                //таблица model
                  ->leftJoin(GoodCategory::tableName(), 'good_category.id = order_good.good_category_id')
                //таблица model
                  ->leftJoin(TyreModel::tableName(), 'model.code = assort.p_t AND model.prod_code = producer.code')
                  ->orWhere(['like', 'order.code_1c', ':search', [':search' => $search]])
                  ->orWhere(['like', 'order.price', ':search', [':search' => $search]])
                  ->orWhere(['like', 'order.comment', ':search', [':search' => $search]])
                
                //поиск по наименованию производителя дисков
                  ->orWhere(['like', 'CONCAT(good_category.name, \' \', producer.name, \' \', model.name)', ':search', [':search' => $search]])
                //поиск по наименованию производителя дисков
                  ->orWhere(['like', 'CONCAT(good_category.name, \' \', d_producer.name, \' \', d_model.title)', ':search', [':search' => $search]])
                //поиск по наименованию автозапчастей
                  ->orWhere(['like', 'description', ':search', [':search' => $search]]);
        }
        
        $dateFrom = $this->filter['date_from'] ?? null;
        if ($dateFrom) {
            $dateFrom = \DateTime::createFromFormat(DateTimeConstant::AR_DATE_FORMAT, date("c", strtotime($dateFrom)));
            $query->andWhere('order.created_at >= :dateFrom', ['dateFrom' => $dateFrom->setTime(0, 0, 0)->format(DateTimeConstant::DATE_FORMAT)]);
        }
        
        $dateTo = $this->filter['date_to'] ?? null;
        if ($dateTo) {
            $dateTo = \DateTime::createFromFormat(DateTimeConstant::AR_DATE_FORMAT, date("c", strtotime($dateTo)));
            $query->andWhere('order.created_at <= :dateTo', ['dateTo' => $dateTo->setTime(23, 59, 59)->format(DateTimeConstant::DATE_FORMAT)]);
        }
        
        $status = $this->filter['status'] ?? null;
        if ($status) {
            $query->andWhere(['order.payment_status_id' => $status])
                  ->orWhere(['order.delivery_status_id' => $status]);
        }
        
        $shopIds = $this->filter['shop_id'] ?? null;
        if ($shopIds) {
            $query->leftJoin('order_delivery_pickup odp', 'odp.order_id = order.id')
                  ->andWhere(['in', 'odp.shop_id', $shopIds]);
        }
        
        $userAddressIds = $this->filter['user_address_id'] ?? null;
        if ($userAddressIds) {
            $query->leftJoin('order_delivery_courier odc', 'odc.order_id = order.id')
                  ->andWhere(['in', 'odc.user_address_id', $userAddressIds]);
        }
        
        return new ActiveDataProvider(
            [
                'query'      => $query,
                'pagination' => [
                    'pageSize' => 20,
                ],
                'sort'       => [
                    'defaultOrder' => [
                        'created_at' => SORT_DESC,
                    ],
                    'attributes'   => [
                        'price',
                        'created_at',
                        'reserve_date',
                        'shipping_date',
                        'code_1c',
                    ],
                ],
            ]
        );
    }
    
    public function formName()
    {
        return '';
    }
}
