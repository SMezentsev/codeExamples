<?php

namespace app\modules\itrack\models\sap\santens\reports;


use app\modules\itrack\components\pghelper;
use app\modules\itrack\events\erp\santens\ReportInterface;
use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\ErpInfo;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\Product;
use app\modules\itrack\models\User;
use app\modules\itrack\models\utility\ReportFilesManager;

class OrderCollectingReport implements ReportInterface
{
    private $generation;
    private $fields;
    private $user;
    private $users;
    private $data;
    private $orderData;
    private $boxesWithoutPallet;
    private $product;
    private $reportId;
    private $pallets;
    private $reportFilesManager;
    
    public function __construct()
    {
        $this->generation = null;
        $this->orderData = null;
        $this->pallets = [];
        $this->boxesWithoutPallet = [];
        $this->fields = [];
        $this->user = \Yii::$app->user->getIdentity();
        $this->product = null;
        $this->data = null;
        $this->reportId = null;
        $this->users = User::getUsersDictionary();
    }
    
    
    /**
     * Запускает цепочку методов для создания файлов с информацией о выполнении заказа
     *
     * @param Generation $generation
     *
     * @return string
     * @throws \ErrorException
     */
    public function generateReport($generation)
    : string
    {
        $this->product = $generation->product->toArray();
        $this->generation = $generation;
        $this->reportId = $generation->third_party_order_id;
        $this->setOrderFactData();
        $this->generateJson();
        
        return $this->write();
    }
    
    /**
     * Удаляет файлы отчета
     *
     * @param string $fileName
     *
     * @return void
     * @throws \ErrorException
     */
    public function deleteReport($fileName)
    : void
    {
        try {
            $this->reportFilesManager->deleteReportPath($fileName, self::FILE_PREFIX);
        } catch (\Exception $e) {
            throw new \ErrorException('При удалении отчета не удалось обновить данные в бд, но отчет для sap ' .
                'был успешно сгенерирован и его не удалось удалить, требуется вручную удалить файлы: ' . $fileName . '.xml' .
                ' и ' . $fileName . '.in: ' . $e->getMessage());
        }
    }
    
