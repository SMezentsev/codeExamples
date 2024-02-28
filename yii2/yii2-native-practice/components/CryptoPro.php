<?php

namespace app\modules\itrack\components;

class CryptoPro
{
    
    const TMP_FILE_PATH_TPL = '/tmp/cp_sign_%s';
    
    /**
     * Подпись файла с помошью
     * /opt/cprocsp/bin/amd64/csptestf
     *
     * @param string $filePath
     * @param string $sigPath
     *
     * @return bool|string
     * @throws \Exception
     */
    public static function signFile($filePath, $signPath)
    {
        if (file_exists($signPath)) {
            throw new \Exception('Signature path (' . $signPath . ') alredy exists');
        }
        
        $ismParams = ISMarkirovka::getISMParams();
        $bin = $ismParams['csptestf_bin'];
        
        $alg = $ismParams['token_alg'] ?? '';
        $params = ' -sfsign -sign -detached -add -in ' . $filePath . ' -out ' . $signPath . ' -my ' . $ismParams['key_email'] . ((!empty($alg)) ? ' -alg ' . $alg : '');
        $command = $bin . $params;
        //$command = 'base64 < '.$filePath.' > '.$signPath.'';
        
        $res = system($command);
        
        return $res;
    }
    
    /**
     * @param $data
     * @param $fileIdent
     * @param $connectionId
     *
     * @return false|string
     * @throws \Exception
     */
    public static function signString($data, $fileIdent, $connectionId)
    {
        $ism = new ISMarkirovka($connectionId);
        $ismParams = $ism->getISMParams();
        
        /**
         * Обозначаем пути до файлов документа и подписи
         * (софтина подписи работает с файлами - как на входе
         *  так и на выходе)
         */
        $filePath = sprintf(CryptoPro::TMP_FILE_PATH_TPL, $fileIdent . '.xml');
        $signPath = sprintf(CryptoPro::TMP_FILE_PATH_TPL, $fileIdent . '.xml.p7s');
        $outputPath = sprintf(CryptoPro::TMP_FILE_PATH_TPL, $fileIdent . '.out');
        file_put_contents($filePath, $data);
        
        if (isset($ismParams['sign_mode']) && $ismParams['sign_mode'] == 'remote') {
            /* в случае подписи на удаленной машине - копируем файл на удаленный хост */
            $scpCommand = 'scp -o StrictHostKeychecking=no -P ' . $ismParams['sign_remote_port'] . ' ' . $filePath . ' ' . $ismParams['sign_remote_ssh'] . ':' . $filePath;
            echo "\n\n\nRUN: " . $scpCommand . "\n\n";
            $res = system($scpCommand . ' >' . $outputPath . ' 2>' . $outputPath, $retval);
            if (!empty($retval)) {
                $err = file_get_contents($outputPath);
                @unlink($outputPath);
                @unlink($filePath);
                @unlink($signPath);
                throw new \Exception($err, 101);
            }
        }
        
        $bin = $ismParams['csptestf_bin'];
        $alg = $ismParams['token_alg'] ?? 'GOST94_256';
        $params = ' -sfsign -sign -detached -add -in ' . $filePath . ' -out ' . $signPath . ' -my ' . $ismParams['key_email'] . ' -alg ' . $alg . ' -password ' . $ismParams['key_password'];
        $command = $bin . $params;
        
        if (isset($ismParams['sign_mode']) && $ismParams['sign_mode'] == 'remote') {
            $command = 'ssh -o StrictHostKeychecking=no -p ' . $ismParams['sign_remote_port'] . ' ' . $ismParams['sign_remote_ssh'] . ' ' . $command;
        }
        
        echo "\n\n\nRUN: " . $command . "\n\n";
        $res = system($command . ' >>' . $outputPath . ' 2>>' . $outputPath, $retval);
        if (!empty($retval)) {
            $err = file_get_contents($outputPath);
            @unlink($outputPath);
            @unlink($filePath);
            @unlink($signPath);
            throw new \Exception($err, 101);
        }
        
        if (isset($ismParams['sign_mode']) && $ismParams['sign_mode'] == 'remote') {
            /* если подписывали удаленно - забираем результат */
            $scpCommand = 'scp -o StrictHostKeychecking=no -P ' . $ismParams['sign_remote_port'] . ' ' . $ismParams['sign_remote_ssh'] . ':' . $signPath . ' ' . $signPath;
            echo "\n\n\nRUN: " . $scpCommand . "\n\n";
            $res = system($scpCommand . ' >>' . $outputPath . ' 2>>' . $outputPath, $retval);
            if (!empty($retval)) {
                $err = file_get_contents($outputPath);
                @unlink($outputPath);
                @unlink($filePath);
                @unlink($signPath);
                throw new \Exception($err, 101);
            }
        }
        
        $sign = file_get_contents($signPath);
        unlink($filePath);
        unlink($signPath);
        unlink($outputPath);
        
        return $sign;
    }
}