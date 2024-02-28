<?php

namespace app\modules\itrack\models;

/**
 * This is the model class for table "menu".
 *
 * @property integer $id
 * @property string  $uid
 * @property string  $parent
 * @property integer $ord
 * @property string  $url
 * @property string  $permissions
 * @property string  $amask
 * @property boolean $skad
 */
class Menu extends \yii\db\ActiveRecord
{
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'menu';
    }
    
    static function find()
    {
        $q = parent::find();
        $q->andWhere(["active" => true]);
        
        return $q;
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['uid'], 'required'],
            [['uid', 'parent', 'url', 'permissions', 'amask'], 'string'],
            [['ord'], 'integer'],
            [['skad', 'active'], 'boolean'],
            [['uid'], 'unique'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'uid'         => 'Uid',
            'parent'      => 'Parent',
            'ord'         => 'Ord',
            'url'         => 'Url',
            'permissions' => 'Permissions',
            'amask'       => 'Amask',
            'skad'        => 'Skad',
        ];
    }
    
}