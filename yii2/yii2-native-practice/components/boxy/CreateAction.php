<?php
/**
 * @link      http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license   http://www.yiiframework.com/license/
 */

namespace app\modules\itrack\components\boxy;

/**
 * CreateAction implements the API endpoint for creating a new model from the given data.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author EagleMoor <eaglemoor@webspirit.pro>
 * @since  2.0
 */
class CreateAction extends \yii\rest\Action
{
    /**
     * @var string the scenario to be assigned to the new model before it is validated and saved.
     */
    public $scenario = \yii\base\Model::SCENARIO_DEFAULT;
    /**
     * @var string the name of the view action. This property is need to create the URL when the model is successfully created.
     */
    public $viewAction = 'view';
    
    public $accessTokenKey = 'access-token';
    
    /**
     * Creates a new model.
     *
     * @return \yii\db\ActiveRecordInterface the model newly created
     * @throws \yii\web\ServerErrorHttpException if there is any error when creating the model
     */
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }
        
        /* @var $model \yii\db\ActiveRecord */
        $model = new $this->modelClass([
            'scenario' => $this->scenario,
        ]);
        
        $model->load(\Yii::$app->getRequest()->getBodyParams(), '');
        if ($model->save()) {
            $response = \Yii::$app->getResponse();
            $response->setStatusCode(201);
            $id = implode(',', array_values($model->getPrimaryKey(true)));
            
            if (null !== $accessToken = \Yii::$app->getRequest()->get($this->accessTokenKey)) {
                $params = [$this->viewAction, 'id' => $id, $this->accessTokenKey => $accessToken];
            } else {
                $params = [$this->viewAction, 'id' => $id];
            }
            
            $response->getHeaders()->set('Link', \yii\helpers\Url::toRoute($params, true));
        } elseif (!$model->hasErrors()) {
            throw new \yii\web\ServerErrorHttpException('Failed to create the object for unknown reason.');
        }
        
        $model->refresh();
        
        return $model;
    }
}
