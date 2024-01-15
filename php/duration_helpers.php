<?php

declare(strict_types=1);

namespace AtasFun\RemindMe;

function fillInDurationResult(array $match_key, int $index, string $key, array &$result) {
  if (($index < count($match_key)) and (strlen($match_key[$index]) > 0)) {
    $result[$key] = intval($match_key[$index]);
  }
}

function getDurationInSeconds(string $input_string) {
  $best_result = array('year' => 0, 'month' => 0, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0);
  $longest_duration = 0;
  
  $regex = '/((?<year>\d{1,2})\s*y(ears|ear|rs|r)?($|,|:|<.*)?)?\s*((?<month>\d{1,2})\s*m(onths|onth|nths|nth|ons|on)($|,|:|<.*)?)?\s*((?<week>\d{1,2})\s*w(eeks|eek|ks|k)?($|,|:|<.*)?)?\s*((?<day>\d{1,3})\s*d(ays|ay)?($|,|:|<.*)?)?\s*((?<hour>\d{1,3})\s*h(ours|our|rs|r)?($|,|:|<.*)?)?\s*((?<minute>\d{1,3})\s*m(inutes|inute|ins|in)?($|,|:|<.*)?)?\s*/';
  
  if (preg_match_all($regex, $input_string, $matches)) {
    $num_indices = count($matches['year']);
    $keys = ['year', 'month', 'week', 'day', 'hour', 'minute'];
    for ($i = 0; $i < $num_indices; $i++) {
      $result = array('year' => 0, 'month' => 0, 'week' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0);
      foreach ($keys as $key) {
          fillInDurationResult($matches[$key], $i, $key, $result);
      }

      $duration = ((((($result['year'] * 12 + $result['month']) * 4 + $result['week']) * 7 + $result['day']) * 24 + $result['hour']) * 60 + $result['minute']) * 60;
      
      if ($duration > $longest_duration) {
        $longest_duration = $duration;
        $best_result = $result;
      }
    }
  }
  
  return [$best_result, $longest_duration];
}

function getTimeDelta(array $result): \DateInterval {
  return new \DateInterval('P' . $result['year'] . 'Y' . $result['month'] . 'M' . $result['week']. 'W' . $result['day'] . 'DT' . $result['hour'] . 'H' . $result['minute']. 'M');
}

function addTimeDelta(\DateTimeImmutable $current_datetime, array $result): \DateTimeImmutable {
  $time_delta = getTimeDelta($result);
  $new_datetime = $current_datetime->add($time_delta);
  $truncated_datetime = $new_datetime->setTime(hour: intval($new_datetime->format('H')), minute: intval($new_datetime->format('i')), second: 0, microsecond: 0);
  return $truncated_datetime;
}

function shouldSendDm($input_string): bool {
  $send_dm = false;
  
  $regex = '/(^|.*\s|.*>)(?<dm>dm)($|\s.*|<.*)/';
  
  if (preg_match($regex, $input_string, $matches)) {
    if (in_array('dm', $matches) and ($matches['dm'] == 'dm')) {
      $send_dm = true;
    }
  }
  
  return $send_dm;
}

function getFormattedDateTime(\DateTimeImmutable $datetime_to_format): string {
  $datetime_in_pacific = $datetime_to_format->setTimezone(new \DateTimeZone('America/Los_Angeles'));
  return $datetime_in_pacific->format('l M j, Y') . ' at ' . $datetime_in_pacific->format('g:i A T');
}

?>
