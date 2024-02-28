<?php

namespace app\modules\itrack\events\erp\santens;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\sap\santens\reports\OrderCollectingReport;
use yii\base\Event;

Event::on(OrderCompleteEvent::class, OrderCompleteEvent::COMPLETE_ORDER, function (OrderCompleteEvent $event) {
    $event->generateReport();
});

Event::on(OrderCompleteEvent::class, OrderCompleteEvent::DELETE_COMPLETE_ORDER_FILE, function (OrderCompleteEvent $event) {
    $event->deleteReport();
});

/**
 * Событие завершения заказа
 * Class OrderCompleteEvent
 *
 * @package app\modules\itrack\events\erp
 */
class OrderCompleteEvent extends Event
{
    const COMPLETE_ORDER = 'COMPLETE_ORDER';
    const DELETE_COMPLETE_ORDER_FILE = 'DELETE_COMPLETE_ORDER_FILE';
    
    private $generation;
    private $orderCollectingReport;
    private $reportFile;
    
    public function __construct(OrderCollectingReport $orderCollectingReport)
    {
        $this->generation = null;
        $this->reportFile = null;
        $this->orderCollectingReport = $orderCollectingReport;
    }
    
    public function setGenerations($generation)
    : void
    {
        $this->generation = $generation;
    }
    
    /**
     * Запускает формирование отчета
     *
     * @return void
     * @throws \ErrorException
     */
    public function generateReport()
    : void
    {
        if ($this->generation->codetype_uid === CodeType::CODE_TYPE_INDIVIDUAL) {
            throw new \ErrorException('Нельзя сформировать отчет для генерации индивидуальных кодов.');
        }
        
        $this->reportFile = $this->orderCollectingReport->generateReport($this->generation);
    }
    
    public function deleteReport()
    {
        if ($this->reportFile === null) {
            return true;
        }
        
        $this->orderCollectingReport->deleteReport($this->reportFile);
    }
}