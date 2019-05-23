PHP AMQP FACADE
===============================

Модуль для работы с очередями RabbitMQ. 

## Основные особенности

Модуль позволяет выполнять отложенные вычисления и передавать информацию как в рамках одного проекта, так и между проектами.

Модуль не привязан к каким-либо фреймворкам и для его работы требуется только PHP 5.4 или выше с расширениями `bcmath` и `pdo_mysql`.

#### Отказоустойчивость

Для обеспечения отказоустойчивости, модуль поддерживает возможность работы с разными драйверами очередей одновременно.

Драйвер, указанный первым по счету в конфиге, будет использоваться в качестве основного. Все остальные в качестве резервных. 

В модуле есть драйверы RabbitMQ и MySQL. Если необходимо добавить свой драйвер, его нужно указать в конфигурации `QueueFacade` и он должен реализовывать интерфейс `DriverInterface`.

#### Передача сообщений между проектами

Чтобы передавать сообщения из одного проекта в другой, они должны быть подключены к одному серверу rabbitmq (и иметь общий коннект к MySQL для резервной очереди).

Может возникнуть проблема, что пространства имен у классов Payload отличаются в разных проектах. 
Чтобы избежать ошибок, нужно ассоциировать класс Payload проекта, в котором отправляются сообщения, с идентичным классом Payload проекта, в котором принимаются сообщения. 

Это можно сделать при помощи свойства `payloadMap` в конфигурации драйверов проекта, где соощения принимаются:


```php
'payloadMap' => [
    'libs\queue\component1\Payload' => 'common\components\queue\component1\Payload',
    'libs\queue\component2\Payload' => 'common\components\queue\component2\Payload',
],
```


## Начало работы

