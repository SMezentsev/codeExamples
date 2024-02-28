<?php


namespace app\modules\itrack\models\sap\bayer;


class SerialNumberConfirmationMessage
{
    const REJECTED = 'R';
    const PARTIAL = 'P';
    const COMPLETED = 'C';
    public $idType;
    public $size;
    public $actionCode;
    public $gtin;
    public $listRange;
    public $receivingSystem;
    public $serialNumbers;
    
    public function __construct(SerialNumberRequestMessage $serialNumberRequest, $actionErrors = false)
    {
        $this->actionCode = ($actionErrors) ? self::REJECTED : self::COMPLETED;
        $this->idType = $serialNumberRequest->idType;
        $this->size = $serialNumberRequest->size;
        $this->gtin = $serialNumberRequest->gtin;
        $this->listRange = $serialNumberRequest->listRange;
        $this->receivingSystem = $serialNumberRequest->senderGln;
        $this->serialNumbers = [];
    }
    
    public function build()
    {
        $SerialNumberConfirmationMessage = new \stdClass();
        $SerialNumberConfirmationMessage->ReceivingSystem = $this->receivingSystem;
        $SerialNumberConfirmationMessage->ActionCode = $this->actionCode;
        $SerialNumberConfirmationMessage->Size = $this->size;
        $SerialNumberConfirmationMessage->IDType = $this->idType;
        $SerialNumberConfirmationMessage->ObjectKey = [];
        $objectGtin = new \stdClass();
        $objectGtin->Name = 'GTIN';
        $objectGtin->Value = $this->gtin;
        $SerialNumberConfirmationMessage->ObjectKey[] = $objectGtin;
        $SerialNumberConfirmationMessage->SerialNumbers = $this->serialNumbers;
        
        return $SerialNumberConfirmationMessage;
    }
}