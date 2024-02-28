<?php

namespace common\components\order\Queue\jobs;

use common\components\order\Helper\OrderStatus1cMapper;
use common\components\order\Interfaces\OrderServiceInterface;
use common\components\order\Model\Order;
use common\components\order\Model\OrderStatus;
use common\components\order\Model\OrderStatusCategory;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Console;
use yii\helpers\StringHelper;
use yii\queue\JobInterface;

class OrderStatusJob extends BaseObject implements JobInterface
{
	
	/**
	 * @var array
	 */
	public $orders;
	
	/**
	 * @var OrderServiceInterface
	 */
	private $orderService;
	
	public function __construct(OrderServiceInterface $orderService, $config = [])
	{
		parent::__construct($config);
		$this->orderService = $orderService;
	}
	
	protected function fetchOrderStatuses(): array
	{
		$query = OrderStatus::find()
			->byCategoryId(OrderStatusCategory::CATEGORY_DELIVERY)
			->select(['id', 'code'])
			->asArray();
		
		$statuses = [];
		foreach ($query->each() as $row) {
			$statuses[$row['code']] = (int)$row['id'];
		}
		
		return $statuses;
	}
	
	/**
	 * @var array|null
	 */
	private $statuses;
	
	/**
	 * @return array
	 */
	protected function getStatuses(): array
	{
		return $this->statuses ?? $this->statuses =
			Yii::$app->cache->getOrSet(__METHOD__, fn() => $this->fetchOrderStatuses(), 3600);
	}
	
	public function execute($queue)
	{
		foreach ($this->orders as $inputData) {
			$orderSource = $inputData['order_source'] ?? null;
			$reserveId   = $inputData['order_id'] ?? null;
			
			if (empty($reserveId) || empty($orderSource) || mb_stripos($orderSource, 'sh') !== 0) {
				Console::stdout("Не соответствует требованиям, пропускаем.\n");
				continue;
			}
			
			if (!StringHelper::startsWith($orderSource, 'sh-b2b', false)) {
				Console::stdout("Не b2b заказ, пропускаем\n");
				continue;
			}
			
			$order = Order::find()->byReserveId($reserveId)->one();
			if ($order === null) {
				Console::stdout(sprintf("Резерв с таким идентификатором `%s` не существует, пропускаем.\n", $reserveId));
				continue;
			}
			
			$status1c = $inputData['status'] ?? null;
			$status   = OrderStatus1cMapper::getBy1cCode($status1c);
			if ($status === null) {
				Console::stdout(sprintf("Неизвестный статус 1C `%s`, пропускаем.\n", $status1c));
				continue;
			}
			
			$statusId = $this->getStatuses()[$status] ?? null;
			if ($statusId === null) {
				Console::stdout(sprintf("Неизвестный статус `%s`, пропускаем.\n", $statusId));
				continue;
			}
			
			if ($statusId !== (int)$order->delivery_status_id) {
				Console::stdout(sprintf("Обновляем статус `%s`, на %s (%d).\n", $order->getId(), $status, $statusId));
				$this->orderService->setOrderStatus($order->getId(), $statusId);
			}
		}
	}
	
	
}