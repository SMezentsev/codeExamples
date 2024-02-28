<?php


namespace app\modules\itrack\Services\CodesImport;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;
use app\modules\itrack\Services\Generation\DTO\GenerationDataDto;
use app\modules\itrack\Services\Generation\GenerationService;
use app\modules\itrack\Services\Product\Models\ProductCollection;
use app\modules\itrack\Services\Product\ProductService;

/**
 * Сервис выполняет импорт упакованных кодов
 * Class CodesImportPackedData
 */
final class CodesImportPackedData extends CodesImportService
{
    /**
     * @var Models\CodeCollection
     */
    private $aggregatedData;

    /**
     * CodesImportPackedData constructor.
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
        $this->aggregatedData = $codesImportDataDTO->getAggregatedData();
    }

    /**
     * @throws \ErrorException
     * @throws \yii\db\Exception
     */
    public function import(): void
    {
        $products = $this->aggregatedData->getCodesProducts();
        $this->importProducts($products);
        $codeRows = $this->aggregatedData->getFormattedCodesHierarchy($this->defaultProduct);
        $codeRows = $this->setProductUidToCodeRows($codeRows);
        $cachedProductCollection = $this->productService->getCachedProducts();

        $transaction = \Yii::$app->db->beginTransaction();

        try {
            $this->importCodeRows($codeRows, CodeType::CODE_TYPE_INDIVIDUAL, $cachedProductCollection);
            $this->importCodeRows($codeRows, CodeType::CODE_TYPE_GROUP);
            $this->generationService->flushTempData();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw new \ErrorException(\Yii::t('app', 'Не удалось сохрать данные кодов в бд!'));
        }
    }

    /**
     * @param array $products
     * @throws \ErrorException
     */
    private function importProducts(array $products): void
    {
        foreach ($products as $product) {
            $this->productService->save($this->buildProductDataDTO($product));
        }
    }
}