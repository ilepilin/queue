<?php

namespace ilepilin\queue;

class QueuePayload
{
  /**
   * @var string
   */
  public $class;

  /**
   * @var BasePayload
   */
  public $data;

  /**
   * @var int
   */
  public $delay = 0;

  /**
   * @var int
   */
  private $attempt;

  /**
   * @var int
   */
  private $createdAt;

  /**
   * @param PayloadInterface $payload
   * @param int $delay
   * @return QueuePayload
   */
  public static function createInstance(PayloadInterface $payload, $delay = 0)
  {
    return new static([
      'class' => get_class($payload),
      'data' => $payload,
      'delay' => $delay,
      'attempt' => 0,
      'createdAt' => time(),
    ]);
  }

  /**
   * @param string $data
   * @param array $map необходимо, если payload используется в другом приложении и имеет другие неймспейсы
   * @return QueuePayload
   */
  public static function decode($data, $map = [])
  {
    $data = json_decode($data, true);

    $instance = new static([
      'delay' => $data['delay'],
      'attempt' => $data['attempt'],
      'createdAt' => $data['createdAt'],
      'class' => $data['class'],
    ]);

    /** @var BasePayload $class */
    $class = $map[$instance->class] ?? $instance->class;
    if (!empty($map[$instance->class])) {
      $class = $map[$instance->class];
    }

    $instance->data = $class::createInstance($data['data'] ?? []);

    return $instance;
  }

  /**
   * QueuePayload constructor.
   * @param array $params
   */
  public function __construct($params = [])
  {
    foreach ($params as $property => $value) {
      $this->{$property} = $value;
    }
  }

  /**
   * @return string
   */
  public function __toString()
  {
    return $this->encode();
  }

  /**
   * @return string
   */
  public function encode()
  {
    return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
  }

  /**
   * @return array
   */
  public function toArray()
  {
    return [
      'class' => $this->class,
      'data' => $this->data->encode(),
      'delay' => $this->delay,
      'attempt' => $this->attempt,
      'createdAt' => $this->createdAt,
    ];
  }

  /**
   * @return $this
   */
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
}