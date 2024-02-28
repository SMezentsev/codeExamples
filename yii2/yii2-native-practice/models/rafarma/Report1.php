<?php

namespace app\modules\itrack\models\rafarma;

use Yii;

class Report1 extends \app\modules\itrack\models\Extdata
{
    
    const TYPEOF = "reportRafarma1";
    public $series;
    public $grpcode;
    public $weight;
    //public $group_code;
    public $fio;
    public $isBox;
    public $boxNumber;
    public $boxNumberOk;
    public $minWeight;
    public $maxWeight;
    public $comment;
    public $boxesOkCount;
//    public $typeof;
//    public $created_by;
//    public $created_at;
//    public $object_uid;
    public $confirmed;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'extdata';
    }
    
    static function find()
    {
        $q = parent::find();
        
        if (SERVER_RULE != SERVER_RULE_SKLAD) {
            $q->andWhere(['typeof' => self::TYPEOF]);
        } else {
            $q->from(['extdata' => "(SELECT * FROM _get_extdata('typeof = ''" . self::TYPEOF . "'''))"]);
        }
        
        return $q;
    }
    
    /**
     * Поулчение данных для отчета по концу смены
     *
     * @param string $series серия
     *
     * @return \Yii\db\ActiveQuery
     */
    static function reportEndOfShift($bdate, $edate, $series)
    {
        $query = self::find();
        $query->andWhere(['between', 'created_at', $bdate, $edate]);
        $query->andWhere(['typeof' => self::TYPEOF]);
        if (!empty($series)) {
            $query->andWhere(['params1' => $series]);
        }
        $query->orderBy('created_at');
        
        return $query;
    }
    
    /**
     * Получение данных для отчета по окончанию серии
     *
     * @param string $series серия
     *
     * @return \Yii\db\ActiveQuery
     */
    static function reportEndOfSeries($series)
    {
        $query = self::find();
        $query->andWhere(['typeof' => self::TYPEOF]);
        if (!empty($series)) {
            $query->andWhere(['params1' => $series]);
        }
        $query->orderBy('created_at');
        
        return $query;
    }
    
    /**
     * Генерация WORD отчета по серии за смену
     *
     * @param string $filename путь до файла для сохранения отчета
     * @param string $bdate    дата/время начала смены
     * @param string $edate    дата/время окончания смены
     * @param string $series   серия
     */
    static function generateReportEndOfShift($report, $filename, $bdate, $edate, $series, $withWeight = true, $report_type = 'docx')
    {
        $withWeight = ($withWeight !== "false");
        $pdf = ($report_type == 'pdf');
        
        $query = self::reportEndOfShift($bdate, $edate, $series);
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->setDefaultFontName('Times New Roman');
        //$word->setDefaultFontSize(14);
        //define('DOMPDF_FONT_DIR', '@app/fonts/');
        
        $section = $word->createSection();
        $sectionStyle = $section->getSettings();
        $sectionStyle->setPortrait();             //или setLandscape()
        $sectionStyle->setMarginLeft(15 * 56.7);  //15mm
        $sectionStyle->setMarginRight(15 * 56.7);
        //$sectionStyle->setBorderBottomSize(10 * 56.7);
        //$sectionStyle->setBorderTopColor('C0C0C0');
        
        $word->addFontStyle('titleStyle1', [
            'color'     => '000000',
            'size'      => 14,
            'bold'      => false,
            'italic'    => true,
            'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE,
            'name'      => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('titleStyle', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => true,
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('titleStyle2', [
            'color'  => '000000',
            'size'   => 14,
            'bold'   => false,
            'italic' => true,
            'name'   => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('main_bold', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => true,
        ]);
        $word->addFontStyle('main', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => false,
        ]);
        $word->addTableStyle('base_table',
            ['cellMargin' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(0.1), 'size' => 8, 'borderSize' => 1, 'borderColor' => '000000', 'width' => 90 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER],
            ['bold' => true, 'bgColor' => 'DDDDDD', 'alignment' => 'center', 'valign' => 'center']);
        $parStyleCenter = ['alignment' => 'center', 'spaceBefore' => 0, 'textAlignment' => 'center', 'spaceAfter' => 0];
        $mainTableText = ['size' => 8, 'name' => $pdf ? 'DejaVu Sans' : 'Calibri'];
        $mainTableTextBold = ['size' => 8, 'bold' => true, 'name' => $pdf ? 'DejaVu Sans' : 'Calibri'];
        $mainTableTextItalic = ['size' => 8, 'bold' => false, 'name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'italic' => true];
        
        $i = 0;
        $data = $query->all();
        $operators = [];
        $gofra = 0;
        $ind = 0;
        $prevmin = 0;
        $prevmax = 0;
        $time = time();
        $isl3 = false;
        foreach ($data as $el) {
            if (time() > ($time + 30) && $report) {
                $report->refresh();
                if ($report->status == 'CANCEL') {
                    return;
                }
                $time = time();
            }
            $a = $el->toArray();
            if ($a["grpcode"] == "changeWeight") {
                $change = ["created_at" => $a["created_at"], "fio" => $a["fio"]];
                continue;
            }
            
            if (empty($a["grpcode"])) {
                continue;
            }
            $grpCode = \app\modules\itrack\models\Code::findOneByCode($a["grpcode"]);
            if (empty($grpCode)) {
                continue;
            }
            if ($grpCode->l3) {
                continue;
            } //пропускаем бандероли пока что
            if (!isset($operators[$a["fio"]])) {
                $roleName = " ";
                $user = \app\modules\itrack\models\User::find()->andWhere(['fio' => $a["fio"]])->one();
                if (!empty($user)) {
                    $roles = \Yii::$app->authManager->getRolesByUser($user->id);
                    $role = array_pop($roles);
                    $roleName = $role->description;
                }
                $operators[$a["fio"]] = $roleName;
            }
            if ($i == 0) {
                //инициирование данных по номенклатуре
                $res = \Yii::$app->db->createCommand("SELECT nomenclature.name,generations.object_uid,generations.num,nomenclature.cnt,nomenclature.hasl3,nomenclature.band_in_korob_cnt
                                                                    FROM get_full_codes(:arr) as codes "
                    . " LEFT JOIN product ON codes.product_uid=product.id"
                    . " LEFT JOIN nomenclature ON product.nomenclature_uid = nomenclature.id"
                    . " LEFT JOIN generations ON generation_uid = generations.id"
                    . " WHERE codetype_uid = :codetype and nomenclature.id is not null", [
                    ":arr"      => \app\modules\itrack\components\pghelper::arr2pgarr([$a["grpcode"]]),
                    ":codetype" => \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL,
                ])->queryOne();
                $nomenclature_name = "Неизвестный препарат";
                $number = $series;//"XXXX";
                $band_in_korob_cnt = $cnt_in_box = 0;
                if (!empty($res)) {
                    $nomenclature_name = $res["name"];
                    //$number = $res["object_uid"]."/".$res["num"];
                    $cnt_in_box = intval($res["cnt"]);
                    $band_in_korob_cnt = intval($res["band_in_korob_cnt"]);
                    $isl3 = $res["hasl3"];
                }
            }
            if (($i % 24) == 0) {
                if ($i) {
                    $section->addTextBreak(2);
                    //конец страницы
                    $table = $section->addTable('base_table');
                    $table->addRow();
                    $table->addCell(3100, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Должность', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                    $table->addCell(3100, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('ФИО', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                    $table->addCell(3100, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Подпись', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                    foreach ($operators as $op => $v) {
                        $table->addRow();
                        $table->addCell(3100, ['valign' => 'center'])->addText(mb_convert_encoding($v, 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
                        $table->addCell(3100, ['valign' => 'center'])->addText(mb_convert_encoding($op, 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
                        $table->addCell(3100, ['valign' => 'center'])->addText(' ', $mainTableText, $parStyleCenter);
                    }
                    $section->addPageBreak();
                }
                $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
                $textrun->addText(mb_convert_encoding($nomenclature_name . ", №" . $number, 'HTML-ENTITIES', 'UTF-8'), 'titleStyle1');
                $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
                $textrun->addText(mb_convert_encoding("Отчет за смену ", 'HTML-ENTITIES', 'UTF-8'), 'titleStyle');
                $textrun->addText(\Yii::$app->formatter->asDatetime($bdate, 'php:d.m.Y') . ", " . \Yii::$app->formatter->asDatetime($bdate, 'php:H:i') . '-' . \Yii::$app->formatter->asDatetime($edate, 'php:H:i'), 'titleStyle2');
                //$section->addTextBreak(2);
                //шапка таблицы
                $table = $section->addTable('base_table');
                $table->addRow();
                $table->addCell(900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Время', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                $table->addCell(900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('№ короба', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                if ($withWeight) {
                    $table->addCell(900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Масса от(г)', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                }
                if ($withWeight) {
                    $table->addCell(900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Масса до(г)', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                }
                if ($withWeight) {
                    $table->addCell(900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Соответствие', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                }
                if ($withWeight) {
                    $table->addCell(1000, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Действительная масса короба(г)', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                }
                $table->addCell(1900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('ФИО оператора УПМН/ мастера смены', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                $table->addCell($withWeight ? 1900 : 4900, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding(($withWeight) ? 'Примечание (№ стерилизации)' : 'Примечание', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
            }
            
            $ch = "&#8594;"; //"→";
            if ($a["weight"] < $a["minWeight"]) {
                $ch = "&#8595;";
            } //"↓";
            if ($a["weight"] > $a["maxWeight"]) {
                $ch = "&#8593;";
            } //"↑";
            $gofra++;
            if (!empty($grpCode)) {
                if ($isl3) {
                    $c = \Yii::$app->db->createCommand("select count(*) from _get_codes_array(:codes) WHERE parent_code=:code", [":codes" => $grpCode->childrens, ":code" => $grpCode->code])->queryScalar();
                } else {
                    $c = count(\app\modules\itrack\components\pghelper::pgarr2arr($grpCode->childrens) ?? []);
                }
            } else {
                $c = 0;
            }
            $ind += $c;
            if ($isl3) {
                if ($c < $band_in_korob_cnt) {
                    $a["comment"] .= "<br/>Неполный короб $c бандеролей";
                }
            } else {
                if ($c < $cnt_in_box) {
                    $a["comment"] .= "<br/>Неполный короб $c упаковок";
                }
            }
            if ($grpCode->removed) {
                $a["comment"] .= "<br/>Разгруппирован";
            }
            
            if ($prevmin && $prevmax && ($prevmin != $a["minWeight"] || $prevmax != $a["maxWeight"])) {
                $table->addRow();
                $table->addCell(900, ['valign' => 'center'])->addText(\Yii::$app->formatter->asDatetime($change["created_at"] ?? $a["created_at"], 'php:H:i'), $mainTableText, $parStyleCenter);
                $table->addCell(900, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
                if ($withWeight) {
                    $table->addCell(900, ['valign' => 'center'])->addText($a["minWeight"], $mainTableTextItalic, $parStyleCenter);
                }
                if ($withWeight) {
                    $table->addCell(900, ['valign' => 'center'])->addText($a["maxWeight"], $mainTableTextItalic, $parStyleCenter);
                }
                if ($withWeight) {
                    $table->addCell(900, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
                }
                if ($withWeight) {
                    $table->addCell(1000, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
                }
                $table->addCell(1900, ['valign' => 'center'])->addText($change["fio"] ?? "-", $mainTableTextBold, $parStyleCenter);
                $table->addCell($withWeight ? 1900 : 4900, ['valign' => 'center'])->addText(mb_convert_encoding("Изменение ориент. массы короба", 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
                $change = [];
            }
            
            $table->addRow();
            $table->addCell(900, ['valign' => 'center'])->addText(\Yii::$app->formatter->asDatetime($a["created_at"], 'php:H:i'), $mainTableText, $parStyleCenter);
            $table->addCell(900, ['valign' => 'center'])->addText(sprintf('%02d', $a["boxNumberOk"]), $mainTableText, $parStyleCenter);
            if ($withWeight) {
                $table->addCell(900, ['valign' => 'center'])->addText($a["minWeight"], $mainTableTextItalic, $parStyleCenter);
            }
            if ($withWeight) {
                $table->addCell(900, ['valign' => 'center'])->addText($a["maxWeight"], $mainTableTextItalic, $parStyleCenter);
            }
            if ($withWeight) {
                $table->addCell(900, ['valign' => 'center'])->addText($ch, $mainTableTextBold, $parStyleCenter);
            }
            if ($withWeight) {
                $table->addCell(1000, ['valign' => 'center'])->addText($a["weight"], $mainTableTextBold, $parStyleCenter);
            }
            $table->addCell(1900, ['valign' => 'center'])->addText(mb_convert_encoding($a["fio"], 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
            $table->addCell($withWeight ? 1900 : 4900, ['valign' => 'center'])->addText(mb_convert_encoding($a["comment"], 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
            $i++;
            
            $prevmin = $a["minWeight"];
            $prevmax = $a["maxWeight"];
        }
        
        if ($i) {
            //Итого
            $table->addRow();
            $table->addCell(900, ['valign' => 'center'])->addText(mb_convert_encoding("Итого за смену", 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
            $table->addCell(900, ['valign' => 'center'])->addText(mb_convert_encoding("$gofra шт.", 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
            if ($withWeight) {
                $table->addCell(900, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
            }
            if ($withWeight) {
                $table->addCell(900, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
            }
            if ($withWeight) {
                $table->addCell(900, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
            }
            if ($withWeight) {
                $table->addCell(1000, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
            }
            $table->addCell(1900, ['valign' => 'center'])->addText("-", $mainTableText, $parStyleCenter);
            $table->addCell($withWeight ? 1900 : 4900, ['valign' => 'center'])->addText(mb_convert_encoding("$ind упак.", 'HTML-ENTITIES', 'UTF-8'), $mainTableText, $parStyleCenter);
            
            
            $section->addTextBreak(2);
            
            $table = $section->addTable('base_table');
            $table->addRow();
            $table->addCell(3100, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Должность', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
            $table->addCell(3100, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('ФИО', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
            $table->addCell(3100, ['bgColor' => 'DDDDDD', 'valign' => 'center'])->addText(mb_convert_encoding('Подпись', 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
            foreach ($operators as $op => $v) {
                $table->addRow();
                $table->addCell(3100, ['valign' => 'center'])->addText(mb_convert_encoding($v, 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                $table->addCell(3100, ['valign' => 'center'])->addText(mb_convert_encoding($op, 'HTML-ENTITIES', 'UTF-8'), $mainTableTextBold, $parStyleCenter);
                $table->addCell(3100, ['valign' => 'center'])->addText(' ', $mainTableTextBold, $parStyleCenter);
            }
        }
        
        if ($report_type == 'pdf') {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'PDF');
            $writer->save($filename);
            $writer->isPdf = true;
            //  PDF settings
            $paperSize = 'A4';
            $orientation = 'portrait';
            
            //  Create PDF
            $pdf = new \Dompdf\Dompdf();
            $pdf->setPaper(strtolower($paperSize), $orientation);
            $html = $writer->getContent();
            //гвозди со стилями
            $html = str_replace("text-decoration: underline", "text-decoration: none", $html);
//            $html = str_replace('</style>', "table {border: 0px}\ntd {border: 0px}\n</style>", $html);
//                $html = str_replace('<body>', "<body>\n".'<div id="header">
//     <h1>Widgets Express</h1>
//   </div>', $html);
            $pdf->loadHtml(str_replace(PHP_EOL, '', $html));
            $pdf->render();
            
            $canvas = $pdf->get_canvas();
            $canvas->page_text(280, 820, "{PAGE_NUM}/{PAGE_COUNT}", null, 10, [0, 0, 0]);
            //  Write to file
            file_put_contents($filename, $pdf->output());
            //file_put_contents($filename.".html", $html);
        } else {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
            $writer->save($filename);
        }
    }
    
    /**
     * Генерация WORD отчета по выпуску серии
     *
     * @param string $filename путь до файла для сохранения отчета
     * @param string $series   серия
     */
    static function generateReportEndOfSeries($report, $filename, $series, $report_type = 'docx')
    {
        $json_data = [];
        $query = self::reportEndOfSeries($series);
        $pdf = ($report_type == 'pdf');
        $gtin = "";
        
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->setDefaultFontName('Times New Roman');
        //$word->setDefaultFontSize(14);
        
        $section = $word->createSection();
        $sectionStyle = $section->getSettings();
        $sectionStyle->setPortrait();             //или setLandscape()
        $sectionStyle->setMarginLeft(15 * 56.7);  //15mm
        $sectionStyle->setMarginRight(15 * 56.7);
        //$sectionStyle->setBorderBottomSize(10 * 56.7);
        //$sectionStyle->setBorderTopColor('C0C0C0');
        
        $word->addFontStyle('titleStyle1', [
            'color'     => '000000',
            'size'      => 14,
            'bold'      => false,
            'italic'    => true,
            'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE,
            'name'      => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('titleStyle', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => true,
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('main_bold', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => true,
        ]);
        $word->addFontStyle('main', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => false,
        ]);
        $word->addFontStyle('tabletxt', [
            'color' => '000000',
            'size'  => 12,
            'bold'  => false,
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('tabletxtMin', [
            'color' => '000000',
            'size'  => 8,
            'bold'  => false,
            'name'  => $pdf ? 'DejaVu Sans' : 'Times New Roman',
        ]);
        $word->addFontStyle('tabletxt1', [
            'color'  => '000000',
            'size'   => 12,
            'bold'   => false,
            'italic' => true,
            'name'   => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addTableStyle('base_table',
            ['size' => 12, 'borderSize' => 1, 'borderColor' => '000000', 'width' => 90 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER],
            ['bold' => true, 'bgColor' => 'DDDDDD', 'align' => 'center', 'valign' => 'center']);
        $word->addTableStyle('invis_table', ['cellMargin' => 0, 'size' => 12, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'width' => 90 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
        $cellStyle = ['valign' => 'center'];
        $parStyleCenter = ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $parStyleLeft = ['alignment' => 'left', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $parStyle = ['alignment' => 'left', 'spaceBefore' => 0, 'spaceAfter' => 0];
        
        $i = 0;
        $data = $query->all();
        $full_box = 0;
        $full_box_on_pallet = $nfull = $pallets = [];
        $ind = 0;
        $bdate = $edate = "";
        $nomenclature_name = "Неизвестный препарат";
        $number = $series; //"XXXX";
        $cnt_on_pallet = $band_in_korob_cnt = $cnt_in_box = 0;
        $time = time();
        $isl3 = false;
        foreach ($data as $el) {
            if (time() > ($time + 30) && $report) {
                $report->refresh();
                if ($report->status == 'CANCEL') {
                    return;
                }
                $time = time();
            }
            $a = $el->toArray();
            if ($i == 0 || empty($cnt_in_box)) {
                $res = \Yii::$app->db->createCommand("SELECT nomenclature.gtin, nomenclature.name,generations.object_uid,generations.num,nomenclature.cnt,nomenclature.cnt_on_pallet,nomenclature.hasl3,nomenclature.band_in_korob_cnt"
                    . "      FROM get_full_codes(:arr) as codes "
                    . " LEFT JOIN product ON codes.product_uid=product.id"
                    . " LEFT JOIN nomenclature ON product.nomenclature_uid = nomenclature.id"
                    . " LEFT JOIN generations ON generation_uid = generations.id"
                    . " WHERE codetype_uid = :codetype and nomenclature.id is not null"
                    , [
                        ":arr"      => \app\modules\itrack\components\pghelper::arr2pgarr([$a["grpcode"]]),
                        ":codetype" => \app\modules\itrack\models\CodeType::CODE_TYPE_INDIVIDUAL,
                    ])->queryOne();
                if (!empty($res)) {
                    $nomenclature_name = $res["name"];
                    //$number = $res["object_uid"]."/".$res["num"];
                    $cnt_in_box = intval($res["cnt"]);
                    $cnt_on_pallet = intval($res["cnt_on_pallet"]);
                    $band_in_korob_cnt = intval($res["band_in_korob_cnt"]);
                    $gtin = $res["gtin"];
                    $isl3 = $res["hasl3"];
                }
            }
            if (empty($a["grpcode"])) {
                continue;
            }
            $grpCode = \app\modules\itrack\models\Code::findOneByCode($a["grpcode"]);
            if (!empty($grpCode) && $grpCode->removed) {
                continue;
            }
            if (!empty($grpCode) && $grpCode->l3) {
                continue;
            }  //пропускаем бандероли
            
            if (!empty($grpCode)) {
                if ($isl3) {
                    $c = \Yii::$app->db->createCommand("select count(*) from _get_codes_array(:codes) WHERE parent_code=:code", [":codes" => $grpCode->childrens, ":code" => $grpCode->code])->queryScalar();
                    $ind += (count(\app\modules\itrack\components\pghelper::pgarr2arr($grpCode->childrens)) - $c);
                } else {
                    $c = count(\app\modules\itrack\components\pghelper::pgarr2arr($grpCode->childrens));
                    $ind += $c;
                }
                if (!empty($grpCode->parent_code)) {
                    $pallets[$grpCode->parent_code] = ($pallets[$grpCode->parent_code] ?? 0) + 1;
                }
            } else {
                $c = 0;
            }
            
            if ($isl3) {
                if ($c == $band_in_korob_cnt) {
                    $full_box++;
                    $full_box_on_pallet[(isset($grpCode->parent_code)) ? $grpCode->parent_code : ""] = ($full_box_on_pallet[(isset($grpCode->parent_code)) ? $grpCode->parent_code : ""] ?? 0) + 1;
                } else {
                    $a["cnt"] = (count(\app\modules\itrack\components\pghelper::pgarr2arr($grpCode->childrens)) - $c);
                    $a["parrent"] = (isset($grpCode->parent_code)) ? $grpCode->parent_code : "";
                    $nfull[] = $a;
                }
            } else {
                if ($c == $cnt_in_box) {
                    $full_box++;
                    $full_box_on_pallet[(isset($grpCode->parent_code)) ? $grpCode->parent_code : ""] = ($full_box_on_pallet[(isset($grpCode->parent_code)) ? $grpCode->parent_code : ""] ?? 0) + 1;
                } else {
                    $a["cnt"] = $c;
                    $a["parrent"] = (isset($grpCode->parent_code)) ? $grpCode->parent_code : "";
                    $nfull[] = $a;
                }
            }
            if (empty($bdate)) {
                $bdate = $a["created_at"];
            }
            $edate = $a["created_at"];
            $i++;
        }
        $fpallets = 0;
        $npallets = [];
        foreach ($pallets as $pallet => $cnt) {
            if ($cnt >= $cnt_on_pallet) {
                $fpallets++;
            } else {
                //ищем неполные паллеты
                /** TODO  запрос через _get_extdata
                 *
                 */
                $p = \app\modules\itrack\models\Extdata::find()->andWhere(['typeof' => Report4::TYPEOF, 'data1' => $pallet, 'params1' => $series])->one();
                if (!empty($p)) {
                    $npallets[] = $p;
                }
            }
        }
        
        $json_data["series"] = (string)$series;
        $json_data["isl3"] = $isl3;
        
        //вывод отчета
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding(($nomenclature_name ?? '') . ", №" . ($number ?? ''), 'HTML-ENTITIES', 'UTF-8'), 'titleStyle1');
        $json_data["nomenclature"] = (string)$nomenclature_name;
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding("Выпуск готового продукта", 'HTML-ENTITIES', 'UTF-8'), 'titleStyle');
        $section->addTextBreak(1);
        
        $table = $section->addTable('invis_table');
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Начало серии', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(\Yii::$app->formatter->asDatetime($bdate, 'php: d.m.Y H:i'), 'tabletxt1', $parStyle);
        $json_data["startDate"] = \Yii::$app->formatter->asDatetime($bdate, 'php: d.m.Y H:i');
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Окончание серии', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(\Yii::$app->formatter->asDatetime($edate, 'php: d.m.Y H:i'), 'tabletxt1', $parStyle);
        $json_data["endDate"] = \Yii::$app->formatter->asDatetime($edate, 'php: d.m.Y H:i');
        if ($isl3) {
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Количество потребительских упаковок в 1 бандероле', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100)->addText(mb_convert_encoding(($cnt_in_box ?? '') . " шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
            $json_data["cnt_in_box"] = (integer)$cnt_in_box;
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Количество бандеролей в 1 коробе', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100)->addText(mb_convert_encoding(($cnt_in_box ?? '') . " шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
            $json_data["band_in_korob_cnt"] = (integer)$band_in_korob_cnt;
        } else {
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Количество потребительских упаковок в 1 коробе', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100)->addText(mb_convert_encoding(($cnt_in_box ?? '') . " шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
            $json_data["cnt_in_box"] = (integer)$cnt_in_box;
        }
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Количество полных транспортных коробов', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding(($full_box ?? '') . " шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["full_boxes"] = (integer)$full_box;
        $json_data["not_full_boxes"] = [];
        foreach ($nfull as $box) {
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Неполный транспортный короб № ' . sprintf('%02d', $box["boxNumberOk"]), 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding($box["cnt"] . ($isl3 ? " банд." : " шт."), 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
            $json_data["not_full_boxes"][] = ["box_number" => (integer)$box["boxNumberOk"], "cnt" => (integer)$box["cnt"]];
        }
        $c = self::getOtborCnt($series, $bdate, $edate);
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Общее количество потребительских упаковок, выпущенных за серию, включая отбор проб', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText((intval($ind) + intval($c) ?? '') . mb_convert_encoding(" шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["total_cnt"] = (integer)(intval($ind) + intval($c) ?? '');
        
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Отбор проб в процессе упаковки', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding(($c ?? '') . " шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["otbor"] = (integer)($c ?? '');
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Образцы маркированных потребительских упаковок', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding("2 шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["samples"] = 2;
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Образцы маркированных этикеток на транспортный короб', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding("2 шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["samples_box"] = 2;
        
        $section->addTextBreak(1);
        $table = $section->addTable('invis_table');
        $table->addRow();
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('Мастер смены', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleLeft);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(ФИО)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(подпись)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('"____"_________20__г.<br/>(дата)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addRow();
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('Начальник участка', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleLeft);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(ФИО)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(подпись)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('"____"_________20__г.<br/>(дата)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        
        $section->addTextBreak(1);
        $section->addPageBreak();
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding(($nomenclature_name ?? '') . ", №" . ($number ?? ''), 'HTML-ENTITIES', 'UTF-8'), 'titleStyle1');
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding("ИТОГО: сдано для РЕАЛИЗАЦИИ", 'HTML-ENTITIES', 'UTF-8'), 'titleStyle');
        $section->addTextBreak(1);
        
        $table = $section->addTable('invis_table');
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Количество транспортных коробов на одной паллете', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(($cnt_on_pallet ?? '') . mb_convert_encoding(" шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["cnt_on_pallet"] = (integer)($cnt_on_pallet ?? '');
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Количество полных паллет', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(($fpallets ?? '') . mb_convert_encoding(" шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["full_pallets"] = (integer)($fpallets ?? '');
        if (!empty($npallets)) {
            foreach ($npallets as $pal) {
                $bb = [];
                $table->addRow();
                $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Неполная паллета № ' . sprintf('%02d', $pal->data4) . ': ', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
                $table->addCell(3100, $cellStyle)->addText("", 'tabletxt', $parStyle);
                $table->addRow();
                $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Полных транспортных коробов', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
                if (isset($full_box_on_pallet[$pal->data1])) {
                    $table->addCell(3100, $cellStyle)->addText($full_box_on_pallet[$pal->data1] . mb_convert_encoding(" шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
                } else {
                    $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding("0 шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
                }
                $json_data["not_full_pallets"][] = ["pallet_number" => (integer)$pal->data4, "full_boxes" => (integer)isset($full_box_on_pallet[$pal->data1]) ? $full_box_on_pallet[$pal->data1] : 0];
            }
        } else {
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Неполная паллета №   ' . ': ', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100, $cellStyle)->addText("", 'tabletxt', $parStyle);
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Полных транспортных коробов', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding("   шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
//            $table->addRow();
//            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Неполный транспортный короб № ', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
//            $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding("   шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
//            $json_data["not_full_pallets"][] = ["pallet_number" => null, "full_boxes" => null, "not_full_boxes" => [["box_number" => null, "cnt" => null]]];
        }
        foreach ($nfull as $box) {
            $table->addRow();
            $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Неполный транспортный короб № ' . sprintf('%02d', $box["boxNumberOk"]), 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
            $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding($box["cnt"] . ($isl3 ? " банд." : " шт."), 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        }
        
        
        $table->addRow();
        $table->addCell(6000, $cellStyle)->addText(mb_convert_encoding('Общее количество потребительских упаковок', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyle);
        $table->addCell(3100, $cellStyle)->addText(mb_convert_encoding(($ind ?? '') . " шт.", 'HTML-ENTITIES', 'UTF-8'), 'tabletxt1', $parStyle);
        $json_data["total_end"] = (integer)($ind ?? '');
        
        $section->addTextBreak(1);
        $table = $section->addTable('invis_table');
        $table->addRow();
        $table->addCell(3000, $cellStyle)->addText(mb_convert_encoding('Начальник участка', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleLeft);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(ФИО)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2000, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(подпись)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('"____"_________20__г.<br/>(дата)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addRow();
        $table->addCell(3000, $cellStyle)->addText(mb_convert_encoding('Начальник упаковочного производства', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleLeft);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(ФИО)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2000, $cellStyle)->addText(mb_convert_encoding('_____________<br/>(подпись)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        $table->addCell(2500, $cellStyle)->addText(mb_convert_encoding('"____"_________20__г.<br/>(дата)', 'HTML-ENTITIES', 'UTF-8'), 'tabletxt', $parStyleCenter);
        
        if ($report_type == 'json') {
            file_put_contents($filename, json_encode($json_data));
        } else {
            if (\app\modules\itrack\models\Constant::get('RafarmaReportEndOfSeries') == 'pdf' || $report_type == 'pdf') {
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'PDF');
                $writer->save($filename);
                $writer->isPdf = true;
                
                //  PDF settings
                $paperSize = 'A4';
                $orientation = 'portrait';
                
                //  Create PDF
                $pdf = new \Dompdf\Dompdf();
                $pdf->setPaper(strtolower($paperSize), $orientation);
                $html = $writer->getContent();
                //гвозди со стилями
                $html = str_replace("text-decoration: underline", "text-decoration: none", $html);
                $html = str_replace('</style>', "table {border: 0px}\ntd {border: 0px}\n</style>", $html);
//                $html = str_replace('<body>', "<body>\n".'<div id="header">
//     <h1>Widgets Express</h1>
//   </div>', $html);
                $pdf->loadHtml(str_replace(PHP_EOL, '', $html));
                $pdf->render();
                
                $canvas = $pdf->get_canvas();
                $canvas->page_text(280, 820, "{PAGE_NUM}/{PAGE_COUNT}", null, 10, [0, 0, 0]);
                //  Write to file
                file_put_contents($filename, $pdf->output());
                //file_put_contents($filename.".html", $html);
                
            } else {
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
                $writer->save($filename);
            }
        }
        if (!isset($json_data["not_full_pallets"])) {
            $json_data["not_full_pallets"] = [];
        }
        
        return [$series, $gtin, $json_data["total_cnt"], $json_data["full_boxes"] + count($json_data["not_full_boxes"]), $json_data["full_pallets"] + count($json_data["not_full_pallets"])];
    }
    
    /**
     * Получение кол-ва кодов попавших в отбор+утилизацию
     *
     * @param type $series
     * @param type $bdate
     * @param type $edate
     */
    static function getOtborCnt($series, $bdate, $edate)
    {
        if (empty($bdate) || empty($edate)) {
            return 0;
        }
        if (SERVER_RULE != SERVER_RULE_SKLAD) {
            $res = \Yii::$app->db->createCommand("select sum(cardinality(codes))as cnt from operations
                                left join product ON (operations.product_uid=product.id or product.id=ANY(operations.products))
                                where operation_uid in (:op1,:op2) and
                                product.series=:series and operations.created_at>=:bdate and operations.created_at<=:edate
                ", [
                ":series" => $series,
                ":bdate"  => $bdate,
                ":edate"  => $edate,
                ":op1"    => \app\modules\itrack\models\Fns::OPERATION_CONTROL_ID,
                ":op2"    => \app\modules\itrack\models\Fns::OPERATION_WDEXT_ID,
            ])->queryOne();
        } else {
            $data = [];
            $res = \Yii::$app->db_main->createCommand("select distinct unnest(codes)as code from operations
                                left join product ON (operations.product_uid=product.id or product.id=ANY(operations.products))
                                where operation_uid in (:op1,:op2) and
                                product.series=:series and operations.created_at>=:bdate and operations.created_at<=:edate
                ", [
                ":series" => $series,
                ":bdate"  => $bdate,
                ":edate"  => $edate,
                ":op1"    => \app\modules\itrack\models\Fns::OPERATION_CONTROL_ID,
                ":op2"    => \app\modules\itrack\models\Fns::OPERATION_WDEXT_ID,
            ])->queryAll();
            foreach ($res as $r) {
                $data[$r["code"]] = 1;
            }
            
            $res = \Yii::$app->db_main->createCommand("select distinct unnest(codes)as code from operations
                                left join product ON (operations.product_uid=product.id or product.id=operations.product_uid)
                                where operation_uid in (:op1,:op2) and
                                product.series=:series and operations.created_at>=:bdate and operations.created_at<=:edate
                ", [
                ":series" => $series,
                ":bdate"  => $bdate,
                ":edate"  => $edate,
                ":op1"    => \app\modules\itrack\models\Fns::OPERATION_CONTROL_ID,
                ":op2"    => \app\modules\itrack\models\Fns::OPERATION_WDEXT_ID,
            ])->queryAll();
            foreach ($res as $r) {
                $data[$r["code"]] = 1;
            }
            
            return count($data);
        }
        if (isset($res["cnt"])) {
            $c = intval($res["cnt"]);
        } else {
            $c = 0;
        }
        
        return $c;
    }
    
    /**
     * Получение кол-ва паллет по всей серии
     * only master
     *
     * @param string $series серия
     *
     * @return int
     */
    static function getPalletaCnt($series)
    {
        if (SERVER_RULE != SERVER_RULE_SKLAD) {
            $res = \Yii::$app->db->createCommand("select count(*) as cnt from codes_grp
                                                        LEFT JOIN product ON product_uid = product.id
                                                        WHERE product.series = :series and is_paleta(flag) = true", [
                ":series" => $series,
            ])->queryOne();
        } else {
            $data = [];
            //данные слейва
            $res = \Yii::$app->db_main->createCommand("select codes_grp.id from codes_grp
                                                        LEFT JOIN product ON product_uid = product.id
                                                        WHERE product.series = :series and is_paleta(flag) = true", [
                ":series" => $series,
            ])->queryAll();
            foreach ($res as $r) {
                $data[$r["id"]] = 1;
            }
            //данные кеша
            $res = \Yii::$app->db->createCommand("select codes_cache.id from codes_cache
                                                        LEFT JOIN product ON product_uid = product.id
                                                        WHERE product.series = :series and is_paleta(flag) = true", [
                ":series" => $series,
            ])->queryAll();
            foreach ($res as $r) {
                $data[$r["id"]] = 1;
            }
            
            return count($data);
        }
        if (empty($res)) {
            return 0;
        }
        
        return intval($res["cnt"]);
    }
    
    /**
     * Получение кол-ва паллет по всей серии за смену
     * only master
     *
     * @param string $series серия
     * @param string $bdate  таймштамп начало смены
     * @param string $edate  таймштамп конец смены
     *
     * @return int
     */
    static function getPalletaCntShift($series, $bdate, $edate)
    {
        if (SERVER_RULE != SERVER_RULE_SKLAD) {
            $res = \Yii::$app->db->createCommand("select count(*) as cnt from (
                                                            select code_uid from history where operation_uid in (41,43) and created_at >= :bdate and created_at <= :edate
                                                    ) as history
                                                    LEFT JOIN codes_grp ON codes_grp.id = code_uid
                                                    LEFT JOIN product ON product_uid = product.id
                                                    WHERE codes_grp.id is not null and is_paleta(flag) and series=:series
                                            ", [
                ":series" => $series,
                ":bdate"  => $bdate,
                ":edate"  => $edate,
            ])->queryOne();
        } else {
            $data = [];
            //данные слейва
            $res = \Yii::$app->db_main->createCommand("select code_uid from (
                                                            select code_uid from history where operation_uid in (41,43) and created_at >= :bdate and created_at <= :edate
                                                    ) as history
                                                    LEFT JOIN codes_grp ON codes_grp.id = code_uid
                                                    LEFT JOIN product ON product_uid = product.id
                                                    WHERE codes_grp.id is not null and is_paleta(flag) and series=:series
                                            ", [
                ":series" => $series,
                ":bdate"  => $bdate,
                ":edate"  => $edate,
            ])->queryAll();
            foreach ($res as $r) {
                $data[$r["code_uid"]] = 1;
            }
            //данные кеша
            $res = \Yii::$app->db->createCommand("select (a->>'code_uid')::bigint as code_uid from (
                                        select jsonb_array_elements(history) as a from codes_cache
                                                LEFT JOIN generations ON codes_cache.generation_uid = generations.id
                                                LEFT JOIN product ON product.id = codes_cache.product_uid
                                                WHERE generations.codetype_uid=2 and product.series=:series) as aa
                                                WHERE
                                                (a->>'operation_uid')::bigint in (41,43)
                                                and (a->>'created_at')::timestamp with time zone >= :bdate
                                                and (a->>'created_at')::timestamp with time zone <= :edate
                                                ", [
                ":series" => $series,
                ":bdate"  => $bdate,
                ":edate"  => $edate,
            ])->queryAll();
            foreach ($res as $r) {
                $data[$r["code_uid"]] = 1;
            }
            
            return count($data);
        }
        if (empty($res)) {
            return 0;
        }
        
        return intval($res["cnt"]);
    }
    
    public function scenarios()
    {
        return [
            'default' => ['boxesOkCount', 'series', 'grpcode', 'weight', 'fio', 'typeof', 'isBox', 'boxNumber', 'boxNumberOk', 'minWeight', 'maxWeight', 'comment', 'confirmed'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['series', 'typeof'], 'required'],
            ['confirmed', 'boolean'],
        ];
    }
    
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $query,
            'pagination' => false,
        ]);
        $query->andWhere(new \yii\db\Expression("data1 != ''"));
        $query->andWhere(new \yii\db\Expression("data1 != 'changeWeight'"));
        
        if (!empty($params["bdate"])) {
            $query->andFilterWhere(['>=', 'created_at', $params["bdate"]]);
        }
        if (!empty($params["edate"])) {
            $query->andFilterWhere(['<=', 'created_at', $params["edate"]]);
        }
        
        if (!empty($params["series"])) {
            $query->andFilterWhere(['params1' => $params["series"]]);
        }
        if (!empty($params["isBox"])) {
            $query->andFilterWhere(['data6' => $params["isBox"]]);
        }
        if (!empty($params["boxNumber"])) {
            $query->andFilterWhere(['data7' => $params["boxNumber"]]);
        }
        if (!empty($params["object_uid"])) {
            $query->andFilterWhere(['object_uid' => $params["object_uid"]]);
        }
        $query->orderBy(['created_at' => SORT_ASC]);
        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
        
        $query->with('object');
        $query->with('createdBy');
        
        return $dataProvider;
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            if (empty($event->sender->created_at)) {
                $event->sender->created_at = 'NOW()';
            }
            $data = [];
            $data[] = $event->sender->data1 = $event->sender->grpcode ?? '';
            $data[] = $event->sender->data2 = $event->sender->weight ?? '';
            $data[] = $event->sender->data3 = $event->sender->fio ?? '';
            $data[] = $event->sender->data4 = $event->sender->minWeight ?? '';
            $data[] = $event->sender->data5 = $event->sender->maxWeight ?? '';
            $data[] = $event->sender->data6 = $event->sender->isBox ?? '';
            $data[] = $event->sender->data7 = $event->sender->boxNumber ?? '';
            $event->sender->data = \app\modules\itrack\components\pghelper::arr2pgarr($data);
            $params = [];
            $params[] = $event->sender->params1 = $event->sender->series ?? '';
            $params[] = $event->sender->params2 = $event->sender->comment ?? '';
            $params[] = $event->sender->params3 = $event->sender->boxNumberOk ?? '';
            $params[] = '';
            $event->sender->params = \app\modules\itrack\components\pghelper::arr2pgarr($params);
            $event->sender->typeof = self::TYPEOF;
        });
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */
            if (empty($event->created_by)) {
                $this->created_by = Yii::$app->user->getId();
            }
            
            $user = Yii::$app->user->getIdentity();
            $event->sender->object_uid = $user->object_uid;
            $event->sender->typeof = self::TYPEOF;
        });
    }
    
    public function fields()
    {
        return [
            'series'      => function () {
                return $this->params1;
            },
            'comment'     => function () {
                return $this->params2;
            },
            'boxNumberOk' => function () {
                return $this->params3;
            },
            'grpcode'     => function () {
                return $this->data1;
            },
            'weight'      => function () {
                return (float)$this->data2;
            },
            'fio'         => function () {
                return $this->data3;
            },
            'minWeight'   => function () {
                return $this->data4;
            },
            'maxWeight'   => function () {
                return $this->data5;
            },
            'isBox'       => function () {
                return $this->data6;
            },
            'boxNumber'   => function () {
                return $this->data7;
            },
            'typeof'      => function () {
                return $this->typeof;
            },
            'created_by'  => function () {
                return $this->created_by;
            },
            'created_at'  => function () {
                return $this->created_at;
            },
            'object_uid'  => function () {
                return $this->object_uid;
            },
            'object'      => function () {
                return $this->object;
            },
            'createdBy'   => function () {
                return $this->createdBy;
            },
        ];
    }
    
    public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->confirmed) {
            $report = new \app\modules\itrack\models\Report();
            $report->load([
                "report_type" => 'RafarmaReportEndOfSeries',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$this->series]),
                "created_by"  => \Yii::$app->user->getId(),
                "typeof"      => 'pdf',
            ], '');
            $report->save(false);
            $report->refresh();
            
            $fns = new \app\modules\itrack\models\Fns();
            $fns->load([
                "operation_uid" => \app\modules\itrack\models\Fns::OPERATION_1C_OUT,
                "state"         => \app\modules\itrack\models\Fns::STATE_1CPREPARING,
                "created_by"    => \Yii::$app->user->getIdentity()->id,
                "object_uid"    => \Yii::$app->user->getIdentity()->object_uid,
                "data"          => \app\modules\itrack\components\pghelper::arr2pgarr(['0400', $this->series, (string)$report->id]),
            ], '');
            $fns->save(false, ["operation_uid", "state", "created_by", "object_uid", "data"]);
            
            return true;
        }
        
        //фича если не прислали групповой код, считаем это повторным взвешиванием
        if (empty($this->grpcode)) {
            $q = self::find();
            
            if (SERVER_RULE != SERVER_RULE_SKLAD) {
                $q->andWhere(['params1' => $this->series, 'data7' => $this->boxNumber]);
            } else {
                $q->from(['extdata' => "(SELECT * FROM _get_extdata('params1 = ''" . $this->series . "'' and data7 = ''" . $this->boxNumber . "'' and typeof = ''" . self::TYPEOF . "'''))"]);
            }
            
            $old = $q->one();
            if (!empty($old)) {
                $this->params3 = $this->boxNumberOk = $this->boxesOkCount - 1;
                $this->created_at = $old->created_at;
                $this->data1 = $this->grpcode = $old->data1; //сохраняем групповой код от старой операции
                $old->delete();
            }
        }
        
        return parent::save($runValidation, $attributeNames);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getObject()
    {
        return $this->hasOne(\app\modules\itrack\models\Facility::class, ['id' => 'object_uid']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(\app\modules\itrack\models\User::class, ['id' => 'created_by']);
    }
    
}
