<?php


namespace app\modules\itrack\models\erp;

use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationStatus;
use yii\base\Model;

/**
 * Класс для работы с ERP
 * Class ErpOrdersConductor
 *
 * @package app\modules\itrack\models\erp
 */
class ErpOrdersConductor extends Model
{
    private $user;
    private $erpAdapter;
    
    public function __construct(ErpAdapter $erpAdapter)
    {
        $this->user = \Yii::$app->user->getIdentity();
        $this->erpAdapter = $erpAdapter;
    }
    
    /**
     * Получение информации о заказе в заданном формате
     *
     * @param $generationId
     *
     * @return array
     */
    public function info($generationId, $signs = 'true')
    {
        $generation = Generation::findOne(['id' => $generationId]);
        $product = $generation->product;
        $nomenclature = $generation->product->nomenclature;
        $cdate = new \DateTime($product->components[0]['cdate']);
        $operationDate = ($generation->sent_to_suz != null) ? new \DateTime($generation->sent_to_suz) : $cdate;
        
        return [
            'order_id'            => $generation->id,
            'subject_id'          => $generation->object->fns_subject_id,
            'operation_date'      => $operationDate->format(DATE_W3C),
            'status'              => $this->getGenerationStatusTxt($generation->status_uid),
            'series_number'       => $product->series,
            'expiration_date'     => $product->components[0]['expdate'],
            'gtin'                => $nomenclature->gtin,
            'packing_status'      => $generation->packing_status,
            'registration_status' => $generation->registration_status,
            'signs'               => ($signs == 'true') ? $this->getSigns($generation->id) : [],
        ];
    }
    
    /**
     * Окончания упаковки на производственной
     * линии определенной партии продукта
     *
     * @param $orderId
     * @param $packingStatus
     *
     * @throws \ErrorException
     */
    public function packingCompleted($orderId, $packingStatus)
    {
        $this->erpAdapter->packingCompleted($orderId, $packingStatus);
        
        try {
            $generation = Generation::findOne(['id' => $orderId]);
            $generation->packing_status = $packingStatus;
            
            if (!$generation->save()) {
                throw new \ErrorException('Не удалось обновить данные о статусе упаковки');
            }
        } catch (\ErrorException $e) {
            throw new \ErrorException('Не удалось обновить данные о статусе упаковки');
        }
        
        $this->updateDocument311Status($generation->product_uid);
    }
    
    /**
     * Окончание регистрации партии продукта
     *
     * @param $orderId
     * @param $registrationStatus
     */
    public function registrationCompleted($orderId, $registrationStatus)
    {
        $this->erpAdapter->registrationCompleted($orderId, $registrationStatus);
    }
    
