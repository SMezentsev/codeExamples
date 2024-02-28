<?php


namespace app\modules\itrack\Services\CodesImport\Interfaces;

use app\modules\itrack\models\ProductionOrder;
use app\modules\itrack\Services\CodesImport\Models\CodeCollection;

/**
 * Interface CodesImportDataParserInterface
 */
interface CodesImportDataParserInterface
{
    /**
     * @param array $importData
     */
    public function parse(array $importData): void;

    /**
     * @return bool|null
     */
    public function getIsTransfer(): ?bool;

    /**
     * @return ProductionOrder|null
     */
    public function getProductionOrder(): ?ProductionOrder;

    /**
     * @return array|null
     */
    public function getProductData(): ?array;

    /**
     * @return string|null
     */
    public function getSubjectData(): ?string;

    /**
     * @return CodeCollection
     */
    public function getAggregatedDataCollection(): CodeCollection;

    /**
     * @return CodeCollection
     */
    public function getSsccDataCollection(): CodeCollection;

    /**
     * @return CodeCollection
     */
    public function getSerialDataCollection(): CodeCollection;
}