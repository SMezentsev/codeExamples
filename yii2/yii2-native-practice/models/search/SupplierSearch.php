<?php

namespace app\modules\itrack\models\search;

use app\modules\itrack\models\Suppliers;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * SupplierSearch represents the model behind the search form of `app\modules\mailer\models\Suppliers`.
 */
class SupplierSearch extends Suppliers
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['inn', 'subject_id'], 'string'],
            [['name'], 'safe'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }
    
    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Suppliers::find();
        
        // add conditions that should always apply here
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        
        $this->load($params, '');
        
        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }
        
        // grid filtering conditions
        $query->andFilterWhere([
            'id'         => $this->id,
            'inn'        => $this->inn,
            'subject_id' => $this->subject_id,
        ]);
        
        $query->andFilterWhere(['ilike', 'name', $this->name]);
        
        return $dataProvider;
    }
}
