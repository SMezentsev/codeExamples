<?php

namespace app\modules\itrack\models\exchange\exceptions;

class SerializeException extends \Exception
{
    public function __construct($message = null, $code = 0, Exception $previous = null) {
        parent::__construct(serialize($message), $code, $previous);
    }
}