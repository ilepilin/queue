<?php

namespace ilepilin\queue\log;

class DriverLogWriter
{
  /**
   * @var string
   */
  public $logPrefix;

  /**
   * @var string
   */
  public $loggerClass;

  /**
   * @var bool
   */
  protected $showLog = false;

  /**
   * DriverLogWriter constructor.
   * @param array $params
   */
  function __construct(array $params = [])
  {
    foreach ($params as $property => $value) {
      $this->{$property} = $value;
    }

    $this->init();
  }

  public function init()
  {
    if (
      defined('QUEUE_DEBUG')
      && constant('QUEUE_DEBUG') === true
      || function_exists('codecept_debug')
    ) {
      $this->showLog = true;
    }
  }

  /**
   * @param mixed $message
   * @param array $replacements
   * @return bool
   */
  public function log($message, $replacements = [])
  {
    if (!$message) {
      return false;
    }

    $message = sprintf(
      '%s [%s] %s' . PHP_EOL,
      date('H:i:s'),
      $this->logPrefix,
      (is_array($message) || is_object($message)) ? print_r($message, true) : strtr($message, $replacements)
    );

    if ($class = $this->loggerClass) {
      $this->externalLog($class, $message);
    }

    if (!$this->showLog) {
      return true;
    }

    if (function_exists('codecept_debug')) {
      codecept_debug($message);

      return true;
    }

    echo $message;

    return true;
  }

  /**
   * @param string $class
   * @param string $message
   * @return bool
   */
  private function externalLog($class, $message)
  {
    $reflection = new \ReflectionClass($class);
    if (!$reflection->hasMethod('log') || !$reflection->getMethod('log')->isPublic()) {
      return false;
    }

    $level = $reflection->getConstant('LEVEL_INFO') ?: null;

    if ($reflection->getMethod('log')->isStatic()) {
      $class::log($message, $level);

      return true;
    }

    $reflection->newInstance()->log($message, $level);

    return true;
  }
}