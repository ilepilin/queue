<?php

namespace ilepilin\queue;

interface WorkerInterface
{
  /**
   * @return string
   */
  public static function channelName();

  /**
   * @param PayloadInterface $payload
   * @return bool
   */
  public function work(PayloadInterface $payload);
}