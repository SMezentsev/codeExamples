<?php


namespace app\modules\itrack\Services\Generation\Interfaces;

use app\modules\itrack\models\Generation;
use app\modules\itrack\Services\Generation\DTO\GenerationDataDto;

/**
 * Interface GenerationServiceInterface
 */
interface GenerationServiceInterface
{
    /**
     * @param GenerationDataDto $generationDataDto
     * @return Generation
     */
    public function saveGenerationData(GenerationDataDto $generationDataDto): Generation;

    /**
     * @param GenerationDataDto $generationDataDto
     * @return Generation
     */
    public function createExtCodesGeneration(GenerationDataDto $generationDataDto): Generation;

    /**
     * @return void
     */
    public function flushTempData(): void;
}