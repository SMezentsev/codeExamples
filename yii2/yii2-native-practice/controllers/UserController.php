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
use app\modules\itrack\models\UserSort;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\rbac\Role;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * @OA\Post(
 *   path="/users",
 *   tags={"Пользователи"},
 *   description="Создание пользователя",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Пользователь",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/users",
 *  tags={"Пользователи"},
 *  description="Получение списка номенклатур",
 *  @OA\Response(
 *      response="200",
 *      description="Пользователи",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="nomenclatures",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_User")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/users/{id}",
 *  tags={"Пользователи"},
 *  description="Получение пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Пользователь",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/users/{id}/permissions",
 *  tags={"Пользователи"},
 *  description="Получение прав пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Права пользователя",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="permissions",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_User_Permission")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/me/permissions",
 *  tags={"Пользователи"},
 *  description="Получение прав авторизованного пользователя",
 *  @OA\Response(
 *      response="200",
 *      description="Права пользователя",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="permissions",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_User_Permission")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/users/{id}/roles",
 *  tags={"Пользователи"},
 *  description="Получение ролей пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Роли пользователя",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="roles",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/users/{id}/sessions",
 *  tags={"Пользователи"},
 *  description="Получение сессий пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Сессии пользователя",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="users",
 *              type="array",
 *              @OA\Items(
 *                  @OA\Property(type="object",
 *                      @OA\Property(property="id", type="string", example="12312aaa"),
 *                      @OA\Property(property="user_uid", type="integer", example=123),
 *              ))
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/users/{id}",
 *  tags={"Пользователи"},
 *  description="Изменение пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Пользователь",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/users/{id}/roles",
 *  tags={"Пользователи"},
 *  description="Изменение роли пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *      )
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Роли пользователя",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/users/{id}",
 *  tags={"Пользователи"},
 *  description="Удаление пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="204",
 *      description="",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/users/{id}/sessions",
 *  tags={"Пользователи"},
 *  description="Удаление сессии пользователя",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="204",
 *      description="",
 *  ),
 *  security={{"access-token":{}}}
 * )
 */
class UserController extends ActiveController
{
    use ControllerTrait;
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'users',
    ];
    
    public $modelClass;
    
    public function init()
    {
        $this->modelClass = \Yii::$app->user->identityClass;
        parent::init();
    }
    
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
//new -to remove
        switch ($action) {
            case 'update':
                if (!\Yii::$app->user->can('users-crud') && !empty($model) && $model->id != \Yii::$app->user->id) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'create':
            case 'delete':
            case 'permissions':
            case 'roles':
            case 'set-role':
                if (!\Yii::$app->user->can('users-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'view':
                if (!\Yii::$app->user->can('users') && !empty($model) && $model->id != \Yii::$app->user->id) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'index':
                if (!\Yii::$app->user->can('users') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case 'delete-sessions':
            case 'session':
                if (!\Yii::$app->user->can('users-session')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    public function actionControllers()
    {
        $authManager = \Yii::$app->getAuthManager();
        $permission = $authManager->getPermission('user-controller');
        $roles = $authManager->getRoles();
        $users = [];
        if (!empty($permission)) {
            foreach ($roles as $role) {
                $p = $authManager->getPermissionsByRole($role->name);
                if (isset($p[$permission->name])) {
                    $users = array_merge($users, $authManager->getUserIdsByRole($role->name));
                }
            }
        }
        
        return ['controllers' => User::find()->andWhere(['in', 'id', $users])/*->with('manufacturer')*/ ->all()];
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
        
        return ['roles' => array_values($roles)];
    }
    
    /**
     * Подготовка данных для вывода в actionIndex (actions['index'])
     *
     * @return ActiveDataProvider
     */
    public function prepareDataProvider()
    {
        $userSort = new UserSort();
        
        $dataProvider = $userSort->searchUser(\Yii::$app->request->getQueryParams());
        
        if (!\Yii::$app->user->can('see-all-objects')) {
            $dataProvider->query->andWhere(['=', 'users.object_uid', \Yii::$app->user->identity->object_uid]);
        }
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['users' => array_map(function ($v) {
                return ["uid" => $v["id"], "id" => $v["id"], "name" => $v["fio"] . "(" . $v["login"] . ")"];
            }, $dataProvider->query->orderBy('fio')->all())];
        }
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Просмотр списка пользователей", []);
        return $dataProvider;
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
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Привязка роли $roleName к пользователю $user->login ($user->fio)", []);
        
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
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Просмотр списка сессиий у пользователей", [["field" => "Идентификатор пользователя", "value" => $userId], ["field" => "Логин", "value" => $user->login]]);
        
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
        
        \Yii::$app->getResponse()->setStatusCode(204);
    }
}
