<?php


namespace app\modules\itrack\Services\CodesImport\Interfaces;

use app\modules\itrack\models\User;

/**
 * Interface CodesImportManagerInterface
 */
interface CodesImportManagerInterface
{
    /**
     * @param User $user
     */
    public function setUser(User $user): void;

    /**
     * @param array $importData
     */
    public function importData(array $importData): void;
}