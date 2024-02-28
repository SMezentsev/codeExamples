<?php


namespace app\modules\itrack\components\auth;

use app\modules\itrack\models\User;
use yii\web\Request;

/**
 * Класс для работы с http авторизацией
 * для использования требует установка соответствия групп в конфигурации
 * params.php вида:
 * ['httpAuth']['groups']['массив соответствия групп ролям itrack']
 * в headers должны передаваться в зависимости от задачи определенные
 * наборы заголовков из констант
 * Class HttpAuth
 *
 * @package app\modules\itrack\components\auth
 */
class HttpAuth
{
    const HOST_HTTP_HEADER = 'X-Forwarded-Host';
    const IP_HTTP_HEADER = 'X-Forwarded-For';
    const LOGIN_HTTP_HEADER = 'X-SSO-RemoteUser';
    const GROUP_HTTP_HEADER = 'X-SSO-Membership';
    const EMAIL_HTTP_HEADER = 'X-SSO-PD-EMail';
    const FIRST_NAME_HTTP_HEADER = 'X-SSO-PD-FirstName';
    const LAST_NAME_HTTP_HEADER = 'X-SSO-PD-LastName';
    private $request;
    private $host;
    private $ip;
    private $login;
    private $group;
    private $email;
    private $firstName;
    private $lastName;
    private $user;
    
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user = null;
        $this->setAuthDataFromRequest();
        $this->checkHost();
    }
    
    
    public function checkHttpAuth()
    {
        if ($this->request->headers[self::LOGIN_HTTP_HEADER] !== null ||
            $this->request->headers[self::GROUP_HTTP_HEADER] !== null ||
            $this->request->headers[self::IP_HTTP_HEADER] !== null ||
            $this->request->headers[self::HOST_HTTP_HEADER] !== null ||
            $this->request->headers[self::EMAIL_HTTP_HEADER] !== null ||
            $this->request->headers[self::FIRST_NAME_HTTP_HEADER] !== null ||
            $this->request->headers[self::LAST_NAME_HTTP_HEADER] !== null) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Выполняет авторизацию по http заголовкам
     *
     * @return \app\modules\itrack\components\boxy\User|null
     * @throws HttpAuthHeadersException
     * @throws \ErrorException
     */
    public function doHttpAuth()
    {
        if ($this->login === null || $this->group === null) {
            throw new HttpAuthHeadersException('Не были переданы заголовки для авторизации: '
                . $this->login . ' ' . $this->group);
        }
        
        $this->setItrackGroup();
        
        $this->user = User::findByLogin($this->login);
        
        if ($this->user === null) {
            $this->registerNewUser();
        }
        
        $this->checkGroup();
        
        return $this->user;
    }
    
    /**
     * Устанавливает базовые данные для авторизации, регистрации, проверки доступа
     */
    private function setAuthDataFromRequest()
    {
        if ($this->request->headers[self::LOGIN_HTTP_HEADER] !== null &&
            $this->request->headers[self::GROUP_HTTP_HEADER] !== null) {
            $this->login = $this->request->headers[self::LOGIN_HTTP_HEADER];
            $this->group = $this->request->headers[self::GROUP_HTTP_HEADER];
        } else {
            $this->login = null;
            $this->group = null;
        }
        
        if ($this->request->headers[self::IP_HTTP_HEADER] !== null &&
            $this->request->headers[self::HOST_HTTP_HEADER] !== null) {
            $this->ip = $this->request->headers[self::IP_HTTP_HEADER];
            $this->host = $this->request->headers[self::HOST_HTTP_HEADER];
        } else {
            $this->ip = null;
            $this->host = null;
        }
        
        if ($this->request->headers[self::EMAIL_HTTP_HEADER] !== null &&
            $this->request->headers[self::FIRST_NAME_HTTP_HEADER] !== null &&
            $this->request->headers[self::LAST_NAME_HTTP_HEADER] !== null) {
            $this->email = $this->request->headers[self::EMAIL_HTTP_HEADER];
            $this->firstName = $this->request->headers[self::FIRST_NAME_HTTP_HEADER];
            $this->lastName = $this->request->headers[self::LAST_NAME_HTTP_HEADER];
        } else {
            $this->email = null;
            $this->firstName = null;
            $this->lastName = null;
        }
    }
    
    /**
     * Проверяет значения ip и host
     */
    private function checkHost()
    {
//        if ($this->ip === null || $this->host === null) {
//            throw new HttpAuthHeadersException('Неправильные данные ip или host');
//        }

//        if ($this->host !== $this->request->getHostName() &&  $this->ip !== $this->request->getUserIP()) {
//            throw new HttpAuthHeadersException('Неправильные данные ip или host');
//        }
    }
    
    /**
     * Создает нового пользователя
     *
     * @throws \ErrorException
     */
    private function registerNewUser()
    {
        if ($this->email === null || $this->firstName === null || $this->lastName === null) {
            throw new \ErrorException('Недостаточно данных для регистрации: '
                . $this->email . ' ' . $this->firstName . ' ' . $this->firstName);
        }
        
        $user = new User();
        $user->active = true;
        $user->login = $this->login;
        $user->email = $this->email;
        $user->fio = $this->lastName . ' ' . $this->firstName;
        $user->passwd = substr(str_shuffle(strtolower(sha1(rand() . time() . "asfasfa21412"))), 0, 20);
        
        if (!$user->save()) {
            throw new \ErrorException('Не удалось создать нового пользователя');
        }
    }
    
    /**
     * Проверяет наличие выбранной группы у пользователя при необходимости
     * заменяет ее или добавляет новому пользователю
     *
     * @throws \ErrorException
     */
    private function checkGroup()
    {
        if (!array_key_exists('httpAuth', \Yii::$app->params)) {
            throw new \ErrorException('Не установлена конфигурация http авторизации.');
        }
        
        $userRoles = \Yii::$app->authManager->getRolesByUser($this->user->id);
        $roleName = \Yii::$app->params['httpAuth']['groups'][$this->group];
        
        if (count($userRoles) === 0) {
            $this->setRole($roleName);
        } else {
            if (array_keys($userRoles)[0] !== $roleName) {
                $this->setRole($roleName);
            }
        }
    }
    
    /**
     * Разбивает список групп по разделителю и ищет первую попавшуюся группу для которой
     * установлено соответствие в файле конфигурации
     *
     * @throws \ErrorException
     */
    private function setItrackGroup()
    {
        $this->group = explode(';', $this->group);
        $itrackGroup = null;
        
        foreach ($this->group as $group) {
            if (array_key_exists($group, \Yii::$app->params['httpAuth']['groups'])) {
                $itrackGroup = $group;
            }
        }
        
        if ($itrackGroup === null) {
            throw new \ErrorException('Не найдено соответствие хотя бы одной группы роли itrack: ' . implode($this->group));
        }
        
        $this->group = $itrackGroup;
    }
    
    /**
     * Устанавливает роль пользователю
     *
     * @param $roleName
     *
     * @throws \ErrorException
     */
    private function setRole($roleName)
    {
        $role = \Yii::$app->authManager->getRole($roleName);
        
        if (!$role) {
            throw new \ErrorException("Роль с названием {$roleName} не найдена.");
        }
        
        $this->user->setRole($role);
    }
}