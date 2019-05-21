<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\log\DriverLogWriter;
use ilepilin\queue\QueuePayload;
use PDO;
use Exception;

class MySQL extends AbstractDriver
{
  const DRIVER_CODE = 'mysql';

  /**
   * @var string
   */
  public $host;
  /**
   * @var string
   */
  public $username;
  /**
   * @var string
   */
  public $password;
  /**
   * @var string
   */
  public $dbname;
  /**
   * @var string
   */
  public $loggerClass;
  /**
   * @var bool
   */
  public $isTableCreated;
  /**
   * @var string
   */
  public $code;
  /**
   * @var array
   */
  protected $payloadMap = [];
  /**
   * @var DriverLogWriter
   */
  private $logger;
  /**
   * @var PDO
   */
  private $db;

  /**
   * Название таблицы в БД
   * @return string
   */
  public static function tableName()
  {
    return '{{%queue_reserve}}';
  }

  public function init()
  {
    $this->logger = new DriverLogWriter([
      'loggerClass' => $this->loggerClass
    ]);

    $this->prepareTable();
  }

  protected function pushInternal($queueName, QueuePayload $payload)
  {
    $tableName = static::tableName();
    $query = $this->getPdo()->prepare("INSERT INTO $tableName 
(rabbit_code, channel_name, payload) 
VALUES 
(:rabbitCode, :channelName, :payload)");

    return $query->execute([
      ':rabbitCode' => $this->code,
      ':channelName' => $queueName,
      ':payload' => $payload->encode(),
    ]);
  }

  public function pop($queueName)
  {
    $tableName = static::tableName();
    $stmt = $this->getPdo()->prepare("SELECT id, payload FROM $tableName WHERE rabbit_code = :rabbitCode and channel_name = :channelName LIMIT 1");
    $stmt->bindValue(':rabbitCode', $this->code);
    $stmt->bindValue(':channelName', $queueName);
    $stmt->execute();
    $queuqReserve = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$queuqReserve) {
      return false;
    }

    try {
      $message = json_decode($queuqReserve['payload'], true);
      $id = $queuqReserve['id'];
    } catch (Exception $e) {
      return false;
    }
    $this->delete($id);

    return QueuePayload::decode($message, $this->payloadMap);
  }

  public function close()
  {

  }

  public function isEmpty($queueName)
  {
    $stmt = $this->getPdo()->prepare("SELECT id FROM $tableName WHERE rabbit_code = :rabbitCode and channel_name = :channelName LIMIT 1");
    $stmt->bindValue(':rabbitCode', $this->code);
    $stmt->bindValue(':channelName', $queueName);
    $stmt->execute();
    $queuqReserve = $stmt->fetchColumn();

    return !$queuqReserve;
  }

  /**
   * Восстановить данные из БД в очередь через другой драйвер
   * @param AbstractDriver $driver
   * @throws Exception
   */
  public function recoverAll(AbstractDriver $driver)
  {
    $tableName = static::tableName();
    $tasksQuery = $this->getPdo()->prepare("SELECT * FROM $tableName WHERE rabbit_code = :rabbitCode");
    $tasksQuery->execute([':rabbitCode' => $this->code]);

    $reserveIds = [];
    while ($reserve = $tasksQuery->fetch()) {
      $result = $driver->push($reserve['channel_name'], QueuePayload::decode($reserve['payload']));
      $result && $this->delete($reserve['id']);
    }
  }

  /**
   * @inheritdoc
   */
  public function getDriverCode()
  {
    return self::DRIVER_CODE;
  }

  /**
   * Удалить задачу из резервной очереди
   * @param int $id ID резервной задачи
   */
  private function delete($id)
  {
    $tableName = static::tableName();
    $id = (int)$id;

    $this->getPdo()->exec("DELETE FROM $tableName WHERE id = $id");
  }

  /**
   * @return PDO
   * @throws Exception
   */
  private function getPdo()
  {
    if ($this->db) {
      return $this->db;
    }
    try {
      $this->db = new \PDO('mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8', $this->username, $this->password);
      $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    } catch (\PDOException $e) {
      throw new Exception('DB Connect Exception');
    }

    return $this->db;
  }

  /**
   * Создание таблицы для резервирования задач, если это нужно
   */
  private function prepareTable()
  {
    $tableName = static::tableName();
    if ($this->isTableCreated || $this->getPdo()->query("SHOW TABLES LIKE '$tableName'")->rowCount()) {
      // Таблица существует
      return;
    }

    // Создание таблицы
    $this->getPdo()->exec("
      CREATE TABLE $tableName
      (
        id INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        rabbit_code VARCHAR(64) NOT NULL,
        channel_name VARCHAR(128) NOT NULL,
        payload TEXT
      ) CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB
");

    $this->isTableCreated = true;
  }
}