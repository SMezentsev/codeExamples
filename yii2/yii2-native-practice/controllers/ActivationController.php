<?php

declare(strict_types=1);

namespace app\modules\itrack\controllers;

use app\modules\itrack\components\boxy\ControllerTrait;
use yii\rest\Controller;

/**
 * @OA\Post(
 *     path="/assign",
 *     tags={"Операции с кодами"},
 *     description="Присвоение",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="code", type="string", example="123123123"),
 *                 @OA\Property(property="serial", type="string", example="a123123"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/pack",
 *     tags={"Операции с кодами"},
 *     description="Упаковка",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="field", type="string", example="codes"),
 *                 @OA\Property(property="message", type="string", example="Код уже используется"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/gofraAdd",
 *     tags={"Операции с кодами"},
 *     description="Добавление в гофрокороб",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="field", type="string", example="codes"),
 *                 @OA\Property(property="message", type="string", example="Код уже привязан к гофрокоробу"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/gofraAddUni",
 *     tags={"Операции с кодами"},
 *     description="Добавление в гофрокороб однородное",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */
/**
 * @OA\Post(
 *     path="/paleta",
 *     tags={"Операции с кодами"},
 *     description="Создание паллеты",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/paletaUni",
 *     tags={"Операции с кодами"},
 *     description="Создание паллеты однородное",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/paletaAdd",
 *     tags={"Операции с кодами"},
 *     description="Добавление гофрокороба в паллету",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/paletaAddUni",
 *     tags={"Операции с кодами"},
 *     description="Добавление гофрокороба в паллету однородное",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="123123123"),
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/block",
 *     tags={"Операции с кодами"},
 *     description="Блокировка",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="note", type="string", example="Причина"),
 *                 @OA\Property(property="qrcode", type="string", example="Опционально"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/unblock",
 *     tags={"Операции с кодами"},
 *     description="Разблокировка",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="note", type="string", example="Причина"),
 *                 @OA\Property(property="qrcode", type="string", example="Опционально"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/removeTSD",
 *     tags={"Операции с кодами"},
 *     description="Утилизация",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="note", type="string", example="Причина"),
 *                 @OA\Property(property="qrcode", type="string", example="Опционально"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/withdrawal",
 *     tags={"Операции с кодами"},
 *     description="Изъятие кода из группового",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="note", type="string", example="Причина"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/incom",
 *     tags={"Операции с кодами"},
 *     description="Приемка на производстве",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="invoice", type="string", example="1"),
 *                 @OA\Property(property="invoiceDate", type="string", example="2020-03-11"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/incomLog",
 *     tags={"Операции с кодами"},
 *     description="Приемка в логистическом центре",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="invoice", type="string", example="1"),
 *                 @OA\Property(property="invoiceDate", type="string", example="2020-03-11"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/outcom",
 *     tags={"Операции с кодами"},
 *     description="Перемещение на производстве",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="invoice", type="string", example="1"),
 *                 @OA\Property(property="invoiceDate", type="string", example="2020-03-11"),
 *                 @OA\Property(property="object_uid", type="integer", example=2),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/outcomLog",
 *     tags={"Операции с кодами"},
 *     description="Перемещение в логистическом центре",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="invoice", type="string", example="1"),
 *                 @OA\Property(property="invoiceDate", type="string", example="2020-03-11"),
 *                 @OA\Property(property="object_uid", type="integer", example=2),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/outcomRetail",
 *     tags={"Операции с кодами"},
 *     description="Отгрузка контрагенту на производстве",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="invoice", type="string", example="1"),
 *                 @OA\Property(property="invoiceDate", type="string", example="2020-03-11"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/outcomRetailLog",
 *     tags={"Операции с кодами"},
 *     description="Отгрузка контрагенту в логистическом центре",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="invoice", type="string", example="1"),
 *                 @OA\Property(property="invoiceDate", type="string", example="2020-03-11"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/unGroup",
 *     tags={"Операции с кодами"},
 *     description="Разгруппировка",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="groupCode", type="string", example="1231231234"),
 *                 @OA\Property(property="note", type="string", example="Причина"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */

/**
 * @OA\Post(
 *     path="/return",
 *     tags={"Операции с кодами"},
 *     description="Возврат",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="codes", type="array", @OA\Items(
 *                     @OA\Property(property="code", type="string", example="101asd123")
 *                 )),
 *                 @OA\Property(property="note", type="string", example="Причина"),
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="",
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             example={
 *                 @OA\Property(property="status", type="string", example="ok"),
 *                 @OA\Property(property="data", type="string", example="Ok"),
 *             }
 *         )
 *     ),
 *     security={{"access-token" = {}}}
 * )
 */
class ActivationController extends Controller
{
    use ControllerTrait;

    public $modelClass = 'app\modules\itrack\models\CodeStatusFunction';

    public $actionClass = 'app\modules\itrack\controllers\actions\CodeStatusAction';

    public function actions()
    {
        return [
            'remove' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'remove',
            ],
            'block' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,

                'scenario' => 'block',
            ],
            'unblock' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'unblock',
            ],
            'pack' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'pack',
            ],
            'packFull' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'packFull',
            ],
            'paletaUni' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'paletaUni',
            ],
            'paleta' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'paleta',
            ],
            'paletaAdd' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'paletaAdd',
            ],
            'paletaAddUni' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'paletaAddUni',
            ],
            'gofraAdd' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'gofraAdd',
            ],
            'gofraAddUni' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'gofraAddUni',
            ],
            'incom' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'incom',
            ],
            'incomLog' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'incomLog',
            ],
            'outcom' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'outcom',
            ],
            'outcomLog' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'outcomLog',
            ],
            'outcomRetail' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'outcomRetail',
            ],
            'outcomRetailLog' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'outcomRetailLog',
            ],
            'unGroup' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'unGroup',
            ],
            'return' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'returned',
            ],
            'returnExt' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'returnedExt',
            ],
            'removeTSD' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'removeTSD',
            ],
            'withdrawal' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'withdrawal',
            ],
            'back' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'back',
            ],
            'transfer' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'transfer',
            ],
            'incomeExt' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'incomeExt',
            ],
            'incomeReverse' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'incomeReverse',
            ],
            'assign' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'assign',
            ],
            'relabel' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'relabel',
            ],
            'l3' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'l3',
            ],
            'l3Uni' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'l3Uni',
            ],
            'l3Add' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'l3Add',
            ],
            'l3AddUni' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'l3AddUni',
            ],
            'refuse' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'refuse',
            ],
            'serialize' => [
                'class' => $this->actionClass,
                'modelClass' => $this->modelClass,
                'scenario' => 'serialize',
            ],
        ];
    }
}
