<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;

class OcsConnector extends ActiveRecord
{
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'ocs_connectors';
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['display_name', 'url', 'name'], 'safe'],
            [['display_name', 'url', 'name'], 'required'],
        ];
    }
}
