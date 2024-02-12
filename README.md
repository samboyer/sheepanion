# Sheepanion - sheepsay bot for Webex.

# It does everything you could want, and nothing that you need, from a sheep bot.

# Truly the Bot of the People(TM)

## Features

- Send anonymous sheep messages to your friends
- Complex sheep adoption system
- Effective Baa-ltruism (donations)

_(new features are welcome via PRs; my sheepanion task allocation budget is quite low)_

## Get Started

1. Send 'help' to sheepanion@webex.bot on Webex.
2. Done.

## Setup your own instance

_what?? something wrong with my instance eh??_

1. register a new bot account with webex
1. paste the bot key into the `BOT_KEY` file
1. launch a webserver (& draw the rest of the owl)
1. establish the webhook:
```bash
BOT_KEY=`cat BOT_KEY`; curl -X POST -d "targetUrl={YOUR_WEB_URL}/webhook.php" -d "resource=messages" -d "event=created" -d "name=sheepanion_webhook" -H "Authorization: Bearer $BOT_KEY" https://webexapis.com/v1/webhooks
```
