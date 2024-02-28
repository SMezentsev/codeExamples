<?php

namespace common\components\order\Repository;

use common\components\order\Dto\OrderDto;
use common\components\order\Dto\OrderGoodDto;
use common\components\order\Interfaces\InvoiceRepositoryInterface;
use common\components\order\Interfaces\OrderGoodRepositoryInterface;
use common\components\order\Interfaces\OrderInterface;
use common\components\order\Interfaces\OrderRepositoryInterface;
use common\components\order\Interfaces\OrderStatusHistoryRepositoryInterface;
use common\components\order\Interfaces\OrderStatusRepositoryInterface;
use common\components\order\Model\Order;
use common\components\order\Model\OrderPaymentMethod;
use common\components\order\Model\OrderStatus;
use common\components\order\Model\OrderStatusCategory;
use common\components\webService\response\CancelOrder;
use common\Exceptions\ValidationErrorException;
use domain\services\Service1c;
use Exception;
use Throwable;
use Yii;
use yii\caching\CacheInterface;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\web\NotFoundHttpException;


class OrderRepository implements OrderRepositoryInterface
{
	/**
	 * @var OrderStatusHistoryRepository
	 */
	protected $orderStatusHistoryRepository;
	
	/**
	 * @var OrderGoodRepository
	 */
	protected $orderGoodRepository;
	
	/**
	 * @var InvoiceRepository
	 */
	protected $invoiceRepository;
	
	/**
	 * @var Service1c
	 */
	protected $orderService;
	
	/**
	 * @var OrderStatusRepository
	 */
	protected $orderStatusRepository;
	
	
	public function __construct(
		OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository,
		OrderGoodRepositoryInterface          $orderGoodRepository,
		InvoiceRepositoryInterface            $invoiceRepository,
		OrderStatusRepositoryInterface        $orderStatusRepository
	)
	{
		$this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
		$this->orderGoodRepository          = $orderGoodRepository;
		$this->invoiceRepository            = $invoiceRepository;
		$this->orderStatusRepository        = $orderStatusRepository;
		$this->orderService                 = Yii::$app->ecommerce->getService1c();
	}
	
	/**
	 * Сохраняет статус оплаты
	 *
	 * @param int $id
	 * @param int $statusId
	 *
	 */
	public function setPaymentStatus(int $id, int $statusId)
	{
		if ($statusId) {
			$order = $this->getOrder($id);
			
			$order->payment_status_id = $statusId;
			$order->save();
		}
	}
	
	/**
	 * Сохраняет статус доставки
	 *
	 * @param int         $id
	 * @param int         $statusId
	 * @param string|null $statusMessage
	 *
	 * @throws NotFoundHttpException
	 */
	public function setDeliveryStatus(int $id, int $statusId, ?string $statusMessage = null): void
	{
		if ($statusId) {
			$order = $this->getOrder($id);
			
			$order->setAttributes(
				[
					'delivery_status_id' => $statusId,
					'status_message'     => StringHelper::truncate($statusMessage, 255),
					'updated_at'         => date('Y-m-d H:i:s'),
				],
				false
			);
			
			$order->save(false);
		}
	}
	
