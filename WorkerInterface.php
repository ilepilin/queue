<?php

namespace ilepilin\queue;

interface WorkerInterface
{
  /**
   * @param PayloadInterface $payload
   * @return bool
   */
  public function work(PayloadInterface $payload);

  /**
   * @return string
   */
  public function getChannelName();
}