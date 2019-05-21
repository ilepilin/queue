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
   * @param string $driverCode
   * @return bool
   */
  public function handle($driverCode)
  {
    $driver = $this->facade->getDriver($driverCode);

    /** @var QueuePayload $payload */
    $payload = $driver->pop($this->chanelName);

    if ($payload === null) {
      return false;
    }

    $result = $this->worker->work($payload->data);

    if (!$result) {
      $payload->incrementAttempt();
      $payload->delay = 0; // При повторном выполнении откладывание задачи не нужно

      // Пушим заново с помощью фасада, тк там могут быть резервные драйверы
      $this->facade->push($this->chanelName, $payload->data);
    }

    return $result;
  }
}