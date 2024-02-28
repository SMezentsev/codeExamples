<?php

namespace common\components\order\Model\Search;

use api\Exceptions\ValidationErrorException;
use common\components\order\Model\OrderStatus;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class OrderStatusHistorySearch extends Model
{
    public ?int $id = null;
    public ?string $title = null;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_id', 'order_status_id'], 'integer'],
            [['id'], 'safe'],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'        => 'ID',
            'order_id'  => 'ID Заказа',
            'order_status_id' => 'ID статуса',
        ];
    }
    
    public function search(array $params = []): ActiveDataProvider
    {
        $query = OrderStatus::find();
        
        $provider = new ActiveDataProvider(
            [
                'query' => $query,
            ]
        );
        
        if (!empty($params) && (!$this->load($params) || !$this->validate())) {
            throw ValidationErrorException::create($this->errors);
        }
        $query->andFilterWhere(
            [
                'id'              => $this->id,
                'order_id'        => $this->order_id,
                'order_status_id' => $this->order_status_id,
            ]
        );
        
        return $provider;
    }
    
    public function formName()
    {
        return '';
    }
}
