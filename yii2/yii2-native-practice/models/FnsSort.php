<?php

/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

/**
 * Created by PhpStorm.
 * User: eaglemoor
 * Date: 02/03/16
 * Time: 16:35
 */

namespace app\modules\itrack\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\web\BadRequestHttpException;

class FnsSort extends Model
{
    
    public $operation_uid;
    public $start_time;
    public $finish_time;
    public $product_uid;
    public $object_uid;
    public $created_by;
    public $state;
    public $series;
    public $invoice_number;
    public $doc_num;
    public $external;
    public $fnsid;
    public $id;
    public $sender;
    public $receiver;
    
    public function rules()
    {
        return [
            [['fnsid', 'sender', 'receiver', 'operation_uid', 'start_time', 'finish_time', 'product_uid', 'object_uid', 'created_by', 'state', 'series', 'invoice_number', 'doc_num', 'external', 'id'], 'safe'],
            ['id', 'integer'],
//            ['external','boolean'],
        ];
    }
    
    public function search($params)
    {
        $query = Fns::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        if (!($this->load($params, '') && $this->validate())) {
            throw new BadRequestHttpException(implode(', ', $this->firstErrors));
        }

        $query->andFilterWhere(['in', 'operations.id', $this->id]);
        $query->andFilterWhere(['in', 'operations.state', $this->state]);
        if (!empty($this->object_uid)) {
            if (in_array($this->operation_uid, [
                        Fns::OPERATION_IMPORT_ID,
                        Fns::OPERATION_601,
                        Fns::OPERATION_211,
                        Fns::OPERATION_416])
            ) {
                $query->andWhere('operations.newobject_uid = :obj2', [
                    ':obj2' => $this->object_uid,
                ]);
            } else {
                $query->andWhere('operations.object_uid = :obj', [
                    ':obj' => $this->object_uid,
                ]);
            }
        }

        if (!empty($this->start_time)) {
            $query->andWhere(new \yii\db\Expression('case when operations.upd THEN date(operations.updated_at) ELSE operations.created_at end >= :start_dt', [':start_dt' => $this->start_time]));
        }
        if (!empty($this->finish_time)) {
            $query->andWhere(new \yii\db\Expression('case when operations.upd THEN date(operations.updated_at) ELSE operations.created_at end <= :finish_dt', [':finish_dt' => $this->finish_time]));
        }
        $query->andFilterWhere(['=', 'operations.product_uid', $this->product_uid]);
        $query->andFilterWhere(['in', 'operations.fnsid', $this->fnsid]);

        $query->andFilterWhere(['in', 'operations.created_by', $this->created_by]);
        if ($this->operation_uid === Fns::OPERATION_IMPORT_ID || $this->operation_uid === '0') {
            $query->andFilterWhere(['in', 'operations.operation_uid', [
                    $this->operation_uid,
                    Fns::OPERATION_IMPORT_ID,
                    Fns::OPERATION_605,
                    Fns::OPERATION_606,
                    Fns::OPERATION_607,
                    Fns::OPERATION_617,
                    Fns::OPERATION_623,
                    Fns::OPERATION_DEFAULT
                ]
            ]);
        } elseif ($this->operation_uid == Fns::OPERATION_OUTCOMERETAIL_ID) {
            $query->andFilterWhere(['in', 'operations.operation_uid', [
                    Fns::OPERATION_OUTCOMERETAIL_ID,
                    Fns::OPERATION_OUTCOMERETAILUNREG_ID,
                ]
            ]);
        } elseif ($this->operation_uid == Fns::OPERATION_601) {
            $query->andFilterWhere(['in', 'operations.operation_uid', [
                    Fns::OPERATION_601,
                    Fns::OPERATION_615,
                    Fns::OPERATION_613,
                    Fns::OPERATION_609,
                ]
            ]);
        } elseif (!isset($this->operation_uid)) {
            $query->andFilterWhere(['=', 'operations.is_uploaded', true]);
        } else {
            if ($this->operation_uid == Fns::OPERATION_BACK_ID) {
                $query->andFilterWhere(['in', 'operations.operation_uid', [
                        $this->operation_uid,
                        Fns::OPERATION_250,
                        Fns::OPERATION_251,
                    ]
                ]);
            } else {
                $query->andFilterWhere(['=', 'operations.operation_uid', $this->operation_uid]);
            }
        }
        $query->leftJoin('product', 'product.id = product_uid');
        $query->leftJoin('invoices', 'invoices.id = invoice_uid');
        $query->leftJoin(['objects' => 'objects'], 'objects.id = operations.object_uid');
        $query->leftJoin(['newobjects' => 'objects'], 'newobjects.id = operations.newobject_uid');
        if (!empty($this->sender)) {
            $query->andWhere(['objects.fns_subject_id', $this->sender]);
        }
        if (!empty($this->receiver)) {
            $query->andWhere(['newobjects.fns_subject_id', $this->receiver]);
        }

        if (!empty($this->series)) {
            $query->andWhere(new \yii\db\Expression('(product.series = :series or :series=ANY(operations.data)) or products && (select array_agg(id) from product where series=:series)', [':series' => $this->series]));
        }

        if (!empty($this->invoice_number)) {
            $query->andFilterWhere(['=', 'invoices.invoice_number', $this->invoice_number]);
        }
        if (!empty($this->doc_num)) {
            $query->andWhere(new \yii\db\Expression('operations.data[2] = :docnum', [':docnum' => $this->doc_num]));
        }
        if (!empty($this->external)) {
            $query->andFilterWhere(['=', 'objects.external', $this->external == 'true' ? true : false]);
        }
        $query->with('product');
        $query->with('invoice');
        $query->with('object');
        $query->with('newobject');
        $query->with('user');
        $query->with('product.nomenclature');

        return $dataProvider;
    }

}
