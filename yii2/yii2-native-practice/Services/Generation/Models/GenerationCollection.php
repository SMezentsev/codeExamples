<?php


namespace app\modules\itrack\Services\Generation\Models;

use app\modules\itrack\models\Generation;
use yii\base\InvalidParamException;

/**
 * Class GenerationCollection
 */
class GenerationCollection extends \SplObjectStorage
{
    /**
     * @param object $generation
     * @param null $data
     */
    public function attach($generation, $data = null): void
    {
        if (is_a($generation, Generation::class)) {
            parent::attach($generation, $data);
        } else {
            throw new InvalidParamException('Ожидается объект типа Generation!');
        }
    }
}