	/**
	 * Отменяет заказа
	 *
	 * @param Order $order
	 * @param       $statusId
	 *
	 * @return OrderStatus|CancelOrder
	 * @throws NotFoundHttpException
	 * @throws Throwable
	 */
	public function cancel(Order $order, $statusId)
	{
		$user = Yii::$app->user->getIdentity();
		
		try {
			$orderService = $this->orderService->getOrderInfo($user->code_1c, $order->code_1c);
		} catch (Exception $e) {
			throw new NotFoundHttpException();
		}
		
		/**
		 * @var CancelOrder $result
		 */
		$result = $this->orderService->cancelOrder($user->code_1c, $order->code_1c);
		if (true === (bool)$result['status']) {
			$orderStatus     = \domain\entities\service1c\Order::STATUS_RESERVE_CANCEL;
			$orderStatusText = ArrayHelper::getValue($orderService::getOrderStatusOptions(), $orderStatus);
			
			$result['order'][$user->id] = [
				'orderStatus'     => $orderStatus,
				'orderStatusText' => $orderStatusText,
			];
			
			// Чистим кешь по текущему пользователю
			TagDependency::invalidate($this->getCache(), [$this->getCacheTagsUserOrder()]);
			Yii::debug("Invalidate cache for tag: {$this->getCacheTagsUserOrder()}", 'application.b2b.order.cancel');
			
			$status = $this->orderStatusRepository->setStatus($order->id, OrderStatus::STATUS_CANCEL);
			$this->setDeliveryStatus($order->id, $status->id);
			
			return $status;
		}
		
		return $result;
	}
	
	/**
	 * Получение модели заказа
	 *
	 * @param int $id
	 *
	 * @return Order
	 * @throws NotFoundHttpException
	 */
	public function getOrder(int $id): Order
	{
		if (!$model = Order::find()->where(['id' => $id])->one()) {
			throw new NotFoundHttpException(Yii::t('app', 'Не найден заказ id={id}', ['id' => $id]));
		}
		
		return $model;
	}
	
	/**
	 * Сохраняет заказ
	 *
	 * @param OrderDto $dto
	 *
	 * @return OrderInterface
	 * @throws ValidationErrorException
	 */
	public function create(OrderDto $dto): OrderInterface
	{
		$order = new Order(
			[
				'code_1c'                 => $dto->code1c,
				'opt_user_id'             => $dto->optUserId,
				'order_delivery_type_id'  => $dto->deliveryTypeId,
				'order_payment_method_id' => $dto->orderPaymentMethodId === OrderPaymentMethod::METHOD_INVOICE ? OrderPaymentMethod::INVOICE : OrderPaymentMethod::CASH,
				'price'                   => $dto->price,
				'reserve_date'            => $dto->reserveDate,
				'shipping_date'           => $dto->shippingDate,
				'created_at'              => $dto->createdAt,
				'comment'                 => $dto->comment,
			]
		);
		
		if (false === $order->save()) {
			throw ValidationErrorException::create($order->getErrors());
		}
		
		//выставляем дефолтные статус заказа (не оплачен и собирается)
		$this->orderStatusRepository->setDefaultStatus(
			$order->getId(),
			[
				OrderStatusCategory::CATEGORY_PAYMENT,
				OrderStatusCategory::CATEGORY_DELIVERY,
			]
		);
		
		//Дублируем статусы в заказ
		$this->setPaymentStatus(
			$order->id,
			$this->orderStatusRepository->getDefaultStatus(OrderStatusCategory::CATEGORY_PAYMENT)->id
		);
		
		$this->setDeliveryStatus(
			$order->id,
			$this->orderStatusRepository->getDefaultStatus(OrderStatusCategory::CATEGORY_DELIVERY)->id
		);
		
		//сохраняем позиции из корзины
		if (isset($dto->cart)) {
			foreach ($dto->cart as $item) {
				//вычисляем тип товара (disk, tyre, ...)
				
				if (!empty($item['goodType'])) {
					$goodCategoryId = $this->orderGoodRepository->getGoodCategoryByEngName($item['goodType']);
					
					$this->orderGoodRepository->create(
						new OrderGoodDto(
							$order->id,
							$item['goodId'],
							$goodCategoryId,
							$item['quantity'],
							$item['price'],
							$dto->createdAt,
						)
					);
				}
			}
		}
		
		return $order;
	}
	
	
	/**
	 * @return CacheInterface
	 */
	protected function getCache()
	{
		return Yii::$app->cache;
	}
	
	protected function getCacheTagsUserOrder()
	{
		$userId = Yii::$app->user->getId();
		
		return "orders-user-{$userId}";
	}
}