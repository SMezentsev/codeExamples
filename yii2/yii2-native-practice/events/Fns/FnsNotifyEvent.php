<?php

namespace app\modules\itrack\events\Fns;

use app\modules\itrack\models\Fns;
use yii\base\Event;

class FnsNotifyEvent extends Event
{
    const EVENT_SEND_NOTIFY = 'sendNotify';

    /**
     * @var Fns
     */
    private $fns;

    public function setFns(Fns $fns):void
    {
        $this->fns = $fns;
    }

    public function getFns():Fns
    {
        return $this->fns;
    }
}