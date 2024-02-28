<?php


namespace app\modules\itrack\Services\CodesImport\DTO;

use app\modules\itrack\Services\CodesImport\Models\CodeCollection;

/**
 * Class CodeDataDTO
 */
class CodeDataDto
{
    /**
     * @var string
     */
    private $code;
    /**
     * @var bool
     */
    private $transferFlag = false;
    /**
     * @var int
     */
    private $codeType;
    /**
     * @var string|null
     */
    private $cryptoTail;
    /**
     * @var array
     */
    private $product = [];
    /**
     * @var CodeCollection|null
     */
    private $dataItems;

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return bool
     */
    public function getTransferFlag(): bool
    {
        return $this->transferFlag;
    }

    /**
     * @param bool $transferFlag
     */
    public function setTransferFlag(bool $transferFlag): void
    {
        $this->transferFlag = $transferFlag;
    }

    /**
     * @return int
     */
    public function getCodeType(): int
    {
        return $this->codeType;
    }

    /**
     * @param int $codeType
     */
    public function setCodeType(int $codeType): void
    {
        $this->codeType = $codeType;
    }

    /**
     * @return string|null
     */
    public function getCryptoTail(): ?string
    {
        return $this->cryptoTail;
    }

    /**
     * @param string|null $cryptoTail
     */
    public function setCryptoTail(?string $cryptoTail): void
    {
        $this->cryptoTail = $cryptoTail;
    }

    /**
     * @return array
     */
    public function getProduct(): ?array
    {
        return $this->product;
    }

    /**
     * @param array $product
     */
    public function setProduct(array $product): void
    {
        $this->product = $product;
    }

    /**
     * @return CodeCollection|null
     */
    public function getDataItems(): ?CodeCollection
    {
        return $this->dataItems;
    }

    /**
     * @param CodeCollection $dataItems
     */
    public function setDataItems(CodeCollection $dataItems): void
    {
        $this->dataItems = $dataItems;
    }
}