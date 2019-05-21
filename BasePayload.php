<?php

namespace ilepilin\queue;

class BasePayload implements PayloadInterface
{
  /** @var  integer */

  public $priority = 0;

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

  /**
   * @param $data
   * @return $this
   */
  public static function decode($data)
  {
    $class = get_called_class();
    return new $class(json_decode($data, true));
  }
}