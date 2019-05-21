<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\QueuePayload;

interface DriverInterface
{

  /**
   * Уникальный код драйвера
   * @return string
   */
  public static function getCode();

  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  public function push($queueName, QueuePayload $payload);

  /**
   * @param $queueName
   */
  public function pop($queueName);

  /**
   * @return bool
   */
  public function close();

  /**
   * @param $queueName
   * @return bool
   */
  public function isEmpty($queueName);
}