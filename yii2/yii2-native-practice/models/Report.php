<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\AuditBehavior;
use app\modules\itrack\components\boxy\ActiveRecord;
use \yii\db\Expression;
use Yii;

/**
 * This is the model class for table "reports".
 *
 * @property integer $id
 * @property boolean $status
 * @property string  $report_type
 * @property string  $params
 * @property string  $params
 * @property string  $typeof
 * @property integer $created_by
 *
 * @property User    $createdBy
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_Report",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example=1),
 *          @OA\Property(property="status", type="string", example="READY"),
 *          @OA\Property(property="report_type", type="string", example="type"),
 *          @OA\Property(property="params", type="string", example="{}"),
 *          @OA\Property(property="prcnt", type="integer", example=100),
 *          @OA\Property(property="created_at", type="string", example="2019-07-23 14:29:11+0300"),
 *          @OA\Property(property="createdBy", ref="#/components/schemas/app_modules_itrack_models_User"),
 *          @OA\Property(property="urlDownload", type="string", example="http://itrack-rf-api.dev-og.com/reports/1/download"),
 *      }
 * )
 */
class Report extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'reports';
    }

    public function behaviors()
    {
        return [['class' => AuditBehavior::class]];
    }
    
    public function fields()
    {
        return [
            'uid'         => 'id',
            'status',
            'report_type',
            'type',
            'params',
            'prcnt',
            'created_at'  => function () {
                return Yii::$app->formatter->asDatetime($this->created_at);
            },
            'createdBy',
            'urlDownload' => function () {
                if (!$this->status) {
                    return null;
                }
                
                $file = $this->getFileName() . '.xls';
                if (!file_exists($file)) {
                    $file = $this->getFileName() . '.csv';
                    if (!file_exists($file)) {
                        $file = $this->getFileName() . '.docx';
                        if (!file_exists($file)) {
                            $file = $this->getFileName() . '.pdf';
                            if (!file_exists($file)) {
                                $file = $this->getFileName() . '.json';
                                if (!file_exists($file)) {
                                    return null;
                                }
                            }
                        }
                    }
                }
                
                $token = '';
                if (Yii::$app->request->get('access-token') != '') {
                    $token = Yii::$app->request->get('access-token');
                }
                if (Yii::$app->request->headers->get('Authorization')) {
                    $token = str_replace('Bearer ', '', Yii::$app->request->headers->get('Authorization'));
                }
                
                return Yii::$app->urlManager->createAbsoluteUrl(['itrack/report/download', 'id' => $this->id, 'access-token' => $token]);
            },
        ];
    }
    
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert) {
            $has = self::find()->andWhere(['report_type' => $this->report_type, 'params' => $this->params, 'typeof' => $this->typeof])->andWhere(['>=', 'created_at', new Expression("now()-interval '5 min' ")])->one();
            if (!empty($has)) {
                $this->id = $has->id;
                $this->refresh();
                
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['report_type', 'created_by'], 'required'],
            [['status', 'report_type', 'params', 'typeof'], 'string'],
            [['created_at'], 'safe'],
            [['created_by', 'prcnt'], 'integer'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'          => Yii::t('app', 'ID'),
            'status'      => Yii::t('app', 'Статус'),
            'report_type' => Yii::t('app', 'Тип отчета'),
            'params'      => Yii::t('app', 'Параметры'),
            'created_at'  => Yii::t('app', 'Создано'),
            'created_by'  => Yii::t('app', 'Кем создано'),
        ];
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
    
    public function getFileName()
    {
        $fileName = Yii::getAlias('@reportPath') . DIRECTORY_SEPARATOR . $this->type . '-' . $this->id;
        return $fileName;
    }
    
    public function getType()
    {
        switch ($this->report_type) {
            case 'RafarmaReport7';
                $type = 'Production_Order';
                break;
            case 'RafarmaReport7_2';
                $type = 'Production_OrderByShift';
                break;
            default:
                $type = $this->report_type;
        }
        return $type;
    }
            
    
    public function delete()
    {
        $fn = $this->fileName;
        
        if (file_exists($fn . '.csv')) {
            unlink($fn . '.csv');
        }
        if (file_exists($fn . '.xls')) {
            unlink($fn . '.xls');
        }
        if (file_exists($fn . '.docx')) {
            unlink($fn . '.docx');
        }
        if (file_exists($fn . '.pdf')) {
            unlink($fn . '.pdf');
        }
        if (file_exists($fn . '.json')) {
            unlink($fn . '.json');
        }
        
        return parent::delete();
    }
    
}
