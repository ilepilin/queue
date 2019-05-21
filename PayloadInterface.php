<?php

namespace ilepilin\queue;

interface PayloadInterface
{
  /**
   * @param array|string $data
   * @return PayloadInterface
   */
  public static function createInstance($data);

  /**
   * @return string
   */
  public function encode();
}