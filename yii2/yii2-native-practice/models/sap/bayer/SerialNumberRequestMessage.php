<?php


namespace app\modules\itrack\models\sap\bayer;


class SerialNumberRequestMessage
{
    public  $idType;
    public  $size;
    public  $gtin;
    public  $listRange;
    public  $senderGln;
    public  $receiverGln;
    private $serialNumberRequest;
    
    public function __construct($serialNumberRequest)
    {
        $this->serialNumberRequest = $serialNumberRequest;
        $this->parseSerialNumberRequest();
    }
    
    private function parseSerialNumberRequest()
    {
        $this->idType = $this->serialNumberRequest->IDType;
        $this->size = $this->serialNumberRequest->Size;
        
        $reqFields = [];
        
        foreach ($this->serialNumberRequest->ObjectKey as $reqField) {
            $reqFields[$reqField->Name] = $reqField->Value;
        }
        
        foreach ($reqFields as $key => $field) {
            switch ($key) {
                case 'GTIN':
                    $this->gtin = $field;
                    break;
                case 'LIST_RANGE':
                    $this->listRange = $field;
                    break;
                case 'SENDER_GLN':
                    $this->senderGln = $field;
                    break;
                case 'RECEIVER_GLN':
                    $this->receiverGln = $field;
                    break;
                default:
                    continue;
            }
        }
    }
}