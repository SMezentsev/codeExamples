<?php


namespace app\modules\itrack\Services\Generation\DTO;

use app\modules\itrack\models\ProductionOrder;

class GenerationDataDto
{
    /**
     * @var int
     */
    private $userId;
    /**
     * @var int
     */
    private $objectId;
    /**
     * @var int
     */
    private $codeType;
    /**
     * @var ProductionOrder
     */
    private $productionOrder;
    /**
     * @var int
     */
    private $productId;
    /**
     * @var array
     */
    private $codes;

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @return int
     */
    public function getObjectId(): int
    {
        return $this->objectId;
    }

    /**
     * @return int
     */
    public function getCodeType(): int
    {
        return $this->codeType;
    }

    /**
     * @return ProductionOrder
     */
    public function getProductionOrder(): ProductionOrder
    {
        return $this->productionOrder;
    }

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @return array
     */
    public function getCodes(): ?array
    {
        return $this->codes;
    }

    /**
     * @param int $userId
     */
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * @param int $objectId
     */
    public function setObjectId(int $objectId): void
    {
        $this->objectId = $objectId;
    }

    /**
     * @param int $codeType
     */
    public function setCodeType(int $codeType): void
    {
        $this->codeType = $codeType;
    }

    /**
     * @param ProductionOrder $productionOrder
     */
    public function setProductionOrder(ProductionOrder $productionOrder): void
    {
        $this->productionOrder = $productionOrder;
    }

    /**
     * @param int $productId
     */
    public function setProductId(int $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @param array $codes
     */
    public function setCodes($codes): void
    {
        $this->codes = $codes;
    }
}