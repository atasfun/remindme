<?php

declare(strict_types=1);

namespace AtasFun\RemindMe;

use \DateTimeImmutable;
use \PDO;

function createSqlConnection(string $server, string $database, string $username, string $password): PDO {
  $sql = "mysql:host=$server;dbname=$database;";
  $dsn_options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
  try {
    $db_connection = new PDO($sql, $username, $password, $dsn_options);
  }
  catch (PDOException $error) {
    die('Failed to connect to SQL: ' . $error->getMessage());
  }
  return $db_connection;
}

function getSqlStatements(): array {
  $sql_statements = array();
  
  $sql_statements['create_last_notification_table'] = <<<'EOD'
CREATE TABLE IF NOT EXISTS last_notification (
  SERVER_ID       BIGINT PRIMARY KEY NOT NULL,
  NOTIFICATION_ID BIGINT             NOT NULL
);
EOD;
  $sql_statements['create_queue_table'] = <<<'EOD'
CREATE TABLE IF NOT EXISTS queue (
  QUEUE_ID          VARCHAR(255) PRIMARY KEY NOT NULL,
  MESSAGE_ID        BIGINT                   NOT NULL,
  USER              TEXT                     NOT NULL,
  REMINDER_DATETIME BIGINT                   NOT NULL,
  SEND_DM           INT                      NOT NULL
);
EOD;
  $sql_statements['create_archive_table'] = <<<'EOD'
CREATE TABLE IF NOT EXISTS archive (
  MESSAGE_ID        BIGINT NOT NULL,
  USER              TEXT   NOT NULL,
  REMINDER_DATETIME BIGINT NOT NULL,
  MESSAGE_DATETIME  BIGINT NOT NULL,
  SEND_DM           INT    NOT NULL
);
EOD;

  $sql_statements['get_last_notification']     = 'SELECT NOTIFICATION_ID FROM last_notification WHERE SERVER_ID = :server_id';
  $sql_statements['set_last_notification']     = 'INSERT INTO last_notification (SERVER_ID, NOTIFICATION_ID) VALUES(:server_id, :notification_id)';
  $sql_statements['update_last_notification']  = 'UPDATE last_notification SET NOTIFICATION_ID = :notification_id where SERVER_ID = :server_id';
  $sql_statements['replace_last_notification'] = 'REPLACE INTO last_notification (SERVER_ID, NOTIFICATION_ID) VALUES(:server_id, :notification_id)';
  
  $sql_statements['add_queue']          = 'REPLACE INTO queue (QUEUE_ID, MESSAGE_ID, USER, REMINDER_DATETIME, SEND_DM) VALUES(:queue_id, :message_id, :user, :reminder_datetime, :send_dm)';
  $sql_statements['find_queue']         = 'SELECT COUNT(*) FROM queue WHERE QUEUE_ID = :queue_id';
  $sql_statements['get_queue']          = 'SELECT QUEUE_ID, MESSAGE_ID, USER, SEND_DM from queue WHERE REMINDER_DATETIME <= :current_datetime LIMIT 1';
  $sql_statements['remove_from_queue']  = 'DELETE from queue WHERE QUEUE_ID = :queue_id';
  $sql_statements['count_queue']        = 'SELECT COUNT(*) FROM queue';
  
  $sql_statements['add_archive']          = 'REPLACE INTO archive (MESSAGE_ID, USER, REMINDER_DATETIME, MESSAGE_DATETIME, SEND_DM) VALUES(:message_id, :user, :reminder_datetime, :message_datetime, :send_dm)';
  $sql_statements['count_archive']        = 'SELECT COUNT(*) FROM archive';
    
  return $sql_statements;
}

function getSqlStatement(string $variable): string {
    return getSqlStatements()[$variable];
}

