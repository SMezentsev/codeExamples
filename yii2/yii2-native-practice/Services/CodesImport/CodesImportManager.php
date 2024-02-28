<?php


namespace app\modules\itrack\Services\CodesImport;

use app\modules\itrack\models\Facility;
use app\modules\itrack\Services\CodesImport\CodesImportServiceFactory;
use app\modules\itrack\Services\CodesImport\DTO\CodesImportDataDto;
use app\modules\itrack\Services\CodesImport\Interfaces\CodesImportManagerInterface;
use app\modules\itrack\models\User;

/**
 * Class CodesImportManager
 */
class CodesImportManager implements CodesImportManagerInterface
{
    /**
     * @var User
     */
    private $user;
    /**
     * @var CodesImportDataDto
     */
    private $codesImportDataDto;
    /**
     * @var CodesImportServiceFactory
     */
    private $codesImportServiceFactory;
    /**
     * @var CodesImportDataParser
     */
    private $codesImportDataParser;

    public function __construct(
        CodesImportDataDto $codesImportDataDto,
        CodesImportServiceFactory $codesImportServiceFactory,
        CodesImportDataParser $codesImportDataParser
    )
    {
        $this->codesImportDataDto = $codesImportDataDto;
        $this->codesImportServiceFactory = $codesImportServiceFactory;
        $this->codesImportDataParser = $codesImportDataParser;
    }

    /**
     * @todo добавить трэйт для получения дефолтного юзера root если пользователь не установлен при вызове менеджера
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @param array $importData
     * @throws \ErrorException
     */
    public function importData(array $importData): void
    {
        try {
            $this->codesImportDataParser->parse($importData);
        } catch (\Exception $e) {
            throw new \ErrorException(
                \Yii::t('app', 'Не удалось выполнить парсинг данных: ' . $e->getMessage())
            );
        }

        $this->codesImportDataDto->setIsTransfer($this->codesImportDataParser->getIsTransfer());
        $this->codesImportDataDto->setAggregatedData($this->codesImportDataParser->getAggregatedDataCollection());
        $this->codesImportDataDto->setSsccCollection($this->codesImportDataParser->getSsccDataCollection());
        $this->codesImportDataDto->setSerialsCollection($this->codesImportDataParser->getSerialDataCollection());
        $this->codesImportDataDto->setProduct($this->codesImportDataParser->getProductData());
        $this->codesImportDataDto->setObject(Facility::findByFnsSubjectId($this->codesImportDataParser->getSubjectData()));
        $this->codesImportDataDto->setUser($this->user);
        $this->codesImportDataDto->setProductionOrder($this->codesImportDataParser->getProductionOrder());

        if (count($this->codesImportDataDto->getAggregatedData()) > 0) {
            try {
                $codesImportManager = $this->codesImportServiceFactory->getCodesImportService(
                    CodesImportServiceFactory::IMPORT_PACKED_DATA,
                    $this->codesImportDataDto
                );
                $codesImportManager->import();
            } catch (\Exception $e) {
                throw new \ErrorException(
                    \Yii::t(
                        'app',
                        'Не удалось выполнить импорт агрегированных кодов: {message}',
                        ['message' => $e->getMessage()]
                    )
                );
            }
        }

        if (count($this->codesImportDataDto->getSsccCollection()) > 0 ||
            count($this->codesImportDataDto->getSerialsCollection()) > 0) {
            try {
                $codesImportManager = $this->codesImportServiceFactory->getCodesImportService(
                    CodesImportServiceFactory::IMPORT_NON_PACKED_DATA,
                    $this->codesImportDataDto
                );
                $codesImportManager->import();
            } catch (\Exception $e) {
                throw new \ErrorException(
                    \Yii::t(
                        'app',
                        'Не удалось выполнить импорт пустых кодов: {message}',
                        ['message' => $e->getMessage()]
                    )
                );
            }
        }
    }

}