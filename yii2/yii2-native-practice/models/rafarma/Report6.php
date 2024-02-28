<?php

namespace app\modules\itrack\models\rafarma;

use Yii;

class Report6 extends \app\modules\itrack\models\Extdata
{
    
    const TYPEOF = 'reportRafarma6';
    public $series;
    public $brak;
    public $zakaz_number;
    
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
    
    static function addMulti($data)
    {
        $added = [];
        foreach ($data as $element) {
            //var_dump($element);$die;
            $e = new self;
            if ($e->load($element, '')) {
                if ($e->save()) {
                    $e->refresh();
                    $added[] = $e;
                }
            }
        }
        
        return ['data' => $added];
    }
    
    static function report($bdate, $edate)
    {
        $query = self::find();
        $query->andWhere(['typeof' => self::TYPEOF]);
        $query->andWhere(['between', 'created_at', $bdate, $edate]);

//        $query->orderBy(['created_at' => SORT_ASC]);
        return $query;
    }
    
    /**
     * Генерация WORD отчета
     *
     * @param string $filename имя файла куда запишеться отчет
     * @param date   $bdate    начало периода отчета
     * @param date   $edate    окончание периода отчета
     */
    static function generateReport($report, $filename, $bdate, $edate)
    {
        $query = self::report($bdate, $edate);
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
        
        $word->addFontStyle('titleStyle', [
            'color' => '000000',
            'size'  => 18,
            'bold'  => true,
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
        $word->addTableStyle('base_table', ['size' => 11, 'borderSize' => 1, 'borderColor' => '000000', 'width' => 100 * 50, 'unit' => 'pct', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER], ['bold' => true, 'bgColor' => 'DDDDDD', 'align' => 'center', 'valign' => 'center']);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 100]);
        $textrun->addText('Журнал событий', 'titleStyle');
        $section->addTextBreak(2);
        
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText("Начало:\t\t", 'main_bold');
        $textrun->addText(\Yii::$app->formatter->asDatetime($bdate, 'php:H:i d/m/Y'), 'main');
        $textrun = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
        $textrun->addText("Окончание:\t", 'main_bold');
        $textrun->addText(\Yii::$app->formatter->asDatetime($edate, 'php:H:i d/m/Y'), 'main');
        $section->addTextBreak(1);
        
        $table = $section->addTable('base_table');
        $table->addRow();
        $table->addCell(2000)->addText('Дата', ['bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(2000)->addText('Время', ['bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(2000)->addText('Пользователь', ['bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $table->addCell(4000)->addText('Текст события', ['bold' => true, 'size' => 12, 'valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $data = $query->all();
        $time = time();
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
            $table->addCell(2000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($a["fio"]);
            $table->addCell(4000, ['valign' => 'center', 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER])->addText($a["message"]);
        }
        $section->addTextBreak(1);
        
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
        $writer->save($filename);
        echo "save: $filename" . PHP_EOL;
    }
    
    /*
     * Создание множества моделей
     */
    
    public function scenarios()
    {
        return [
            'default' => ['series', 'brak', 'zakaz_number'],
        ];
    }

//    public function save($runValidation = true, $attributeNames = null) {
//
//        $r = self::findOne(['params1' => $this->series, 'params2' => $this->zakaz_number]);
//        if(!empty($r))
//        {
//            $r->brak = $this->brak;
//            return $r->updateInternal();
//        }
//        else
//            return parent::save($runValidation, $attributeNames);
//    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['series', 'brak', 'zakaz_number'], 'required'],
        ];
    }
    
    public function search($params)
    {
        $query = self::find();
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query'      => $query,
            'pagination' => false,
        ]);
        if (!empty($params["object_uid"])) {
            $query->andFilterWhere(['object_uid' => $params["object_uid"]]);
        }
        $query->orderBy(['created_at' => SORT_ASC]);
        
        \app\modules\itrack\components\boxy\Helper::sortAndFilterQuery($query);
        
        return $dataProvider;
    }
    
    public function init()
    {
        parent::init();
        $this->on(self::EVENT_BEFORE_INSERT, function ($event) {
            /** @var $event ModelEvent */
            $event->sender->created_at = 'NOW()';
            $data = [];
            $data[] = $event->sender->data1 = $event->sender->brak ?? '';
            $data[] = '';
            $data[] = '';
            $data[] = '';
            $data[] = '';
            $data[] = '';
            $data[] = '';
            $event->sender->data = \app\modules\itrack\components\pghelper::arr2pgarr($data);
            $params = [];
            $params[] = $event->sender->params1 = $event->sender->series ?? '';
            $params[] = $event->sender->params2 = $event->sender->zakaz_number ?? '';
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
            'brak'         => function () {
                return $this->data1;
            },
            'series'       => function () {
                return $this->params1;
            },
            'zakaz_number' => function () {
                return $this->params2;
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
