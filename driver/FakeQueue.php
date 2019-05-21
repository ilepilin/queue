<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\log\DriverLogWriter;
use ilepilin\queue\QueuePayload;

class FakeQueue extends AbstractDriver
{
  const DRIVER_CODE = 'fake';
  /**
   * @var DriverLogWriter
   */
  protected $logger;

  /**
   * @var string
   */
  public $loggerClass;

  /**
   * @var array
   */
  protected $payloadMap = [];

  protected static $queues = [];

  public $maxAttemptCount = 10;

  public function init()
  {
    $this->logger = new DriverLogWriter([
      'loggerClass' => $this->loggerClass
    ]);
  }

  private function isMaxAttemptExceeded($attempts)
  {
    return $attempts > $this->maxAttemptCount;
  }

  protected function pushInternal($queueName, QueuePayload $payload)
  {
    if ($this->isMaxAttemptExceeded($payload->getAttempt())) return false;
    $encodedMessage = $payload->encode();
    $this->logger->log('PUSH message' . $encodedMessage);
    if (!array_key_exists($queueName, self::$queues)) {
      self::$queues[$queueName] = [];
    }
    return array_push(self::$queues[$queueName], $encodedMessage);
  }

  public function pop($queueName)
  {
    if (!array_key_exists($queueName, self::$queues)) return null;
    $message = array_shift(self::$queues[$queueName]);
    if ($message === null) return null;
    $this->logger->log('POP message :message', [':message' => $message]);
    return QueuePayload::decode($message, $this->payloadMap);
  }

  public function close()
  {
    return true;
  }

  public function isEmpty($queueName)
  {
    if (!array_key_exists($queueName, self::$queues)) {
      return true;
    }
    return count(self::$queues[$queueName]) == 0;
  }

  /**
   * @inheritdoc
   */
  public function getDriverCode()
  {
    return self::DRIVER_CODE;
  }
}