<?php

namespace app\modules\itrack\components;

class pghelper
{
    /**
     * Alias for pgarr2arr
     *
     * @param string $value
     *
     * @return array
     */
    public static function decode(?string $value)
    : ?array
    {
        return static::pgarr2arr($value);
    }
    
    /**
     * Alias for pgarr2arr
     *
     * @param array $value
     *
     * @return string
     */
    public static function encode(array $value)
    : ?string
    {
        return static::arr2pgarr($value);
    }
    
    /**
     * Convert php array to Postgres array
     *
     * @param $value
     *
     * @return string
     */
    public static function arr2pgarr($value)
    {
        $parts = [];
        foreach ((array)$value as $inner) {
            if (is_array($inner)) {
                $parts[] = self::arr2pgarr($inner);
            } elseif ($inner === null) {
                $parts[] = 'NULL';
            } else {
                $parts[] = '"' . addcslashes($inner, "\"\\") . '"';
            }
        }
        
        return '{' . join(",", (array)$parts) . '}';
    }
    
    /**
     * Convert Postgres array to Php array
     *
     * @param      $s
     * @param int  $start
     * @param null $end
     *
     * @return array|null
     */
    public static function pgarr2arr($s, $start = 0, &$end = null)
    {
        if ($s instanceof \yii\db\ArrayExpression) {
            return $s->getValue();
        }
        if (is_object($s)) {
            return $s;
        }
        if (is_array($s)) {
            return $s;
        }
        
        if (empty($s) || $s[0] != '{') {
            return null;
        }
        $return = [];
        $string = false;
        $quote = '';
        $len = strlen($s);
        $v = '';
        for ($i = $start + 1; $i < $len; $i++) {
            $ch = $s[$i];
            
            if (!$string && $ch == '}') {
                if ($v !== '' || !empty($return)) {
                    if (!is_array($v) && !strcasecmp($v, "null")) {
                        $return[] = null;
                    } else {
                        $return[] = $v;
                    }
                }
                $end = $i;
                break;
            } elseif (!$string && $ch == '{') {
                $v = self::pgarr2arr($s, $i, $i);
            } elseif (!$string && $ch == ',') {
                if (!is_array($v) && !strcasecmp($v, "null")) {
                    $return[] = null;
                } else {
                    $return[] = $v;
                }
                $v = '';
            } elseif (!$string && ($ch == '"' || $ch == "'")) {
                $string = true;
                $quote = $ch;
            } elseif ($string && $ch == $quote && $s[$i - 1] == "\\") {
                $v = substr($v, 0, -1) . $ch;
            } elseif ($string && $ch == $quote && $s[$i - 1] != "\\") {
                $string = false;
            } else {
                $v .= $ch;
            }
        }
        
        return $return;
    }
    
}
