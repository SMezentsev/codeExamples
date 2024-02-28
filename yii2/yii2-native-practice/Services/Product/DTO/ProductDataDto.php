<?php

namespace app\modules\itrack\Services\Product\DTO;

use \DateTime;
use \Exception;

class ProductDataDto
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $gtin;
    /**
     * @var string
     */
    private $series;
    /**
     * @var DateTime
     */
    private $productionDate;
    /**
     * @var DateTime
     */
    private $expirationDate;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getGtin(): string
    {
        return $this->gtin;
    }

    /**
     * @param string $gtin
     */
    public function setGtin(string $gtin): void
    {
        $this->gtin = $gtin;
    }

    /**
     * @return string
     */
    public function getSeries(): string
    {
        return $this->series;
    }

    /**
     * @param string $series
     */
    public function setSeries(string $series): void
    {
        $this->series = $series;
    }

    /**
     * @return string
     */
    public function getProductionDate(): string
    {
        return $this->productionDate->format(DATE_W3C);
    }

    /**
     * @param mixed $productionDate
     * @throws Exception
     */
    public function setProductionDate($productionDate): void
    {
        $this->productionDate = new DateTime($productionDate);
    }

    /**
     * @return string
     */
    public function getExpirationDate(): string
    {
        return $this->expirationDate->format(DATE_W3C);
    }

    /**
     * @param string $expirationDate
     * @throws Exception
     */
    public function setExpirationDate(string $expirationDate): void
    {
        $this->expirationDate = new DateTime($expirationDate);
    }
}