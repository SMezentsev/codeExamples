<?php


namespace app\modules\itrack\Services\Generation;

use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\Generation;
use app\modules\itrack\models\GenerationStatus;
use app\modules\itrack\Services\Generation\DTO\GenerationDataDto;
use app\modules\itrack\Services\Generation\Interfaces\GenerationServiceInterface;
use app\modules\itrack\Services\Generation\Models\GenerationCollection;

/**
 * Class GenerationService
 */
class GenerationService implements GenerationServiceInterface
{
    /**
     * @var GenerationCollection
     */
    private $generationCollection;
    /**
     * @var bool
     */
    private $isStartedFillTempTable;
    /**
     * @var \PDO
     */
    private $masterPdo;

    private const GENERATION_UID_KEY = 'generation_uid';
    private const OBJECT_UID_KEY = 'object_uid';
    private const CODE_FIELDS = [
        'code',
        'flag',
        'parent_code',
        'product_uid',
        'object_uid',
        'generation_uid',
        'childrens'
    ];

    /**
     * GenerationService constructor.
     * @param GenerationCollection $generationCollection
     */
    public function __construct(GenerationCollection $generationCollection)
    {
        $this->generationCollection = $generationCollection;
        $this->masterPdo = \Yii::$app->db->getMasterPdo();
        $this->isStartedFillTempTable = false;
    }

    /**
     * @param GenerationDataDto $generationDataDto
     * @return Generation
     * @throws \ErrorException
     * @throws \yii\db\Exception
     */
    public function saveGenerationData(GenerationDataDto $generationDataDto): Generation
    {
        if (!$this->isStartedFillTempTable) {
            $this->dropTempTable();
            $this->createTempTableForImport();;
            $this->isStartedFillTempTable = true;
        }

        $generation = $this->createCodesGeneration($generationDataDto);
        $codesRows = $this->convertCodesDataForImport($generationDataDto->getCodes(), $generation);

        $this->importCodesToTempTable($codesRows);

        return $generation;
    }

    /**
     * @throws \yii\db\Exception
     */
    public function flushTempData(): void
    {
        \Yii::$app->db->createCommand(
            "INSERT INTO codes (code, flag, parent_code, product_uid, object_uid, generation_uid, childrens) 
                    SELECT code, flag, parent_code, product_uid::bigint, object_uid, generation_uid, childrens FROM ser_tmp
                        ON CONFLICT (code) DO UPDATE SET
                            code=excluded.code, flag=excluded.flag, 
                            parent_code=excluded.parent_code, product_uid=excluded.product_uid, 
                            object_uid=excluded.object_uid, childrens=excluded.childrens"
        )->execute();

        $this->dropTempTable();

        \Yii::$app->db->createCommand("commit")->execute();
    }

    /**
     * @throws \yii\db\Exception
     */
    private function dropTempTable(): void
    {
        \Yii::$app->db->createCommand("DROP TABLE IF EXISTS ser_tmp")->execute();
    }

    /**
     * @param GenerationDataDto $generationDataDTO
     * @return Generation
     * @throws \ErrorException
     */
    public function createCodesGeneration(GenerationDataDto $generationDataDTO): Generation
    {
        $generation = new Generation();

        if ($generationDataDTO->getCodeType() === CodeType::CODE_TYPE_GROUP) {
            $generation->scenario = "groupCode";
        } else {
            $generation->scenario = "external";
        }

        $productionOrder = $generationDataDTO->getProductionOrder();
        $generation->load(
            [
                'codetype_uid' => $generationDataDTO->getCodeType(),
                'status_uid' => GenerationStatus::STATUS_PROCESSING,
                'created_by' => $generationDataDTO->getUserId(),
                'comment' => 'генерация для внешних кодов',
                'object_uid' => $generationDataDTO->getObjectId(),
                'cnt' => count($generationDataDTO->getCodes()),
                'capacity' => '0',
                'prefix' => '',
                'product_uid' => $generationDataDTO->getProductId(),
                'equip_uid' => $productionOrder->equip_id,
                'production_order_id' => $productionOrder->id,
                'parent_uid' => null
            ], ''
        );

        if (!$generation->save()) {
            throw new \ErrorException(\Yii::t('app', 'Не удалось создать генерацию'));
        }

        $this->generationCollection->attach($generation);

        return $generation;
    }

    /**
     * @throws \yii\db\Exception
     */
    private function createTempTableForImport(): void
    {
        \Yii::$app->db->createCommand("begin")->execute();
        \Yii::$app->db->createCommand(
            'CREATE TEMP TABLE ser_tmp (code varchar, flag int, parent_code varchar, product_uid varchar, 
                object_uid bigint, generation_uid uuid, childrens varchar[])'
        )->execute();
    }

    /**
     * @param array $codeRows
     */
    private function importCodesToTempTable(array $codeRows): void
    {
        $this->masterPdo->pgsqlCopyFromArray("ser_tmp", $codeRows);
    }

    /**
     * @param array $codes
     * @param Generation $generation
     * @return array
     */
    private function convertCodesDataForImport(array $codes, Generation $generation): array
    {
        $formattedRows = [];

        foreach ($codes as $code) {
            $rowString = '';
            $code[self::GENERATION_UID_KEY] = $generation->id;
            $code[self::OBJECT_UID_KEY] = $generation->object_uid;

            foreach (self::CODE_FIELDS as $key => $field) {
                $delimiter = ($key === 0) ? '' : "\t";
                $fieldValue = ($code[$field] === null) ? NULL : $code[$field];
                $rowString .= $delimiter . $fieldValue;
            }

            $formattedRows[] = $rowString;
        }

        return $formattedRows;
    }
}