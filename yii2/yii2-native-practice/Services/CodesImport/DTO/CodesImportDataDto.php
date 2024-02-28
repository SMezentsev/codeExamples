<?php

namespace app\modules\itrack\Services\CodesImport\DTO;

use app\modules\itrack\models\Facility;
use app\modules\itrack\models\Product;
use app\modules\itrack\models\User;
use app\modules\itrack\models\ProductionOrder;
use app\modules\itrack\Services\CodesImport\Models\CodeCollection;

/**
 * Class CodesDataDTO
 */
class CodesImportDataDto
{
    /**
     * @var bool
     */
    private $isTransfer = false;
    /**
     * @var CodeCollection|null
     */
    private $aggregatedData;
    /**
     * @var CodeCollection|null
     */
    private $ssccCollection;
    /**
     * @var CodeCollection|null
     */
    private $serialsCollection;
    /**
     * @var array|null
     */
    private $product;
    /**
     * @var Facility объект
     */
    private $object;
    /**
     * @var User
     */
    private $user;
    /**
     * @var ProductionOrder
     */
    private $productionOrder;

    /**
     * @return bool
     */
    public function getIsTransfer(): bool
    {
        return $this->isTransfer;
    }

    /**
     * @param bool $isTransfer
     */
    public function setIsTransfer(bool $isTransfer): void
    {
        $this->isTransfer = $isTransfer;
    }

    /**
     * @return CodeCollection|null
     */
    public function getAggregatedData(): ?CodeCollection
    {
        return $this->aggregatedData;
    }

    /**
     * @param CodeCollection|null $aggregatedData
     */
    public function setAggregatedData(?CodeCollection $aggregatedData): void
    {
        $this->aggregatedData = $aggregatedData;
    }

    /**
     * @return CodeCollection|null
     */
    public function getSsccCollection(): ?CodeCollection
    {
        return $this->ssccCollection;
    }

    /**
     * @param CodeCollection|null $ssccCollection
     */
    public function setSsccCollection(?CodeCollection $ssccCollection): void
    {
        $this->ssccCollection = $ssccCollection;
    }

    /**
     * @return CodeCollection|null
     */
    public function getSerialsCollection(): ?CodeCollection
    {
        return $this->serialsCollection;
    }

    /**
     * @param CodeCollection|null $serialsCollection
     */
    public function setSerialsCollection(?CodeCollection $serialsCollection): void
    {
        $this->serialsCollection = $serialsCollection;
    }

    /**
     * @return array|null
     */
    public function getProduct(): ?array
    {
        return $this->product;
    }

    /**
     * @param array|null $product
     */
    public function setProduct(?array $product): void
    {
        $this->product = $product;
    }

    /**
     * @return Facility
     */
    public function getObject(): Facility
    {
        return $this->object;
    }

    /**
     * @param Facility $object
     */
    public function setObject(Facility $object): void
    {
        $this->object = $object;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return ProductionOrder
     */
    public function getProductionOrder(): ProductionOrder
    {
        return $this->productionOrder;
    }

    /**
     * @param ProductionOrder $productionOrder
     */
    public function setProductionOrder(ProductionOrder $productionOrder): void
    {
        $this->productionOrder = $productionOrder;
    }
}