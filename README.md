# RemindMe
Reminder bot ([@remindme@mstdn.social](https://mstdn.social/@remindme)) for [Fediverse](https://en.wikipedia.org/wiki/Fediverse), e.g., [Mastodon](https://en.wikipedia.org/wiki/Mastodon_(social_network)), [Lemmy](https://en.wikipedia.org/wiki/Lemmy_(social_network)), etc.

## Features
- Allows timed reminders to be set by Fediverse users by tagging the bot in a message, e.g., `@remindme@mstdn.social 1 day`
- The bot will post an acknowledgement message when registering the reminder, and posts another message after the desired duration
- The bot supports durations in minutes, hours, days, weeks, months, and years
- The bot also support date-times in ISO-8601, e.g., `2025-01-30T15:33+02:00`. If no time is given, then midnight UTC will be used
- Currently, the bot posts messages publicly (the acknowledgment and reminder messages). If a private message is desired, `dm` or `pm` (case-insensitive) should be added to the message, e.g., `@remindme@mstdn.social 1 day dm`
- Uses [mastodon-api-client](https://github.com/vazaha-nl/mastodon-api-client) as the Mastodon API to read/write to the Fediverse

## Usage
- Set a reminder: `@remindme@mstdn.social [duration]`, e.g., `1 day`, `1 month` etc
- Set a reminder for a date-time: `@remindme@mstdn.social [ISO-8601 date-time]`, e.g., `2025-01-30T15:33+02:00`
- Set a reminder privately: `@remindme@mstdn.social [duration] dm`, e.g., `1 day dm`, `1 day DM`, `1 month dm` etc

## Development
- The bot is written in PHP, though the author is not well-versed in PHP, so the code has lots of room for improvement. The bot was written as a fun project to contribute to the Fediverse.
- [add_reminders.php](https://github.com/atasfun/remindme/blob/main/php/add_reminders.php) and [send_reminders.php](https://github.com/atasfun/remindme/blob/main/php/send_reminders.php) are run on a cron job every minute to add and send reminders respectively.

### Bugs, issues, feature requests, etc
Please open a GitHub issue, contact [@atasfun@mastodon.gamedev.place](https://mastodon.gamedev.place/@atasfun), or send an email

### Improving the bot
If you would like to improve the bot's features, please feel free to submit a GitHub pull request

## Author
[Allison Liemhetcharat](https://www.linkedin.com/in/allison-liem/) ([@allisonliem@mastodon.online](https://mastodon.online/@allisonliem))

