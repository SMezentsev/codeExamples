<?php

namespace common\components\order\Model\Search;

use common\components\order\Model\OrderDeliveryType;
use api\Exceptions\ValidationErrorException;
use yii\data\ActiveDataProvider;
use yii\base\Model;

class OrderDeliveryTypeSearch extends Model
{
    public $id;
    public $name;
    
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'string'],
            [['id'], 'safe'],
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'   => 'ID',
            'name' => 'Наименование',
        ];
    }
    
    public function search(array $params = []): ActiveDataProvider
    {
        $query = OrderDeliveryType::find();
        
        if (isset($param['id'])) {
            $query->andWhere(['id' => (int)$param['id']]);
        }
        
        $provider = new ActiveDataProvider(
            [
                'query' => $query,
            ]
        );
        
        if (!$this->validate()) {
            throw ValidationErrorException::create($this->errors);
        }
        
        return $provider;
    }
    
    public function formName()
    {
        return '';
    }
}
