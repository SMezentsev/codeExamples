<?php

namespace common\components\order\Model;

use yii\db\ActiveRecord;

/**
 *
 * @property string $number
 */
class Invoice extends ActiveRecord
{
	/**
	 * @inheritdoc
	 */
	public static function tableName(): string
	{
		return '{{%invoice}}';
	}
	
	/**
	 * @inheritdoc
	 */
	public function rules(): array
	{
		return [
			[['number'], 'required'],
			[['number'], 'string'],
		];
	}
	
	/**
	 * @inheritdoc
	 */
	public function attributeLabels(): array
	{
		return [
			'number' => '№ счета',
		];
	}
}