<?php

namespace ilepilin\queue;

class QueuePayload
{
  private $createdAt;
  private $attempt;
  /** @var  BasePayload */
  public $payloadData;
  /** @var int Время откладывания задачи */
  public $delay = 0;

  public static function createPayload(PayloadInterface $payload, $delay)
  {
    $instance = new self();
    $instance->attempt = 0;
    $instance->createdAt = time();
    $instance->payloadData = $payload;
    $instance->delay = $delay;
    return $instance;
  }

  /**
   * @return string
   */
  public function encode()
  {
    return json_encode([
      'createdAt' => $this->createdAt,
      'attempt' => $this->attempt,
      'delay' => $this->delay,
      'payload' => $this->payloadData->encode(),
      'payloadClass' => get_class($this->payloadData),
    ], JSON_UNESCAPED_UNICODE);
  }

  private static function createPayloadDataObject($payloadClassName, $payloadData)
  {
    $payloadData = json_decode($payloadData, true);
    if (!$payloadData || !$payloadClassName) return null;

    /** @var PayloadInterface $payloadObject */
    return new $payloadClassName($payloadData);
  }

  public static function decode($data, $map = [])
  {
    $data = json_decode($data, true);
    $instance = new self();
    $instance->attempt = static::getValue($data, 'attempt');
    $instance->createdAt = static::getValue($data, 'createdAt');
    $instance->delay = static::getValue($data, 'delay');

    $payloadClass = static::getValue($data, 'payloadClass');
    if (!empty($map[$payloadClass])) {
      $payloadClass = $map[$payloadClass];
    }

    $instance->payloadData = static::createPayloadDataObject(
      $payloadClass,
      static::getValue($data, 'payload')
    );
    return $instance;
  }

  public function incrementAttempt()
  {
    $this->attempt++;
    return $this;
  }

  /**
   * @return int
   */
  public function getAttempt()
  {
    return $this->attempt;
  }

  public function __toString()
  {
    return $this->encode();
  }

  public static function getValue(array $array, $key, $default = null)
  {
    return array_key_exists($key, $array) ? $array[$key] : $default;
  }
}