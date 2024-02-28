<?php

namespace common\components\order\Service;

use common\components\order\Dto\InvoiceDto;
use common\components\order\Dto\OrderDto;
use common\components\order\Dto\OrderInvoiceDto;
use common\components\order\Dto\ServiceFactoryDto;
use common\components\order\Interfaces\InvoiceRepositoryInterface;
use common\components\order\Interfaces\OrderDeliveryRepositoryManagerInterface;
use common\components\order\Interfaces\OrderDeliveryTypeRepositoryInterface;
use common\components\order\Interfaces\OrderEventInterface;
use common\components\order\Interfaces\OrderEventRepositoryInterface;
use common\components\order\Interfaces\OrderGoodRepositoryInterface;
use common\components\order\Interfaces\OrderInterface;
use common\components\order\Interfaces\OrderInvoiceRepositoryInterface;
use common\components\order\Interfaces\OrderRepositoryInterface;
use common\components\order\Interfaces\OrderServiceInterface;
use common\components\order\Model\Order;
use common\components\order\Model\OrderPaymentMethod;
use common\components\order\Repository\InvoiceRepository;
use common\components\order\Repository\OrderDeliveryRepositoryManager;
use common\components\order\Repository\OrderRepository;
use common\Exceptions\ValidationErrorException;
use common\interfaces\B2BUserInterface;
use common\models\forms\B2BOrderForm;
use common\models\OptUser;
use yii\web\NotFoundHttpException;

class OrderService implements OrderServiceInterface
{
	/**
	 * @var B2BUserInterface|OptUser
	 */
	protected $user;
	/**
	 * @var B2BOrderForm
	 */
	protected $form;
	
	/**
	 * @var OrderGoodRepositoryInterface
	 */
	protected $orderGoodsRepository;
	
	/**
	 * @var OrderRepository
	 */
	protected $orderRepository;
	
	/**
	 * @var OrderDeliveryTypeRepositoryInterface
	 */
	protected $orderDeliveryTypeRepository;
	
	/**
	 * @var OrderDeliveryRepositoryManager
	 */
	protected $orderDeliveryRepositoryManager;
	
	/**
	 * @var InvoiceRepository
	 */
	protected $invoiceRepository;
	
	/**
	 * @var OrderInvoiceRepositoryInterface
	 */
	protected $orderInvoiceRepository;
	
	/**
	 * @var OrderEventRepositoryInterface
	 */
	protected $orderEventRepository;
	
	public function __construct(
		OrderRepositoryInterface                $orderRepository,
		OrderGoodRepositoryInterface            $orderGoodRepository,
		OrderDeliveryTypeRepositoryInterface    $orderDeliveryTypeRepository,
		OrderDeliveryRepositoryManagerInterface $orderDeliveryRepositoryManager,
		InvoiceRepositoryInterface              $invoiceRepository,
		OrderInvoiceRepositoryInterface         $orderInvoiceRepository,
		OrderEventRepositoryInterface           $orderEventRepository
	)
	{
		$this->orderRepository                = $orderRepository;
		$this->orderGoodsRepository           = $orderGoodRepository;
		$this->orderDeliveryTypeRepository    = $orderDeliveryTypeRepository;
		$this->orderDeliveryRepositoryManager = $orderDeliveryRepositoryManager;
		$this->invoiceRepository              = $invoiceRepository;
		$this->orderInvoiceRepository         = $orderInvoiceRepository;
		$this->orderEventRepository           = $orderEventRepository;
	}
	
	/**
	 * @param ServiceFactoryDto $orderServiceDto
	 *
	 * @return Order
	 * @throws ValidationErrorException
	 */
	public function create(ServiceFactoryDto $orderServiceDto): OrderInterface
	{
		$deliveryType = $this->orderDeliveryTypeRepository->getDeliveryType(
			$orderServiceDto->deliveryType
		);
		
		//сохраняем данные в таблицу Заказов (Order)
		if (!$order = $this->orderRepository->create(
			new OrderDto(
				$orderServiceDto->userId,
				$orderServiceDto->code1c,
				$deliveryType->id,
				$orderServiceDto->delivery['payment'],
				$orderServiceDto->cart,
				$orderServiceDto->price * 100 ?? 0,
				$orderServiceDto->shipping_date ?? '',
				$orderServiceDto->shipping_date ?? '',
				$orderServiceDto->createdAt ?? '',
				$orderServiceDto->delivery['comment'] ?? ''
			)
		)) {
			throw ValidationErrorException::create($order->errors);
		}
		
		if (OrderPaymentMethod::INVOICE == $order->order_payment_method_id) {
			if (!empty($orderServiceDto->invoiceNumber)) {
				//создаем счет
				$invoice = $this->invoiceRepository->create(
					new InvoiceDto($orderServiceDto->invoiceNumber)
				);
				//привязываем счет к заказу
				$this->orderInvoiceRepository->create(
					new OrderInvoiceDto($order->id, $invoice->id)
				);
			}
		}
		
		//Сохраняем данные о доставке
		$this->orderDeliveryRepositoryManager->create($orderServiceDto->create(), $order, $deliveryType);
		
		return $order;
	}
	
	/**
	 * Устанавливает статус для заказа
	 *
	 * @param int         $orderId
	 * @param int         $statusId
	 * @param string|null $statusMessage
	 *
	 * @return void
	 * @throws NotFoundHttpException
	 */
	public function setOrderStatus(int $orderId, int $statusId, ?string $statusMessage = null): void
	{
		$this->orderRepository->setDeliveryStatus($orderId, $statusId, $statusMessage);
	}
	
	public function event(OrderEventInterface $event): void
	{
		$this->orderEventRepository->event($event);
	}
	
}