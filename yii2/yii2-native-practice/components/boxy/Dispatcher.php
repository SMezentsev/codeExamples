<?php
/**
 * @link      http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license   http://www.yiiframework.com/license/
 */

namespace app\modules\itrack\components\boxy;

//use \yii\base\Component;
//use \yii\base\ErrorHandler;

class Dispatcher extends \yii\log\Dispatcher
{
    
    /**
     * @var Logger the logger.
     */
    private $_logger;
    
    /**
     * Gets the connected logger.
     * If not set, [[\Yii::getLogger()]] will be used.
     *
     * @return Logger the logger.
     * @property Logger the logger. If not set, [[\Yii::getLogger()]] will be used.
     */
    public function getLogger()
    {
        if ($this->_logger === null) {
            \Yii::setLogger(\Yii::createObject(Logger::class));
            
            $this->setLogger(\Yii::getLogger());
        }
        
        return $this->_logger;
    }
    
    /**
     * Sets the connected logger.
     *
     * @param Logger $value the logger.
     */
    public function setLogger($value)
    {
        $this->_logger = $value;
        $this->_logger->dispatcher = $this;
    }
    
}
