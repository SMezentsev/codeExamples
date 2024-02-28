<?php

namespace app\modules\itrack\components\Notify\Fns\Interfaces;

use app\modules\itrack\models\Fns;

/**
 * Interface FnsNotifyServiceInterface
 */
interface FnsNotifyServiceInterface
{
    /**
     * @param Fns $fns
     */
    public function send(Fns $fns): void;

    /**
     * @param Fns $fns
     * @return array
     */
    public function check(Fns $fns): array;
}