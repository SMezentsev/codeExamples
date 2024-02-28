<?php

namespace app\modules\itrack;

use app\modules\itrack\components\Notify\Fns\FnsMacrosBuilder;
use app\modules\itrack\components\Notify\Fns\FnsNotifyService;
use app\modules\itrack\components\Notify\Fns\Interfaces\FnsMacrosBuilderInterface;
use app\modules\itrack\components\Notify\Fns\Interfaces\FnsNotifyServiceInterface;
use app\modules\itrack\events\Fns\FnsNotifyEvent;
use app\modules\itrack\Handler\FnsNotifyHandler;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * @OA\OpenApi(
 *     @OA\Server(
 *         url="/",
 *         description="Описание АПИ"
 *     ),
 *     @OA\Info(
 *         version="1.0.0",
 *         title="iTrack API",
 *         description="описание методов",
 *     ),
 * )
 *
 * @OA\SecurityScheme(
 *   securityScheme="access-token",
 *   type="apiKey",
 *   name="access-token",
 *   in="query"
 * )
 */

/**
 * @OA\Get(
 *     path="/",
 *     description="Home page",
 *     @OA\Response(response="default", description="Welcome page")
 * )
 */
class Module extends BaseModule implements BootstrapInterface
{
    
    public $defCapacity;
    public $defPrefix;
    public $ourGtins;
    
    public function init()
    {
        parent::init();
        
        try {
            \Yii::$app->db->open();
        } catch (\Exception $ex) {
            throw new \yii\web\BadRequestHttpException('Ошибка соединения с базой данных. Повторите попытку позже или обратитесь к администраторам');
        }
        
        $res = \Yii::$app->db->createCommand("select array_agg(gtin) as g from nomenclature
                                                        left join objects on nomenclature.object_uid = objects.id
                                                        where object_uid is null or objects.external=false
                                            ")->queryOne();
        $this->ourGtins = components\pghelper::pgarr2arr($res["g"]);
        if (is_null($this->ourGtins)) {
            $this->ourGtins = [];
        }
    }

    public function bootstrap($app)
    {
        $this->di();
        $this->addEventListeners();
    }


    private function di():void
    {
        Yii::$container->setSingleton(FnsNotifyServiceInterface::class, [
            'class' => FnsNotifyService::class,
        ]);

        Yii::$container->setSingleton(FnsMacrosBuilderInterface::class, [
            'class' => FnsMacrosBuilder::class,
        ]);
    }


    private function addEventListeners():void
    {
        Event::on(FnsNotifyEvent::class, FnsNotifyEvent::EVENT_SEND_NOTIFY, [Yii::$container->get
        (FnsNotifyHandler::class), 'handle']);
    }
}