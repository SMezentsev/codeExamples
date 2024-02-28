<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 15.04.15
 * Time: 17:54
 */

namespace app\modules\itrack\components\boxy;

trait ControllerTrait
{
    
    /**
     * Добавление модуля авторизации
     *
     * ```php
     * $authExcept = false — отключить авторизацию
     * $authExcept = [] — включить авторизацию
     * $authExcept = ['auth'] — включить авторизацию везде, кроме actionAuth
     * ```
     *
     * @return mixed
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        
        $authExcept = $this->authExcept();
        
        if (false !== $authExcept) {
            $behaviors['authenticator'] = [
                'class'       => \yii\filters\auth\CompositeAuth::class,
                'authMethods' => [
                    \yii\filters\auth\HttpBearerAuth::class,
                    \yii\filters\auth\QueryParamAuth::class,
                ],
                'except'      => $authExcept,
            ];
        }

//        $accessControl = $this->accessControl();
//        if (false !== $accessControl && is_array($accessControl)) {
//            $accessControl = \yii\helpers\ArrayHelper::merge([
//                'class' => 'yii\filters\AccessControl',
//                'ruleConfig' => ['class' => 'yii\boxy\AccessRule'],
//                'rules' => []
//            ], $accessControl);
//            $behaviors['access'] = $accessControl;
//        }
        
        return $behaviors;
    }

//    protected function accessControl() {
//        return false;
//    }
    
    /**
     * Поиск модели по id
     *
     * ```php
     * $modelClass = 'app\models\User';
     * ```
     *
     * @param        $id
     * @param string $modelClass
     *
     * @return \yii\db\ActiveRecordInterface
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\NotFoundHttpException
     */
    public function findModel($id, $modelClass = null)
    {
        $findModelClass = $modelClass;
        
        if (null == $findModelClass) {
            $fields = \Yii::getObjectVars($this);
            
            if (!$findModelClass && isset($fields['modelClass']) && $fields['modelClass']) {
                $findModelClass = $fields['modelClass'];
            }
        }
        
        if (!$findModelClass) {
            throw new \yii\base\InvalidConfigException('Not set $modelClass');
        }
        
        $object = $findModelClass::findOne($id);
        if (!$object) {
            throw new \yii\web\NotFoundHttpException("Object not found: $id");
        } else {
            return $object;
        }
    }
    
    /**
     * Все параметры по fields & expand
     *
     * ```php
     * url?fields=id,name&expand=object
     * ```
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public function extraParams()
    {
        $params = \Yii::getObjectVars($this);
        $serializer = 'yii\rest\Serializer';
        if (isset($params['serializer'])) {
            $serializer = $params['serializer'];
        }
        
        /** @var \yii\rest\Serializer $serializer */
        $serializer = \Yii::createObject($this->serializer);
        
        return array_merge($serializer->requestedFields[0], $serializer->requestedFields[1]);
    }
    
    protected function authExcept()
    {
        if ($this instanceof \yii\console\Controller) {
            return false;
        }
        
        return [];
    }
}