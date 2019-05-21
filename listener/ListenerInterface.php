<?php

namespace ilepilin\queue\listener;

interface ListenerInterface
{
  public function handle($driverCode);
}