    private function getSigns(string $generationId)
    {
        $individualCodes = \Yii::$app->db->createCommand(
            'SELECT * FROM codes WHERE generation_uid=:generation_uid AND parent_code IS NOT NULL AND flag <> 9',
            [
                'generation_uid' => $generationId,
            ]
        )->queryAll();
        
        $allCodes = \Yii::$app->db->createCommand('SELECT code FROM codes WHERE generation_uid=:generation_uid AND flag > :flag',
            [
                'generation_uid' => $generationId,
                'flag'           => 1,
            ])->queryAll();
        
        $rawPackedCodes = [];
        
        foreach ($individualCodes as $code) {
            $rawPackedCodes[] = $code['code'];
        }
        
        $rawAllCodes = [];
        
        foreach ($allCodes as $code) {
            $rawAllCodes[] = $code['code'];
        }
        
        $rawUnpackedCodes = array_diff($rawAllCodes, $rawPackedCodes);
        
        foreach ($individualCodes as $code) {
            if ($code['parent_code'] != '' && $code['parent_code'] != null && $code['parent_code'] != 'NULL') {
                if ($code['without_box']) {
                    $pallets[$code['parent_code']] = $code['parent_code'];
                } else {
                    $boxes[$code['parent_code']] = $code['parent_code'];
                }
            }
        }
        
        if (count($boxes) > 0) {
            $boxString = '(';
            foreach ($boxes as $box) {
                $boxString .= \Yii::$app->db->quoteValue($box) . ',';
            }
            
            $boxString = rtrim($boxString, ',') . ')';
            
            $boxCodes = \Yii::$app->db->createCommand('SELECT * FROM codes WHERE code IN ' . $boxString)->queryAll();
            
            foreach ($boxCodes as $box) {
                if ($box['parent_code'] != '' && $box['parent_code'] != null && $box['parent_code'] != 'NULL') {
                    $pallets[$box['parent_code']] = $box['parent_code'];
                }
            }
            
            foreach ($boxCodes as $key => $box) {
                if ($box['flag'] == 9 || $box['flag'] == 256 || $box['flag'] == 8 || $box['flag'] == 4 || $box['flag'] == 16) {
                    unset($boxCodes[$key]);
                }
            }
        } else {
            $boxCodes = [];
        }
        
        if (count($pallets) > 0) {
            $palletsString = '(';
            
            foreach ($pallets as $pallet) {
                $palletsString .= \Yii::$app->db->quoteValue($pallet) . ',';
            }
            
            $palletsString = rtrim($palletsString, ',') . ')';
            
            $palletCodes = \Yii::$app->db->createCommand('SELECT * FROM codes WHERE code IN ' . $palletsString)->queryAll();
            
            foreach ($palletCodes as $key => $pallet) {
                if ($pallet['flag'] == 9 || $pallet['flag'] == 256 || $pallet['flag'] == 8 || $pallet['flag'] == 4 || $pallet['flag'] == 16) {
                    unset($palletCodes[$key]);
                }
            }
            
            foreach ($palletCodes as $key => $pallet) {
                $inPallet = 0;
                
                foreach ($boxCodes as $box) {
                    if ($box['parent_code'] == $pallet['code']) {
                        $inPallet++;
                        $palletCodes[$key]['without_box'] = false;
                        $palletCodes[$key]['boxes'][$box['code']] = $box;
                        
                        foreach ($individualCodes as $code) {
                            if ($box['code'] == $code['parent_code']) {
                                $palletCodes[$key]['boxes'][$box['code']]['individual_codes'][$code['code']] = $code;
                            }
                        }
                    }
                }
                
                if ($inPallet == 0) {
                    foreach ($individualCodes as $code) {
                        if ($pallet['code'] == $code['parent_code']) {
                            $palletCodes[$key]['without_box'] = true;
                            $palletCodes[$key]['individual_codes'][$code['code']] = $code;
                        }
                    }
                }
            }
        } else {
            $palletCodes = [];
        }
        
        foreach ($boxCodes as $key => $box) {
            foreach ($individualCodes as $code) {
                if ($box['code'] == $code['parent_code']) {
                    $boxCodes[$key]['individual_codes'][$code['code']] = $code;
                }
            }
        }
        
        foreach ($boxCodes as $box) {
            if ($box['parent_code'] == 'NULL' || $box['parent_code'] == null) {
                $boxesWithoutPallet[] = $box;
            }
        }
        
        $data = [];
        foreach ($palletCodes as $pallet) {
            foreach ($pallet['boxes'] as $key1 => $box) {
                foreach ($box['individual_codes'] as $item) {
                    $data[$pallet['code']][$box['code']][] = $item['code'];
                }
            }
        }
        
        foreach ($boxesWithoutPallet as $key1 => $box) {
            foreach ($box['individual_codes'] as $item) {
                $data[$box['code']][] = $item['code'];
            }
        }
        
        return [
            'packed'   => $data,
            'unpacked' => array_values($rawUnpackedCodes),
        ];
    }
    
    private function updateDocument311Status($series)
    {
        $documents = \Yii::$app->db->createCommand('SELECT * FROM operations WHERE operation_uid=:operation_uid AND product_uid=:product_uid', [
            'operation_uid' => 9,
            'product_uid'   => $series,
        ])->queryAll();
        
        foreach ($documents as $document) {
            if ($document['state'] >= 3) {
                continue;
            }
            
            \Yii::$app->db->createCommand('UPDATE operations SET state=:state, fns_start_send = now() WHERE id=:id', [
                'state' => 3,
                'id'    => $document['id'],
            ])->execute();
        }
    }
    
    private function getGenerationStatusTxt($status)
    {
        switch ($status) {
            case GenerationStatus::STATUS_CREATED:
                $statusTxt = 'Создан';
                break;
            case GenerationStatus::STATUS_PROCESSING:
                $statusTxt = 'Подготавливается';
                break;
            case GenerationStatus::STATUS_READY:
                $statusTxt = 'Готов';
                break;
            case GenerationStatus::STATUS_FAIL:
                $statusTxt = 'Ошибка';
                break;
            case GenerationStatus::STATUS_CLOSED:
                $statusTxt = 'Закрыт';
                break;
            case GenerationStatus::STATUS_NOTENOUGH:
                $statusTxt = 'Недостаточно резерва';
                break;
            case GenerationStatus::STATUS_CONFIRMED:
                $statusTxt = 'Подтвержден';
                break;
            case GenerationStatus::STATUS_DECLINED:
                $statusTxt = 'Отклонен';
                break;
            case GenerationStatus::STATUS_TIMEOUT:
                $statusTxt = 'Таймаут ожидания';
                break;
            case GenerationStatus::STATUS_SKZKM:
                $statusTxt = 'СКЗМ';
                break;
            case GenerationStatus::STATUS_CONFIRMEDWOADDON:
                $statusTxt = 'Выслан на оборудование';
                break;
            case GenerationStatus::STATUS_CONFIRMEDREPORT:
                $statusTxt = 'Подтвержден отчет';
                break;
            default:
                throw new \ErrorException('Статус не найден');
        }
        
        return $statusTxt;
    }
}