<?php

namespace ilepilin\queue;

use Exception;
use ReflectionClass;
use ilepilin\queue\driver\DriverInterface;

/**
 * Фасад для работы с очередями
 * @package ilepilin\queue
 */
class QueueFacade
{
  /**
   * @var DriverInterface[]
   * @see setDriver()
   * @see getDriver()
   */
  private $drivers;

  /**
   * QueueFacade constructor.
   * @param array $params
   * @throws Exception
   */
  public function __construct($params = [])
  {
    if ($params) foreach ($params as $property => $value) {
      /** Если существует сеттер, то сетим через него */
      $setter = 'set' . $property;
      if (method_exists($this, $setter)) {
        $this->$setter($value);
      } else {
        $this->{$property} = $value;
      }
    }
  }

  /**
   * @param $name
   * @return mixed
   * @throws Exception
   */
  public function __get($name)
  {
    $getter = 'get' . $name;
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
    $setter = 'set' . $name;
    if (method_exists($this, $setter)) {
      $this->$setter($value);
    } elseif (method_exists($this, 'get' . $name)) {
      throw new Exception('Setting read-only property: ' . get_class($this) . '::' . $name);
    } else {
      throw new Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
    }
  }

  /**
   * @param $driver
   * @throws Exception
   */
  private function setDriver($driver)
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

      return $this->drivers[$driverObj->getDriverCode()] = $driverObj;
    }

    // Если передали экземлпяр класса драйвера
    if (!(new ReflectionClass($driver))->implementsInterface('\ilepilin\queue\driver\DriverInterface')) {
      throw new Exception('The driver class must implement \ilepilin\queue\driver\DriverInterface.');
    }

    return $this->drivers[$driver->getDriverCode()] = $driver;
  }

  /**
   * @param $code
   * @return DriverInterface
   */
  public function getDriverByCode($code)
  {
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
    foreach($drivers as $driver) {
      $this->setDriver($driver);
    }
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
    if (!$reflection->implementsInterface('\ilepilin\queue\driver\DriverInterface')) {
      throw new Exception('The driver class must implement \ilepilin\queue\driver\DriverInterface.');
    }

    if (!empty($config['logger'])) {
      $config['logger'] = $this->buildLogger($config['logger']);
    }

    return $reflection->newInstance($config);
  }

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

    return $reflection->newInstance($logger);
  }

  /**
   * @param string $chanelName
   * @param BasePayload $payload
   * @param integer $delay
   * @return bool
   * @throws \InvalidArgumentException
   */
  public function push($chanelName, BasePayload $payload, $delay = 0)
  {
    // Перебираем драйверы до тех пор, пока не найдем рабочий
    foreach ($this->drivers as $driver) {
      $isPushed = $driver->push(
        $chanelName,
        QueuePayload::createPayload($payload, $delay)
      );

      if ($isPushed) {
        return true;
      }
    }

    return false;
  }
}