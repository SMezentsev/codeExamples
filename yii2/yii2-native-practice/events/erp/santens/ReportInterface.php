<?php

namespace app\modules\itrack\events\erp\santens;

use app\modules\itrack\models\Generation;

/**
 * Интерфейс для отчетов генерируемых через события
 * Interface ReportInterface
 *
 * @package app\modules\itrack\events\erp
 */
interface ReportInterface
{
    /**
     * Метод генерации файла отчета и запись его на диск
     *
     * @param Generation $generation
     *
     * @return mixed
     */
    function generateReport($generation);
    
    /**
     * Метод удаления отчета с диска в случае ошибок при генерации
     *
     * @param string $fileName
     *
     * @return mixed
     */
    function deleteReport($fileName);
}