<?php

namespace app\modules\itrack\Handler;

use app\modules\itrack\components\Notify\Fns\Interfaces\FnsNotifyServiceInterface;
use app\modules\itrack\events\Fns\FnsNotifyEvent;

class FnsNotifyHandler
{
    /**
     * @var FnsNotifyServiceInterface
     */
    private $service;

    public function __construct(FnsNotifyServiceInterface $service)
    {
        $this->service = $service;
    }

    public function handle(FnsNotifyEvent $event)
    {
        $this->service->send($event->getFns());
    }
}