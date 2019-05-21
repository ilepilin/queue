#!/usr/bin/env php
<?php

// Пример демона для обработки сообщений из очереди AMQP, на примере Yii2 framework

define('QUEUE_DEBUG', true);
define('DAEMON', true);

require(__DIR__ . '/../vendor/autoload.php');
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

/** Чтобы действовали правильные неймспейсы */
require(__DIR__ . '/../common/config/bootstrap.php');
require(__DIR__ . '/../console/config/bootstrap.php');

$config = yii\helpers\ArrayHelper::merge(
  require(__DIR__ . '/../common/config/main.php'),
  require(__DIR__ . '/../common/config/main-local.php'),
  require(__DIR__ . '/../console/config/main.php'),
  require(__DIR__ . '/../console/config/main-local.php')
);

$application = new yii\console\Application($config);

pcntl_signal(SIGTERM, 'sigHandler');
pcntl_signal(SIGHUP, 'sigHandler');
pcntl_signal(SIGINT, 'sigHandler');

function sigHandler($signal)
{
  global $stop;

  switch ($signal) {
    case SIGTERM:
      echo 'SIGTERM' . PHP_EOL;
      $stop = true;
      break;
    case SIGINT:
      echo 'SIGINT' . PHP_EOL;
      $stop = true;
      break;
    case SIGHUP:
      echo 'SIGHUP' . PHP_EOL;
      $stop = true;
      break;
  }
}

global $stop;
$stop = false;
$chanelName = end($argv);

if (in_array($chanelName, ['-h', '--help'])) {
  echo 'Usages' . PHP_EOL;
  printf('[php] %s [%s]' . PHP_EOL, $argv[0], implode(',', [
    // \common\queue\Worker::CHANNEL_NAME,
  ]));
  echo PHP_EOL;

  exit();
}

if ($chanelName == $argv[0]) {
  exec('./' . $argv[0] . ' --help', $output);
  echo implode(PHP_EOL, $output) . PHP_EOL;

  exit();
}

$worker = null;
switch ($chanelName) {
//  case \common\queue\Worker::CHANNEL_NAME:
//    $worker = new \common\queue\Worker();
//    break;
//  default:
//    exit("Wrong chanel name\n");
}

if ($worker === null) {
  exit("Worker for chanel $chanelName not found\n");
}

/** @var ilepilin\queue\QueueFacade $facade */
$facade = Yii::$app->get('queue');

$listener = new \ilepilin\queue\listener\Listener($facade, $worker);

/** @var integer время последнего обращения к бд */
$lastDbConnectTime = 0;
/** @var integer интервал обращения к бд */
$dbConnectPeriod = 15;

while (!$stop) {
  if ($listener->handle(\ilepilin\queue\driver\RabbitMQ::DRIVER_CODE) === null) {
    // для поддержания постоянного коннекта делаем запрос к бд каждые 15 сек
    if (time() > $lastDbConnectTime + $dbConnectPeriod) {
      $application->db->createCommand('SELECT 1')->execute();
      $lastDbConnectTime = time();
    }

    usleep(200000);
  }

  // для обработки сигналов при каждой итерации
  pcntl_signal_dispatch();
}
