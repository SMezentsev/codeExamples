<?php

namespace app\modules\itrack\controllers;


use app\modules\itrack\components\boxy\ActiveController;
use app\modules\itrack\components\boxy\ControllerTrait;
use yii\data\ArrayDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;

/**
 * @OA\Post(
 *   path="/roles",
 *   tags={"Роли"},
 *   description="Создание роли",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *      )
 *   ),
 *   @OA\Response(
 *      response=201,
 *      description="Роль",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/roles",
 *  tags={"Роли"},
 *  description="Получение списка ролей",
 *  @OA\Response(
 *      response="200",
 *      description="Роли",
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
 *  path="/roles/permissions",
 *  tags={"Роли"},
 *  description="Получение списка прав",
 *  @OA\Response(
 *      response="200",
 *      description="Права",
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
 *  path="/me/roles",
 *  tags={"Роли"},
 *  description="Получение ролей авторизованного пользователя",
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
 *  path="/roles/types",
 *  tags={"Роли"},
 *  description="Получение типов ролей",
 *  @OA\Response(
 *      response="200",
 *      description="Типы ролей",
 *      @OA\JsonContent(
 *          @OA\Property(
 *              property="types",
 *              type="array",
 *              @OA\Items(ref="#/components/schemas/app_modules_itrack_models_Role_Type")
 *          )
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Get(
 *  path="/roles/{id}",
 *  tags={"Роли"},
 *  description="Получение роли",
 *  @OA\Parameter(
 *      in="path",
 *      name="id",
 *      required=true,
 *      description="Идентифкатор",
 *  ),
 *  @OA\Response(
 *      response="200",
 *      description="Роль",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Put(
 *  path="/roles/{id}",
 *  tags={"Роли"},
 *  description="Изменение роли",
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
 *      description="Роли",
 *      @OA\JsonContent(
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Role")
 *      )
 *  ),
 *  security={{"access-token":{}}}
 * )
 */

