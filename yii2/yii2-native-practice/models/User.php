<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 14.04.15
 * Time: 14:31
 */

namespace app\modules\itrack\models;

use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\rbac\Permission;
use yii\rbac\Role;
use yii\web\ServerErrorHttpException;

/**
 * Class Users
 *   Сущность Пользовтатель системы
 *
 * @property integer      $id               - Идентификатор пользотваеля
 * @property string       $created_at       - Дата созданя пользователя
 * @property boolean      $active           - Флаг активности - при false  - счтается временно приоставноленным
 * @property string       $bdate            - Дата начала действия пользовтаеля
 * @property string       $edate            - Дата окончания действия пользотваеля (за пределами дат - доступа нет)
 * @property string       $login            - Логин для доступа в систему
 * @property string       $passwd           - Пароль для доступа в систему
 * @property string       $deleted_at       - Флаг признак удаления пользователя (при NULL считается не удаленным)
 * @property integer      $deleted_by       - Ссылка на удалившего
 * @property string       $fio              - ФИО пользотваеля
 * @property string       $email            - Емайл пользователя
 * @property integer      $object_uid       - Ссылка на Объект к которому привязан пользотваель (могут быть NULL  - без определенного места жителства)
 * @property integer      $manufacturer_uid — Производитель
 *
 * @property Permission[] $permissions
 * @property Role[]       $roles
 * @property Facility     $object
 * @property Manufacturer $manufacturer
 *
 * Метроды:
 *  - получение списка пользователей
 *  - изменнеие пользователя
 *  - просмотр пользователя
 *  - удаления пользовтеля
 *  - просмотр списка ролей у пользователя
 *  - добавлений/удаления роли у пользовтаеля
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_User_Auth",
 *      type="object",
 *      required={"login", "password"},
 *      properties={
 *          @OA\Property(property="login", type="string", description="Логин", example="test"),
 *          @OA\Property(property="password", type="string", description="Пароль", example="test"),
 *      }
 * )
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_User",
 *      type="object",
 *      properties={
 *          @OA\Property(property="uid", type="integer", example=1234),
 *          @OA\Property(property="login", type="string", example="test"),
 *          @OA\Property(property="fio", type="string", example="no name"),
 *          @OA\Property(property="active", type="boolean", example=true),
 *          @OA\Property(property="email", type="string", example="test@test.ru"),
 *          @OA\Property(property="object_uid", type="integer", example=1),
 *          @OA\Property(property="bdate", type="string", example="2019-07-23"),
 *          @OA\Property(property="edate", type="string", example=null),
 *          @OA\Property(property="created_at", type="string", example="2019-07-23 14:29:11+0300"),
 *          @OA\Property(property="manufacturer_uid", type="integer", example=0),
 *      }
 * )
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_User_Role",
 *      type="object",
 *      properties={
 *          @OA\Property(property="type", type="integer", example=1),
 *          @OA\Property(property="name", type="string", example="testRole"),
 *          @OA\Property(property="description", type="string", example="Тестовая роль"),
 *          @OA\Property(property="ruleName", type="string", example=null),
 *          @OA\Property(property="data", type="object",
 *              @OA\Property(property="roleType", ref="#/components/schemas/app_modules_itrack_models_Role_Type"
 *          )),
 *          @OA\Property(property="createdAt", type="string", example="2018-08-28 09:40:22+0300"),
 *          @OA\Property(property="updatedAt", type="string", example="2019-11-29 16:27:58+0300"),
 *      }
 * )
 */

/**
 * @OA\Schema(schema="app_modules_itrack_models_User_Permission",
 *      type="object",
 *      properties={
 *          @OA\Property(property="type", type="integer", example=1),
 *          @OA\Property(property="name", type="string", example="Test"),
 *          @OA\Property(property="description", type="string", example="Тест"),
 *          @OA\Property(property="ruleName", type="string", example=null),
 *          @OA\Property(property="data", type="object",),
 *          @OA\Property(property="createdAt", type="string", example="2018-08-28 09:40:22+0300"),
 *          @OA\Property(property="updatedAt", type="string", example="2019-11-29 16:27:58+0300"),
 *      }
 * )
 */
