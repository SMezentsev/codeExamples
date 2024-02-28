<?php


namespace app\modules\itrack\Services\CodesImport;

use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;
use app\modules\itrack\Services\CodesImport\Interfaces\CodesImportServiceFactoryInterface;
use app\modules\itrack\Services\CodesImport\Interfaces\CodesImportServiceInterface;

/**
 * Фабрика создает объект сервиса нужного типа
 * CodesImportPackedData - производит импрорт иерархии уже упакованных кодов
 * CodesImportNonPackedData - производит импорт неупакованных кодов
 * Class CodesImportServiceFactory
 */
class CodesImportServiceFactory implements CodesImportServiceFactoryInterface
{
    public const IMPORT_PACKED_DATA = 1;
    public const IMPORT_NON_PACKED_DATA = 2;

    /**
     * @param int $mode
     * @param CodesImportDataDto $codesDataDTO
     * @return CodesImportServiceInterface
     * @throws \ErrorException
     * @throws \yii\base\InvalidConfigException
     */
    public function getCodesImportService(int $mode, CodesImportDataDto $codesDataDTO): CodesImportServiceInterface
    {
        switch ($mode) {
            case self::IMPORT_PACKED_DATA:
                return \Yii::createObject(CodesImportPackedData::class, [$codesDataDTO]);
            case self::IMPORT_NON_PACKED_DATA:
                return \Yii::createObject(CodesImportNonPackedData::class, [$codesDataDTO]);
            default:
                throw new \ErrorException(\Yii::t('app', 'Выбранный режим работы не поддерживается'));
        }
    }
}