    /**
     * Устанавливает поля необходимые для генерации файла
     *
     * @return void
     * @throws \ErrorException
     */
    private function generateJson()
    : void
    {
        try {
            $data = [];
            $data['id'] = $this->reportId;
            $data['startTime'] = date('Y-m-d\TH:i:sP', time());
            $data['endTime'] = date('Y-m-d\TH:i:sP', time());
            $data['sampleNumbers'] = [];
            $data['readyBox'] = [];
            
            $boxes = [];
            
            $i = 0;
            
            foreach ($this->pallets as $key => $pallet) {
                $boxes[$i] = [
                    'boxNumber' => $pallet['code'],
                    'partial' => false,
                    'startTime' => str_replace('+00:00', 'Z', gmdate('c', strtotime($pallet['activated_at']))),
                    'endTime' => str_replace('+00:00', 'Z', gmdate('c', strtotime($pallet['activated_at']))),
                    'userName' => $this->users[$pallet['activated_by']]['fio'],
                    'contentNumbers' => []
                ];

                foreach ($pallet['boxes'] as $code) {
                    $boxes[$i]['contentNumbers'][] = $code['code'];
                }

                $i++;
                
                foreach ($pallet['boxes'] as $box) {
                    $boxes[$i] = [
                        'boxNumber' => $box['code'],
                        'partial' => false,
                        'startTime' => str_replace('+00:00', 'Z', gmdate('c', strtotime($box['activated_at']))),
                        'endTime' => str_replace('+00:00', 'Z', gmdate('c', strtotime($box['activated_at']))),
                        'userName' => $this->users[$box['activated_by']]['fio'],
                        'contentNumbers' => []
                    ];
                    
                    foreach ($box['individual_codes'] as $code) {
                        $boxes[$i]['contentNumbers'][] = $code['code'];
                    }
                    
                    $i++;
                }
            }
            
            foreach ($this->boxesWithoutPallet as $box) {
                $boxes[$i] = [
                    'boxNumber' => $box['code'],
                    'partial' => false,
                    'startTime' => str_replace('+00:00', 'Z', gmdate('c', strtotime($box['activated_at']))),
                    'endTime' => str_replace('+00:00', 'Z', gmdate('c', strtotime($box['activated_at']))),
                    'userName' => $this->users[$box['activated_by']]['fio'],
                    'contentNumbers' => []
                ];
                
                foreach ($box['individual_codes'] as $code) {
                    $boxes[$i]['contentNumbers'][] = $code['code'];
                }
                
                $i++;
            }
        } catch (\Exception $e) {
            throw new \ErrorException('При генерации отчета произошла ошибка: ' . $e->getMessage());
        }
        
        $data['readyBox'] = $boxes;
        $this->data = json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    private function setOrderFactData()
    : void
    {
        $boxes = [];
        $pallets = [];
        $boxesWithoutPallet = [];
        $groupCodes = \Yii::$app->db->createCommand(
            'SELECT c.code, g.codetype_uid, c.childrens, c.parent_code FROM codes AS c
                LEFT JOIN generations AS g ON g.id = c.generation_uid
                    WHERE c.generation_uid=:generation_uid AND c.flag <> 9 AND c.flag <> 0', [
                'generation_uid' => $this->generation->id,
            ]
        )->queryAll();
        
        if (count($groupCodes) === 0) {
            throw new \ErrorException('Не найдены упакованные коды в этом заказе.');
        }
        
        $allCodes = [];
        
        foreach ($groupCodes as $groupCode) {
            $data = array_diff(pghelper::pgarr2arr($groupCode['childrens']), $allCodes);
            $allCodes = array_merge($allCodes, $data);
        }
        
        $rawCodesString = '(';
        foreach ($allCodes as $rawCode) {
            $rawCodesString .= \Yii::$app->db->quoteValue($rawCode) . ',';
        }
        
        $rawCodesString = rtrim($rawCodesString, ',') . ')';
        
        $rawCodesData = \Yii::$app->db->createCommand(
            'SELECT * FROM codes AS c
                LEFT JOIN product AS p ON p.id = c.product_uid
                LEFT JOIN nomenclature AS n ON n.id = p.nomenclature_uid
                LEFT JOIN generations AS g ON g.id = c.generation_uid
                    WHERE c.code IN ' . $rawCodesString
        )->queryAll();
        
        $individualCodes = [];
        
        foreach ($rawCodesData as $code) {
            if ($code['codetype_uid'] === CodeType::CODE_TYPE_INDIVIDUAL) {
                $individualCodes[] = $code;
            }
        }
        
        foreach ($individualCodes as $code) {
            if ($code['parent_code'] != '' && $code['parent_code'] != null && $code['parent_code'] != 'NULL') {
                if ($code['codetype_uid'] === CodeType::CODE_TYPE_INDIVIDUAL) {
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

            $boxCodes = \Yii::$app->db->createCommand(
                'SELECT *, h.created_at AS activated_at, h.created_by AS activated_by FROM codes AS c
                        LEFT JOIN history AS h ON h.code_uid=c.id
                        WHERE h.operation_uid=41 AND code IN ' . $boxString
            )->queryAll();

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

            $palletCodes = \Yii::$app->db->createCommand(
                'SELECT *, h.created_at AS activated_at, h.created_by AS activated_by FROM codes AS c
                        LEFT JOIN history AS h ON h.code_uid=c.id
                        WHERE h.operation_uid=41 AND code IN ' . $palletsString
            )->queryAll();

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
        
        $this->boxesWithoutPallet = $boxesWithoutPallet;
        $this->pallets = $palletCodes;
        
        if (count($this->pallets) === 0 && count($this->boxesWithoutPallet) === 0) {
            throw new \ErrorException('По данному заказу не была выполнена упаковка продукции.');
        }
    }
    
    /**
     * Выполняет запись файла на диск
     *
     * @return string
     * @throws \ErrorException
     */
    private function write()
    : string
    {
        $fileName = 'report-' . $this->reportId . '.json';
        $path = \Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . \Yii::$app->params["santensFtpConfig"]['reportsDir'];

        try {
            file_put_contents($path . DIRECTORY_SEPARATOR . $fileName, $this->data);
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось записать отчет на диск.');
        }
        
        return $fileName;
    }
}