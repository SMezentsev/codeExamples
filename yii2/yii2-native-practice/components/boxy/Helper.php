<?php
/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 06.05.15
 * Time: 15:37
 */

namespace app\modules\itrack\components\boxy;


class Helper
{
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
    
    public static function pgarr2arr($str, $start = 0)
    {
        static $p;
        $charAfterSpaces = function ($str, &$p) {
            $p += strspn($str, " \t\r\n", $p);
            
            return substr($str, $p, 1);
        };
        
        if ($start == 0) {
            $p = 0;
        }
        $result = [];
        
        // Leading "{".
        $c = $charAfterSpaces($str, $p);
        if ($c != '{') {
            return;
        }
        $p++;
        
        // Array may contain:
        // - "-quoted strings
        // - unquoted strings (before first "," or "}")
        // - sub-arrays
        while (1) {
            $c = $charAfterSpaces($str, $p);
            
            // End of array.
            if ($c == '}') {
                $p++;
                break;
            }
            
            // Next element.
            if ($c == ',') {
                $p++;
                continue;
            }
            
            // Sub-array.
            if ($c == '{') {
                $result[] = self::pgarr2arr($str, $p);
                continue;
            }
            
            // Unquoted string.
            if ($c != '"') {
                $len = strcspn($str, ",}", $p);
                $v = stripcslashes(substr($str, $p, $len));
                if (!strcasecmp($v, "null")) {
                    $result[] = null;
                } else {
                    $result[] = $v;
                }
                $p += $len;
                continue;
            }
            
            // Quoted string.
            $m = null;
            if (preg_match('/" ((?' . '>[^"\\\\]+|\\\\.)*) "/Asx', $str, $m, 0, $p)) {
                $result[] = stripcslashes($m[1]);
                $p += strlen($m[0]);
                continue;
            }
        }
        
        return $result;
    }
    
    public static function sortAndFilterQuery(\yii\db\QueryInterface &$query, $model = null)
    {
        $model = (is_string($model)) ? new $model : $model;
        
        $sort = \Yii::$app->request->getQueryParam('sort');
        if ($sort) {
            $key = (0 === strpos($sort, '-')) ? substr($sort, 1) : $sort;
            $fKey = null;
            
            if ($model instanceof \app\modules\itrack\components\boxy\ActiveRecord) {
                $fKey = $model->getAttributeRealName($key);
            } elseif ($model instanceof \yii\db\ActiveRecord) {
                if ($model->getTableSchema()->getColumn($key)) {
                    $fKey = $key;
                }
            } elseif ($model instanceof \yii\base\Model) {
                $fKey = (isset($model->attributes()[$key])) ? $model->attributes()[$key] : null;
            } else {
                $fKey = $key;
            }
            
            if ($fKey) {
                $order = (0 === strpos($sort, '-')) ? "$fKey DESC" : "$fKey ASC";
                $query->orderBy($order);
            }
        }
        
        $filter = \Yii::$app->request->getQueryParam('filter');
        if (is_array($filter)) {
            foreach ($filter as $key => $value) {
                $fKey = null;
                $type = null;
                if ($model instanceof \app\modules\itrack\components\boxy\ActiveRecord) {
                    $fKey = $model->getAttributeRealName($key);
                } elseif ($model instanceof \yii\db\ActiveRecord) {
                    $fKey = $model->getTableSchema()->getColumn($key);
                    $type = $fKey->phpType;
                } elseif ($model instanceof \yii\base\Model) {
                    $fKey = (isset($model->attributes()[$key])) ? $model->attributes()[$key] : null;
                } else {
                    $fKey = $key;
                }
                
                $query->andFilterWhere(['ilike', $fKey, $value]);
            }
        }
    }
}