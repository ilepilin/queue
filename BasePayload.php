<?php

namespace ilepilin\queue;

abstract class BasePayload implements PayloadInterface
{
  /**
   * @var int
   */
  public $priority = 0;

  /**
   * @param $data
   * @return $this
   */
  public static function createInstance($data)
  {
    $class = get_called_class();

    if (is_string($data)) {
      $data = json_decode($data, true);
    }

    return new $class($data);
  }

  /**
   * BasePayload constructor.
   * @param array $params
   */
  function __construct(array $params = [])
  {
    if ($params) foreach ($params as $property => $value) {
      $this->{$property} = $value;
    }

    $this->init();
  }

  public function init()
  {
  }

  /**
   * @return string
   */
  public function encode()
  {
    return json_encode(get_object_vars($this), JSON_UNESCAPED_UNICODE);
  }
}