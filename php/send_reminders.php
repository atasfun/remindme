<?php
namespace AtasFun\RemindMe;
?>

<html>
<head><title>Send reminders</title></head>
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

# TODO what if the program dies after reading the entry from the database, before sending the reminder?
# TODO what if multiple clients read the same entry from the database?
$num_reminders_sent = 0;
$keep_going = true;
while ($keep_going) {
  $keep_going = false;
  $row = getNextQueue($db_conn, new \DateTimeImmutable());
  if ($row != null) {
    $keep_going = true;
    [$queue_id, $message_id, $user, $send_dm] = $row;
    $reply = "@$user Here is your reminder!";
    print "Message id: $message_id, reply: $reply<br />" . \PHP_EOL;
    
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
    
    removeFromQueue($db_conn, $queue_id);
    $num_reminders_sent++;
  }
}

print "Done after sending $num_reminders_sent reminders!<br />" . \PHP_EOL;

?>
</body>
</html>
