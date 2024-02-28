<?php

namespace app\modules\itrack\components;

class BayerCryptoPro extends CryptoPro
{
    
    const TMP_FILE_PATH_TPL = '/src/runtime/bayer/';
    
    public static function signString($data, $fileIdent, $connectionId)
    {
        $sign = null;
        
        if (!is_dir(self::TMP_FILE_PATH_TPL)) {
            return $sign;
        }
        
        //проверка ошибок
        if (file_exists(self::TMP_FILE_PATH_TPL . 'ERR/' . $fileIdent)) {
            @unlink(self::TMP_FILE_PATH_TPL . 'ERR/' . $fileIdent);
            throw new \Exception('Ошибка формирования подписи', 1);
        }
        
        
        //проверяем а вдруг мы заказывали ранее и подпись готова
        if (file_exists(self::TMP_FILE_PATH_TPL . 'OUT/' . $fileIdent . '.p7s')) {
            $sign = file_get_contents(self::TMP_FILE_PATH_TPL . 'OUT/' . $fileIdent . '.p7s');
            if (!empty($sign)) {
                @unlink(self::TMP_FILE_PATH_TPL . 'OUT/' . $fileIdent . '.p7s');
                
                return $sign;
            } else {
                return null;
            }  //файл создан - но пустой - ждем еще
        }
        
        //проверяем создавали ли мы заяку уже
        if (file_exists(self::TMP_FILE_PATH_TPL . 'IN/' . $fileIdent)) {
            return null;  //ждем еще
        }
        
        
        file_put_contents(self::TMP_FILE_PATH_TPL . 'IN/' . $fileIdent, $data);
        
        return null;
    }
    
}
