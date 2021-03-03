<?php

namespace ilepilin\queue\listener;

use ilepilin\queue\QueuePayload;
use ilepilin\queue\QueueFacade;
use ilepilin\queue\WorkerInterface;

class Listener implements ListenerInterface
{
  /**
   * @var QueueFacade
   */
  private $facade;

  /**
   * @var WorkerInterface
   */
  private $worker;

  /**
   * @var string
   */
  private $chanelName;

  /**
   * Listener constructor.
   * @param QueueFacade $facade
   * @param WorkerInterface $worker
   */
  public function __construct(QueueFacade $facade, WorkerInterface $worker)
  {
    $this->facade = $facade;
    $this->worker = $worker;
    $this->chanelName = $worker::channelName();
  }

  /**
   * Слушает указанную очередь и, при наличии сообщений в ней, запускает их в обработку
   *
   * @param null|string $driverCode
   * @return bool|null
   */
  public function handle($driverCode = null)
  {
    $driver = $this->facade->getDriver($driverCode);

    /** @var QueuePayload $payload */
    $payload = $driver->pop($this->chanelName);

    if ($payload === null) {
      return null;
    }

    $result = $this->worker->work($payload->data);

    if (!$result) {
      $payload->incrementAttempt();
      $payload->delay = 0; // При повторном выполнении откладывание задачи не нужно

      $driver->push($this->chanelName, $payload);
    }

    return $result;
  }
}