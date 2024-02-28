<?php

namespace common\components\order\Model\Search;

use api\Exceptions\ValidationErrorException;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use common\components\order\Model\OrderStatus;

class OrderStatusSearch extends Model
{
    public ?int $id = null;
    public ?int $status_category_id = null;
    public ?string $code_1c = null;
    public ?string $name = null;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'string'],
            [['status_category_id', 'code_1c'], 'integer'],
            [['id'], 'safe'],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'                 => 'ID',
            'code_1c'            => 'ID заказа 1С',
            'status_category_id' => 'ID статуса',
            'name'               => 'Наименование статуса',
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
                'id'                 => $this->id,
                'name'               => $this->name,
                'status_category_id' => $this->status_category_id,
                'code_1c' => $this->code_1c,
            ]
        );
        
        return $provider;
    }
    
    public function formName()
    {
        return '';
    }
}
