<?php

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\LabelTemplates;
use yii\web\NotAcceptableHttpException;

class LabelController extends ActiveController
{
    
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\LabelTemplates';
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'labels',
    ];
    
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        if (SERVER_RULE == SERVER_RULE_SKLAD) {
            unset($actions['update']);
            unset($actions['delete']);
            unset($actions['create']);
        }
        
        return $actions;
    }
    
    public function actionDownload($id)
    {
        $mc = $this->modelClass;
        $m = $mc::findOne($id);
        if (empty($m)) {
            throw new \yii\web\NotFoundHttpException('Шаблон не найден');
        }
        
        \Yii::$app->getResponse()->sendContentAsFile($m->tempdata, $m->filename);
    }
    
    public function actionTypes()
    {
        $ret = [];
        foreach (LabelTemplates::$types as $k => $v) {
            $ret[] = ['key' => $k, 'value' => $v];
        }
        
        return ['types' => $ret];
    }
    
    public function prepareDataProvider()
    {
        $sort = new LabelTemplates;
        $params = \Yii::$app->request->getQueryParams();
        $dataProvider = new \yii\data\ActiveDataProvider();
        $dataProvider->query = $sort->find();
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'object_uid', \Yii::$app->user->identity->object_uid]);
        }
        if (isset($params["name"]) && !empty($params["name"])) {
            $dataProvider->query->andWhere(['ilike', 'label_templates.name', $params["name"]]);
        }
        if (isset($params["typeof"]) && !empty($params["typeof"])) {
            $dataProvider->query->andWhere(['=', 'label_templates.typeof', $params["typeof"]]);
        }
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['labels' => array_map(function ($v) {
                return ["uid" => $v["id"], 'object_uid' => $v["object_uid"], "id" => $v["id"], "name" => $v["name"]];
            }, $dataProvider->query->all())];
        }
        
        return $dataProvider;
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'create':
            case 'update':
            case 'delete':
                if (!\Yii::$app->user->can('reference-labels-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('reference-labels')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('reference-labels') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
}
