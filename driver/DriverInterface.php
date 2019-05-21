<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\QueuePayload;

interface DriverInterface
{
  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  public function push($queueName, QueuePayload $payload);

  public function pop($queueName);

  public function close();

  public function isEmpty($queueName);

  /**
   * Уникальный код драйвера
   * @return string
   */
  public function getDriverCode();
}