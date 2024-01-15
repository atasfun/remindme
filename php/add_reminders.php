<?php
namespace AtasFun\RemindMe;
?>

<html>
<head><title>Add reminders</title></head>
<body>
<?php

require_once 'vendor/autoload.php';

include('database_helpers.php');
include('duration_helpers.php');
include('mastodon_helpers.php');

print 'Time: ' . getFormattedDateTime(new \DateTimeImmutable('now', new \DateTimeZone('America/Los_Angeles'))) . '<br />' . \PHP_EOL;

$post_on_mastodon = true;
$server_id = getServerId();

print "Server id: $server_id<br />" . \PHP_EOL;

// Get secrets
[$sql_server, $sql_db, $sql_user, $sql_password] = getSqlSecret($server_id);
[$server, $secret_token] = getMastodonSecret($server_id);

// Connect to the SQL database
$db_conn = createSqlConnection($sql_server, $sql_db, $sql_user, $sql_password);
print 'Connected to SQL<br />' . \PHP_EOL;

// Create Mastodon client
$client = createMastondonClient($server, $secret_token);
print 'Created Mastodon client<br />' . \PHP_EOL;

$last_notification_id = getLastNotification($db_conn, $server_id);

$types_to_include = array('mention');

# types and exclude_types don't seem to have an effect
#$notifications = $client->methods()->notifications()->get(since_id: $id_since, types: $types_to_include, exclude_types: $types_to_exclude);
$notifications = $client->methods()->notifications()->get(since_id: $last_notification_id);

$num_reminders_added = 0;

foreach ($notifications as $notification) {
  if (!in_array($notification->type, $types_to_include)) {
    continue;
  }

  $notification_id = $notification->id;
  $message = $notification->status->content;
  $message_id = $notification->status->id;
  $user = getFullUser($notification->account->acct, $server);
  $message_datetime = \DateTimeImmutable::createFromMutable($notification->status->created_at);

  print "Received from: $user: $message, id: $message_id<br />" . \PHP_EOL;
  
  [$result, $duration_seconds] = getDurationInSeconds($message);
  $send_dm = shouldSendDm($message);
  if ($duration_seconds <= 0) {
    print "No duration found, skipping!<br />" . \PHP_EOL;
    # Update the last notification id
    if (($last_notification_id == null) or ($notification_id > $last_notification_id)) {
      setLastNotification($db_conn, $server_id, $notification_id);
      $last_notification_id = $notification_id;
    }
    continue;
  }
  
  $reminder_datetime = addTimeDelta($message_datetime, $result);
  
  $send_reply = addQueue($db_conn, $message_id, $user, $reminder_datetime, $send_dm);
  
  # Update the last notification id
  if (($last_notification_id == null) or ($notification_id > $last_notification_id)) {
    setLastNotification($db_conn, $server_id, $notification_id);
    $last_notification_id = $notification_id;
  }
  
  $reply = "@$user Ok, I will remind you on " . getFormattedDateTime($reminder_datetime) . '.';
  print "Reply:<br />$reply<br />" . \PHP_EOL;
  
  if ($send_reply) {
    # Post on Mastodon
    try {
      if ($post_on_mastodon) {
        $visibility = ($send_dm ? 'direct' : null);
        $client->methods()->statuses()->create(
          $reply,
          [],
          in_reply_to_id: $message_id,
          visibility: $visibility,
        );
      }
    }
    catch (\Exception $error) {
      print 'Caught exception while posting on Mastodon: ' . $error->getMessage() . '<br />' . \PHP_EOL;
    }
    
    $num_reminders_added++;

    addArchive($db_conn, $message_id, $user, $reminder_datetime, $message_datetime, $send_dm);
  }
}

print "Done after adding $num_reminders_added reminders!<br />" . \PHP_EOL;

?>
</body>
</html>