class User extends \app\modules\itrack\components\boxy\User
{
    const SYSTEM_USER = 99999999;
    /**
     * Системный специалист
     */
    const ROLE_ROOT = 'root';
    /**
     * Администратор
     */
    const ROLE_ADMIN = 'admin';
    /**
     * Аналитик
     */
    const ROLE_ANALYST = 'analyst';
    /**
     * Оператор производства
     */
    const ROLE_PRODUCTION_OPERATOR = 'productionOperator';
    /**
     * Контролер
     */
    const ROLE_CHECK_MAN = 'checkMan';
    /**
     * Кладовщик
     */
    const ROLE_STOCK_MAN = 'stockMan';
    static $auditOperation = AuditOperation::OP_USER;
    public $check_rights = true;
    public $attributeMap = [
        'password' => 'passwd',
    ];
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'users';
    }
    
    /**
     * Return not deleted users
     *
     * @inheritdoc
     * @return static
     */
    public static function find()
    {
        return parent::find()->andWhere('deleted_at is null');
    }
    
    public static function findByLogin($login)
    {
        // try login as login, email or phone
        $userQuery = static::find();
        $userQuery->andWhere(['login' => $login]);
        
        return $userQuery->one();
    }
    
    public static function primaryKey()
    {
        return ['id'];
    }
    
    public function behaviors()
    {
        return [['class' => \app\modules\itrack\components\AuditBehavior::class]];
    }
    
    public function save($runValidation = true, $attributeNames = null)
    {
        $ret = parent::save($runValidation, $attributeNames);
        if (empty($this->id)) {
            $u = self::findByLogin($this->login);
            if (!empty($u)) {
                $this->id = $u->id;
                $this->refresh();
            }
        }
        
        return $ret;
    }
    
    public function updatePassword()
    {
        if (!empty($this->passwd) && ($this->getOldAttribute('passwd') != $this->passwd)) {
            $passwordParams = \Yii::$app->params['password'];
            $passwordMethod = $passwordParams['method'];
            $passwordSalt = $passwordParams['salt'];
            
            $this->passwd = $passwordMethod($passwordSalt . $this->passwd);
        }
    }
    
    public function init()
    {
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            $this->updatePassword();
            $event->sender->is_equip = false;
        });
        $this->on(self::EVENT_BEFORE_UPDATE, function ($event) {
            $this->updatePassword();
            $event->sender->is_equip = false;
        });
        
        parent::init(); // TODO: Change the autogenerated stub
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['login'], 'required'],
            [['login'],
                'unique',
                'filter'  => function ($query) {
                    $query->where(['login' => $this->login]);
                    $query->andFilterWhere(['<>', 'id', $this->id]);
                    
                    return $query;
                },
                'message' => 'Данный логин уже занят'],
            [['created_at', 'bdate', 'edate', 'deleted_at'], 'safe'],
            [['active'], 'boolean'],
            [['login', 'passwd', 'fio', 'email'], 'string'],
            [['deleted_by', 'object_uid', 'manufacturer_uid'], 'integer'],
            ['email', 'email'],
            ['email', 'unique'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'created_at' => 'Дата создания',
            'active'     => 'Активация',
            'bdate'      => 'Старт активности',
            'edate'      => 'Окончание активности',
            'login'      => 'Логин',
            'passwd'     => 'Пароль',
            'deleted_at' => 'Дата удаления',
            'deleted_by' => 'Удаливший',
            'fio'        => 'ФИО',
            'email'      => 'Эл. почта',
            'object_uid' => 'Объект',
        ];
    }
    
    public function validatePassword($password)
    {
        if (!isset(\Yii::$app->params['password'])) {
            throw new ServerErrorHttpException;
        }
        
        $passwordParams = \Yii::$app->params['password'];
        $method = $passwordParams['method'];
        $salt = $passwordParams['salt'];
        
        return $this->passwd === $method($salt . $password);
    }
    
    public function fields()
    {
        return [
            'uid'        => 'id',
            'login',
            'fio',
            'active',
            'email',
            'object_uid',
            'bdate',
            'edate',
            'created_at' => function () {
                return ($this->created_at) ? \Yii::$app->formatter->asDatetime($this->created_at) : null;
            },
            'manufacturer_uid',
        ];
    }
    
    public function extraFields()
    {
        return [
            'manufacturer',
            'object',
            'deleted_at',
            'deleted_by',
            
            'permissions' => function () {
                return array_values($this->permissions);
            },
            'roles'       => function () {
                return array_values($this->roles);
            },
        ];
    }
    
    /**
     *  Получение Access-token от авторизованного пользовтаеля
     *
     * @return type
     */
    public function getToken()
    {
        $token = '';
        if (\Yii::$app->request->get('access-token') != '') {
            $token = \Yii::$app->request->get('access-token');
        }
        if (\Yii::$app->request->headers->get('Authorization')) {
            $token = str_replace('Bearer ', '', \Yii::$app->request->headers->get('Authorization'));
        }
        
        return $token;
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getManufacturer()
    {
        return $this->hasOne(Manufacturer::class, ['id' => 'manufacturer_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeletedBy()
    {
        return $this->hasOne(User::class, ['id' => 'deleted_by']);
    }
    
    /**
     * Запрет на физическое удаление пользователя
     *
     * @return bool
     */
    public function delete()
    {
        $this->deleted_at = new Expression('NOW()');
        $this->deleted_by = \Yii::$app->user->getId();
        $this->login = 'deleted_' . md5(microtime()) . '_' . $this->login;
        
        return $this->save();
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(Facility::class, ['id' => 'object_uid']);
    }
    
    /**
     * Получение ролей пользователя
     *
     * @return \yii\rbac\Role[]
     */
    public function getRoles()
    {
        $roles = \Yii::$app->authManager->getRolesByUser($this->id);
        $roles = array_map(function ($item) {
            $item->createdAt = \Yii::$app->formatter->asDatetime($item->createdAt);
            $item->updatedAt = \Yii::$app->formatter->asDatetime($item->updatedAt);
            
            return $item;
        }, $roles);
        
        return $roles;
    }
    
    /**
     * Получение прав доступа
     *
     * @return \yii\rbac\Permission[]
     */
    public function getPermissions()
    {
        $permissions = \Yii::$app->authManager->getPermissionsByUser($this->id);
        
        $permissions = array_map(function ($item) {
            $item->createdAt = \Yii::$app->formatter->asDatetime($item->createdAt);
            $item->updatedAt = \Yii::$app->formatter->asDatetime($item->updatedAt);
            
            return $item;
        }, $permissions);
        
        $hasL3 = Constant::get('hasL3');
        //чистака от пермишенов L3
        foreach ($permissions as $k => $v) {
            if ($hasL3 != 'true' && in_array($k, ['codeFunction-l3', 'codeFunction-l3-uniform', 'codeFunction-l3-add', 'codeFunction-l3-add-uniform'])) {
                unset($permissions[$k]);
            }
        }
        
        if (\Yii::$app->request->getQueryParam('view') == 'all' || \Yii::$app->request->getBodyParam('view') == 'all') {
            return $permissions;
        }
        //объединяем права с причинами
        $withdrawal = [];
        $remove = [];
        $removed = [];
        $returned = [];
        $back = [];
        
        $ignoreObjects = false;
        if (Constant::get('ignoreUserObject') == 'true') {
            if (isset($permissions["see-all-objects"])) {
                $ignoreObjects = true;
            }
        }
        
        
        foreach ($permissions as $k => $v) {
            if ($ignoreObjects && in_array($k, [
                    'codeFunction-l3-add',
                    'codeFunction-l3-add-uniform',
                    "codeFunction-l3",
                    "codeFunction-l3-uniform",
                    
                    'codeFunction-gofra-add',
                    'codeFunction-gofra-add-uniform',
                    'codeFunction-outcome-log',
                    'codeFunction-outcome-prod',
                    'codeFunction-retail-log',
                    'codeFunction-retail-prod',
                    "codeFunction-pack",
                    "codeFunction-pack-full",
                    
                    "codeFunction-paleta",
                    "codeFunction-paleta-add",
                    "codeFunction-paleta-add-uniform",
                    "codeFunction-paleta-uniform",
                    
                    "codeFunction-withdrawal-tsd-archive",
                    "codeFunction-withdrawal-tsd-control",
                    "codeFunction-withdrawal-tsd-declar",
                    "codeFunction-withdrawal-tsd-douk",
                    "codeFunction-withdrawal-tsd-err",
                    "codeFunction-withdrawal-tsd-ext1",
                    "codeFunction-withdrawal-tsd-ext2",
                    "codeFunction-withdrawal-tsd-ext3",
                    "codeFunction-withdrawal-tsd-ext4",
                    "codeFunction-withdrawal-tsd-ext5",
                    "codeFunction-withdrawal-tsd-ext6",
                    "codeFunction-withdrawal-tsd-ext7",
                    "codeFunction-withdrawal-tsd-ext8",
                    "codeFunction-withdrawal-tsd-ext9",
                    "codeFunction-withdrawal-tsd-other",
                ])) {
                if (isset($permissions[$k]->data->codes[0]->need)) {
                    foreach ($permissions[$k]->data->codes[0]->need as $k1 => $v1) {
                        if (preg_match('#^object\:#si', $v1)) {
                            unset($permissions[$k]->data->codes[0]->need[$k1]);
                        }
                    }
                    $permissions[$k]->data->codes[0]->need = array_values($permissions[$k]->data->codes[0]->need);
                }
                if (isset($permissions[$k]->data->codes[1]->need)) {
                    foreach ($permissions[$k]->data->codes[1]->need as $k1 => $v1) {
                        if (preg_match('#^object\:#si', $v1)) {
                            unset($permissions[$k]->data->codes[1]->need[$k1]);
                        }
                    }
                    $permissions[$k]->data->codes[1]->need = array_values($permissions[$k]->data->codes[1]->need);
                }
                if (isset($permissions[$k]->data->codes[2]->need)) {
                    foreach ($permissions[$k]->data->codes[2]->need as $k1 => $v1) {
                        if (preg_match('#^object\:#si', $v1)) {
                            unset($permissions[$k]->data->codes[2]->need[$k1]);
                        }
                    }
                    $permissions[$k]->data->codes[2]->need = array_values($permissions[$k]->data->codes[2]->need);
                }
            }
            if (preg_match('#^codeFunction-back-(.*)#i', $k, $m)) {
                if (empty($back)) {
                    $back = $permissions[$k];
                    $back->data->reasons = [];
                    $back->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                    unset($back->data->comment);
                    unset($back->data->note);
                    $back->name = "codeFunction-back";
                } else {
                    $back->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                }
                unset($permissions[$k]);
            }
            if (preg_match('#^codeFunction-return-(.*)#i', $k, $m)) {
                if (empty($returned)) {
                    $returned = $permissions[$k];
                    $returned->data->reasons = [];
                    $returned->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                    unset($returned->data->comment);
                    unset($returned->data->note);
                    $returned->name = "codeFunction-return";
                } else {
                    $returned->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                }
                unset($permissions[$k]);
            }
            if (preg_match('#^codeFunction-withdrawal-tsd-(.*)#i', $k, $m)) {
                if (empty($withdrawal)) {
                    $withdrawal = $permissions[$k];
                    $withdrawal->data->reasons = [];
                    $withdrawal->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment, $permissions[$k]->data->document];
                    unset($withdrawal->data->comment);
                    unset($withdrawal->data->note);
                    $withdrawal->name = "codeFunction-withdrawal-tsd";
                } else {
                    $withdrawal->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment, $permissions[$k]->data->document];
                }
                unset($permissions[$k]);
            }
            if (preg_match('#^codeFunction-removed-tsd-(.*)#i', $k, $m)) {
                if (empty($removed)) {
                    $removed = $permissions[$k];
                    $removed->data->reasons = [];
                    $removed->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                    unset($removed->data->comment);
                    unset($removed->data->note);
                    $removed->name = "codeFunction-removed-tsd";
                } else {
                    $removed->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                }
                unset($permissions[$k]);
            }
            if (preg_match('#^codeFunction-remove-web-(.*)#i', $k, $m)) {
                if (empty($remove)) {
                    $remove = $permissions[$k];
                    $remove->data->reasons = [];
                    $remove->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                    unset($remove->data->comment);
                    unset($remove->data->note);
                    $remove->name = "codeFunction-remove-web";
                } else {
                    $remove->data->reasons[] = [$permissions[$k]->data->note, $permissions[$k]->data->comment];
                }
                unset($permissions[$k]);
            }
        }
        
        if (!empty($back)) {
            $permissions["codeFunction-back"] = $back;
        }
        if (!empty($withdrawal)) {
            $permissions["codeFunction-withdrawal-tsd"] = $withdrawal;
        }
        if (!empty($removed)) {
            $permissions["codeFunction-removed-tsd"] = $removed;
        }
        if (!empty($remove)) {
            $permissions["codeFunction-remove-web"] = $remove;
        }
        if (!empty($returned)) {
            $permissions["codeFunction-return"] = $returned;
        }
        
        return $permissions;
    }
    
    /**
     * @param Role $role
     *
     * @return \yii\rbac\Permission[]
     */
    public function setRole(Role $role)
    {
        \Yii::$app->authManager->revokeAll($this->id);
        \Yii::$app->authManager->assign($role, $this->id);
        
        return $this->getPermissions();
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function getSessions()
    {
        return $this->hasMany(AccessToken::class, ['user_uid' => 'id']);
    }

    /**
     * Возвращает словарь пользователей
     * @return array
     */
    public static function getUsersDictionary(): array
    {
        $users = self::find()->select(['id', 'login', 'fio'])->where(['is_equip' => false])->asArray()->all();
        return (count($users) > 0) ? ArrayHelper::index($users, 'id') : [];
    }
}