function executeSql(PDO $db_conn, string $sql_statement, array $values_to_bind) {
  $prepared_sql = $db_conn->prepare($sql_statement);
  foreach ($values_to_bind as $param => $value) {
    $prepared_sql->bindParam(":$param", $value[0], PDO::PARAM_STR);
  }
  try {
    if (!$prepared_sql->execute()) {
      print "Failed to execute: $sql_statement<br />" . \PHP_EOL;
    }
  }
  catch (PDOException $error) {
    print "Failed to execute: $sql_statement: " . $error->getMessage() . '<br />' . \PHP_EOL;
  }
  return $prepared_sql;
}

function createTables(PDO $db_conn) {
  $sql_statements = getSqlStatements();

  $sqls = [
    getSqlStatement('create_last_notification_table'),
    getSqlStatement('create_queue_table'),
    getSqlStatement('create_archive_table'),
  ];
  
  foreach ($sqls as $sql) {
    executeSql($db_conn, $sql, array());
  }
}

function getLastNotification(PDO $db_conn, int $server_id): ?int {
  $last_notification = null;
  
  $results = executeSql($db_conn, getSqlStatement('get_last_notification'), array('server_id' => [$server_id]))->fetchAll();
  foreach ($results as $result) {
    $last_notification = $result[0];
  }
  
  return $last_notification;
}

function setLastNotification(PDO $db_conn, int $server_id, int $new_notification_id) {
  executeSql($db_conn, getSqlStatement('replace_last_notification'),
    array('server_id'       => [$server_id],
          'notification_id' => [$new_notification_id]));
}

function addQueue(PDO $db_conn, int $message_id, string $user, DateTimeImmutable $reminder_datetime, bool $send_dm): bool {
  // Max length of queue_id is 255
  $queue_id = substr("$user:$message_id", 0, 255);
  
  // Ensure that we don't already have the item in the queue
  $result = executeSql($db_conn, getSqlStatement('find_queue'), array('queue_id' => [$queue_id]))->fetch();
  if ($result[0] > 0) {
    return false;
  }
  
  $reminder_datetime_timestamp = $reminder_datetime->getTimestamp();
  $send_dm_int = ($send_dm ? 1 : 0);
  
  executeSql($db_conn, getSqlStatement('add_queue'), 
    array('queue_id'          => [$queue_id],
          'message_id'        => [$message_id],
          'user'              => [$user],
          'reminder_datetime' => [$reminder_datetime_timestamp],
          'send_dm'           => [$send_dm_int]));
  return true;
}

function getNextQueue(PDO $db_conn, DateTimeImmutable $current_datetime): ?array {
  $current_datetime_timestamp = $current_datetime->getTimestamp();
  
  $results = executeSql($db_conn, getSqlStatement('get_queue'), array('current_datetime' => [$current_datetime_timestamp]))->fetchAll();
  if (count($results) > 0) {
    return $results[0];
  }
  else {
    return null;
  }
}

function countQueue(PDO $db_conn): int {
  $result = executeSql($db_conn, getSqlStatement('count_queue'))->fetch();
  return $result[0];
}

function removeFromQueue(PDO $db_conn, string $queue_id) {
  executeSql($db_conn, getSqlStatement('remove_from_queue'), array('queue_id' => [$queue_id]));
}

function addArchive(PDO $db_conn, int $message_id, string $user, DateTimeImmutable $reminder_datetime, DateTimeImmutable $message_datetime, bool $send_dm) {
  $reminder_datetime_timestamp = $reminder_datetime->getTimestamp();
  $message_datetime_timestamp = $message_datetime->getTimestamp();
  $send_dm_int = ($send_dm ? 1 : 0);
  
  executeSql($db_conn, getSqlStatement('add_archive'),
  array('message_id'        => [$message_id],
        'user'              => [$user],
        'reminder_datetime' => [$reminder_datetime_timestamp],
        'message_datetime'  => [$message_datetime_timestamp],
        'send_dm'           => [$send_dm_int]));
}

function countArchive(PDO $db_conn): int {
  $result = executeSql($db_conn, getSqlStatement('count_archive'))->fetch();
  return $result[0];
}

?>