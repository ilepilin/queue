<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\QueuePayload;

abstract class BaseDriver implements DriverInterface
{
  /**
   * @throws \InvalidArgumentException
   */
  public function __construct(array $params = [])
  {
    foreach ($params as $property => $value) {
      $this->{$property} = $value;
    }

    $this->init();
  }

  public function init()
  {
  }

  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  final public function push($queueName, QueuePayload $payload)
  {
    return $this->pushInternal($queueName, $payload);
  }

  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  abstract protected function pushInternal($queueName, QueuePayload $payload);
}