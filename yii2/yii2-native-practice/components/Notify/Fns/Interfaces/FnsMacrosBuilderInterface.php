<?php

namespace app\modules\itrack\components\Notify\Fns\Interfaces;

use app\modules\itrack\components\Notify\Fns\FnsMacros;
use app\modules\itrack\models\Fns;

interface FnsMacrosBuilderInterface
{
    public function build(Fns $fns):FnsMacros;
}