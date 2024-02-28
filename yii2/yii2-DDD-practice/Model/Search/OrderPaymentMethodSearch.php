<?php

namespace common\components\order\Model\Search;

use api\Exceptions\ValidationErrorException;
use common\components\order\Model\OrderPaymentMethod;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class OrderPaymentMethodSearch extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    
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
            'id'    => 'ID',
            'name' => 'Наименование способа оплаты',
        ];
    }
    
    public function search(array $params = []): ActiveDataProvider
    {
        $query = OrderPaymentMethod::find();
        
        $provider = new ActiveDataProvider(
            [
                'query' => $query,
            ]
        );
        
        if (!empty($params) && (!$this->load($params) || !$this->validate())) {
            throw ValidationErrorException::create($this->errors);
        }
        
        echo $this->name;
        $query->andFilterWhere(
            [
                'id'    => $this->id,
                'name' => $this->name,
            ]
        );
        
        return $provider;
    }
    
    public function formName()
    {
        return '';
    }
}
