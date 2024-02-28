<?php


namespace app\modules\itrack\Services\CodesImport\Interfaces;

/**
 * Class CodesImportServiceInterface
 */
interface CodesImportServiceInterface
{
    /**
     * Основной метод импорта кодов
     * @return void
     */
    public function import(): void;
}