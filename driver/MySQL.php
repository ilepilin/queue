<?php

namespace ilepilin\queue\driver;

use ilepilin\queue\log\DriverLogWriter;
use ilepilin\queue\QueuePayload;
use PDO;
use Exception;

class MySQL extends BaseDriver
{
  const DRIVER_CODE = 'mysql';
  const TABLE_NAME = 'queue_reserve';

  public $host;
  public $port = 3306;
  public $username;
  public $password;
  public $dbname;

  /**
   * @var
   */
  public $tableName;

  /**
   * @var string
   */
  public $loggerClass;

  /**
   * @var bool
   */
  public $isTableCreated;

  /**
   * @var int
   */
  public $maxAttemptCount = 10;

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
   * @inheritdoc
   */
  public static function getCode()
  {
    return self::DRIVER_CODE;
  }

  /**
   * @inheritdoc
   */
  public function init()
  {
    $this->logger = new DriverLogWriter([
      'loggerClass' => $this->loggerClass
    ]);

    $this->prepareTable();
  }

  public function pop($queueName)
  {
    $message = $this->fetch($queueName);

    if (!$message) {
      return false;
    }

    try {
      $data = json_decode($message['data'], true);
      $id = $message['id'];
    } catch (Exception $e) {
      $this->logger->log("Json decoding error: " . $e->getMessage());

      return false;
    }

    $this->logger->log('POP message :message', [':message' => $data]);

    $this->delete($id);

    return QueuePayload::decode($data, $this->payloadMap);
  }

  /**
   * Название таблицы в БД
   * @return string
   */
  public function getTableName()
  {
    return $this->tableName;
  }

  /**
   * @return bool
   */
  public function close()
  {
    // соединение закрывается при выгрузке объекта PDO из памяти
    $this->db = null;

    return true;
  }

  /**
   * @param $queueName
   * @return bool
   */
  public function isEmpty($queueName)
  {
    $message = $this->fetch($queueName);

    return !$message;
  }

  /**
   * @param string $queueName
   * @param QueuePayload $payload
   * @return bool
   */
  protected function pushInternal($queueName, QueuePayload $payload)
  {
    if ($this->isMaxAttemptExceeded($payload->getAttempt())) {
      return false;
    }

    $tableName = $this->getTableName();

    $sql = <<<SQL
      INSERT INTO $tableName 
      (channel_name, payload) 
      VALUES 
      (:channelName, :payload)
SQL;

    $query = $this->getPdo()->prepare($sql);

    $message = $payload->encode();

    $this->logger->log('PUSH message :message', [':message' => $message]);

    return $query->execute([
      ':channelName' => $queueName,
      ':payload' => $message,
    ]);
  }

  /**
   * @param $queueName
   * @return mixed
   */
  private function fetch($queueName)
  {
    $tableName = $this->getTableName();

    $sql = <<<SQL
      SELECT `id`, `message`
      FROM $tableName
      WHERE `channel_name` = :channelName
      ORDER BY id
      LIMIT 1;
SQL;

    $statement = $this->getPdo()->prepare($sql);

    $statement->bindValue(':channelName', $queueName);
    $statement->execute();

    return $statement->fetch(PDO::FETCH_ASSOC);
  }

  /**
   * Удалить задачу из резервной очереди
   * @param int $id ID резервной задачи
   */
  private function delete($id)
  {
    $tableName = $this->getTableName();
    $id = (int)$id;

    $this->getPdo()->exec("DELETE FROM $tableName WHERE id = $id");
  }

  /**
   * @return PDO
   * @throws Exception
   */
  private function getPdo()
  {
    if (!$this->db) {
      try {
        $this->db = new \PDO(
          sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8', $this->host, $this->port, $this->dbname),
          $this->username,
          $this->password
        );
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      } catch (\PDOException $e) {
        throw new Exception('DB Connection error: ' . $e->getMessage());
      }
    }

    return $this->db;
  }

  /**
   * Создание таблицы для резервирования задач, если это нужно
   */
  private function prepareTable()
  {
    $tableName = $this->getTableName();
    if ($this->isTableCreated) {
      return;
    }

    if ($this->getPdo()->query("SHOW TABLES LIKE '$tableName'")->rowCount()) {
      // Таблица существует
      $this->isTableCreated = true;

      return;
    }

    try {
      // Создание таблицы
      $this->getPdo()->exec("
      CREATE TABLE $tableName (
        id INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        channel_name VARCHAR(64) NOT NULL,
        message TEXT
      ) CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB
");
    } catch (\PDOException $e) {
      $this->logger->log('Creating table error: :message', [':message' => $e->getMessage()]);

      return;
    }

    $this->isTableCreated = true;
  }

  /**
   * @param $attempts
   * @return bool
   */
  private function isMaxAttemptExceeded($attempts)
  {
    return $attempts > $this->maxAttemptCount;
  }
}