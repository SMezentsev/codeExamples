<?php

namespace common\components\order\Queue\jobs;

use common\components\notify\OrderNotify;
use common\components\order\Dto\OrderEventDto;
use common\components\order\Exception\OrderException;
use common\components\order\Exception\OrderInvalidUserData;
use common\components\order\Factory\B2BOrderFormFactoryByOrder;
use common\components\order\Interfaces\OrderEventInterface;
use common\components\order\Interfaces\OrderInterface;
use common\components\order\Interfaces\OrderServiceInterface;
use common\components\order\Model\Invoice;
use common\components\order\Model\Order;
use common\components\order\Model\OrderPaymentMethod;
use common\components\order\Model\OrderStatus;
use common\models\forms\B2BOrderForm;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use domain\entities\service1c\OrderReserve;
use Exception;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\di\NotInstantiableException;
use yii\helpers\Console;
use yii\queue\JobInterface;
use yii\queue\Queue;
use yii\web\NotFoundHttpException;

class PlaceOrderJob extends BaseObject implements JobInterface
{
	/**
	 * Максимальное кол-во попыток
	 */
	private const MAX_ATTEMPTS = 500;
	
	/**
	 * Максимальное кол-во попыток
	 */
	private const MAX_TIME_SECONDS = 60 * 45; // 45 минут
	
	/**
	 * Идентификатор заказа
	 *
	 * @var int
	 */
	public $orderId;
	/**
	 * @var int Текущий номер повторной попытки
	 */
	public $attemptCount;
	
	/**
	 * @var OrderReserve|bool|null
	 */
	private $reserve;
	
	/**
	 * @param $queue
	 *
	 * @return void
	 * @throws NotFoundHttpException
	 * @throws OrderException
	 * @throws OrderInvalidUserData
	 * @throws Throwable
	 */
	public function execute($queue)
	{
		$this->attemptCount = (int)$this->attemptCount + 1;
		
		$now = new DateTime();
		
		$createdAt   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $this->getOrder()->created_at);
		$leftSeconds = $now->getTimestamp() - $createdAt->getTimestamp();
		
		Console::stdout(sprintf("Обработка заказа: %d\n", $this->orderId));
		Console::stdout(sprintf("\tПопытка: %d\n", $this->attemptCount));
		
