<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 15.04.15
 * Time: 17:05
 */

namespace app\modules\itrack\controllers;

use app\commands\RbacController;
use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use app\modules\itrack\models\AccessToken;
use app\modules\itrack\models\User;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\rbac\Role;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

class EquipController extends ActiveController
{
    
    use ControllerTrait;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'equipment',
    ];
    public $modelClass = 'app\modules\itrack\models\Equip';
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];

//        if (SERVER_RULE == SERVER_RULE_SKLAD) {
//            unset($actions['update']);
//            unset($actions['delete']);
//            unset($actions['create']);
//        }
        
        return $actions;
    }
    
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case 'update':
                if (!\Yii::$app->user->can('equip-crud') && !empty($model) && $model->id != \Yii::$app->user->id) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'create':
            case 'delete':
            case 'permissions':
            case 'roles':
            case 'set-role':
                if (!\Yii::$app->user->can('equip-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('equip') && !empty($model) && $model->id != \Yii::$app->user->id) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('equip') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'delete-sessions':
            case 'session':
                if (!\Yii::$app->user->can('equip-session')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    public function actionTypes()
    {
        return ['types' => array_map(function ($id, $name) {
            return ['uid' => $id, 'id' => $id, 'name' => $name];
        }, array_keys(\app\modules\itrack\models\Equip::$equipTypes), array_values(\app\modules\itrack\models\Equip::$equipTypes))];
    }
    
    /**
     * Получение ролей пользователя
     *
     * @param null|int $id
     *
     * @return array
     */
    public function actionRoles($id = null)
    {
        if (!$id) {
            $roles = [];
            
            // Фильтрация доступных ролей
            $accessRoles = [];
            foreach (\Yii::$app->user->getIdentity()->roles as $role) {
                $accessRoles = ArrayHelper::merge($accessRoles, RbacController::getAccessRoles($role->name));
            }
            foreach (\Yii::$app->authManager->getRoles() as $role) {
                if (in_array($role->name, $accessRoles)) {
                    $roles[] = $role;
                }
            }
        } else {
            $roles = \Yii::$app->authManager->getRolesByUser($id);
        }
        
        $roles = array_map(function ($item) {
            /** @var Role $item */
            $item->createdAt = \Yii::$app->formatter->asDatetime($item->createdAt);
            $item->updatedAt = \Yii::$app->formatter->asDatetime($item->updatedAt);
            
            $a = (array)$item;
            
            $permissions = \Yii::$app->authManager->getPermissionsByRole($item->name);
            $a['permissions'] = ($permissions) ? array_values($permissions) : [];
            
            return $a;
        }, $roles);
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_EQUIP, 'Просмотр списка ролей у оборудования', [["field"=>"Роль","value"=>$id]]);
        return ['roles' => array_values($roles)];
    }
    
    /**
     * Подготовка данных для вывода в actionIndex (actions['index'])
     *
     * @return ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $userSort = new \app\modules\itrack\models\EquipSort();
        
        $dataProvider = $userSort->searchUser(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'users.object_uid', \Yii::$app->user->identity->object_uid]);
        }
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['equipment' => array_map(function ($v) {
                return ["uid" => $v["id"], "id" => $v["id"], "name" => $v["fio"] . "(" . $v["login"] . ")"];
            }, $dataProvider->query->orderBy('fio')->all())];
        }

//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_EQUIP, 'Просмотр списка оборудования', []);
        return $dataProvider;
    }
    
    public function actionOcs()
    {
        return ['ocs' => \app\modules\itrack\models\OcsConnector::find()->all()];
    }
    
    
    /**
     * @param null $id
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionPermissions($id = null)
    {
        if ($id) {
            $user = $this->findModel($id);
        } else {
            $user = \Yii::$app->user->getIdentity();
        }

//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_EQUIP, 'Просмотр списка прав у оборудования', [["field"=>"Идентификатор пользователя","value"=>$id]]);
        return ['permissions' => array_values($user->permissions)];
    }
    
    public function actionSetRole($id)
    {
        /** @var User $user */
        $user = $this->findModel($id);
        
        $roleName = \Yii::$app->getRequest()->getBodyParam('role');
        $role = \Yii::$app->authManager->getRole($roleName);
        if (!$role) {
            throw new NotFoundHttpException("Role with name {$roleName} not found");
        }
        
        $permissions = $user->setRole($role);
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_EQUIP, 'Добавление роли ' . $roleName . ' к оборудованию ' . $user->login . " ($user->fio)", []);
        
        return ['permissions' => array_values($permissions)];
    }
    
    /**
     * Список сессий пользователя
     *
     * @param $userId
     *
     * @return array
     * @throws MethodNotAllowedHttpException
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionSessions($userId)
    {
        /** @var User $user */
        $user = $this->findModel($userId);
        
        $this->checkAccess($this->action->id, $user, ['userId' => $userId]);
        
        $sessions = $user->getSessions();
        $dataProvider = new ActiveDataProvider([
            'query' => $sessions,
        ]);

//        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_EQUIP, "Просмотр списка активных сессий у $user->login ($user->fio)", []);
        return $dataProvider;
    }
    
    /**
     * Удаление сессии пользователя
     *
     * @param $userId
     * @param $id
     *
     * @throws MethodNotAllowedHttpException
     * @throws NotFoundHttpException
     * @throws ServerErrorHttpException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionDeleteSessions($userId, $id)
    {
        /** @var User $user */
        $user = $this->findModel($userId);
        
        $this->checkAccess($this->action->id, $user, ['userId' => $userId, 'id' => $id]);
        
        /** @var AccessToken $sessions */
        $sessions = $user->sessions;
        foreach ($sessions as $session) {
            if ($session->id == $id) {
                if ($session->delete() === false) {
                    throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
                }
                break;
            }
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_EQUIP, "Удаление у оборудования $user->login ($user->fio) сессии $id", [["field" => "Идентификатор пользователя", "value" => $userId], ["field" => "Логин", "value" => $user->login], ["field" => "Идентификатор сессии", "value" => $id]]);
        \Yii::$app->getResponse()->setStatusCode(204);
    }
    
}
