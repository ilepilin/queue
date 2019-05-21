<?php

namespace ilepilin\queue;

use Exception;
use ReflectionClass;
use ilepilin\queue\driver\DriverInterface;

/**
 * Фасад для работы с драйверами очередей
 * @package ilepilin\queue
 *
 * @property DriverInterface $driver
 */
class QueueFacade
{
  /**
   * @var DriverInterface[]
   */
  private $drivers = [];

  /**
   * QueueFacade constructor.
   * @param array $params
   * @throws Exception
   */
  public function __construct($params = [])
  {
    foreach ($params as $property => $value) {
      /** Если существует сеттер, то сетим через него */
      $setter = 'set' . ucfirst($property);

      if (method_exists($this, $setter)) {
        $this->$setter($value);
        continue;
      }

      $this->{$property} = $value;
    }
  }

  /**
   * @param $name
   * @return mixed
   * @throws Exception
   */
  public function __get($name)
  {
    $getter = 'get' . ucfirst($name);

    if (method_exists($this, $getter)) {
      return $this->$getter();
    } elseif (method_exists($this, 'set' . $name)) {
      throw new Exception('Getting write-only property: ' . get_class($this) . '::' . $name);
    } else {
      throw new Exception('Getting unknown property: ' . get_class($this) . '::' . $name);
    }
  }

  /**
   * @param $name
   * @param $value
   * @throws Exception
   */
  public function __set($name, $value)
  {
    $setter = 'set' . ucfirst($name);

    if (method_exists($this, $setter)) {
      $this->$setter($value);
    } elseif (method_exists($this, 'get' . $name)) {
      throw new Exception('Setting read-only property: ' . get_class($this) . '::' . $name);
    } else {
      throw new Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
    }
  }

  /**
   * @param string $code
   * @return DriverInterface
   * @throws Exception
   */
  public function getDriver($code = null)
  {
    if (!$code) {
      return reset($this->drivers);
    }

    if (!isset($this->drivers[$code])) {
      throw new Exception("Driver '$code' is not exists");
    }

    return $this->drivers[$code];
  }

  /**
   * @param array $drivers
   * @throws Exception
   */
  public function setDrivers(array $drivers)
  {
    foreach ($drivers as $driver) {
      $this->setDriver($driver);
    }
  }

  /**
   * @param array|DriverInterface $driver
   * @return DriverInterface
   * @throws Exception
   */
  public function setDriver($driver)
  {
    // Если передали конфиг для драйвера
    if (is_array($driver)) {
      if (empty($driver['class'])) {
        throw new Exception('Driver configuration must be an array containing a "class" element.');
      }

      $class = $driver['class'];
      unset($driver['class']);

      /** @var DriverInterface $driverObj */
      $driverObj = $this->buildDriver($class, $driver);

      return $this->drivers[$driverObj->getCode()] = $driverObj;
    }

    // Если передали экземлпяр класса драйвера
    if (!($driver instanceof DriverInterface)) {
      throw new Exception('The driver class must implement ' . DriverInterface::class);
    }

    return $this->drivers[$driver->getCode()] = $driver;
  }

  /**
   * @param $class
   * @param $config
   * @return DriverInterface|object
   * @throws Exception
   */
  private function buildDriver($class, $config)
  {
    $reflection = new ReflectionClass($class);
    if (!$reflection->implementsInterface(DriverInterface::class)) {
      throw new Exception('The driver class must implement ' . DriverInterface::class);
    }

    if (!empty($config['logger'])) {
      $logger = $this->buildLogger($config['logger']);
      $logger && $config['logger'] = $logger;
    }

    return $reflection->newInstance($config);
  }

  /**
   * @param $logger
   * @return object
   * @throws Exception
   */
  private function buildLogger($logger)
  {
    if (!is_array($logger)) {
      return $logger;
    }

    if (empty($logger['class'])) {
      throw new Exception('Logger configuration must be an array containing a "class" element.');
    }

    $class = $logger['class'];
    unset($logger['class']);

    $reflection = new ReflectionClass($class);
    if (!$reflection->hasMethod('log')) {
      throw new Exception('Logger class must have a "log" method.');
    }

    return $reflection->newInstance($logger);
  }

  /**
   * @param string $chanelName
   * @param BasePayload $payload
   * @param integer $delay
   * @return bool
   */
  public function push($chanelName, BasePayload $payload, $delay = 0)
  {
    // Пытаемся отправить в очередь через все драйверы по порядку
    foreach ($this->drivers as $driver) {
      $success = $driver->push(
        $chanelName,
        QueuePayload::createInstance($payload, $delay)
      );

      if ($success) {
        return true;
      }
    }

    return false;
  }
}