<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 08.05.15
 * Time: 17:17
 */

namespace app\modules\itrack\controllers\actions;

use app\modules\itrack\models\CodeStatusFunction;
use yii\rest\Action;
use Yii;

/**
 * Class CodeStatusAction
 *
 * Универсальный action для работы с изменением статусов кодов
 *
 * @package app\modules\itrack\controllers\actions
 */
class CodeStatusAction extends Action
{
    
    public $scenario;
    
    public function run()
    {
        $scenario = $this->scenario;
        $params = Yii::$app->getRequest()->getBodyParams();

        switch ($scenario) {
            case 'remove':
            case 'block':
            case 'unblock':
                if (null != Yii::$app->getRequest()->getBodyParam('bdate') &&
                    null != Yii::$app->getRequest()->getBodyParam('edate') &&
                    null != Yii::$app->getRequest()->getBodyParam('series')
                ) {
                    $scenario .= 'ByDate';
                }
                break;
            case 'serialize':
                if(isset($params['multipleSerialization']))
                {
                    $scenario .= 'Multiple';
                }
                break;
        }
        
        /** @var CodeStatusFunction $model */
        $model = new $this->modelClass([
            'scenario' => $scenario,
        ]);
        $model->load($params, '');
        $model->force = Yii::$app->request->get('force');
        
        $trans = \Yii::$app->db->beginTransaction();
        
        if ($model->$scenario()) {
            if(!empty($model->infotxt)) {
                $trans->rollBack();
            } else {
                $trans->commit();
            }
            if ($model->status == "AutoGeneration") {
                \Yii::$app->getResponse()->setStatusCode(422);
                
                return ['field' => 'groupCode', 'message' => "Не могу получить автоматический групповой код, проверьте наличие резерва"];
            } else {
                \Yii::$app->getResponse()->setStatusCode(201);
                if (!empty($model->infotxt)) {
                    return ['status' => 'ok', 'data' => $model->status, 'info' => $model->infotxt];
                } else {
                    return ['status' => 'ok', 'data' => $model->status];
                }
            }
        }
        
        try {
            $trans->rollBack();
        } catch (\Exception $ex) {
        }
        
        return $model;
    }
}