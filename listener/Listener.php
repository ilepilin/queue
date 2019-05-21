<?php

namespace ilepilin\queue\listener;

use ilepilin\queue\QueuePayload;
use ilepilin\queue\WorkerInterface;
use ilepilin\queue\QueueFacade;

class Listener implements ListenInterface
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
    $this->worker = $worker;
    $this->facade = $facade;
    $this->chanelName = $worker->getChannelName();
  }

  /**
   * Handle queue message messages
   *
   * Пытаемся разобрать очередь переданным драйвером
   * Если не получается, пушим заново с помощью фасада (пушится всеми драйверами по очереди, пока не запушится)
   *
   * @param string $driverCode
   * @return bool|null Return null, if no message in queue
   * @throws \Exception
   */
  public function handle($driverCode)
  {
    $driver = $this->facade->getDriverByCode($driverCode);

    /** @var QueuePayload $payload */
    $payload = $driver->pop($this->chanelName);
    if ($payload === null) return null;
    if (!$workResult = $this->worker->work($payload->payloadData)) {
      $payload->incrementAttempt();
      $payload->delay = 0; // При повторном выполнении откладывание задачи не нужно
      $this->facade->push($this->chanelName, $payload);
    }
    return $workResult;
  }
}