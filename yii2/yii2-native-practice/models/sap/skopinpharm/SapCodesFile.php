<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use app\modules\itrack\models\Generation;
use Yii;
use yii\base\Model;
use app\modules\itrack\models\FnsOcs;

/**
 * Класс выполняет функции поиск файла с кодами и загружает их
 * Class SapLogWriter
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class SapCodesFile extends Model
{
    private $codesDir = '/src/runtime/skopinCodes/';
    private $codesDirArchive = '/src/runtime/skopinCodes/Archive/';

    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    /**
     * Попытка получить коды из Файла
     * @param Generation $gen
     * @return array
     */
    public function checkCodes(Generation $gen)
    {
        $files = $this->getCodeFiles();
        foreach ($files as $file) {
            if ($this->checkFileForGeneration($file, $gen)) {
                $codes = $this->getCodes($file);
                rename($file, $this->codesDirArchive . basename($file));

                return $codes;
            }
        }

        return [];
    }

    /**
     * список файлов xml из каталога
     * @return array
     */
    private function getCodeFiles(): array
    {
        $files = [];
        if (is_dir($this->codesDir)) {
            if ($dh = opendir($this->codesDir)) {
                while (($file = readdir($dh)) !== false) {
                    if (preg_match('#.*\.xml#si', $file)) {
                        $files[] = $this->codesDir . $file;
                    }
                }
                closedir($dh);
            }
        }

        return $files;
    }

    /**
     * проверка файла генерации на совпадение с генерацией
     * @param string $filename
     * @param Generation $generation
     * @return bool
     */
    private function checkFileForGeneration(string $filename, Generation $generation)
    {
        $xml = new \SimpleXMLElement(file_get_contents($filename));

        $gtin = '';
        foreach ($xml->ObjectKey as $key) {
            if ($key->Name == 'GTIN') {
                $gtin = $key->Value;
                break;
            }
        }

        $size = count($xml->SerialNumber);

        if ($generation->cnt == $size && $generation->product->nomenclature->gtin == $gtin) {
            return true;
        }

        return false;
    }

    /**
     * Забираем из xml SerialNumber
     * @param $filename
     * @return array
     */
    private function getCodes($filename): array
    {
        $xml = new \SimpleXMLElement(file_get_contents($filename));

        return (array)$xml->SerialNumber;
    }
}