<?php

namespace ilepilin\queue\listener;

interface ListenInterface
{
  public function handle($driverCode);
}