/**
 * @OA\Delete(
 *  path="/roles/{id}",
 *  tags={"Роли"},
 *  description="Удаление роли",
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
class RoleController extends ActiveController
{
    use ControllerTrait;
    
    public $modelClass = 'app\modules\itrack\models\Role';
    
    public $serializer = [
        'class'              => 'app\modules\itrack\components\boxy\Serializer',
        'collectionEnvelope' => 'roles',
    ];
    
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        
        unset($actions['update']);
//        unset($actions['delete']);
        unset($actions['create']);

//        $actions['delete']['checkAccess'] = [$this, 'checkAccess'];
        
        return $actions;
    }
    
    public function actionTypes()
    {
        return ["roleTypes" => [["uid" => 1, "name" => "Производство"], ["uid" => 2, "name" => "Логистический центр"]]];
    }
    
    public function actionRemove($id)
    {
        $role = \yii::$app->authManager->getRole($id);
        if (!$role) {
            throw new NotFoundHttpException("Role with name {$id} not found");
        }
        
        \Yii::$app->authManager->remove($role);
        \Yii::$app->response->statusCode = 204;
        
        return;
    }
    
    public function actionPermissions($id = null)
    {
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Просмотр списка прав", [$id]);
        
        if ($id) {
            $permissions = \Yii::$app->authManager->getPermissionsByRole($id);
        } else {
            $permissions = \Yii::$app->authManager->getPermissions();
        }
        
        $hasL3 = \app\modules\itrack\models\Constant::get('hasL3');
        foreach ($permissions as $k => $v) {
            if ($hasL3 != 'true' && in_array($k, ['codeFunction-l3', 'codeFunction-l3-uniform', 'codeFunction-l3-add', 'codeFunction-l3-add-uniform'])) {
                unset($permissions[$k]);
            }
        }
        
        return ['permissions' => array_values($permissions)];
    }
    
    public function actionCreate()
    {
        $roleName = "role" . md5(microtime());
        $roleDescription = \Yii::$app->getRequest()->getBodyParam('description');
        if (empty($roleDescription)) {
            throw new BadRequestHttpException("Не заполнено поле Название роли");
        }
        $type = \Yii::$app->getRequest()->getBodyParam('type');
        
        if (!in_array($type, [1, 2])) {
            throw new BadRequestHttpException("Не верный тип роли");
        }

        $role = \yii::$app->authManager->getRole($roleName);
        if ($role) {
            throw new BadRequestHttpException("Роль с таким наименованием уже создана");
        }

        $obj = \yii::$app->authManager->createRole($roleName);
        $obj->name = $roleName;
        $obj->description = $roleDescription;
        $obj->data = ['roleType' => ['uid' => $type, 'name' => (($type == 1) ? 'Производство' : 'Логистический центр')]];
        \yii::$app->authManager->add($obj);
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Создание роли $roleName", []);
        
        return ['role' => $obj];
    }
    
    public function actionUpdate($id)
    {
        $roleDescription = \Yii::$app->getRequest()->getBodyParam('description');
        $type = \Yii::$app->getRequest()->getBodyParam('type');
        
        if (!in_array($type, [1, 2])) {
            throw new BadRequestHttpException("Не верный тип роли");
        }
        if (empty($roleDescription)) {
            throw new BadRequestHttpException("Не заполнено поле Название роли");
        }
        if (empty($id)) {
            throw new BadRequestHttpException("Не заполнено поле roleName");
        }
        
        $role = \yii::$app->authManager->getRole($id);
        if (!$role) {
            throw new NotFoundHttpException("Role with name {$id} not found");
        }
        $role->data = ['roleType' => ['uid' => $type, 'name' => (($type == 1) ? 'Производство' : 'Логистический центр')]];
        $role->description = $roleDescription;
        \yii::$app->authManager->update($id, $role);
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Изменение роли $roleDescription", []);
        
        return ['role' => $role];
    }
    
    public function actionChangeright()
    {
        $name = \Yii::$app->request->getBodyParam('name');
        $description = \Yii::$app->request->getBodyParam('description');
        
        $right = \Yii::$app->authManager->getPermission($name);
        if (!$right) {
            throw new NotFoundHttpException("Право с именем {$name} не найдено");
        }
        $right->description = $description;
        \Yii::$app->authManager->update($name, $right);
        
        return $right;
    }
    
    public function actionSetPermission($id)
    {
        $permissionName = \Yii::$app->getRequest()->getBodyParam('permission');
        $action = \Yii::$app->getRequest()->getBodyParam('action');
        
        if (!is_array($permissionName)) {
            $permissionName = [$permissionName];
        }
        
        $role = \Yii::$app->authManager->getRole($id);
        if (!$role) {
            throw new NotFoundHttpException("Role with name {$id} not found");
        }
        
        if ($action == 'setup') {
            //если setup - то предварительно убираем все пермишены
            $ps = \Yii::$app->authManager->getPermissionsByRole($id);
            foreach ($ps as $p) {
                \Yii::$app->authManager->removeChild($role, $p);
            }
        }
        
        foreach ($permissionName as $pName) {
            $permission = \Yii::$app->authManager->getPermission($pName);
            if (!$permission) {
                throw new NotFoundHttpException("Permission with name {$pName} not found");
            }
            try {
                if ($action == 'set' || $action == 'setup') {
                    \Yii::$app->authManager->addChild($role, $permission);
                } else {
                    \Yii::$app->authManager->removeChild($role, $permission);
                }
            } catch (\Exception $e) {
                throw new BadRequestHttpException("Ошибка, обратитесь к администратору (или это правило уже есть в этой роли)");
            }
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Добавление прав в роль $id", [["field" => "Действие", "value" => $action], ["field" => "Права", "value" => $permissionName]]);
        
        return ['permissions' => array_values(\Yii::$app->authManager->getPermissionsByRole($id))];
    }
    
    public function prepareDataProvider()
    {
        $dataProvider = new ArrayDataProvider([
            'allModels' => \Yii::$app->authManager->getRoles(),
            'sort'      => [
                'attributes'   => [
                    'createdAt' => [],
                ],
                'defaultOrder' => [
                    'createdAt' => SORT_DESC,
                ],
            ],
        ]);
        
        if (\Yii::$app->request->getQueryParam('combo') == 'true') {
            return ['roles' => array_values(array_map(function ($v) {
                return ["name" => $v->name, 'description' => $v->description];
            }, $dataProvider->allModels))];
        }
        
        //\app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_USER, "Просмотр списка ролей", []);
        
        return $dataProvider;
    }
    
    /**
     * @param string       $action
     * @param Nomenclature $model
     * @param array        $params
     *
     * @throws NotAcceptableHttpException
     */
    public function checkAccess($action, $model = null, $params = [])
    {
        switch ($action) {
            case "delete":
            case "new":
                if (!\Yii::$app->user->can('reference-roles')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            case "index":
                if (!\Yii::$app->user->can('reference-roles') && \Yii::$app->request->getQueryParam('combo') != 'true') {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
            default:
                if (!\Yii::$app->user->can('users-crud')) {
                    throw new NotAcceptableHttpException("Запрет на выполнение операции");
                }
                break;
        }
        
        return parent::checkAccess($action, $model, $params);
    }
    
    protected function verbs()
    {
        return [
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH', 'POST'],
            'delete' => ['DELETE'],
            'view'   => ['GET'],
            'index'  => ['GET'],
        ];
    }
}