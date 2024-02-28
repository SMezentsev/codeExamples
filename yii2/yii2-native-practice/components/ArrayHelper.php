<?php

declare(strict_types=1);

namespace app\modules\itrack\components;

use ArrayObject;

/**
 * Class ArrayFormatterHelper.
 */
class ArrayHelper
{
    public static function arr2str(array $data): string
    {
        if (1 === \count($data)) {
            return $data[0];
        }

        return '[' . implode(', ', $data) . ']';
    }

    public static function cloneArray(array $array): array
    {
        $result = new ArrayObject($array);
        return $result->getArrayCopy();
    }
}
