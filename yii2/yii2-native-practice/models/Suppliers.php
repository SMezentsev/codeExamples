<?php

namespace app\modules\itrack\models;

use app\modules\itrack\components\AuditBehavior;

/**
 * This is the model class for table "suppliers".
 * справочник поставщики
 *
 * @property int    $id
 * @property string $name
 * @property string $regnum
 * @property string $address
 * @property int    $parent_uid
 * @property string $subject_id
 * @property string $inn
 */
class Suppliers extends \yii\db\ActiveRecord
{
    static $auditOperation = AuditOperation::OP_MANUF;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'suppliers';
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    static function find()
    {
        $q = parent::find();
        $q->select(new \yii\db\Expression("*, exists(select * from suppliers as a where a.parent_uid = suppliers.id) as grp"));
        
        return $q;
    }
    
    /**
     * @return array
     */
    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'inn', 'subject_id'], 'required'],
            [['name', 'regnum', 'address', 'subject_id', 'inn'], 'string'],
            [['parent_uid'], 'default', 'value' => null],
            [['parent_uid'], 'integer'],
        ];
    }
    
    /**
     * @return false|int
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function delete()
    {
        self::updateAll(['parent_uid' => null], ['parent_uid' => $this->id]);
        
        return parent::delete();
    }
    
    public function init()
    {
        parent::init();
        
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            if ($event->sender->id == $event->sender->parent_uid && !empty($event->sender->parent_uid)) {
                $this->addError('parent_uid', 'Ссылка на родительский элемент не может ссылаться на самого себя');
                
                return false;
            }
        });
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'name'       => 'Name',
            'regnum'     => 'Regnum',
            'address'    => 'Address',
            'parent_uid' => 'Parent Uid',
            'subject_id' => 'Subject ID',
            'inn'        => 'INN',
        ];
    }
    
    /**
     * @return array|false
     */
    public function fields()
    {
        return array_merge(parent::fields(), ['grp', 'uid' => 'id']);
    }
    
    /**
     * @return array
     */
    public function attributes()
    {
        return array_merge(parent::attributes(), ['grp']);
    }
}
