<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\log\DriverLogWriter;
use ilepilin\queue\QueuePayload;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ extends AbstractDriver
{
  const DRIVER_CODE = 'rabbit_mq';

  public $host;
  public $port;
  public $user;
  public $password;
  public $vhost = '/';
  public $insist = false;
  public $loginMethod = 'AMQPLAIN';
  public $loginResponse = null;
  public $locale = 'en_US';
  public $connectionTimeout = 3;
  public $readWriteTimeout = 3;
  public $context = null;
  public $keepalive = false;
  public $heartbeat = 0;
  public $xMaxPriority = 5;
  public $maxAttemptCount = 10;

  const DELIVERY_MODE_DURABLE = 2;
  const DELAYED_EXCHANGE_NAME = 'delayed_exchange';

  /**
   * @var string
   */
  public $loggerClass;

  /**
   * @var AMQPStreamConnection
   */
  protected $connection;

  /**
   * @var AMQPTable
   */
  protected $table;

  /**
   * @var AMQPChannel|null если rabbitmq не запущен будет null
   */
  protected $channel;

  /**
   * @var DriverLogWriter
   */
  protected $logger;

  /**
   * @var array
   */
  protected $payloadMap = [];

  public function init()
  {
    $this->logger = new DriverLogWriter([
      'loggerClass' => $this->loggerClass
    ]);
    $this->connect();
  }

  private function isMaxAttemptExceeded($attempts)
  {
    return $attempts > $this->maxAttemptCount;
  }

  protected function pushInternal($queueName, QueuePayload $payload)
  {
    if ($this->isMaxAttemptExceeded($payload->getAttempt())) return false;
    try {
      if (!$this->channel) return false;
      $this->declareChannel($queueName);
      $encodedMessage = $payload->encode();
      $this->logger->log('PUSH message ' . $encodedMessage . ' delay:' . $payload->delay);
      $amqpMessage = new AMQPMessage($encodedMessage, [
        'delivery_mode' => self::DELIVERY_MODE_DURABLE,
        'priority' => $payload->payloadData->priority > $this->xMaxPriority
          ? $this->xMaxPriority
          : $payload->payloadData->priority,
      ]);

      if ($payload->delay) {
        $this->declareExchange(self::DELAYED_EXCHANGE_NAME);
        $headers = new AMQPTable(['x-delay' => $payload->delay * 1000]);
        $amqpMessage->set('application_headers', $headers);
        $this->channel->queue_bind($queueName, self::DELAYED_EXCHANGE_NAME, $queueName);
        $this->channel->basic_publish($amqpMessage, self::DELAYED_EXCHANGE_NAME, $queueName);
      } else {
        $this->channel->basic_publish($amqpMessage, '', $queueName);
      }

    } catch (\Exception $e) {
      $this->logger->log('Push error: :error', [':error' => $e->getMessage()]);
      return false;
    }
    return true;
  }

  public function pop($queueName)
  {
    if (!$this->channel) return false;
    $this->declareChannel($queueName);
    $message = $this->channel->basic_get($queueName);
    if ($message === null) return null;
    $this->channel->basic_ack($message->delivery_info['delivery_tag']);
    $this->logger->log('POP message :message', [':message' => $message->body]);
    return QueuePayload::decode($message->body, $this->payloadMap);
  }

  public function close()
  {
    try {
      if (!$this->channel) return false;
      $this->channel->close();
      $this->connection->close();
    } catch (\Exception $e) {
      $this->logger->log('Close error: :error', [':error' => $e->getMessage()]);
      return false;
    }
    return true;
  }

  public function isEmpty($queueName)
  {
    if (!$this->channel) return false;
    $this->declareChannel($queueName);
    return !$this->channel->basic_get($queueName, true);
  }

  /**
   * @inheritdoc
   */
  public function getDriverCode()
  {
    return self::DRIVER_CODE;
  }

  private function connect()
  {
    try {
      $this->connection = new AMQPStreamConnection(
        $this->host,
        $this->port,
        $this->user,
        $this->password,
        $this->vhost,
        $this->insist,
        $this->loginMethod,
        $this->loginResponse,
        $this->locale,
        $this->connectionTimeout,
        $this->readWriteTimeout,
        $this->context,
        $this->keepalive,
        $this->heartbeat
      );
      $this->channel = $this->connection->channel();
    } catch (\Exception $e) {
      $this->logger->log('Connect error: :error', [':error' => $e->getMessage()]);
      return false;
    }
    return true;
  }

  /**
   * @param $queueName
   * @return mixed|null
   */
  private function declareChannel($queueName)
  {
    if (!$this->channel) return false;
    return $this->channel->queue_declare($queueName, false, true, false, false, false, [
      'x-max-priority' => ['I', $this->xMaxPriority]
    ]);
  }

  /**
   * Создаем точку доступа с типом x-delayed-message с экста заголовком x-delayed-type в котором указываем тип работы
   * точки доступа direct. Точка доступа используется для отложенных сообщений с использованием плагина
   * rabbitmq_delayed_message_exchange https://github.com/rabbitmq/rabbitmq-delayed-message-exchange
   * @param $exchangeName string название точки доступа
   * @return mixed|null
   */
  private function declareExchange($exchangeName)
  {
    return $this->channel->exchange_declare($exchangeName, 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
      "x-delayed-type" => "direct"
    )));
  }
}