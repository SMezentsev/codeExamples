<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 15.04.15
 * Time: 11:43
 */

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\AuthController as AU;
use app\modules\itrack\models\AccessToken;
use app\modules\itrack\models\User;
use yii\web\UnauthorizedHttpException;

/**
 * @OA\Post(
 *   tags={"Auth"},
 *   path="/auth",
 *   @OA\RequestBody(
 *      required=true,
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(ref="#/components/schemas/app_modules_itrack_models_User_Auth")
 *      )
 *   ),
 *   @OA\Response(
 *      response=200,
 *      description="Пользователь найден",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(
 *              @OA\Property(property="user", ref="#/components/schemas/app_modules_itrack_models_User"),
 *              @OA\Property(property="token", ref="#/components/schemas/app_modules_itrack_models_AccessToken"),
 *              @OA\Property(property="roles", type="array", @OA\Items(
 *                  ref="#/components/schemas/app_modules_itrack_models_User_Role"
 *              )),
 *              @OA\Property(property="permissions", type="array", @OA\Items(
 *                  ref="#/components/schemas/app_modules_itrack_models_User_Permission"
 *              )),
 *          )
 *      )
 *   ),
 *   @OA\Response(
 *      response=401,
 *      description="&nbsp;&nbsp;401 Пользователь не найден<br>&nbsp;&nbsp;402 Вы неверно указали пароль<br>6001 Пользователь уже залогинен<br>6003 Пользователь заблокирован. Обратитесь к администраторам",
 *      @OA\Schema(
 *      )
 *   ),
 * )
 */

/**
 * @OA\Get(
 *   tags={"Version"},
 *   path="/version",
 *   @OA\Response(
 *      response=200,
 *      description="",
 *      @OA\MediaType(
 *          mediaType="application/json",
 *          @OA\Schema(
 *              @OA\Property(property="androidVersion",
 *                  @OA\Property(property="url", type="string", example="http://itrack-rf-api.dev-og.com/bin/itrack-2.1.2.7.apk",),
 *                  @OA\Property(property="lastVersion", type="string", example="1.0.0.0",),
 *              ),
 *          )
 *      )
 *   ),
 *   security={{"access-token":{}}}
 * )
 */
class AuthController extends AU
{
    public function authExcept()
    {
        return ['auth', 'version', 'api-version'];
    }
    
    public function actionAuth()
    {
        if (\Yii::$app->getRequest()->getBodyParam('test') == true) {
            $file = file_get_contents("https://m0121-hcioem.fmst.eu1.hana.ondemand.com/cxf/ICH_DataExchange_SOAP_v4");
            var_dump($file);
            exit;
        }
        $force = \Yii::$app->getRequest()->getBodyParam('force');
        if (1 == $force || 'true' == $force || true === $force) {
            $force = true;
        } else {
            $force = false;
        }
        
        $request = \Yii::$app->getRequest();
        
        $login = \Yii::$app->getRequest()->getBodyParam('login');
        $password = \Yii::$app->getRequest()->getBodyParam('password');
        $user = User::findByLogin($login);
        
        if (is_a($user, 'app\modules\itrack\models\User') && !$user->validatePassword($password)) {
            throw new \yii\web\UnauthorizedHttpException("Вы неверно указали пароль", 402);
        }
        
        /** @var User $modelClass */
        if (empty($user)) {
            throw new \yii\web\UnauthorizedHttpException("Пользователь не найден", 401);
        }
        
        if (!$user->active) {
            throw new UnauthorizedHttpException("Пользователь заблокирован. Обратитесь к администраторам.", 6003);
        }
        
        if ($user && isset($user->permissions['oneAuth'])) {
            $tokenExist = AccessToken::find()->where(['user_uid' => $user->id])->one();
            if ($tokenExist && !$force) {
                throw new UnauthorizedHttpException("Пользователь уже залогинен", 6001);
            }
        }
        
        $accessTokenClass = \Yii::$app->user->accessTokenClass;
        $token = $accessTokenClass::generateForUser($user);
        
        $data = [
            'user'  => $user,
            'token' => $token,
        ];
        
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_AUTH, 'Авторизация, логин: ' . $user->login,
            [
                ['field' => 'Идентификатор пользователя', 'value' => $user->id],
                ['field' => 'ФИО', 'value' => $user->fio],
            ]);
        // add rules
        if ($data['user']) {
            $data['roles'] = array_values($data['user']->roles);
            $data['permissions'] = array_values($data['user']->permissions);
            
            if (!sizeof($data['roles']) || !sizeof($data['permissions'])) {
                throw new \yii\web\UnauthorizedHttpException("Вам не назначены права. Обратитесь к администраторам.", 6002);
            }
        }
        
        return $data;
    }
    
    public function actionPing()
    {
//        if (SERVER_RULE == SERVER_RULE_RF)
        return ['status' => 1];
//        throw new BadRequestHttpException("Ping access only on itrack-rf");
    }
    
    public function actionVersion()
    {
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_APKVERSION, 'Проверка версии APK', []);
        $androidVersion = \Yii::$app->params['androidVersion'];
        
        $url = str_replace(['{version}'], [$androidVersion['lastVersion']], $androidVersion['url']);
        
        $androidVersion['url'] = $url;
        
        return ['androidVersion' => $androidVersion];
    }
    
    /**
     * @OA\Get(
     *   tags={"Auth"},
     *   path="/logout",
     *   @OA\Response(
     *      response=204,
     *      description="",
     *   ),
     *   security={{"access-token":{}}}
     * )
     */
    
    public function actionLogout()
    {
        $token = \Yii::$app->user->getIdentity()->accessToken;
        $accessTokenClass = \Yii::$app->user->accessTokenClass;
        $accessToken = $accessTokenClass::findOne(['id' => $token, 'user_uid' => \Yii::$app->user->getId()]);
        
        if ($accessToken->delete() === false) {
            throw new \yii\web\ServerErrorHttpException('Failed to delete the object for unknown reason.');
        }
        \app\modules\itrack\models\AuditLog::Audit(\app\modules\itrack\models\AuditOperation::OP_LOGOUT, 'Выход из системы', []);
        
        \Yii::$app->getResponse()->setStatusCode(204);
    }
    
    /**
     * Возвращает текущую версию api
     *
     * @return array
     */
    public function actionApiVersion()
    {
        $versionFilePath = \Yii::getAlias('@app') . DIRECTORY_SEPARATOR . 'version.txt';
        $error = '';
        $version = '';
        try {
            $version = file_get_contents($versionFilePath);
        } catch (\Exception $e) {
            $error = 'Не удалось получить версию api.';
        }
        
        return [
            'success' => ($error === '') ? true : false,
            'payload' => [
                'version' => trim($version),
            ],
            'error'   => $error,
        ];
    }
}
