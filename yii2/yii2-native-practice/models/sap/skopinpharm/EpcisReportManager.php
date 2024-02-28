<?php


namespace app\modules\itrack\models\sap\skopinpharm;

use app\modules\itrack\models\Product;
use Yii;
use yii\base\Model;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\Code;

/**
 * Класс управляет построением отчетов по fns докам и отправкой отчетов в sap
 * Class EpcisReportManager
 * @package app\modules\itrack\models\sap\skopinpharm
 */
class EpcisReportManager extends Model
{
    private $operation;
    private $sapConnector;
    private $sapLogWriter;

    const FNS_AGGREGATION_DOC = 915;
    const FNS_PRODUCTION_DOC = 313;

    public function __construct(
        $config = [],
        SapIchConnector $sapConnector,
        SapLogWriter $sapLogWriter
    ) {
        parent::__construct($config);

        $this->operation = null;
        $this->sapConnector = $sapConnector;
        $this->sapLogWriter = $sapLogWriter;
    }

    /**
     * @param array $operation
     * @return void
     */
    public function setOperation(array $operation): void
    {
        $this->operation = $operation;
    }

    /**
     * Отправляет отчет в sap, при отправке
     * также отчет сохраняется на диск и пишется лог
     * @return bool
     * @throws \ErrorException
     * @throws \ReflectionException
     * @throws \yii\base\InvalidConfigException
     */
    public function sendReport(): bool
    {
        $isSapNomenclature = $this->checkNomenclature();
        $isSapNomenclature = true;

        if ($isSapNomenclature === false) {
            return false;
        }

        $reportData = $this->buildReport();

        if ($reportData === '') {
            return false;
        }

        $this->writeEpcisReport($reportData);
        $response = $this->sapConnector->epcisReportRequest($reportData);
        $this->sapLogWriter->setOperation($this->operation);
        $this->sapLogWriter->saveLog($reportData, $response);

        return true;
    }

    /**
     * Нам не требуется выполнять запросы в sap для кодов которые мы из него не получали
     * @return bool
     * @throws \ErrorException
     */
    private function checkNomenclature(): bool
    {
        $product = Product::findOne($this->operation['product_uid']);

        if ($product === null) {
            throw new \ErrorException('Не удалось получить товарную карту для операции.');
        }

        return $product->nomenclature->get_sap_codes;
    }

    /**
     * Записывает отчет epcis на диск
     * @param string $xmlData
     * @throws \ErrorException
     * @throws \ReflectionException
     */
    private function writeEpcisReport(string $xmlData)
    {
        $epcisReportPath = \Yii::getAlias('@runtime') . \Yii::$app->params['epcisReportPatch'];

        if ($this->operation['fnsid'] === self::FNS_AGGREGATION_DOC) {
            $docName = 'EpcisReportAggregation';
        } else {
            $docName = 'EpcisReportCommissioning';
        }

        $fileName = $docName . '_' . date('Ymd', time()) . '_' . rand(1000, 9999) . '.xml';
        $path = $epcisReportPath . DIRECTORY_SEPARATOR . $fileName;

        try {
            file_put_contents($path, $xmlData);
        } catch (\Exception $e) {
            throw new \ErrorException('Не удалось записать файл отчета ' . $path . ' на диск.');
        }
    }

    /**
     * Выполняет построение нужного отчета по типу дока
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    private function buildReport(): string
    {
        $report = null;
        $reportData = '';

        switch ($this->operation['fnsid']) {
            case self::FNS_AGGREGATION_DOC:
                $report = \Yii::createObject(EpcisReportAggregation::class);
                $report->setData($this->getAggregationData());
                $report->setProductData($this->operation['product_uid']);
                $report->setOperationDate(new \DateTime($this->operation['updated_at']));
                break;
            case self::FNS_PRODUCTION_DOC:
                $report = \Yii::createObject(EpcisReportCommissioning::class);
                $report->setData($this->getCommissioningData());
                $report->setProductData($this->operation['product_uid']);
                $report->setOperationDate(new \DateTime($this->operation['created_at']));
                break;
            default:
                return $reportData;
        }

        $report->setMode($this->operation['code_flag']);

        return $report->buildReport();
    }

    /**
     * Возвращает список кодов в нужном формате для отчета aggregation
     * @return array
     */
    private function getAggregationData(): array
    {
        return pghelper::pgarr2arr($this->operation['codes_data']);
    }

    /**
     * Возвращает список кодов в нужном формате для отчета commissioning
     * @return array
     */
    private function getCommissioningData(): array
    {
        $groupCodes = pghelper::pgarr2arr($this->operation['codes']);
        $highLevelRawCodes = pghelper::pgarr2arr($this->operation['codes_data']);
        $fullCodes = pghelper::pgarr2arr($this->operation['full_codes']);

        if ($highLevelRawCodes !== null) {
            foreach ($highLevelRawCodes as $highLevelRawCode) {
                $highLevelCode = key(json_decode($highLevelRawCode, true));

                if (!in_array($highLevelCode, $fullCodes)) {
                    $fullCodes[] = $highLevelCode;
                    $groupCodes[] = $highLevelCode;
                }
            }
        }

        if (empty($fullCodes)) {
            throw new \ErrorException(
                \Yii::t(
                    'app',
                    'Не удалось сформировать запрос в sap, не найден список упакованных кодов в данных документа.'
                )
            );
        }

        $individualCodes = [];

        foreach ($fullCodes as $code) {
            if (!in_array($code, $groupCodes)) {
                $individualCodes[] = $code;
            }
        }

        if (empty($individualCodes)) {
            throw new \ErrorException(
                \Yii::t(
                    'app',
                    'Не удалось сформировать запрос в sap, не найден список упакованных кодов в данных документа.'
                )
            );
        }

        return [
            'groupCodes' => Code::getCodesData($groupCodes),
            'serials'    => $individualCodes
        ];
    }
}