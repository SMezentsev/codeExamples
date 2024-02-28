<?php

namespace common\components\order\Queue;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use yii\base\InvalidConfigException;

class OrderStatusQueue extends \yii\queue\amqp_interop\Queue
{
	
	public function __construct($config = [])
	{
		$this->maxPriority = null;
		parent::__construct($config);
	}
	
	/**
	 * @return void
	 * @throws InvalidConfigException
	 */
	protected function setupBroker(): void
	{
		if ($this->setupBrokerDone) {
			return;
		}
		
		if ($this->maxPriority !== null) {
			throw new InvalidConfigException(__CLASS__ . '::maxPriority not used!');
		}
		
		$queue = $this->context->createQueue($this->queueName);
		$queue->addFlag(AmqpQueue::FLAG_DURABLE);
		$queue->setArguments($this->queueOptionalArguments);
		$this->context->declareQueue($queue);
		
		$topic = $this->context->createTopic($this->exchangeName);
		$topic->setType($this->exchangeType);
		$topic->addFlag(AmqpTopic::FLAG_DURABLE);
		$this->context->declareTopic($topic);
		
		$this->context->bind(new AmqpBind($queue, $topic, $this->routingKey));
		
		$this->setupBrokerDone = true;
	}
}