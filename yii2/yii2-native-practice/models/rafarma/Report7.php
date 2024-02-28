<?php

namespace app\modules\itrack\models\rafarma;

use Yii;

class Report7 extends \app\modules\itrack\models\Extdata
{
    
    const TYPEOF = "reportRafarma7";
    public $date;
    public $shift;
    public $zakaz;
    public $nomenclature;
    public $serie;
    public $prodcnt;
    public $brakcnt;
    public $brakinfo;
    public $fio;
    public $equipid;
    
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
     * Поулчение данных для отчета по производству
     *
     * @param string $series серия
     *
     * @return \Yii\db\ActiveQuery
     */
    static function report($equipid, $bdate, $edate, $nomenclature, $shift)
    {
        $query = self::find();
        $query->andWhere(['between', 'params3', $bdate, $edate]);
        $query->andWhere(['typeof' => self::TYPEOF]);
        if (!empty($equipid)) {
            $query->andWhere(['params2' => $equipid]);
        }
        if (!empty($shift)) {
            $query->andWhere(['data1' => $shift]);
        }
        if (!empty($nomenclature)) {
            $query->andWhere(['ilike', 'data3', $nomenclature]);
        }
        
        $query->orderBy('params3, data1');
        
        return $query;
    }
    
    /**
     * Генерация WORD отчета по производству
     *
     * @param string $filename путь до файла для сохранения отчета
     * @param string $series   серия
     */
    static function generateReport($report, $filename, $equipid, $bdate, $edate, $nomenclature, $shift, $report_type = 'docx')
    {
        $json_data = [];
        $query = self::report($equipid, $bdate, $edate, $nomenclature, $shift);
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
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('main', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => false,
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
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
        $word->addTableStyle('base_table', ['size' => 11, 'borderSize' => 1, 'borderColor' => '000000', 'width' => 100 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER], ['bold' => true, 'bgColor' => 'DDDDDD', 'align' => 'center', 'valign' => 'center']);
        $word->addTableStyle('invis_table', ['cellMargin' => 0, 'size' => 12, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'width' => 90 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
        $cellStyle = ['valign' => 'center'];
        $parStyleCenter = ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $parStyleLeft = ['alignment' => 'left', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $parStyle = ['alignment' => 'left', 'spaceBefore' => 0, 'spaceAfter' => 0];
        
        $i = 0;
        $json_data = [];
        $data = $query->all();
        $equip = \app\modules\itrack\models\Equip::findOne($equipid);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding('ОТЧЕТ О ПРОИЗВОДСТВЕ', 'HTML-ENTITIES', 'UTF-8'), 'titleStyle');
        $section->addTextBreak(2);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Дата формирования отчета: \t\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(\Yii::$app->formatter->asDatetime(time(), 'php:H:i d/m/Y'), 'main');
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Производственная линия №:\t\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(mb_convert_encoding($equip->fio . " " . $equip->login, 'HTML-ENTITIES', 'UTF-8'), 'main');
        $section->addTextBreak(1);
        
        $table = $section->addTable('base_table');
        $table->addRow(50, ['tblHeader' => true]);
        $table->addCell(500)->addText(mb_convert_encoding('Дата', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(500)->addText(mb_convert_encoding('Смена', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(500)->addText(mb_convert_encoding('Заказ №', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1500)->addText(mb_convert_encoding('Наименование препарата', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(500)->addText(mb_convert_encoding('Серия', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1500)->addText(mb_convert_encoding('Количество сериализованной продукции', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(500)->addText(mb_convert_encoding('Кол-во брака', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1000)->addText(mb_convert_encoding('Статистика по отбраковке', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1000)->addText(mb_convert_encoding('ФИО оператора', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        
        $key = "";
        $buf = [];
        $time = time();
        
        foreach ($data as $el) {
            if (time() > ($time + 30) && $report) {
                $report->refresh();
                if ($report->status == 'CANCEL') {
                    return;
                }
                $time = time();
            }
            $a = $el->toArray();
            $a["date"] = preg_replace('#\s.*$#si', '', $a["date"]);
            
            if (empty($key) || $key != ($a["date"] . $a["shift"] . $a["zakaz"] . $a["nomenclature"] . $a["serie"])) {
                if (!empty($key)) {
                    $kol = 0;
                    $kbrak = 0;
                    $brak = [];
                    $fio = [];
                    foreach ($buf as $v) {
                        $kol += $v["prodcnt"];
                        $fio[$v["fio"]] = 1;
                        $b = json_decode($v["brakinfo"], true);
                        foreach ($b as $t => $k) {
                            $kbrak += $k;
                            if (!isset($brak[$t])) {
                                $brak[$t] = $k;
                            } else {
                                $brak[$t] += $k;
                            }
                        }
                    }
                    $s = [];
                    foreach ($brak as $k => $v) {
                        $s[] = $k . "-" . $v;
                    }
                    $brak = implode(", ", $s);
                    
                    //вывод буфа
                    $table->addRow();
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(\Yii::$app->formatter->asDate($buf[0]["date"], 'php:d.m.Y'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["shift"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["zakaz"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["nomenclature"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["serie"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kol, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kbrak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($brak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding(implode(", ", array_keys($fio)), 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    
                    $buf = [];
                }
                //заполнение новым кеем
                $key = $a["date"] . $a["shift"] . $a["zakaz"] . $a["nomenclature"] . $a["serie"];
            }
            $buf[] = $a;
            
            $i++;
        }
        if (!empty($buf)) {
            $kol = 0;
            $kbrak = 0;
            $brak = [];
            $fio = [];
            foreach ($buf as $v) {
                $kol += $v["prodcnt"];
                $fio[$v["fio"]] = 1;
                $b = json_decode($v["brakinfo"], true);
                foreach ($b as $t => $k) {
                    $kbrak += $k;
                    if (!isset($brak[$t])) {
                        $brak[$t] = $k;
                    } else {
                        $brak[$t] += $k;
                    }
                }
            }
            $s = [];
            foreach ($brak as $k => $v) {
                $s[] = $k . "-" . $v;
            }
            $brak = implode(", ", $s);
            
            //вывод буфа
            $table->addRow();
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(\Yii::$app->formatter->asDate($buf[0]["date"], 'php:d.m.Y'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["shift"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["zakaz"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["nomenclature"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["serie"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kol, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kbrak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($brak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding(implode(", ", array_keys($fio)), 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
        }
        
        if ($report_type == 'json') {
            file_put_contents($filename, json_encode($json_data));
        } else {
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
                $html = str_replace('</style>', "th {border: 1px solid black;}\n</style>", $html);
                $pdf->loadHtml(str_replace(PHP_EOL, '', $html));
                $pdf->render();

//                $canvas = $pdf->get_canvas();
//                $canvas->page_text(280, 820, "{PAGE_NUM}/{PAGE_COUNT}", null, 10, array(0, 0, 0));
                //  Write to file
                file_put_contents($filename, $pdf->output());
                //file_put_contents($filename.".html", $html);
            } else {
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
                $writer->save($filename);
            }
        }
        
        return $json_data;
    }
    
    /**
     * Генерация WORD отчета по производству
     *
     * @param string $filename путь до файла для сохранения отчета
     * @param string $series   серия
     */
    static function generateReportByShift($report, $filename, $equipid, $bdate, $edate, $nomenclature, $shift, $report_type = 'docx')
    {
        $json_data = [];
        $query = self::report($equipid, $bdate, $edate, $nomenclature, $shift);
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
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
        ]);
        $word->addFontStyle('main', [
            'color' => '000000',
            'size'  => 14,
            'bold'  => false,
            'name'  => $pdf ? 'DejaVu Sans' : 'Calibri',
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
        $word->addTableStyle('base_table', ['size' => 11, 'borderSize' => 1, 'borderColor' => '000000', 'width' => 100 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER], ['bold' => true, 'bgColor' => 'DDDDDD', 'align' => 'center', 'valign' => 'center']);
        $word->addTableStyle('invis_table', ['cellMargin' => 0, 'size' => 12, 'borderSize' => 0, 'borderColor' => 'FFFFFF', 'width' => 90 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
        $cellStyle = ['valign' => 'center'];
        $parStyleCenter = ['alignment' => 'center', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $parStyleLeft = ['alignment' => 'left', 'spaceBefore' => 0, 'spaceAfter' => 0];
        $parStyle = ['alignment' => 'left', 'spaceBefore' => 0, 'spaceAfter' => 0];
        
        $i = 0;
        $json_data = [];
        $data = $query->all();
        $equip = \app\modules\itrack\models\Equip::findOne($equipid);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding('ОТЧЕТ ЗА СМЕНУ', 'HTML-ENTITIES', 'UTF-8'), 'titleStyle');
        $section->addTextBreak(2);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Производственная линия №:\t\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(mb_convert_encoding($equip->fio . " " . $equip->login, 'HTML-ENTITIES', 'UTF-8'), 'main');
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Дата:\t\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(mb_convert_encoding(\Yii::$app->formatter->asDate($bdate, "php:d/m/Y"), 'HTML-ENTITIES', 'UTF-8'), 'main');
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Смена №:\t\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(mb_convert_encoding($shift, 'HTML-ENTITIES', 'UTF-8'), 'main');
        $section->addTextBreak(1);
        
        $table = $section->addTable('base_table');
        $table->addRow(50, ['tblHeader' => true]);
        $table->addCell(500)->addText(mb_convert_encoding('Заказ №', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1500)->addText(mb_convert_encoding('Наименование препарата', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(500)->addText(mb_convert_encoding('Серия', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1500)->addText(mb_convert_encoding('Количество сериализованной продукции', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(500)->addText(mb_convert_encoding('Кол-во брака', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1000)->addText(mb_convert_encoding('Статистика по отбраковке', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(1000)->addText(mb_convert_encoding('ФИО оператора', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 9, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        
        $key = "";
        $buf = [];
        $time = time();
        
        foreach ($data as $el) {
            if (time() > ($time + 30) && $report) {
                $report->refresh();
                if ($report->status == 'CANCEL') {
                    return;
                }
                $time = time();
            }
            $a = $el->toArray();
            
            if (empty($key) || $key != ($a["date"] . $a["shift"] . $a["zakaz"] . $a["nomenclature"] . $a["serie"])) {
                if (!empty($key)) {
                    $kol = 0;
                    $kbrak = 0;
                    $brak = [];
                    $fio = [];
                    foreach ($buf as $v) {
                        $kol += $v["prodcnt"];
                        $fio[$v["fio"]] = 1;
                        $b = json_decode($v["brakinfo"], true);
                        foreach ($b as $t => $k) {
                            $kbrak += $k;
                            if (!isset($brak[$t])) {
                                $brak[$t] = $k;
                            } else {
                                $brak[$t] += $k;
                            }
                        }
                    }
                    $s = [];
                    foreach ($brak as $k => $v) {
                        $s[] = $k . "-" . $v;
                    }
                    $brak = implode(", ", $s);
                    
                    //вывод буфа
                    $table->addRow();
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["zakaz"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["nomenclature"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["serie"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kol, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kbrak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($brak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding(implode(", ", array_keys($fio)), 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
                    
                    //заполнение новым кеем
                    $buf = [];
                }
                $key = $a["date"] . $a["shift"] . $a["zakaz"] . $a["nomenclature"] . $a["serie"];
            }
            $buf[] = $a;
            $i++;
        }
        if (!empty($buf)) {
            $kol = 0;
            $kbrak = 0;
            $brak = [];
            $fio = [];
            foreach ($buf as $v) {
                $kol += $v["prodcnt"];
                $fio[$v["fio"]] = 1;
                $b = json_decode($v["brakinfo"], true);
                foreach ($b as $t => $k) {
                    $kbrak += $k;
                    if (!isset($brak[$t])) {
                        $brak[$t] = $k;
                    } else {
                        $brak[$t] += $k;
                    }
                }
            }
            $s = [];
            foreach ($brak as $k => $v) {
                $s[] = $k . "-" . $v;
            }
            $brak = implode(", ", $s);
            
            //вывод буфа
            $table->addRow();
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["zakaz"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["nomenclature"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($buf[0]["serie"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kol, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(500, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($kbrak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($brak, ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
            $table->addCell(1000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding(implode(", ", array_keys($fio)), 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'size' => 10]);
        }
        
        if ($report_type == 'json') {
            file_put_contents($filename, json_encode($json_data));
        } else {
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
                $html = str_replace('</style>', "th {border: 1px solid black;}\n</style>", $html);
                $pdf->loadHtml(str_replace(PHP_EOL, '', $html));
                $pdf->render();

//                $canvas = $pdf->get_canvas();
//                $canvas->page_text(280, 820, "{PAGE_NUM}/{PAGE_COUNT}", null, 10, array(0, 0, 0));
                //  Write to file
                file_put_contents($filename, $pdf->output());
                //file_put_contents($filename.".html", $html);
            } else {
                $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
                $writer->save($filename);
            }
        }
        
        return $json_data;
    }
    
    public function scenarios()
    {
        return [
            'default' => ['date', 'shift', 'zakaz', 'nomenclature', 'serie', 'prodcnt', 'brakcnt', 'brakinfo', 'fio', 'equipid'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['date', 'shift', 'zakaz', 'nomenclature', 'serie', 'prodcnt', 'brakinfo', 'fio', 'equipid'], 'required'],
            [['date', 'shift', 'zakaz', 'nomenclature', 'serie', 'prodcnt', 'brakinfo', 'fio', 'equipid'], 'string'],
            ['shift', 'in', 'range' => ['1', '2', '3']],
            ['date', 'match', 'pattern' => '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@'],
            ['prodcnt',
                function ($attr, $params, $validator) {
                    if (!intval($this->$attr)) {
                        $this->addError($attr, 'Должно быть числом');
                    }
                }],
            ['equipid',
                function ($attr, $params, $validator) {
                    $e = \app\modules\itrack\models\Equip::findOne(intval($this->$attr));
                    if (empty($e)) {
                        $this->addError($attr, 'Должно быть идентификатором оборудования');
                    }
                }],
        ];
    }
    
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $query,
            'pagination' => false,
        ]);
        
        if (!empty($params["bdate"])) {
            $query->andFilterWhere(['>=', 'params3', $params["bdate"]]);
        }
        if (!empty($params["edate"])) {
            $query->andFilterWhere(['<=', 'params3', $params["edate"]]);
        }
        
        if (!empty($params["serie"])) {
            $query->andFilterWhere(['params1' => $params["serie"]]);
        }
        if (!empty($params["equipid"])) {
            $query->andFilterWhere(['params2' => $params["equipid"]]);
        }
        if (!empty($params["shift"])) {
            $query->andFilterWhere(['data1' => $params["shift"]]);
        }
        if (!empty($params["nomenclature"])) {
            $query->andFilterWhere(['ilike', 'data3', $params["nomenclature"]]);
        }
        
        $query->orderBy(['params3' => SORT_ASC]);
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
            $data[] = $event->sender->data1 = $event->sender->shift ?? '';
            $data[] = $event->sender->data2 = $event->sender->zakaz ?? '';
            $data[] = $event->sender->data3 = $event->sender->nomenclature ?? '';
            $data[] = $event->sender->data4 = $event->sender->fio ?? '';
            $data[] = $event->sender->data5 = $event->sender->prodcnt ?? '';
            $data[] = $event->sender->data6 = $event->sender->brakcnt ?? '';
            $data[] = $event->sender->data7 = $event->sender->brakinfo ?? '';
            $event->sender->data = \app\modules\itrack\components\pghelper::arr2pgarr($data);
            $params = [];
            $params[] = $event->sender->params1 = $event->sender->serie ?? '';
            $params[] = $event->sender->params2 = $event->sender->equipid ?? '';
            $params[] = $event->sender->params3 = $event->sender->date ?? '';
            $params[] = '';
            $event->sender->params = \app\modules\itrack\components\pghelper::arr2pgarr($params);
            $event->sender->typeof = self::TYPEOF;
        });
        $this->on(self::EVENT_BEFORE_VALIDATE, function ($event) {
            /** @var $event ModelEvent */
            if (empty($event->created_by)) {
                $this->created_by = Yii::$app->user->getId();
            }
            
            if (is_object($event->sender->brakinfo) || is_array($event->sender->brakinfo)) {
                $event->sender->brakinfo = json_encode($event->sender->brakinfo);
            }
            $user = Yii::$app->user->getIdentity();
            $event->sender->object_uid = $user->object_uid;
            $event->sender->typeof = self::TYPEOF;
        });
    }
    
    public function fields()
    {
        return [
            'serie'        => function () {
                return $this->params1;
            },
            'equipid'      => function () {
                return $this->params2;
            },
            'date'         => function () {
                return $this->params3;
            },
            'shift'        => function () {
                return $this->data1;
            },
            'zakaz'        => function () {
                return $this->data2;
            },
            'nomenclature' => function () {
                return $this->data3;
            },
            'fio'          => function () {
                return $this->data4;
            },
            'prodcnt'      => function () {
                return $this->data5;
            },
            'brakcnt'      => function () {
                return $this->data6;
            },
            'brakinfo'     => function () {
                return $this->data7;
            },
            'typeof'       => function () {
                return $this->typeof;
            },
            'created_by'   => function () {
                return $this->created_by;
            },
            'created_at'   => function () {
                return $this->created_at;
            },
            'object_uid'   => function () {
                return $this->object_uid;
            },
            'object'       => function () {
                return $this->object;
            },
            'createdBy'    => function () {
                return $this->createdBy;
            },
        ];
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
