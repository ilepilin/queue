<?php

namespace ilepilin\queue\log;

class DriverLogWriter
{
  public $logPrefix;
  public $loggerClass;

  protected $showLog;

  function __construct(array $params = [])
  {
    if ($params) foreach ($params as $property => $value) {
      $this->{$property} = $value;
    }

    $this->init();
  }

  public function init()
  {
    $this->showLog = defined('QUEUE_DEBUG') && constant('QUEUE_DEBUG') == true || function_exists('codecept_debug');
  }

  /**
   * @param mixed $message
   * @param array $replacements
   * @return bool|null
   */
  public function log($message, $replacements = [])
  {
    if (!$message) {
      return null;
    }
    $message = sprintf(
      '%s [%s] %s' . PHP_EOL,
      date('H:i:s'),
      $this->logPrefix,
      is_array($message) || is_object($message) ? print_r($message, true) : strtr($message, $replacements)
    );
    if (!$this->showLog && $class = $this->loggerClass) {
      $reflection = new \ReflectionClass($class);
      if (!$reflection->hasMethod('log') || !$reflection->getMethod('log')->isPublic()) {
        return;
      }
      if ($reflection->getMethod('log')->isStatic()) {
        $class::log($message, $class::LEVEL_INFO);
      } else {
        $reflection->newInstance()->log($message, $class::LEVEL_INFO);
      }

      return;
    }
    if (function_exists('codecept_debug')) {
      codecept_debug($message);
    } else {
      echo $message;
    }
  }
}