<?php

namespace ilepilin\queue\driver;

use InvalidArgumentException;
use ilepilin\queue\QueuePayload;

abstract class AbstractDriver implements DriverInterface
{

  /**
   * @throws \InvalidArgumentException
   */
  function __construct(array $params = [])
  {
    if ($params) foreach ($params as $property => $value) {
      $this->{$property} = $value;
    }

    $this->init();
  }

  abstract protected function init();

  /**
   * @inheritdoc
   * TRICKY Если задачу не удалось добавить в рабит, но удалось добавить в резервную очередь в БД, то метод вернет true,
   * так как задача не утеряна и будет выполнена в будущем
   */
  final public function push($queueName, QueuePayload $payload)
  {
    return $this->pushInternal($queueName, $payload);
  }

  /**
   * Добавить задачу в очередь
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  abstract protected function pushInternal($queueName, QueuePayload $payload);
}