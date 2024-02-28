<?php

namespace app\modules\itrack\components\Notify\Fns;

use app\modules\itrack\components\Notify\Fns\Interfaces\FnsMacrosBuilderInterface;
use app\modules\itrack\components\Notify\Fns\Interfaces\FnsNotifyServiceInterface;
use app\modules\itrack\components\pghelper;
use app\modules\itrack\models\Fns;
use app\modules\itrack\models\Notify;
use InvalidArgumentException;
use Yii;
use yii\base\Component;

class FnsNotifyService extends Component implements FnsNotifyServiceInterface
{
    const SUCCESS_STATES = [
        Fns::STATE_RESPONCE_SUCCESS,
        Fns::STATE_RESPONCE_PART,
        Fns::STATE_COMPLETED,
        Fns::STATE_RECEIVED,
        Fns::STATE_TQS_CONFIRMED
    ];

    /**
     * @var FnsMacrosBuilderInterface
     */
    private $builder;

    public function __construct(FnsMacrosBuilderInterface $builder, $config = [])
    {
        parent::__construct($config);
        $this->builder = $builder;
    }

    /**
     * отправка писем
     * @param Fns $fns
     */
    public function send(Fns $fns): void
    {
        $notifies = $this->getNotifies($fns);
        $attach = $fns->getNotifyAttach();

        /** @var Notify $notify */
        foreach ($notifies as $notify) {
            try {
                $message = $this->formMessage($notify, $fns);
                $notify->send($message['to'], $message['subject'], $message['message'], $attach);
            } catch (InvalidArgumentException $e) {
                Yii::warning($e->getMessage());
                if ($e->getCode() === 0) {
                    continue;
                } elseif ($e->getCode() === 1) {
                    return;
                }
            }
        }
    }

    /**
     * проверка и формирование писем для отправки
     * @param Fns $fns
     * @return array        массив с письмами для отправки
     */
    public function check(Fns $fns): array
    {
        $notifies = $this->getNotifies($fns);
        $messages = [];

        /** @var Notify $notify */
        foreach ($notifies as $notify) {
            try {
                $messages[] = $this->formMessage($notify, $fns);
            } catch (InvalidArgumentException $e) {
                Yii::warning($e->getMessage());
                // @see $this->formMessage method
                if ($e->getCode() === 0) {
                    continue;
                } elseif ($e->getCode() === 1) {
                    return [];
                }
            }
        }

        return $messages;
    }

    private function getNotifies(Fns $fns): array
    {
        $state = (in_array($fns->state, self::SUCCESS_STATES)) ? 'success' : 'error';
        return Notify::getNotify($fns->fnsid, ($fns->internal ? $fns->object_uid : $fns->newobject_uid), $state);
    }

    /**
     * @param Notify $notify
     * @param Fns $fns
     * @param bool $addAttach
     * @return array|null
     * @throws InvalidArgumentException
     */
    private function formMessage(Notify $notify, Fns $fns): ?array
    {
        // TODO: тут не совсем ясно почему без id'шника идем дальше, а если (email === 'none'), то прерываем отсылку
        // всех писем, нужно проверить и поправить если надо
        if (empty($notify->id)) {
            throw new InvalidArgumentException('id cannot be empty', 0);
        }

        if ($notify->email === 'none') {
            throw new InvalidArgumentException('email cannot be none', 1);
        }

        $parameters = $this->getParameters($notify->params);
        $macros = $this->builder->build($fns);

        $message = [
            'id' => $notify->id,
            'to' => explode(',', $notify->email),
            'subject' => $this->replaceMacros($parameters[0], $macros),
            'message' => $this->replaceMacros($parameters[1], $macros),
        ];

        return $message;
    }

    private function getParameters($value):array
    {
        $parameters = pghelper::pgarr2arr($value);

        if (count($parameters) != 2) {
            throw new InvalidArgumentException('parameters cannot be equals 2');
        }

        return $parameters;
    }

    private function replaceMacros(string $message, FnsMacros $macros): string
    {
        $properties = get_object_vars($macros);

        foreach ($properties as $name => $value) {
            $message = str_replace('=' . $name . '=', $macros->$name, $message);
        }

        return $message;
    }
}