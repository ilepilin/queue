<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\log\DriverLogWriter;
use ilepilin\queue\QueuePayload;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ extends BaseDriver
{
  const DRIVER_CODE = 'amqp';

  const DELIVERY_MODE_DURABLE = 2;
  const DELAYED_EXCHANGE_NAME = 'delayed_exchange';

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

  /**
   * @inheritdoc
   */
  public static function getCode()
  {
    return self::DRIVER_CODE;
  }

  public function init()
  {
    $this->logger = new DriverLogWriter([
      'loggerClass' => $this->loggerClass
    ]);

    $this->connect();
  }

  /**
   * @param $queueName
   * @return QueuePayload
   */
  public function pop($queueName)
  {
    if (!$this->channel) {
      return null;
    }

    // подключаемся к очереди
    $this->declareChannel($queueName);

    // получаем сообщение
    $message = $this->channel->basic_get($queueName);
    if ($message === null) {
      return null;
    }

    // подтверждаем получение сообщения
    $this->channel->basic_ack($message->delivery_info['delivery_tag']);
    $this->logger->log('POP message :message', [':message' => $message->body]);

    return QueuePayload::decode($message->body, $this->payloadMap);
  }

  /**
   * @return bool
   */
  public function close()
  {
    if (!$this->channel) {
      return false;
    }

    try {
      $this->channel->close();
      $this->connection->close();
    } catch (\Exception $e) {
      $this->logger->log('Close error: :error', [':error' => $e->getMessage()]);

      return false;
    }

    return true;
  }

  /**
   * @param $queueName
   * @return bool
   */
  public function isEmpty($queueName)
  {
    if (!$this->channel) {
      return false;
    }

    // подключаемся к очереди
    $this->declareChannel($queueName);

    return !$this->channel->basic_get($queueName, true);
  }

  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  protected function pushInternal($queueName, QueuePayload $payload)
  {
    if ($this->isMaxAttemptExceeded($payload->getAttempt())) {
      return false;
    }

    if (!$this->channel) {
      return false;
    }

    try {
      // подключаемся к очереди
      $this->declareChannel($queueName);

      $encodedMessage = $payload->encode();

      $this->logger->log('PUSH message ' . $encodedMessage . ' delay:' . $payload->delay);

      // билдим сообщение для очереди
      $amqpMessage = new AMQPMessage($encodedMessage, [
        'delivery_mode' => self::DELIVERY_MODE_DURABLE,
        'priority' => $payload->data->priority > $this->xMaxPriority
          ? $this->xMaxPriority
          : $payload->data->priority,
      ]);

      if ($payload->delay) {
        // подключаемся к точке доступа с режимом отложенных сообщений
        $this->declareExchange(self::DELAYED_EXCHANGE_NAME);

        // указываем время задержки
        $headers = new AMQPTable(['x-delay' => $payload->delay * 1000]);
        $amqpMessage->set('application_headers', $headers);

        // привязываем очередь к точке доступа
        $this->channel->queue_bind($queueName, self::DELAYED_EXCHANGE_NAME, $queueName);
        // публикуем сообщение с указанием привязанной точки доступа
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
      $this->logger->log('Connection error: :error', [':error' => $e->getMessage()]);

      return false;
    }
    return true;
  }

  /**
   * Подключаемся к очереди, если такой ещё нет - создаем
   * @param string $queueName название очереди
   * @return mixed|null
   */
  private function declareChannel($queueName)
  {
    return $this->channel->queue_declare(
      $queueName, // название очереди
      false, // passive
      true, // durable
      false, // exclusive
      false, // auto_delete
      false, // nowait
      ['x-max-priority' => ['I', $this->xMaxPriority]] // arguments
    );
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
    return $this->channel->exchange_declare(
      $exchangeName, // название точки доступа
      'x-delayed-message', // type
      false, // passive
      true, // durable
      false, // auto_delete
      false, // internal
      false, // nowait
      new AMQPTable([
        "x-delayed-type" => "direct"
      ]) // arguments
    );
  }

  /**
   * @param $attempts
   * @return bool
   */
  private function isMaxAttemptExceeded($attempts)
  {
    return $attempts > $this->maxAttemptCount;
  }
}