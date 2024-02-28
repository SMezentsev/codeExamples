<?php

namespace app\modules\itrack\components;

use app\modules\itrack\models\Code;
use app\modules\itrack\models\CodeType;
use app\modules\itrack\models\Destructor;
use app\modules\itrack\models\Facility;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\Invoice;
use Exception;
use SimpleXMLElement;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;

trait FnsParse
{
    /**
     * Запуск парсинга загруженного докуента
     * @param string $filename
     * @return void
     * @throws BadRequestHttpException
     */
    public function updateJustLoaded(string $filename): void
    {
        $xmlfile = file_get_contents($filename);
        try {
            $xml = new SimpleXMLElement($xmlfile);
        } catch (Exception $ex) {
            throw new BadRequestHttpException(Yii::t('app', 'Ошибка загрузки, не корректный формат xml'));
        }
        foreach ($xml as $xml_attr => $xml_part) {
            break;
        }

        $fnsid = (int)$xml_part->attributes()['action_id'];
        $method = 'update_' . $fnsid;
        if (!empty($fnsid) && method_exists(Fns::class, $method)) {
            $this->$method($xml);
        } else {
            $this->update_default($xml);
        }
    }

    public function update_default($xml)
    {
        $this->operation_uid = Fns::OPERATION_DEFAULT;
        foreach ($xml as $k => $xml_part) {
            break;
        }
        $this->fnsid = (string)($xml_part->attributes()['action_id']);
        //        $this->created_time = preg_replace('#^.*?(\s|T)#si', '', (string) $xml_part->operation_date);
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$xml_part->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$xml_part->operation_date
        ) : $this->created_at;
        $this->updated_at = preg_replace('#(\s|T).*?$#si', '', (string)$xml_part->operation_date);
        $obj = Facility::findOne(['fns_subject_id' => (string)$xml_part->subject_id]);
        if (!empty($obj)) {
            $this->object_uid = $obj->id;
        } else {
            $this->object_uid = Yii::$app->user->getIdentity()->object_uid;
        }
    }

    /**
     * уведомление собственника об отгрузке препаратов для выпуска готовой продукции
     *
     * @param type $xml
     */
    public function update_618($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 618;
        $element = $xml->move_to_release_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'owner_id'       => (string)$element->owner_id,
            'doc_date'       => (string)$element->doc_date,
            'doc_num'        => (string)$element->doc_num,
        ];
        $codes = $this->upd_codes($element);
        $this->fns_params = serialize($params);

        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->owner_id);
    }

    /**
     * Корректировка сведений поставщиком
     *
     * @param type $xml
     */
    public function update_623(SimpleXMLElement $xml)
    {
        $this->operation_uid = Fns::OPERATION_623;

        foreach ($xml as $k => $xml_part) {
            break;
        }

        $this->fnsid = (string)($xml_part->attributes()['action_id']);
        $this->updated_at = preg_replace('#(\s|T).*?$#si', '', (string)$xml_part->operation_date);

        $obj = Facility::findOne(['fns_subject_id' => (string)$xml_part->subject_id]);
        if (!empty($obj)) {
            $this->object_uid = $obj->id;
        } else {
            $this->object_uid = Yii::$app->user->getIdentity()->object_uid;
        }
    }

    public function update_605($xml)
    {
        $this->operation_uid = Fns::OPERATION_605;
        $this->fnsid = 605;
        $element = $xml->refusal_sender_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'receiver_id'    => (string)$element->receiver_id,
            'reason'         => (string)$element->reason,
            'confirm_paused' => (string)$element->confirm_paused ?? '',
        ];
        $codes = $this->upd_codes($element);
        $this->fns_params = serialize($params);
        $this->note = (string)$element->reason;

        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->receiver_id);
    }

    public function update_606($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 606;
        $element = $xml->refusal_receiver_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'shipper_id'     => (string)$element->shipper_id,
            'reason'         => (string)$element->reason,
        ];

        $codes = $this->upd_codes($element);
        $this->fns_params = serialize($params);

        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->shipper_id);
    }

    /**
     *
     * @param type $xml
     */
    public function update_617($xml)
    {
        $this->operation_uid = Fns::OPERATION_617;
        $this->fnsid = 617;
        $element = $xml->receive_order_errors_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'shipper_id'     => (string)$element->shipper_id,
        ];
        $this->fns_params = serialize($params);

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->shipper_id);
    }

    public function update_616($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 616;
        $element = $xml->eeu_import_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'seller_id'      => (string)$element->seller_id,
            'shipper_id'     => (string)$element->shipper_id,
            'contract_type'  => (string)$element->contract_type,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];

        $codes = $this->upd_codes_with_vat($element);
        $params['gtins'] = $codes['gtins'];
        $this->fns_params = serialize($params);

        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->shipper_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_615($xml)
    {
        $this->operation_uid = Fns::OPERATION_615;
        $this->fnsid = 615;
        $element = $xml->eeu_shipment_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'seller_id'      => (string)$element->seller_id,
            'receiver_id'    => (string)$element->receiver_id,
            'contract_type'  => (string)$element->contract_type,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];

        $codes = $this->upd_codes_with_vat($element);
        $params['gtins'] = $codes['gtins'];
        $this->fns_params = serialize($params);

        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->receiver_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_621($xml)
    {
        $this->operation_uid = Fns::OPERATION_DEFAULT;
        $this->fnsid = 621;
        $element = $xml->arbitration_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'      => (string)$element->subject_id,
            'operation_date'  => (string)$element->operation_date,
            'seller_id'       => (string)$element->seller_id,
            'counterparty_id' => (string)$element->counterparty_id,
            'doc_num'         => (string)$element->doc_num,
            'doc_date'        => (string)$element->doc_date,
        ];

        $codes = $this->upd_codes($element);
        $this->fns_params = serialize($params);

        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->counterparty_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_614($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 614;
        $element = $xml->foreign_import_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'         => (string)$element->subject_id,
            'operation_date'     => (string)$element->operation_date,
            'seller_id'          => (string)$element->seller_id,
            'shipper_id'         => (string)$element->shipper_id,
            'custom_receiver_id' => (string)$element->custom_receiver_id,
            'contract_type'      => (string)$element->contract_type,
            'doc_num'            => (string)$element->doc_num,
            'doc_date'           => (string)$element->doc_date,
        ];
        $this->fns_params = serialize($params);
        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->seller_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_612($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 612;
        $element = $xml->state_dispatch_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'receiver_id'    => (string)$element->receiver_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];
        $this->fns_params = serialize($params);
        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->receiver_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_611($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 611;
        $element = $xml->receive_unregistered_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'shipper_id'     => (string)$element->shipper_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->shipper_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_609($xml)
    {
        $this->operation_uid = Fns::OPERATION_609;
        $this->fnsid = 609;
        $element = $xml->change_owner_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'receiver_id'    => (string)$element->receiver_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->receiver_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_607($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 607;
        $element = $xml->accept_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'      => (string)$element->subject_id,
            'operation_date'  => (string)$element->operation_date,
            'counterparty_id' => (string)$element->counterparty_id,
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->counterparty_id);
    }

    public function update_601($xml)
    {
        $this->operation_uid = Fns::OPERATION_601;
        $this->fnsid = 601;
        $element = $xml->move_order_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'receiver_id'    => (string)$element->receiver_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes_with_vat($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->receiver_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }


    public function update_603($xml)
    {
        $this->operation_uid = Fns::OPERATION_601;
        $this->fnsid = 603;
        $element = $xml->move_owner_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'owner_id'       => (string)$element->owner_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->counterparty_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_602($xml)
    {
        $this->operation_uid = Fns::OPERATION_IMPORT_ID;   ///пока без разбора в новые входящие
        $this->fnsid = 602;
        $element = $xml->receive_order_notification;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'shipper_id'     => (string)$element->shipper_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
            'receive_type'   => (string)$element->receive_type,
            'contract_type'  => (string)$element->contract_type,
            'source'         => (string)$element->source,
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);

        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function upd_check_code($codes)
    {
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }
    }

    public function upd_codes($element, $tag = 'order_details')
    {
        $icodes = $gcodes = [];
        if (isset($element->$tag->sgtin)) {
            foreach ($element->$tag->sgtin as $code) {
                $lc = substr((string)$code, 14);
                $lg = substr((string)$code, 0, 14);
                if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                    $icodes[] = substr((string)$code, 14);
                } else {
                    $icodes[] = (string)$code;
                }
            }
        }
        if (isset($element->$tag->sscc)) {
            foreach ($element->$tag->sscc as $code) {
                $gcodes[] = (string)$code;
            }
        }

        return ['icodes' => $icodes, 'gcodes' => $gcodes];
    }

    public function upd_codes_with_vat($element)
    {
        $gtins = $icodes = $gcodes = [];
        foreach ($element->order_details->union as $union) {
            if (isset($union->sgtin)) {
                $lc = substr((string)$union->sgtin, 14);
                $lg = substr((string)$union->sgtin, 0, 14);
                if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                    $icodes[] = substr((string)$union->sgtin, 14);
                } else {
                    $icodes[] = (string)$union->sgtin;
                }
                if (((float)$union->cost - (float)$union->vat_value) != 0) {
                    $vat = round((float)$union->vat_value * 100 / ((float)$union->cost - (float)$union->vat_value), 2);
                } else {
                    $vat = 0;
                }
                $gtins[substr((string)$union->sgtin, 0, 14)] = [(string)$union->cost, $vat];
            }
            if (isset($union->sscc_detail)) {
                $gcodes[] = (string)$union->sscc_detail->sscc;
                if (((float)$union->sscc_detail->detail->cost - (float)$union->sscc_detail->detail->vat_value) != 0) {
                    $vat = round(
                        (float)$union->sscc_detail->detail->vat_value * 100 / ((float)$union->sscc_detail->detail->cost - (float)$union->sscc_detail->detail->vat_value),
                        2
                    );
                } else {
                    $vat = 0;
                }
                $gtins[(string)$union->sscc_detail->detail->gtin] = [(string)$union->sscc_detail->detail->cost, $vat];
            }
        }

        return ['icodes' => $icodes, 'gcodes' => $gcodes, 'gtins' => $gtins];
    }

    /**
     * Поиск нашего объекта по справочнику объекты  ксли Sort = true - то приоритет своим объектм, иначе сторонним
     *
     * @param type $subject_id
     * @param type $sort
     */
    public function upd_object_uid($subject_id, $sort = true)
    {
        $obj = null;
        $objs = Facility::find()->where(['fns_subject_id' => $subject_id])->orderBy(
            ['external' => (!$sort) ? SORT_ASC : SORT_DESC]
        )->all();
        foreach ($objs as $o) {
            $obj = $o;
            if ($o->id == Yii::$app->user->getIdentity()->object_uid) {
                break;
            }
        }

        if (!empty($obj)) {
            $this->object_uid = $obj->id;
        } else {
            $this->object_uid = Yii::$app->user->getIdentity()->object_uid ?? null;
        }
    }

    /**
     * Поиск нашего объекта по справочнику объекты  ксли Sort = true - то приоритет своим объектм, иначе сторонним
     *
     * @param type $subject_id
     * @param type $sort
     */
    public function upd_newobject_uid($subject_id, $sort = true)
    {
        $obj = null;
        if (preg_match('#^\d+$#', $subject_id)) {
            $objs = Facility::find()->where(['fns_subject_id' => $subject_id])->orderBy(
                ['external' => (!$sort) ? SORT_ASC : SORT_DESC]
            )->all();
        } else {
            $objs = Facility::find()->where(['guid' => $subject_id])->orderBy(
                ['external' => (!$sort) ? SORT_ASC : SORT_DESC]
            )->all();
        }

        foreach ($objs as $o) {
            $obj = $o;
            if ($o->id == Yii::$app->user->getIdentity()->object_uid) {
                break;
            }
        }

        if (!empty($obj)) {
            $this->newobject_uid = $obj->id;
        }
    }

    public function upd_invoice_uid($doc_num, $doc_date)
    {
        if (is_array($this->codes)) {
            $c = pghelper::arr2pgarr($this->codes);
        } else {
            $c = $this->codes;
        }
        $invoice = Invoice::find()->andWhere(['invoice_number' => $doc_num])->andWhere(
            new Expression('codes && :codes', [':codes' => $c])
        )->one();

        if (empty($invoice)) {
            $invoice = $this->createInvoice($doc_num, $doc_date);
        }

        $this->invoice_uid = $invoice->id;
    }


    private function createInvoice(string $number, string $date)
    {
        $invoice = new Invoice;
        $invoice->scenario = 'external';
        $invoice->load(
            [
                'invoice_number' => $number,
                'invoice_date'   => \Yii::$app->formatter->asDate($date, 'php:Y-m-d'),
                'codes'          => $this->codes,
                'realcodes'      => $this->codes,
                'object_uid'     => $this->object_uid,
                'newobject_uid'  => $this->newobject_uid,
            ],
            ''
        );
        $invoice->save(false);
        $invoice->refresh();

        return $invoice;
    }

    public function upd_product_uid($codes)
    {
        $product = [];
        $res = Yii::$app->db->createCommand(
            'SELECT distinct product_uid FROM _get_codes_array(:arr)',
            [':arr' => pghelper::arr2pgarr($codes)]
        )->queryAll();
        foreach ($res as $r) {
            if ($r['product_uid'] && $r['product_uid'] != 'NULL') {
                $product[] = $r['product_uid'];
            }
        }
        $this->products = pghelper::arr2pgarr($product);
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }
    }

    public function update_251($xml)
    {
        $this->operation_uid = Fns::OPERATION_251;
        $this->fnsid = 251;
        $element = $xml->refusal_sender;
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'receiver_id'    => (string)$element->receiver_id,
            'reason'         => (string)$element->reason,
            'confirm_paused' => ((string)$element->confirm_paused ?? null),
        ];
        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->receiver_id);
    }

    public function update_252($xml)
    {
        $this->operation_uid = Fns::OPERATION_252;
        $this->fnsid = 252;
        $element = $xml->refusal_receiver;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'shipper_id'     => (string)$element->shipper_id,
            'reason'         => (string)$element->reason,
        ];
        $this->data = pghelper::arr2pgarr([(integer)$element->reason]);
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->fns_params = serialize($params);

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->shipper_id);
        $this->note = (string)$element->reason;
    }

    public function update_391($xml)
    {
        $this->operation_uid = Fns::OPERATION_BACK_ID;
        $this->fnsid = 391;
        $element = $xml->return_to_circulation;
        $params = [
            'subject_id'        => (string)$element->subject_id,
            'operation_date'    => (string)$element->operation_date,
            'withdrawal_reason' => (string)$element->withdrawal_reason,
        ];
        $this->data = pghelper::arr2pgarr([(integer)$element->withdrawal_reason]);
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        switch ((string)$element->withdrawal_reason) {
            case '1':
                $this->note = 'Списание';
                break;
            case '2':
                $this->note = 'Реэкспорт';
                break;
            case '3':
                $this->note = 'Отбор образцов';
                break;
            case '4':
                $this->note = 'Отпуск по льготному рецепту';
                break;
            case '5':
                $this->note = 'Выдача для оказания мед. помощи';
                break;
            case '6':
                $this->note = 'Отгрузка незарегистрированному участнику';
                break;
            case '7':
                $this->note = 'Выборочный контроль';
                break;
        }
        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->fns_params = serialize($params);

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
    }

    public function update_701($xml)
    {
        $this->operation_uid = Fns::OPERATION_INCOME_ID;
        $this->fnsid = 701;
        $element = $xml->accept;
        $params = [
            'subject_id'      => (string)$element->subject_id,
            'operation_date'  => (string)$element->operation_date,
            'counterparty_id' => (string)$element->counterparty_id,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->fns_params = serialize($params);

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);

        $this->upd_newobject_uid((string)$element->counterparty_id);
    }

    public function update_912($xml)
    {
        $this->operation_uid = Fns::OPERATION_UNGROUP_ID;
        $this->fnsid = 912;
        $element = $xml->unit_unpack;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $this->code = (string)$element->sscc;
        $cc = Code::findOneByCode($this->code);
        if (empty($cc)) {
            throw new BadRequestHttpException('Неизвестный код: ' . $this->code);
        }

        $this->indcnt = 1;
        $this->fns_params = serialize($params);

        $this->upd_product_uid([$this->code]);

        $codes_data = [];
        $codes_data[] = json_encode(['grp' => $this->code, 'codes' => []]);
        $this->codes_data = pghelper::arr2pgarr($codes_data);

        $this->upd_object_uid((string)$element->subject_id);
    }

    public function update_913($xml)
    {
        $this->operation_uid = Fns::OPERATION_GROUPSUB_ID;
        $this->fnsid = 913;
        $element = $xml->unit_extract;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
        ];

        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        $codes = $this->upd_codes($element, 'content');
        $this->indcnt = count($codes['icodes']);
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->fns_params = serialize($params);

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
    }

    public function update_914($xml)
    {
        $this->operation_uid = Fns::OPERATION_GROUPADD_ID;
        //        $this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 914;
        $element = $xml->unit_append;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $this->code = (string)$element->sscc;
        $code = Code::findOneByCode($this->code);
        if (empty($code)) {
            throw new BadRequestHttpException('Ошибка распознавания файла, код не найден: ' . $this->code);
        }
        $this->code_flag = $code->flag;

        $codes = $this->upd_codes($element, 'content');
        $datatype = count($codes['icodes']) ? 1 : 2;

        $this->indcnt = count($codes['icodes']);
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $codes_data = [];
        $codes_data[] = json_encode(['grp' => $this->code, 'codes' => array_merge($codes['icodes'], $codes['gcodes'])]);
        $this->codes_data = pghelper::arr2pgarr($codes_data);
        $this->fns_params = serialize($params);
        $this->data = pghelper::arr2pgarr([$datatype]);

        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
    }

    public function update_915($xml)
    {
        $this->operation_uid = Fns::OPERATION_GROUP_ID;
        //        $this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 915;
        $element = $xml->multi_pack;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        if (isset($element->by_sscc)) {
            $this->data = pghelper::arr2pgarr([2]);
            $c = $element->by_sscc;
        } elseif (isset($element->by_sgtin)) {
            $this->data = pghelper::arr2pgarr([1]);
            $c = $element->by_sgtin;
        }

        $datatype = 0;
        $codes_full = [];
        $codes_data = [];
        $grpcnt = 0;
        $indcnt = 0;
        foreach ($c->detail as $detail) {
            $this->code = $gprcode = (string)$detail->sscc;
            $grpcnt++;
            $codes = [];
            if (isset($element->by_sscc)) {
                $root = $detail->content->sscc;
            } else {
                $root = $detail->content->sgtin;
            }
            foreach ($root as $code) {
                $indcnt++;
                if (isset($element->by_sscc)) {
                    $codes[] = (string)$code;
                    $datatype = 2;
                } else {
                    $datatype = 1;
                    $lc = substr((string)$code, 14);
                    $lg = substr((string)$code, 0, 14);
                    if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                        $codes[] = substr((string)$code, 14);
                    } else {
                        $codes[] = (string)$code;
                    }
                }
            }
            $codes_full = array_merge($codes_full, $codes);
            $codes_data[] = json_encode(['grp' => $gprcode, 'codes' => $codes]);
        }
        $this->codes_data = pghelper::arr2pgarr($codes_data);
        $this->codes = pghelper::arr2pgarr($codes_full);
        if (is_array($codes_full) && count($codes_full)) {
            $ccsrc = array_shift($codes_full);
            array_unshift($codes_full, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }

        $code = Code::findOneByCode($this->code);
        if (empty($code)) {
            throw new BadRequestHttpException('Ошибка распознавания файла, код не найден: ' . $this->code);
        }
        $this->code_flag = $code->flag;

        $this->grpcnt = $grpcnt;
        $this->indcnt = $indcnt;

        $this->fns_params = serialize($params);

        $this->upd_product_uid($codes_full);

        $this->data = pghelper::arr2pgarr([$datatype]);

        $this->upd_object_uid((string)$element->subject_id);
    }

    public function update_313($xml)
    {
        $this->operation_uid = Fns::OPERATION_EMISSION_ID;
        //        $this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 313;
        $element = $xml->register_product_emission;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->release_info->doc_date, $m)) {
            $element->release_info->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'confirm_doc'    => (string)$element->release_info->confirmation_num,
            'doc_num'        => (string)$element->release_info->doc_num,
            'doc_date'       => (string)$element->release_info->doc_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $this->fns_params = serialize($params);
        $codes = [];
        foreach ($element->signs->sscc as $code) {
            $codes[] = (string)$code;
        }
        //$this->grpcnt = count($codes);
        $this->codes = pghelper::arr2pgarr($codes);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }

        $this->codes_data = '{}';
        $res = Yii::$app->db->createCommand(
            'select count(*) as grpcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_GROUP, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->grpcnt = is_array($codes) ? count($codes) : 0;

        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->indcnt = $res['indcnt'];
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        //$this->save();
    }

    public function update_311($xml)
    {
        $this->operation_uid = Fns::OPERATION_PACK_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 311;
        $element = $xml->register_end_packing;
        $params = [
            'subject_id'      => (string)$element->subject_id,
            'operation_date'  => (string)$element->operation_date,
            'order_type'      => (string)$element->order_type,
            'owner_id'        => (string)$element->owner_id,
            'series_number'   => (string)$element->series_number,
            'expiration_date' => (string)$element->expiration_date,
            'gtin'            => (string)$element->gtin,
            'tnved_code'      => (string)$element->tnved_code,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $this->fns_params = serialize($params);
        $codes = [];
        foreach ($element->signs->sgtin as $code) {
            $lc = substr((string)$code, 14);
            $lg = substr((string)$code, 0, 14);
            if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                $codes[] = substr((string)$code, 14);
            } else {
                $codes[] = (string)$code;
            }
        }
        $this->codes = pghelper::arr2pgarr($codes);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }
        $this->codes_data = '{}';
        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->indcnt = $res['indcnt'];
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        //$this->save();
    }

    public function update_312($xml)
    {
        $this->operation_uid = Fns::OPERATION_CONTROL_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 312;
        $element = $xml->withdrawal;
        $params = [
            'subject_id'           => (string)$element->subject_id,
            'operation_date'       => (string)$element->operation_date,
            'control_samples_type' => ((integer)$element->withdrawal_type - 18),
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $this->data = pghelper::arr2pgarr([((integer)$element->withdrawal_type - 18)]);
        switch (((integer)$element->withdrawal_type - 18)) {
            case 1:
                $this->note = 'На контроль';
                break;
            case 2:
                $this->note = 'В архив ОКК';
                break;
        }
        $this->fns_params = serialize($params);
        $codes = [];
        foreach ($element->order_details->sgtin as $code) {
            $lc = substr((string)$code, 14);
            $lg = substr((string)$code, 0, 14);
            if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                $codes[] = substr((string)$code, 14);
            } else {
                $codes[] = (string)$code;
            }
        }
        $gcodes = [];
        foreach ($element->order_details->sscc as $code) {
            $gcodes[] = (string)$code;
        }
        $codes = array_merge($codes, $gcodes);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }
        $this->codes = pghelper::arr2pgarr($codes);
        $this->codes_data = '{}';
        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->indcnt = $res['indcnt'];
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        //$this->save();
    }

    public function update_552($xml)
    {
        $this->operation_uid = Fns::OPERATION_WDEXT_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 552;
        $element = $xml->withdrawal;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $params = [
            'subject_id'      => (string)$element->subject_id,
            'operation_date'  => (string)$element->operation_date,
            'doc_num'         => ((string)$element->doc_num) ?? '',
            'doc_date'        => ((string)$element->doc_date) ?? '',
            'withdrawal_type' => (string)$element->withdrawal_type,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        if (in_array((string)$element->withdrawal_type, ['19', '20'])) {
            $this->update_312($xml);

            return;
        }

        switch ((string)$element->withdrawal_type) {
            case '6':
                $this->note = 'Выборочный контроль';
                $t = 'ext1';
                break;
            case '7':
                $this->note = 'Таможенный контроль';
                $t = 'ext2';
                break;
            case '8':
                $this->note = 'Федеральный надзор';
                $t = 'ext3';
                break;
            case '9':
                $this->note = 'В целях клинических исследований';
                $t = 'ext4';
                break;
            case '10':
                $this->note = 'В целях фармацевтической экспертизы';
                $t = 'ext5';
                break;
            case '11':
                $this->note = 'Недостача';
                $t = 'ext6';
                break;
            case '12':
                $this->note = 'Отбор демонстрационных образцов';
                $t = 'ext7';
                break;
            case '13':
                $this->note = 'Списание без передачи на уничтожение';
                $t = 'ext8';
                break;
            case '14':
                $this->note = 'Вывод из оборота КИЗ, накопленных в рамках эксперимента';
                $t = 'ext9';
                break;
            case '15':
                $this->note = 'Производственный брак';
                $t = 'ext15';
                break;
            case '16':
                $this->note = 'Списание разукомплектованной потребительской упаковки';
                $t = 'ext16';
            case '17':
                $this->note = 'Производство медицинских изделий';
                $t = 'ext17';
            case '18':
                $this->note = 'Производство медицинских препаратов';
                $t = 'ext18';
                break;
        }

        $this->data = pghelper::arr2pgarr(
            [
                $t,
                (string)$element->doc_num,
                (string)$element->doc_date,
            ]
        );
        $this->fns_params = serialize($params);
        $codes = [];
        foreach ($element->order_details->sgtin as $code) {
            $lc = substr((string)$code, 14);
            $lg = substr((string)$code, 0, 14);
            if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                $codes[] = substr((string)$code, 14);
            } else {
                $codes[] = (string)$code;
            }
        }
        $this->indcnt = count($codes);
        $gcodes = [];
        foreach ($element->order_details->sscc as $code) {
            $gcodes[] = (string)$code;
        }
        $this->grpcnt = count($gcodes);

        $codes = array_merge($codes, $gcodes);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }
        $this->codes = pghelper::arr2pgarr($codes);
        $this->codes_data = '{}';
        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        //        $this->indcnt = $res['indcnt'];
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        //$this->save();
    }

    public function update_381($xml)
    {
        $this->operation_uid = Fns::OPERATION_OUTCOME_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 381;
        $element = $xml->move_owner;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
            'owner_id'       => (string)$element->owner_id,
        ];
        $this->fns_params = serialize($params);
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;


        //newobject
        $this->upd_object_uid((string)$element->subject_id);
        $this->upd_newobject_uid((string)$element->owner_id);
        //codes
        $codes = [];
        foreach ($element->order_details->sgtin as $code) {
            $lc = substr((string)$code, 14);
            $lg = substr((string)$code, 0, 14);
            if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                $codes[] = substr((string)$code, 14);
            } else {
                $codes[] = (string)$code;
            }
        }
        //        $this->indcnt = count($codes);
        $gcodes = [];
        foreach ($element->order_details->sscc as $code) {
            $gcodes[] = (string)$code;
        }
        $this->grpcnt = count($gcodes);
        $codes = array_merge($codes, $gcodes);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }

        $this->codes = pghelper::arr2pgarr($codes);
        $this->codes_data = '{}';

        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->indcnt = $res['indcnt'];

        // product
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        // invoice
        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    /**
     * Парсинг загружаемого документа и сохранение свойст ФНС документа
     *
     * @param SimpleXMLElement $xml
     *
     * @return void
     */
    public function update_210($xml)
    {
        $this->operation_uid = Fns::OPERATION_210;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 210;
        $element = $xml->query_kiz_info;
        //в документе нет operation_date - даты прописываем здесь, а не в бд, чтобы потом найти этот док
        $this->created_time = date('H:i:s');;
        $this->created_at = date('Y-m-d');
        if (isset($element->sgtin)) {
            $this->code = (string)$element->sgtin;
            $ctype = CodeType::CODE_TYPE_INDIVIDUAL;
            $this->indcnt = 1;
        }
        if (isset($element->sscc_down)) {
            $this->code = (string)$element->sscc_down;
            $ctype = CodeType::CODE_TYPE_GROUP;
            $this->grpcnt = 1;
        }

        $params = [
            'subject_id'   => (string)$element->subject_id,
            'codetype_uid' => $ctype,
        ];
        $this->fns_params = serialize($params);


        //        $res = \Yii::$app->db->createCommand('select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
        //                                                        left join generations on generation_uid=generations.id
        //                                                        where codetype_uid = :codetype', [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr([$this->code])])->queryOne();
        //        $this->indcnt = $res['indcnt'];
        //product
        //        $product = pghelper::pgarr2arr($res['arr']);
        //        $this->products = $res['arr'];
        //        if (count($product) == 1)
        //            $this->product_uid = array_pop($product);
        //

        $this->upd_object_uid((string)$element->subject_id);
        //$this->save();
    }

    public function update_431($xml)
    {
        $this->operation_uid = Fns::OPERATION_OUTCOMESELF_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 431;
        $element = $xml->move_place;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $params = [
            'subject_id'     => (string)$element->subject_id,
            'receiver_id'    => (string)$element->receiver_id,
            'operation_date' => (string)$element->operation_date,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $this->fns_params = serialize($params);

        // newobject
        $this->upd_newobject_uid((string)$element->receiver_id);

        // codes
        $codes = [];
        foreach ($element->order_details->sgtin as $code) {
            $lc = substr((string)$code, 14);
            $lg = substr((string)$code, 0, 14);
            if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                $codes[] = substr((string)$code, 14);
            } else {
                $codes[] = (string)$code;
            }
        }
        $this->indcnt = count($codes);
        $gcodes = [];
        foreach ($element->order_details->sscc as $code) {
            $gcodes[] = (string)$code;
        }
        $this->grpcnt = count($gcodes);
        $codes = array_merge($codes, $gcodes);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }

        $this->codes = pghelper::arr2pgarr($codes);
        $this->codes_data = '{}';

        $res = \Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
                                                                left join generations on generation_uid=generations.id
                                                                where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();

        // product
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        // invoice
        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_541($xml)
    {
        $this->operation_uid = Fns::OPERATION_DESTRUCTION_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 541;
        $element = $xml->move_destruction;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
            'act_number'     => (string)$element->act_number,
            'act_date'       => (string)$element->act_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $destructor_inn = (string)$element->destruction_org->ul->inn;
        $destructor = Destructor::findOne(['inn' => $destructor_inn]);
        if (!empty($destructor)) {
            $params['destruction'] = $destructor->id;
        }
        $type = '';

        $codes = [];
        foreach ($element->order_details->detail as $detail) {
            $type = (string)$detail->destruction_type;
            $decision = (string)$detail->decision ?? '';
            if (isset($detail->sgtin)) {
                $lc = substr((string)$detail->sgtin, 14);
                $lg = substr((string)$detail->sgtin, 0, 14);
                if (in_array($lg, Yii::$app->modules['itrack']->ourGtins)) {
                    $codes[] = substr((string)$detail->sgtin, 14);
                } else {
                    $codes[] = (string)$detail->sgtin;
                }
            }
            if (isset($detail->sscc)) {
                $codes[] = (string)$detail->sscc;
            }
        }

        $params['type'] = $type;
        $params['decision'] = $decision;
        $this->fns_params = serialize($params);

        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }

        $this->codes = pghelper::arr2pgarr($codes);
        $this->codes_data = '{}';

        //cnt
        $res = Yii::$app->db->createCommand(
            'select count(*) as grpcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_GROUP, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->grpcnt = $res['grpcnt'];
        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [':codetype' => CodeType::CODE_TYPE_INDIVIDUAL, ':codes' => pghelper::arr2pgarr($codes)]
        )->queryOne();
        $this->indcnt = $res['indcnt'];

        //product
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        //$this->save();
    }

    public function update_542($xml)
    {
        $this->operation_uid = Fns::OPERATION_DESTRUCTIONACT_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 542;
        $element = $xml->destruction;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $params = [
            'subject_id'         => (string)$element->subject_id,
            'operation_date'     => (string)$element->operation_date,
            'doc_num'            => (string)$element->doc_num,
            'doc_date'           => (string)$element->doc_date,
            'destruction_method' => (string)$element->destruction_method,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;
        $destructor_inn = (string)$element->destruction_org->ul->inn;
        $destructor = Destructor::findOne(['inn' => $destructor_inn]);
        if (!empty($destructor)) {
            $params['destruction'] = $destructor->id;
        }

        $this->fns_params = serialize($params);

        $codes = $this->upd_codes($element);
        $this->indcnt = count($codes['icodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);

        $this->codes_data = '{}';
        $this->upd_product_uid(array_merge($codes['icodes'], $codes['gcodes']));

        $this->upd_object_uid((string)$element->subject_id);
    }

    public function update_415($xml)
    {
        $this->operation_uid = Fns::OPERATION_OUTCOMERETAIL_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 415;
        $element = $xml->move_order;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'receiver_id'    => (string)$element->receiver_id,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
            'turnover_type'  => (string)$element->turnover_type,
            'source'         => (string)$element->source,
            'contract_type'  => (string)$element->contract_type,
            'contract_num'   => (string)$element->contract_num,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;


        $codes = $this->upd_codes_with_vat($element);

        $params['gtins'] = $codes['gtins'];
        $this->fns_params = serialize($params);

        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);

        $this->grpcnt = count($codes['gcodes']);
        $this->indcnt = count($codes['icodes']);

        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [
                ':codetype' => CodeType::CODE_TYPE_INDIVIDUAL,
                ':codes'    => pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']))
            ]
        )->queryOne();

        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);
        // invoice
        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_441($xml)
    {
        $this->operation_uid = Fns::OPERATION_OUTCOMERETAILUNREG_ID;
        //$this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 441;
        $element = $xml->move_unregistered_order;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $inn = '';
        $kpp = '';
        $receiver_id = '';
        if (isset($element->receiver_info->receiver_id)) {
            $receiver_id = (string)$element->receiver_info->receiver_id;
        }
        if (isset($element->receiver_info->receiver_inn->ul)) {
            $inn = (string)$element->receiver_info->receiver_inn->ul->inn;
            $kpp = (string)$element->receiver_info->receiver_inn->ul->kpp;
        }
        if (isset($element->receiver_info->receiver_inn->fl)) {
            $inn = (string)$element->receiver_info->receiver_inn->fl->inn;
        }
        $params = [
            'subject_id'      => (string)$element->subject_id,
            'operation_date'  => (string)$element->operation_date,
            'inn'             => $inn,
            'receiver_ul_inn' => $inn,
            'receiver_ul_kpp' => $kpp,
            'kpp'             => $kpp,
            'doc_num'         => (string)$element->doc_num,
            'doc_date'        => (string)$element->doc_date,
            'contract_type'   => (string)$element->contract_type,
            'regNum'          => $receiver_id,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;


        $codes = $this->upd_codes_with_vat($element);
        $params['gtins'] = $codes['gtins'];
        $this->fns_params = serialize($params);

        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);
        $this->grpcnt = count($codes['gcodes']);
        $this->indcnt = count($codes['icodes']);

        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [
                ':codetype' => CodeType::CODE_TYPE_INDIVIDUAL,
                ':codes'    => pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']))
            ]
        )->queryOne();
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);

        // invoice
        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function updated_461($xml)
    {
        $this->operation_uid = Fns::OPERATION_461;

        $this->fnsid = 461;
        $element = $xml->move_order;
        if (preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', (string)$element->doc_date, $m)) {
            $element->doc_date = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
            'info_org_eeu'   => (string)$element->info_org_eeu,
            'doc_num'        => (string)$element->doc_num,
            'doc_date'       => (string)$element->doc_date,
            'contract_type'  => (string)$element->contract_type,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        $codes = $this->upd_codes_with_vat($element);

        $params['gtins'] = $codes['gtins'];
        $this->fns_params = serialize($params);

        $this->codes = pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']));
        $this->codes_data = '{}';
        $this->upd_check_code($codes['icodes']);
        $this->upd_check_code($codes['gcodes']);

        $this->grpcnt = count($codes['gcodes']);
        $this->indcnt = count($codes['icodes']);

        $res = Yii::$app->db->createCommand(
            'select array_agg(distinct codes.product_uid) as arr,count(*) as indcnt from get_full_codes(:codes) as codes
left join generations on generation_uid=generations.id
where codetype_uid = :codetype',
            [
                ':codetype' => CodeType::CODE_TYPE_INDIVIDUAL,
                ':codes'    => pghelper::arr2pgarr(array_merge($codes['icodes'], $codes['gcodes']))
            ]
        )->queryOne();
        $product = pghelper::pgarr2arr($res['arr']);
        $this->products = $res['arr'];
        if (is_array($product) && count($product) == 1) {
            $this->product_uid = array_pop($product);
        }

        $this->upd_object_uid((string)$element->subject_id);

        // invoice
        $this->upd_invoice_uid((string)$element->doc_num, (string)$element->doc_date);
    }

    public function update_811($xml)
    {
        $this->operation_uid = Fns::OPERATION_RELABEL_ID;
        //        $this->operation_uid = Fns::OPERATION_UPLOADED;
        $this->fnsid = 811;
        $element = $xml->relabeling;
        $params = [
            'subject_id'     => (string)$element->subject_id,
            'operation_date' => (string)$element->operation_date,
        ];
        $this->created_time = (empty($this->created_time)) ? preg_replace(
            '#^.*?(\s|T)#si',
            '',
            (string)$element->operation_date
        ) : $this->created_time;
        $this->created_at = (empty($this->created_at)) ? preg_replace(
            '#(\s|T).*?$#si',
            '',
            (string)$element->operation_date
        ) : $this->created_at;

        $codes = $gcodes = [];
        if (isset($element->relabeling_detail->detail)) {
            foreach ($element->relabeling_detail->detail as $code) {
                if (in_array(substr((string)$code->old_sgtin, 0, 14), Yii::$app->modules['itrack']->ourGtins)) {
                    $old = substr((string)$code->old_sgtin, 14);
                } else {
                    $old = (string)$code->old_sgtin;
                }
                if (in_array(substr((string)$code->new_sgtin, 0, 14), Yii::$app->modules['itrack']->ourGtins)) {
                    $new = substr((string)$code->new_sgtin, 14);
                } else {
                    $old = (string)$code->new_sgtin;
                }


                $codes[] = json_encode(['f1' => $old, 'f2' => $new]);
                $gcodes[] = $old;
                $gcodes[] = $new;
            }
        }

        $this->indcnt = count($codes);

        $this->codes = pghelper::arr2pgarr($gcodes);
        $this->codes_data = pghelper::arr2pgarr($codes);
        $this->fns_params = serialize($params);
        if (is_array($codes) && count($codes)) {
            $ccsrc = array_shift($codes);
            array_unshift($codes, $ccsrc);
            $cc = Code::findOneByCode($ccsrc);
            if (empty($cc)) {
                throw new BadRequestHttpException('Неизвестный код: ' . $ccsrc);
            }
        }

        $this->upd_product_uid($gcodes);

        $this->upd_object_uid((string)$element->subject_id);
    }
}