		try {
			$this->work();
			Console::stdout(sprintf("\tСформирован заказ: %s\n\n", $this->reserve->getId()));
		} catch (OrderInvalidUserData $e) {
			$this->getOrderService()->event(new OrderEventDto($this->orderId, OrderEventInterface::EVENT_VALIDATE_FAIL, ['message' => $e->getMessage()]));
			$this->setErrorStatus($e->getMessage());
			Console::stdout("\tВозникла ошибка валидации пользовательских данных\n");
			throw $e;
		} catch (NotFoundHttpException|Exception $e) {
			$this->getOrderService()->event(new OrderEventDto($this->orderId, OrderEventInterface::EVENT_VALIDATE_FAIL, ['message' => $e->getMessage()]));
			Console::stdout("\tВозникла ошибка\n");
			Console::stdout(sprintf("\t%s\n", $e->getMessage()));
			if ($this->attemptCount <= self::MAX_ATTEMPTS && $leftSeconds <= self::MAX_TIME_SECONDS) {
				$this->makeRetry($queue, $this->getNextDelay($this->attemptCount));
			} else {
				// по какой-то причине этот заказ так и не оформился, уведомляем вторую линию
				$message = sprintf("Достигли лимита по попыткам, оформить заказ не удалось: %s", $e->getMessage());
				$this->setErrorStatus($message);
				Console::stdout($message . "\n");
			}
			throw $e;
		}
	}
	
	/**
	 * @param string|null $message
	 *
	 * @return void
	 * @throws InvalidConfigException
	 * @throws NotInstantiableException
	 */
	protected function setErrorStatus(?string $message): void
	{
		$status = OrderStatus::find()->byStatusError()->cache(3600)->one();
		if ($status !== null) {
			$this->getOrderService()->setOrderStatus($this->orderId, $status->id, $message);
		}
	}
	
	/**
	 * @return OrderServiceInterface
	 * @throws InvalidConfigException
	 * @throws NotInstantiableException
	 */
	protected function getOrderService(): OrderServiceInterface
	{
		/** @var OrderServiceInterface $service */
		$service = Yii::$container->get(OrderServiceInterface::class);
		
		return $service;
	}
	
	/**
	 * Посчитываем задержку перед следующей попыткой
	 *
	 * @param int $attempt
	 *
	 * @return int
	 */
	protected function getNextDelay(int $attempt): int
	{
		$delaySeconds = 3 * $this->attemptCount;
		if ($delaySeconds > 60) {
			$delaySeconds = 60;
		}
		
		return $delaySeconds;
	}
	
	/**
	 * @param Queue $queue
	 * @param int   $delay
	 *
	 * @return void
	 */
	protected function makeRetry(Queue $queue, int $delay): void
	{
		$queue->delay($delay)->push(
			new self(
				[
					'attemptCount' => $this->attemptCount,
					'orderId'      => $this->orderId,
				]
			)
		);
	}
	
	/**
	 * @var Order|OrderInterface
	 */
	private $order;
	
	/**
	 * @return Order|OrderInterface
	 * @throws NotFoundHttpException
	 */
	protected function getOrder(): OrderInterface
	{
		if ($this->order === null) {
			/** @var Order|OrderInterface $orderModel */
			$orderModel = Order::find()->byId($this->orderId)->one();
			if ($orderModel === null) {
				throw new NotFoundHttpException(Yii::t('app', 'Не найден заказ {id}', ['id' => $this->orderId]));
			}
			$this->order = $orderModel;
		}
		
		return $this->order;
	}
	
	/**
	 * @return void
	 * @throws NotFoundHttpException
	 * @throws OrderInvalidUserData
	 * @throws Throwable
	 * @throws \yii\base\Exception
	 * @throws InvalidConfigException
	 * @throws NotInstantiableException
	 */
	protected function work(): void
	{
		/** @var Order|OrderInterface $orderModel */
		$orderModel = $this->getOrder();
		
		$factory   = new B2BOrderFormFactoryByOrder();
		$orderForm = $factory->createByOrder($orderModel);
		
		if ($orderForm->validate() === false) {
			throw new OrderInvalidUserData($this->prepareErrors($orderForm));
		}
		
		$reserve = $orderForm->placeOrder();
		if ($reserve === false) {
			throw new OrderInvalidUserData($this->prepareErrors($orderForm));
		}
		
		$shipmentDt = $orderForm->getOrder()->deliveryDt;
		if (!empty($shipmentDt) && !($shipmentDt instanceof DateTimeInterface)) {
			$shipmentDt = DateTime::createFromFormat('d.m.Y', $shipmentDt)->setTime(15, 0);
		}
		
		$reserveValidUntilDt = $orderForm->getOrder()->reserveEndDt;
		if (!empty($orderForm->getOrder()->reserveEndDt) && !($reserveValidUntilDt instanceof DateTimeInterface)) {
			$reserveValidUntilDt = DateTime::createFromFormat('d.m.Y', $reserveValidUntilDt)->setTime(15, 0);
		}
		
		$orderModel->setAttributes(
			[
				'code_1c'            => $reserve->getId(),
				'updated_at'         => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
				'shipping_date'      => $shipmentDt !== null ? $shipmentDt->format('Y-m-d H:i:s') : null,
				'reserve_date'       => $reserveValidUntilDt !== null ? $reserveValidUntilDt->format('Y-m-d H:i:s') : null,
				'delivery_status_id' => 4 //todo Сделать нормально, через мэпинг по коду 1с
			],
			false
		);
		
		if ((int)$orderModel->order_payment_method_id === OrderPaymentMethod::INVOICE
			&& $reserve->getInvoiceEntity() !== null) {
			$invoice = new Invoice(
				[
					'number' => $reserve->getInvoice(),
				]
			);
			$invoice->save(false);
			$orderModel->link('invoice', $invoice);
		}
		
		$transaction = $orderModel::getDb()->beginTransaction();
		try {
			$orderModel->save(false);
			$transaction->commit();
		} catch (\yii\db\Exception $e) {
			$transaction->rollBack();
			throw $e;
		}
		
		$notify = new OrderNotify();
		//$notify->orderCreate($orderForm, $reserve);
		
		$this->reserve = $reserve;
	}
	
	protected function prepareErrors(B2BOrderForm $orderForm): ?string
	{
		if ($orderForm->hasErrors() === false) {
			return null;
		}
		$errors = $orderForm->getErrors();
		
		$flatten = static function (array $array) {
			$return = [];
			array_walk_recursive(
				$array,
				static function ($a) use (&$return) {
					$return[] = $a;
				}
			);
			
			return $return;
		};
		
		return implode('; ', $flatten($errors[array_key_first($errors)] ?? []));
	}
	
}