<?php

declare(strict_types=1);

namespace AtasFun\RemindMe;

use Vazaha\Mastodon\ApiClient;

function createMastondonClient(string $server, string $token): ApiClient {
  $factory = new \Vazaha\Mastodon\Factories\ApiClientFactory();
  $client = $factory->build();
  
  $client->setBaseUri("https://" . $server);

  $client->setAccessToken($token);
  
  return $client;
}

function getFullUser(string $user, string $server): string {
  if (str_contains($user, "@")) {
    return $user;
  }
  else {
    return $user . "@" . $server;
  }
}

?>