Для работы модуля требуется:
* [rabbitmq-server](https://www.rabbitmq.com/download.html) - RabbitMQ сервер;
* [mysql](https://www.php.net/manual/ru/book.mysql.php) - База данных MySQL;


### Установка

Для установки модуля необходимо добавить пакет в composer

```bash
php composer.phar require ilepilin/queue
```

Или добавить в файл composer.json и запустить обновление

```
"ilepilin/queue": "1.*"
```
```bash
composer update ilepilin/queue
```

### Конфигурирование

Основной компонент модуля, QueueFacade, можно использовать как синглтон и обращаться к нему через Service Locator. 

Ниже приведен пример конфигурации для проекта на фреймворке Yii2:

```php
'components' => [
    ...
    
    'queue' => [
        'class' => '\ilepilin\queue\QueueFacade',
        'drivers' => [
            [
                'class' => '\ilepilin\queue\driver\RabbitMQ',
                'host' => '127.0.0.1',
                'port' => 5672,
                'user' => 'rabbitmq',
                'password' => '******',
                'payloadMap' => [
                    'libs\queue\component1\Payload' => 'common\components\queue\component1\Payload',
                ],
                'loggerClass' => '\yii\log\Logger',
            ],
            [
                'class' => '\ilepilin\queue\driver\MySQL',
                'username' => 'project_user',
                'password' => '******',
                'host' => '127.0.0.1',
                'port' => 3307, // если порт не стандартный
                'dbname' => 'project_db',
                'payloadMap' => [
                    'libs\queue\component1\Payload' => 'common\components\queue\component1\Payload',
                ],
                'loggerClass' => '\yii\log\Logger',
                
                // указать true, когда таблица для резервной очереди будет создана. 
                // в противном случае, при каждом использовании драйвера будет лишний SQL запрос для проверки существования таблицы
                'isTableCreated' => true,
            ]
        ],
    ],
    
    ...
]

```

В текущем примере устанавливаются 2 драйвера: 
* **RabbitMQ** в качестве основного;
* **MySQL** в качестве резервного.


## Использование

Сконфигурированный QueueFacade, по примеру выше, можно удобно использовать следующим образом:

### Добавление в очередь
```php
/** @var \ilepilin\queue\BasePayload $payload */
$payload = new Payload($data);

Yii::$app->queue->push(Worker::channelName(), $payload);
```

### Извлечение из очереди

Пример обработки сообщений из очереди:

```php
/** @var \ilepilin\queue\WorkerInterface $worker */
$worker = new \path\to\Worker();

/** @var \ilepilin\queue\QueueFacade $facade */
$facade = Yii::$app->get('queue');

$listener = new \ilepilin\queue\listener\Listener($facade, $worker);
$listener->handle();

// $listener->handle(\ilepilin\queue\driver\MySQL::getCode()); // для резервных очередей
```

В методе `handle()` Listener будет пытаться получить самое старое сообщение через указанный драйвер.

Если не указать драйвер, то будет использоваться тот, который указан первым в конфиге - в данном случае это **RabbitMQ**.

После успешного получения сообщения, Listener отправит его в обработку, передав в `$worker->work()`.

#### Фоновая обработка сообщений

В файле [daemon.php](daemon.php) находится пример демона для обработки сообщений из очереди. 

Запускается через CLI, в первом аргументе необходимо передать название канала.

```bash
php daemon.php channel_name
```

Для удобного контроля за демонами, рекомендуется использовать supervisord. 

Для этого, необходимо на каждый демон создать конфиг в /etc/supervisord.d/conf.d/

```bash
[program:daemon_name]
command=/usr/bin/php -q /path/to/daemon.php channel_name
umprocs=1
autostart=true
autorestart=true
startretries=10
user = user1
group = group1
startsecs = 0
stdout_logfile=/path/to/daemon_name.log
```
После добавления конфига, можно запускать следующей командой:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start demon_name
```

#### Обработка сообщений резервной очереди

Обработку сообщений резервной очереди можно осуществлять по крону.

Пример скрипта, выполняемого по крону, для отправки сообщений из резервной очереди в основную:

```php
$channelNames = [
    Worker1::channelName(),
    Worker2::channelName(),
    Worker3::channelName(),
    ...
];

/** @var \ilepilin\queue\QueueFacade $facade */
$facade = Yii::$app->queue;

$driver = $facade->getDriver(\ilepilin\queue\drivers\MySQL::getCode());

foreach ($channelNames as $channelName) {
    while ($message = $driver->pop($channelName)) {
        $facade->push($channelName, $message->data)
    }
}

```

## Лицензия

Copyright (c) 2019 Lepilin Igor

Данная лицензия разрешает лицам, получившим копию данного программного обеспечения и сопутствующей документации (в дальнейшем именуемыми «Программное Обеспечение»), безвозмездно использовать Программное Обеспечение без ограничений, включая неограниченное право на использование, копирование, изменение, слияние, публикацию, распространение, сублицензирование и/или продажу копий Программного Обеспечения, а также лицам, которым предоставляется данное Программное Обеспечение, при соблюдении следующих условий:

Указанное выше уведомление об авторском праве и данные условия должны быть включены во все копии или значимые части данного Программного Обеспечения.

ДАННОЕ ПРОГРАММНОЕ ОБЕСПЕЧЕНИЕ ПРЕДОСТАВЛЯЕТСЯ «КАК ЕСТЬ», БЕЗ КАКИХ-ЛИБО ГАРАНТИЙ, ЯВНО ВЫРАЖЕННЫХ ИЛИ ПОДРАЗУМЕВАЕМЫХ, ВКЛЮЧАЯ ГАРАНТИИ ТОВАРНОЙ ПРИГОДНОСТИ, СООТВЕТСТВИЯ ПО ЕГО КОНКРЕТНОМУ НАЗНАЧЕНИЮ И ОТСУТСТВИЯ НАРУШЕНИЙ, НО НЕ ОГРАНИЧИВАЯСЬ ИМИ. НИ В КАКОМ СЛУЧАЕ АВТОРЫ ИЛИ ПРАВООБЛАДАТЕЛИ НЕ НЕСУТ ОТВЕТСТВЕННОСТИ ПО КАКИМ-ЛИБО ИСКАМ, ЗА УЩЕРБ ИЛИ ПО ИНЫМ ТРЕБОВАНИЯМ, В ТОМ ЧИСЛЕ, ПРИ ДЕЙСТВИИ КОНТРАКТА, ДЕЛИКТЕ ИЛИ ИНОЙ СИТУАЦИИ, ВОЗНИКШИМ ИЗ-ЗА ИСПОЛЬЗОВАНИЯ ПРОГРАММНОГО ОБЕСПЕЧЕНИЯ ИЛИ ИНЫХ ДЕЙСТВИЙ С ПРОГРАММНЫМ ОБЕСПЕЧЕНИЕМ. 

