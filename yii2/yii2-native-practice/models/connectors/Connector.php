<?php

namespace app\modules\itrack\models\connectors;

use app\behaviors\JsonBehavior;
use app\behaviors\PgArrayFieldBehavior;
use app\modules\itrack\components\AuditBehavior;
use app\modules\itrack\components\boxy\Helper;
use app\modules\itrack\models\AuditOperation;
use app\modules\itrack\models\Facility;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * This is the model class for table "connector".
 *
 * @property int    $id
 * @property bool   $active
 * @property string $typeof
 * @property string $data
 * @property int    $object_ids
 * @property string $name
 * @property string $description
 */
class Connector extends \yii\db\ActiveRecord
{
    const TYPE_AX = 1;
    
    static $auditOperation = AuditOperation::OP_CONNECTORS;
    /**
     * @var array
     */
    public static $types = [
        self::TYPE_AX => 'Axapta',
    ];
    /**
     * @var array
     */
    public static $connectorTemplates = [
        'Axapta' => [
            ["name" => "url", "type" => "string"],
            ["name" => "user", "type" => "string"],
            ["name" => "password", "type" => "string"],
        ],
    ];
    public $user;
    public $url;
    public $password;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'connector';
    }
    
    /**
     * @param array $type
     * @param int   $limit
     * @param null  $object_uid
     *
     * @return \app\modules\itrack\components\boxy\ActiveRecord[]|Connector|Connector[]|Facility[]|\app\modules\itrack\models\Invoice[]|\app\modules\itrack\models\OcsConnector[]|\app\modules\itrack\models\Product[]|array|\yii\db\ActiveRecord|\yii\db\ActiveRecord[]|null
     */
    static function getActive($type = [], $limit = 1, $object_uid = null)
    {
        //проверка только РФ сервере
        if (SERVER_RULE != SERVER_RULE_RF) {
            return null;
        }
        
        $query = self::find()->andWhere(['active' => true])->limit($limit);
        
        if (!empty($type)) {
            $query->andWhere(['in', 'typeof', $type]);
        }
        
        $obj = \Yii::$app->user->identity->object_uid ?? $object_uid;
        
        if ($obj) {
            $query->andWhere(new Expression('object_ids && ARRAY[:obj]::bigint[]', ['obj' => $obj]));
        }
        
        return $limit === 1 ? $query->one() : $query->all();
    }
    
    /**
     * @return array
     */
    public function behaviors()
    {
        return [
            ['class' => AuditBehavior::class],
            [
                'class'      => PgArrayFieldBehavior::class,
                'attributes' => ['object_ids'],
            ],
            [
                'class'      => JsonBehavior::class,
                'attributes' => ['data'],
            ],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['active'], 'boolean'],
            [['typeof', 'name', 'description', 'url', 'password', 'user'], 'string'],
            [['data'], 'validateDataTemplate', 'skipOnEmpty' => false, 'skipOnError' => false],
            ['typeof', 'in', 'range' => array_values(self::$types)],
            [['object_ids'], 'each', 'rule' => ['integer']],
            [
                ['object_ids'],
                'each',
                'rule' => [
                    'exist',
                    'skipOnEmpty'     => true,
                    'skipOnError'     => true,
                    'targetClass'     => Facility::class,
                    'targetAttribute' => ['object_ids' => 'id'],
                ],
            ],
            [['name', 'typeof'], 'required'],
        ];
    }
    
    /**
     * @param $attribute
     * @param $params
     */
    public function validateDataTemplate($attribute, $params)
    {
        foreach ($this->getConnectorTemplate() as $field) {
            $name = $field["name"];
            
            if (empty($this->{$name})) {
                $this->addError($name, sprintf("Не задано поле: %s", $name));
            }
        }
        
        $this->{$attribute} = $this->mapDataToTemplate();
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'active'      => 'Active',
            'typeof'      => 'Typeof',
            'data'        => 'Data',
            'object_ids'  => 'Object ids',
            'name'        => 'Name',
            'description' => 'Description',
        ];
    }
    
    /**
     * @param $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);
        
        Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
    
    
    /**
     * @return array|mixed
     */
    public function getConnectorTemplate()
    {
        return self::$connectorTemplates[$this->typeof] ?? [];
    }
    
    /**
     * @return array
     */
    public function getData()
    {
        return $this->mapDataToTemplate();
    }
    
    public function extraFields()
    {
        return ['objects', 'connectorTemplate'];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasMany(Facility::class, ['id' => 'object_ids'])->where('1=1');
    }
    
    /**
     * @return array
     */
    protected function mapDataToTemplate()
    {
        $mapped = [];
        
        foreach ($this->getConnectorTemplate() as $template) {
            $field = $template["name"];
            
            if (isset($this->{$field})) {
                $mapped[$field] = $this->{$field};
            }
        }
        
        return $mapped;
    }
}
