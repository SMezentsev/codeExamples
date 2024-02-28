<?php

namespace app\modules\itrack\Services\CodesImport;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;
use app\modules\itrack\Services\Generation\DTO\GenerationDataDto;
use app\modules\itrack\Services\Generation\GenerationService;
use app\modules\itrack\Services\Product\DTO\ProductDataDto;
use app\modules\itrack\Services\Product\Models\ProductCollection;
use app\modules\itrack\Services\Product\ProductService;
use yii\base\Model;
use app\modules\itrack\models\ProductionOrder;
use app\modules\itrack\Services\CodesImport\Interfaces\CodesImportServiceInterface;

/**
 * Базовый класс для сервиса импорта кодов
 * Class CodesImportService
 */
abstract class CodesImportService extends Model implements CodesImportServiceInterface
{
    /**
     * @var bool
     */
    protected $isTransfer;
    /**
     * @var ProductionOrder
     */
    protected $productionOrder;
    /**
     * @var Facility
     */
    protected $object;
    /**
     * @var array|null
     */
    protected $defaultProduct;
    /**
     * @var User
     */
    protected $user;
    /**
     * @var ProductService
     */
    protected $productService;
    /**
     * @var GenerationService
     */
    protected $generationService;

    /**
     * CodesImportService constructor.
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
        $this->isTransfer = $codesImportDataDTO->getIsTransfer();
        $this->productionOrder = $codesImportDataDTO->getProductionOrder();
        $this->object = $codesImportDataDTO->getObject();
        $this->defaultProduct = $codesImportDataDTO->getProduct();
        $this->user = $codesImportDataDTO->getUser();
        $this->productService = $productService;
        $this->generationService = $generationService;
    }

    /**
     * @param array $productData
     * @return ProductDataDto
     * @throws \Exception
     */
    protected function buildProductDataDTO(array $productData): ProductDataDto
    {
        $productDataDTO = new ProductDataDto();
        $productDataDTO->setGtin($productData['gtin']);
        $productDataDTO->setSeries($productData['series']);
        $productDataDTO->setProductionDate($productData['cdate']);
        $productDataDTO->setExpirationDate($productData['expDate']);

        return $productDataDTO;
    }

    /**
     * @param array $codeRows
     * @param int $codeType
     * @param ProductCollection|null $productCollection
     * @throws \ErrorException
     */
    protected function importCodeRows(array $codeRows, int $codeType, ProductCollection $productCollection = null): void
    {
        switch ($codeType) {
            case CodeType::CODE_TYPE_INDIVIDUAL:
                foreach ($productCollection as $product) {
                    $generationDataDto = new GenerationDataDto();
                    $generationDataDto->setProductId($product->id);
                    $generationDataDto->setCodes($this->filterCodeRowsByParams($codeRows, $codeType, $product->id));
                    $generationDataDto->setCodeType($codeType);
                    $this->createGeneration($generationDataDto);
                }
                break;
            case CodeType::CODE_TYPE_GROUP:
                $generationDataDto = new GenerationDataDto();
                $generationDataDto->setCodes($this->filterCodeRowsByParams($codeRows, $codeType));
                $generationDataDto->setCodeType($codeType);
                $this->createGeneration($generationDataDto);
                break;
            default:
                throw new \ErrorException(\Yii::t('app', 'Данный тип кода не поддерживается!'));
        }
    }

    protected function createGeneration(GenerationDataDto $generationDataDto): void
    {
        $generationDataDto->setUserId($this->user->id);
        $generationDataDto->setObjectId($this->object->id);
        $generationDataDto->setProductionOrder($this->productionOrder);

        $this->generationService->saveGenerationData($generationDataDto);
    }

    /**
     * @param array $codeRows
     * @return array
     * @throws \ErrorException
     */
    protected function setProductUidToCodeRows(array $codeRows, $isEmptyGroupCodes = false): array
    {
        if (count($codeRows) === 0) {
            throw new \ErrorException(\Yii::t('app', 'Массив с кодами для импорта пуст!'));
        }

        foreach ($codeRows as $key => $codeRow) {

            if ($codeRow['product_uid'] === null) {
                continue;
            }

            if ($isEmptyGroupCodes) {
                $codeRows[$key]['product_uid'] = null;
            } else {
                $product = $this->productService->findByProductData($this->buildProductDataDTO($codeRow['product_uid']));
                $codeRows[$key]['product_uid'] = $product->id;
            }
        }

        return $codeRows;
    }

    /**
     * @param array $codeRows
     * @param int $codeType
     * @param int|null $productId
     * @return array
     */
    protected function filterCodeRowsByParams(array $codeRows, int $codeType, int $productId = null): array
    {
        $filteredCodeRows = [];

        foreach ($codeRows as $codeRow) {
            if ($codeRow['code_type'] === $codeType && $codeType === CodeType::CODE_TYPE_INDIVIDUAL) {
                if ($codeRow['product_uid'] === $productId) {
                    $filteredCodeRows[] = $codeRow;
                }
            } elseif ($codeRow['code_type'] === $codeType && $codeType === CodeType::CODE_TYPE_GROUP) {
                $filteredCodeRows[] = $codeRow;
            }
        }

        return $filteredCodeRows;
    }
}