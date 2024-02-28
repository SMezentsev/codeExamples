<?php
/**
 * @link      http://original-group.ru/en/projects/itrack/
 * @copyright Copyright (c) 2016 Original Group
 */

namespace app\modules\itrack\models;

use app\modules\itrack\components\boxy\ActiveRecord;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use Exception;
use yii\web\BadRequestHttpException;

/**
 * CLass History
 * Журнал движения/изменения кодов - основной журнал операций
 *
 * @property integer $id             - Идентификатор записи
 * @property string  $created_at     - Дата создания
 * @property integer $operation_uid  - Ссылка на операцию
 * @property integer $code_uid       - ССылка на код
 * @property integer $created_by     - Ссылка на пользовтаеля
 *
 * Методы:
 *  - список, выдача по фильтра, отчеты
 *
 */
class History extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'history';
    }

    public static function historyByDate($bdate, $edate, $series, $object_uid, $product_uid, $invoice)
    {
        $query = new Query();
        $query->select([
            'a.created_at',
            'f.code',
            'gen.codetype_uid',
            'h.name as nname',
            'g.series',
            'b.name as operation',
            'd.fio',
            'e.name as object',
            'e.address',
            'a.data',
            'i.invoice_number as invoice',
            'i.invoice_date',
            'i.dest_address',
            'i.dest_settlement',
            'i.dest_consignee',
            'i.is_gover as gover',
            'a.operation_uid',
            'a.created_by',
        ])->from(['a' => 'history'])
            ->leftJoin('codes as f', 'f.id = a.code_uid')
            ->leftJoin('generations as gen', 'gen.id = f.generation_uid')
            ->leftJoin('history_operation as b', 'a.operation_uid = b.id')
            ->leftJoin('users as d', 'd.id = a.created_by')
            ->leftJoin('objects as e', 'e.id = a.object_uid')
            ->leftJoin('product as g', 'g.id = f.product_uid')
            ->leftJoin('nomenclature as h', 'g.nomenclature_uid = h.id')
            ->leftJoin('invoices as i', 'i.id = a.invoice_uid')
            ->andWhere('a.id is not null');
        if (!empty($series)) {
            $query->andWhere(['a.codeseries' => $series]);
        }
        $query->andWhere(new Expression("a.codeactivate_date between '" . pg_escape_string($bdate) . "' and '" . pg_escape_string($edate) . "'"))
            ->andWhere(['>=', 'a.created_at', $bdate]);
        if (!empty($object_uid)) {
            $query->andWhere(['=', 'a.object_uid', $object_uid]);
        }
        if (!empty($product_uid)) {
            $query->andWhere(['=', 'g.id', $product_uid]);
        }
        if (!empty($invoice)) {
            $query->andWhere(['=', 'i.invoice_number', $invoice]);
        }

        return $query;
    }

    public static function historyForReport()
    {
        $query = new Query();
        $query->select([
            'a.created_at',
            'f.code',
            'gen.codetype_uid',
            'h.name as nname',
            'g.series',
            'b.name as operation',
            'd.fio',
            'e.name as object',
            'e.address',
            'a.data',
            'i.invoice_number as invoice',
            'i.invoice_date',
            'i.dest_address',
            'i.dest_settlement',
            'i.dest_consignee',
            'i.is_gover as gover',
        ])->from('history as a')
            ->leftJoin('history_operation as b', 'a.operation_uid = b.id')
            ->leftJoin('users as d', 'd.id = a.created_by')
            ->leftJoin('objects as e', 'e.id = a.object_uid')
            ->leftJoin('codes as f', 'f.id = a.code_uid')
            ->leftJoin('generations as gen', 'gen.id = f.generation_uid')
            ->leftJoin('product as g', 'g.id = f.product_uid')
            ->leftJoin('nomenclature as h', 'g.nomenclature_uid = h.id')
            ->leftJoin('invoices as i', 'i.id = a.invoice_uid');

        return $query;
    }

    public static function balanceInStock($objectID = null)
    {
        $queryChild = (new Query())->select([
            'objects.id',
            'objects.name as oname',
            'nomenclature.name as nname',
            'case when is_released(flag) then 1 else 0 end as released',
            'case when is_released(flag) then 0 else 1 end as notreleased',
            'case when is_defected(flag) then 1 else 0 end as defected',
            'case when is_claim(flag) then 1 else 0 end as claim',
        ])->from('codes')
            ->leftJoin('product', 'codes.product_uid = product.id')
            ->leftJoin('nomenclature', 'nomenclature.id = nomenclature_uid')
            ->leftJoin('objects', 'objects.id = object_uid')
            ->leftJoin('generations', 'generations.id = generation_uid')
            ->leftJoin('code_types', 'code_types.id = codetype_uid')
            ->where("is_empty(flag) is false and code_types.name!='Групповой'")
            ->andFilterWhere(['objects.id' => $objectID]);

        $query = (new Query())->select([
            'id',
            'oname',
            'nname',
            'sum(released) as released',
            'sum(notreleased) as remain',
            'sum(defected) as defect',
            'sum(claim) as claim',
        ])->from(['a' => $queryChild])
            ->groupBy(['id', 'oname', 'nname'])
            ->orderBy('id, oname, nname');

        return $query;
//        echo $query->createCommand()->rawSql; die;
    }

    /**
     * @return Query
     */
    public static function historyOfCheckMan()
    {
        $query = (new Query())
            ->select([
                'history.created_at',
                'users.fio',
                'codes.code',
                'history.data',
                'history.shopname',
                'history.address',
                'history.comment',
                'nomenclature.name',
                'product.series',
                'product.cdate',
            ])->from('history')
            ->leftJoin('codes', 'codes.id = code_uid')
            ->leftJoin('users', 'history.created_by = users.id')
            ->leftJoin('product', 'product.id = codes.product_uid')
            ->leftJoin('nomenclature', 'nomenclature_uid = nomenclature.id')
            ->where([
                'operation_uid' => 11]);

        return $query;
    }

    public static function historyCheckCode()
    {
        $query = (new Query())
            ->select([
                'checks.created_at',
                'check_source.name as sname',
                'checks.txt',
                'checks.address',
                'codes.code',
                'nomenclature.name as nname',
                'product.series',
                'product.cdate',
                'product.expdate',
                'checks.ucnt',
                'user_requests.phone',
                'user_requests.code_txt',
                'user_requests.shopname',
                'user_requests.shopaddr',
                'user_requests.descrption',
                'user_requests.fio',
                'user_requests.city',
                'user_requests.email',
                'user_requests.goods_name',
            ])->from('checks')
            ->leftJoin('codes', 'code_uid = codes.id')
            ->leftJoin('product', 'product_uid = product.id')
            ->leftJoin('nomenclature', 'nomenclature_uid = nomenclature.id')
            ->leftJoin('check_source', 'source_uid = check_source.id')
            ->leftJoin('user_requests', 'checks.id = user_requests.checks_uid');

        return $query;
    }

    /**
     * @param Code $code
     * @param User $user
     *
     * @return null|History
     */
    public static function createHistoryByOperation11(Code $code, User $user)
    {
        try {
            $sql = 'select make_history_check(:userID, :codeID)';
            $id = Yii::$app->db->createCommand($sql, [
                ':userID' => $user->id,
                ':codeID' => $code->id,
            ])->queryScalar();
        } catch (Exception $ex) {
            throw new BadRequestHttpException('Ошибка проведения контрольной проверки кода: ' . $ex->getMessage());
        }

        if ($id) {
            return static::findOne($id);
        } else {
            return null;
        }
    }

    /**
     * @return Query
     */
    public static function manufacturers()
    {
        $query = (new Query())
            ->select([
                'nomenclature.gtin',
                'nomenclature.name',
                'product.series',
                'product.cdate as release_date',
                'codes.code',
                'history_operation.name as operation',
                "to_char(history.created_at, 'DD/MM/YYYY') as created_at",
                'invoices.dest_consignee',
                'invoices.dest_address',
                'invoices.dest_inn',
                'invoices.dest_settlement',
                'invoices.invoice_number',
                'invoices.invoice_date',
                new Expression('case when length(codes.code)>13 then codes.code else nomenclature.gtin || codes.code end as sgtin'),
            ])
            ->from('history')
            ->leftJoin('invoices', 'history.invoice_uid = invoices.id')
            ->leftJoin('codes', 'history.code_uid = codes.id')
            ->leftJoin('generations', 'codes.generation_uid = generations.id')
            ->leftJoin('product', 'product.id = codes.product_uid')
            ->leftJoin('nomenclature', 'nomenclature.id = product.nomenclature_uid')
            ->leftJoin('history_operation', 'history_operation.id = history.operation_uid')
            ->where('history.operation_uid IN (14,18, 53, 55)')
            ->andWhere('generations.codetype_uid = 1')
            ->orderBy([
                'history.created_at' => SORT_DESC,
            ]);

        return $query;
    }

    /**
     * @return Query
     */
    public static function invoice($invoice = null, $dateStart = null, $dateEnd = null, $objectUid = null, $consignee = null)
    {
        $queryFrom = (new Query())
            ->select([
                'invoices.id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.dest_address',
                'invoices.dest_consignee',
                'invoices.dest_settlement',
                'invoices.created_at',
                'users.fio',
                'objects.name',
                'o2.name as o2name',
                'cont' => new Expression('unnest(invoices.realcodes)'),
            ])
            ->from('invoices')
            ->leftJoin('users', 'users.id = invoices.created_by')
            ->leftJoin('objects', 'objects.id=invoices.object_uid')
            ->leftJoin('objects o2', 'o2.id=invoices.newobject_uid');
        $queryFrom->andFilterWhere(['=', 'invoices.typeof', 0]);
        $queryFrom->andFilterWhere(['>=', 'invoices.created_at', ($dateStart) ? Yii::$app->formatter->asDate($dateStart . ' 00:00:00') : null]);
        $queryFrom->andFilterWhere(['<=', 'invoices.created_at', ($dateEnd) ? Yii::$app->formatter->asDate($dateEnd . ' 23:59:59.999') : null]);
        $queryFrom->andFilterWhere(['=', 'invoices.object_uid', $objectUid]);
        if ($invoice) {
            $queryFrom->andFilterWhere(['=', 'invoices.invoice_number', $invoice]);
        }

        if ($consignee) {
            $queryFrom->andFilterWhere([
                'or',
                ['ilike', 'invoices.dest_consignee', $consignee],
                ['ilike', 'o2.name', $consignee],
            ]);
        }
        $queryFrom->orderBy(['invoices.created_at' => SORT_DESC, 'invoice_date' => SORT_DESC, 'invoice_number' => SORT_ASC]);

        if (!Yii::$app->user->can('see-all-objects')) {
            $queryFrom->andWhere(new Expression('(o2.id = ' . intval(Yii::$app->user->identity->object_uid) . 'or objects.id = ' . intval(Yii::$app->user->identity->object_uid) . ')'));
        }


        return (new Query())->from([$queryFrom]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['operation_uid', 'code_uid', 'created_by'], 'required'],
            [['operation_uid', 'code_uid', 'created_by'], 'integer'],
        ];
    }

    public function fields()
    {
        return [
            'uid'         => 'id',
            'created_at'  => function () {
                return ($this->created_at) ? Yii::$app->formatter->asDatetime($this->created_at) : null;
            },
            'operation_uid',
            'code_uid',
            'created_by',
            'historyData' => function () {
                if (!empty($this->invoice_uid)) {
                    if (SERVER_RULE == SERVER_RULE_SKLAD) {
                        //пока так - в идеале переделать на доступ к invoces Через функции с параметрами для фильтра вьюшки
                        $invoice = Yii::$app->db_main->createCommand('SELECT * from invoices WHERE id=:id', [':id' => $this->invoice_uid])->queryOne();
                        unset($invoice['codes']);
                    } else {
                        $invoice = Invoice::findOne(['id' => $this->invoice_uid]);
                        unset($invoice->codes);
                    }
                } else {
                    $invoice = null;
                }

                return [
                    'object_uid'  => $this->object_uid,
                    'product_uid' => $this->product_uid,
                    'address'     => $this->address,
                    'comment'     => $this->comment,
                    'shopname'    => $this->shopname,
                    'content'     => $this->content,
                    'invoice_uid' => $this->invoice_uid,
                    'invoice'     => $invoice,
                    'data'        => $this->data,
                ];
            },
        ];
    }

    public function extraFields()
    {
        return [
            'createdBy',
            'historyOperation',
            'code',
        ];
    }

    public function scenarios()
    {
        return [
            'default'         => parent::scenarios(),
            'checkManComment' => ['address', 'comment', 'shopname'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'            => Yii::t('app', 'ID'),
            'created_at'    => Yii::t('app', 'Дата создания'),
            'operation_uid' => Yii::t('app', 'ID операции'),
            'code_uid'      => Yii::t('app', 'ID кода'),
            'created_by'    => Yii::t('app', 'Кем создано'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCode()
    {
        return null;//$this->hasOne(Code::class, ['id' => 'code_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getHistoryOperation()
    {
        return $this->hasOne(HistoryOperation::class, ['id' => 'operation_uid']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCreatedBy()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }
}
