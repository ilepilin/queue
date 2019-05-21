<?php

namespace ilepilin\queue;

interface PayloadInterface
{
  /**
   * @return string
   */
  public function encode();

  /**
   * @param $data
   * @return mixed
   */
  public static function decode($data);
}