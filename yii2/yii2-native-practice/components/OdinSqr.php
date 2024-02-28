<?php

namespace app\modules\itrack\components;

class OdinSqr
{
    
    public $error      = true;
    public $xmltype;
    public $docId;
    public $docDate;
    public $odinSnum;
    public $withdrawal_reason;
    public $removed_reason;
    public $removed_reason_id;
    public $receiver;
    public $permission = [];
    public $gtins      = [];
    
    static function parse($qr, $oparams = [])
    {
        $doc = new self;
        $arr = explode(";", $qr);
        
        $doc->xmltype = array_shift($arr);
        
        switch ($doc->xmltype) {
            case "1000":  //Вывод ЛП из оборота
                $doc->docDate = array_shift($arr);
                $doc->odinSnum = array_shift($arr);
                $doc->docId = array_shift($arr);
                $doc->withdrawal_reason = array_shift($arr);
                switch ($doc->withdrawal_reason) {
                    case "Выборочный контроль":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext1";
                        break;
                    case "Таможенный контроль":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext2";
                        break;
                    case "Федеральный надзор":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext3";
                        break;
                    case "В целях клинических исследований":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext4";
                        break;
                    case "В целях фармацевтической экспертизы":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext5";
                        break;
                    case "Недостача":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext6";
                        break;
                    case "Отбор демонстрационных образцов":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext7";
                        break;
                    case "Списание без передачи на уничтожение":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext8";
                        break;
                    case "Вывод из оборота КИЗ, накопленных в рамках эксперимента":
                        $doc->permission[] = "codeFunction-withdrawal-tsd-ext9";
                        break;
                    default:
                        $doc->error = false;
                        break;
                }
                break;
            case "2000":  //перемещение
                $doc->docDate = array_shift($arr);
                $doc->odinSnum = array_shift($arr);
                $doc->docId = array_shift($arr);
                $doc->receiver = array_shift($arr);
                $doc->permission[] = "codeFunction-outcome-log";
                $doc->permission[] = "codeFunction-outcome-prod";
                break;
            case "3000":     //приемка
                $doc->docDate = array_shift($arr);
                $doc->odinSnum = array_shift($arr);
                $doc->docId = array_shift($arr);
                $doc->permission[] = "codeFunction-income-log";
                $doc->permission[] = "codeFunction-income-prod";
                break;
            case "4000":     //отгрузка
                $doc->docDate = array_shift($arr);
                $doc->odinSnum = array_shift($arr);
                $doc->docId = array_shift($arr);
                $doc->permission[] = "codeFunction-retail-log";
                $doc->permission[] = "codeFunction-retail-prod";
                break;
            case "5000":     //утилизация
                $doc->docDate = array_shift($arr);
                $doc->odinSnum = array_shift($arr);
                $doc->docId = array_shift($arr);
                $doc->removed_reason_id = array_shift($arr);
                $doc->removed_reason = array_shift($arr);
                switch ($doc->removed_reason) {
                    case "Брак":
                        $doc->permission[] = "codeFunction-removed-tsd-brak";
                        break;
                    case "Бой":
                        $doc->permission[] = "codeFunction-removed-tsd-boi";
                        break;
                    case 'Истечение срока годности':
                        $doc->permission[] = "codeFunction-removed-tsd-srok";
                        break;
                    default:
                        $doc->error = false;
                        break;
                }
                break;
            case "6000":     //приемка по прямому акцептованию
                $doc->docDate = array_shift($arr);
                $doc->odinSnum = array_shift($arr);
                $doc->docId = array_shift($arr);
                $doc->permission[] = "codeFunction-incomeExt";
                break;
        }
        
        //gtins
        foreach ($arr as $item) {
            $z = explode("&", $item, 3);
            if ($z[2] > 0) {
                $doc->gtins[] = $z;
            }
        }
        
        return $doc;
    }
    
    public function setGtins($arr)
    {
        $this->gtins = $arr;
    }
}
