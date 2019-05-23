<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\log\DriverLogWriter;
use ilepilin\queue\QueuePayload;

class DummyQueue extends BaseDriver
{
  const DRIVER_CODE = 'dummy';

  /**
   * @var array
   */
  protected static $queues = [];

  /**
   * @var string
   */
  public $loggerClass;

  /**
   * @var DriverLogWriter
   */
  protected $logger;

  /**
   * @var array
   */
  protected $payloadMap = [];

  /**
   * @var int
   */
  public $maxAttemptCount = 10;

  /**
   * @inheritdoc
   */
  public static function getCode()
  {
    return self::DRIVER_CODE;
  }

  /**
   * @inheritdoc
   */
  public function init()
  {
    $this->logger = new DriverLogWriter([
      'loggerClass' => $this->loggerClass
    ]);
  }

  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool|int
   */
  protected function pushInternal($queueName, QueuePayload $payload)
  {
    if ($this->isMaxAttemptExceeded($payload->getAttempt())) {
      return false;
    }

    $message = $payload->encode();
    $this->logger->log('PUSH message :message', [':message' => $message]);

    if (!array_key_exists($queueName, self::$queues)) {
      self::$queues[$queueName] = [];
    }

    return array_push(self::$queues[$queueName], $message);
  }

  public function pop($queueName)
  {
    if (!isset($queueName, self::$queues)) {
      return null;
    }

    $message = array_shift(self::$queues[$queueName]);

    $this->logger->log('POP message :message', [':message' => $message]);

    return QueuePayload::decode($message, $this->payloadMap);
  }

  public function close()
  {
    return true;
  }

  /**
   * @param $queueName
   * @return bool
   */
  public function isEmpty($queueName)
  {
    if (!isset($queueName, self::$queues)) {
      return true;
    }

    return count(self::$queues[$queueName]) === 0;
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