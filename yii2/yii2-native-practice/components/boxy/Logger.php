<?php

namespace app\modules\itrack\components\boxy;


class Logger extends \yii\log\Logger
{
    /**
     * Flushes log messages from memory to targets.
     *
     * @param boolean $final whether this is a final call during a request.
     */
    public function flush($final = false)
    {
        if ($final) {
            $duration = \Yii::$app->formatter->asDecimal(microtime(true) - YII_BEGIN_TIME, 4);
            
            $debug_id = '';
            $timeLimit = 1;
            
            if ($debug = \Yii::$app->getModule('debug')) {
                $debug_id = (isset($debug->logTarget) && isset($debug->logTarget->tag)) ? $debug->logTarget->tag
                    : 'undefined';
            }
            
            /** @var \yii\boxy_debug\Module $overhead */
            if ($overhead = \Yii::$app->getModule('overhead')) {
                $timeLimit = $overhead->timeLimit;
            }
            
            if ($duration >= $timeLimit) {
                $memory = sprintf('%.1f MB', memory_get_peak_usage() / 1048576);
                
                \Yii::info(implode('   ', [
                    $debug_id,
                    \Yii::$app->request->method ?? '',
                    $duration . ' s',
                    $memory,
                    \Yii::$app->request->url ?? '',
                ]), 'overhead');
            }
        }
        
        parent::flush($final);
    }
}
