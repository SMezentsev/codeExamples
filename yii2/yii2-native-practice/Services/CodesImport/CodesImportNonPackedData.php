<?php


namespace app\modules\itrack\Services\CodesImport;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;
use app\modules\itrack\Services\Generation\GenerationService;
use app\modules\itrack\Services\Product\ProductService;

/**
 * Class CodesImportNonPackedData
 */
final class CodesImportNonPackedData extends CodesImportService
{
    /**
     * @var Models\CodeCollection|null
     */
    private $ssccCollection;
    /**
     * @var Models\CodeCollection|null
     */
    private $serialsCollection;

    /**
     * CodesImportNonPackedData constructor.
     * @param CodesImportDataDto $codesImportDataDTO
     * @param ProductService $productService
     * @param GenerationService $generationService
     */
    public function __construct(
        CodesImportDataDto $codesImportDataDTO,
        ProductService $productService,
        GenerationService $generationService
    )
    {
        parent::__construct($codesImportDataDTO, $productService, $generationService);
        $this->ssccCollection = $codesImportDataDTO->getSsccCollection();
        $this->serialsCollection = $codesImportDataDTO->getSerialsCollection();
    }

    public function import(): void
    {
        if ($this->defaultProduct === []) {
            throw new \ErrorException(\Yii::t('Не установлен продукт для импорта кодов'));
        }

        $this->productService->save($this->buildProductDataDTO($this->defaultProduct));
        $serialCodeRows = $this->serialsCollection->getFormattedCodesHierarchy($this->defaultProduct);
        $ssccCodeRows = $this->ssccCollection->getFormattedCodesHierarchy($this->defaultProduct);
        $serialCodeRows = $this->setProductUidToCodeRows($serialCodeRows);
        $ssccCodeRows = $this->setProductUidToCodeRows($ssccCodeRows, true);
        $cachedProductCollection = $this->productService->getCachedProducts();

        $transaction = \Yii::$app->db->beginTransaction();

        try {
            $this->importCodeRows($serialCodeRows, CodeType::CODE_TYPE_INDIVIDUAL, $cachedProductCollection);
            $this->importCodeRows($ssccCodeRows, CodeType::CODE_TYPE_GROUP);
            $this->generationService->flushTempData();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new \ErrorException(\Yii::t('app', 'Не удалось сохрать данные кодов в бд!'));
        }

    }

}