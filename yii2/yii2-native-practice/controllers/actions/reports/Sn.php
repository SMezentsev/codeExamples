<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 20.01.16
 * Time: 16:06
 */

namespace app\modules\itrack\controllers\actions\reports;


use app\modules\itrack\models\History;
use app\modules\itrack\models\Report;
use yii\base\Action;
use yii\data\SqlDataProvider;

class Sn extends Action
{
    public function run($dateStart, $dateEnd, $manufacturer, $download = false)
    {
//select distinct on (codes.code) codes.code,nomenclature.gtin,codes.code_sn,codes.release_date,history.created_at,manufacturer.name from codes
//  LEFT JOIN product ON (codes.product_uid = product.id)
//  LEFT JOIN nomenclature ON (product.nomenclature_uid = nomenclature.id)
//  LEFT JOIN manufacturer ON (nomenclature.manufacturer_uid = manufacturer.id)
//  LEFT JOIN history ON (history.operation_uid = 14 and codes.id = history.code_uid)
//   WHERE codes.release_date>=:dateStart and codes.release_date<=:dateEnd and manufacturer.id = :manufacturer
//   ORDER by codes.code,history.created_at desc
        
        if ($download) {
            $report = new Report();
            $report->load([
                "report_type" => 'Sn',
                "params"      => \app\modules\itrack\components\pghelper::arr2pgarr([$dateStart, $dateEnd, $manufacturer]),
                "created_by"  => \Yii::$app->user->getId(),
            ], '');
            $report->save();
            
            return [
                'report' => $report,
            ];
        }
        
        $query = History::sn()
            ->where(['>=', 'codes.release_date', $dateStart])
            ->andWhere(['<=', 'codes.release_date', $dateEnd])
            ->andWhere(['=', 'manufacturer.id', $manufacturer])
            ->orderBy([
                'codes.code'         => SORT_ASC,
                'history.created_at' => SORT_DESC,
            ]);
        
        return new SqlDataProvider([
            'sql' => $query->createCommand()->getRawSql(),
        ]);
    }
}