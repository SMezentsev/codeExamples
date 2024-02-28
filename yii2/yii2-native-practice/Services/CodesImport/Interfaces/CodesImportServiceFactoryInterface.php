<?php


namespace app\modules\itrack\Services\CodesImport\Interfaces;


use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;

/**
 * Interface CodesImportServiceFactoryInterface
 */
interface CodesImportServiceFactoryInterface
{
    /**
     * @param int $mode
     * @param CodesImportDataDto $codesDataDTO
     * @return CodesImportServiceInterface
     */
    public function getCodesImportService(int $mode, CodesImportDataDto $codesDataDTO): CodesImportServiceInterface;
}