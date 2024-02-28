<?php

namespace app\modules\itrack\models\rafarma;

use Yii;

class Report2 extends \app\modules\itrack\models\Extdata
{
    
    const TYPEOF = 'reportRafarma2';
    public $adate;
    public $zakaz_number;
    public $error_state;
    public $error_text;
    public $user;
    public $login;

//    public $typeof;
//    public $created_by;
//    public $created_at;
//    public $object_uid;
    
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
    
    static function report($bdate, $edate)
    {
        $query = self::find();
        $query->andWhere(['between', 'created_at', \Yii::$app->formatter->asDatetime($bdate, "php:Y-m-d H:i:sP"), \Yii::$app->formatter->asDatetime($edate, "php:Y-m-d H:i:sP")]);
        $query->andWhere(['typeof' => self::TYPEOF]);
//        if (!empty($series))
//            $query->andWhere(['params1' => $series]);
        $query->orderBy(['created_at' => SORT_ASC]);
        
        return $query;
    }
    
    /**
     * Генерация WORD отчета
     *
     * @param type $filename
     * @param type $bdate
     * @param type $edate
     */
    static function generateReport($report, $filename, $bdate, $edate, $report_type = 'docx')
    {
        $query = self::report($bdate, $edate);
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->setDefaultFontName('Times New Roman');
        $pdf = ($report_type == 'pdf');
        
        //$word->setDefaultFontSize(14);
        
        $section = $word->createSection();
        $sectionStyle = $section->getSettings();
        $sectionStyle->setPortrait();             //или setLandscape()
        $sectionStyle->setMarginLeft(15 * 56.7);  //15mm
        $sectionStyle->setMarginRight(15 * 56.7);
        //$sectionStyle->setBorderBottomSize(10 * 56.7);
        //$sectionStyle->setBorderTopColor('C0C0C0');
        
        $word->addFontStyle('titleStyle', [
            'color' => '000000',
            'size'  => 18,
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
        $word->addTableStyle('base_table', ['size' => 11, 'borderSize' => 1, 'borderColor' => '000000', 'width' => 100 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER], ['bold' => true, 'bgColor' => 'DDDDDD', 'align' => 'center', 'valign' => 'center']);
        
        $header = $section->createHeader();
        $header->addPreserveText('{PAGE} / {NUMPAGES}', ['bold' => true], ['align' => 'center']);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText(mb_convert_encoding('Журнал ошибок', 'HTML-ENTITIES', 'UTF-8'), 'titleStyle');
        $section->addTextBreak(2);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Начало:\t\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(\Yii::$app->formatter->asDatetime($bdate, 'php:H:i d/m/Y'), 'main');
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText(mb_convert_encoding("Окончание:\t", 'HTML-ENTITIES', 'UTF-8'), 'main_bold');
        $textrun->addText(\Yii::$app->formatter->asDatetime($edate, 'php:H:i d/m/Y'), 'main');
        $section->addTextBreak(1);
        
        
        $table = $section->addTable('base_table');
        
        $table->addRow(50, ['tblHeader' => true]);
        $table->addCell(2000)->addText(mb_convert_encoding('Дата', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(2000)->addText(mb_convert_encoding('Время', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(2000)->addText(mb_convert_encoding('Пользователь', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(4000)->addText(mb_convert_encoding('Текст события', 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri', 'bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        
        $time = time();
        $data = $query->all();
        foreach ($data as $el) {
            $a = $el->toArray();
            if (time() > ($time + 30) && $report) {
                $report->refresh();
                if ($report->status == 'CANCEL') {
                    return;
                }
                $time = time();
            }
            
            $table->addRow();
            $table->addCell(2000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(\Yii::$app->formatter->asDate($a["created_at"], 'php:d.m.Y'));
            $table->addCell(2000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(\Yii::$app->formatter->asTime($a["created_at"], 'php:H:i:s'));
            $table->addCell(2000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding($a["user"], 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri',]);
            $table->addCell(4000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText(mb_convert_encoding(str_replace(',', ', ', $a["error_text"]), 'HTML-ENTITIES', 'UTF-8'), ['name' => $pdf ? 'DejaVu Sans' : 'Calibri',]);
        }
        $section->addTextBreak(1);
        
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
            $html = str_replace("<table>", '<table width="100%" page-break-inside: auto;>', $html);
            $html = preg_replace("#(<tr>\s*<th>.*?</th>\s*</tr>)#si", '<thead>$1</thead>', $html);
            $html = str_replace('</style>', "th {border: 1px solid black;}\n</style>", $html);
//                $html = str_replace('<body>', "<body>\n".'<div id="header">
//     <h1>Widgets Express</h1>
//   </div>', $html);
            $pdf->loadHtml(str_replace(PHP_EOL, '', $html));
            $pdf->render();
            
            $canvas = $pdf->get_canvas();
            $canvas->page_text(280, 820, "{PAGE_NUM}/{PAGE_COUNT}", null, 10, [0, 0, 0]);
            //  Write to file
            file_put_contents($filename, $pdf->output());
            //file_put_contents($filename . ".html", $html);
        } else {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
            $writer->save($filename);
        }
        echo "save: $filename" . PHP_EOL;
    }
    
    public function scenarios()
    {
        return [
            'default' => ['adate', 'zakaz_number', 'error_state', 'error_text', 'typeof', 'user', 'login', 'created_at'],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['typeof'], 'required'],
        ];
    }
    
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
            //'pagination' => false,
        ]);
        
        if (!empty($params["zakaz_number"])) {
            $query->andFilterWhere(['params1' => $params["zakaz_number"]]);
        }
        if (!empty($params["object_uid"])) {
            $query->andFilterWhere(['object_uid' => $params["object_uid"]]);
        }
        $query->orderBy(['created_at' => SORT_DESC]);
        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $event->sender->created_at = \Yii::$app->formatter->asDatetime($event->sender->created_at, "php:Y-m-d H:i:sP");
            $data = [];
            $data[] = $event->sender->data1 = $event->sender->error_state ?? '';
            $data[] = $event->sender->data2 = $event->sender->error_text ?? '';
            $data[] = $event->sender->data3 = $event->sender->user ?? '';
            $data[] = $event->sender->data4 = $event->sender->login ?? '';
            $data[] = '';
            $data[] = '';
            $data[] = '';
            $event->sender->data = \app\modules\itrack\components\pghelper::arr2pgarr($data);
            $params = [];
            $params[] = $event->sender->params1 = $event->sender->zakaz_number ?? '';
            $params[] = '';
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
            'zakaz_number' => function () {
                return $this->params1;
            },
            'error_state'  => function () {
                return $this->data1;
            },
            'error_text'   => function () {
                return $this->data2;
            },
            'user'         => function () {
                return $this->data3;
            },
            'login'        => function () {
                return $this->data4;
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
