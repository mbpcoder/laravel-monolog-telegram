

monolog-telegram
=============

🔔 Telegram Handler for Monolog


# Installation
-----------
Install using composer:

```bash
composer require thecoder/laravel-monolog-telegram  
```

# Usage
Open config/logging.php and change the file

```php

 'channels' => [
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['single', 'telegram'],
    ],
    
    ....
    
        'telegram' => [
            'driver' => 'monolog',
            'level' => 'debug',
            
            'handler' => TheCoder\MonologTelegram\TelegramBotHandler::class,
            'handler_with' => [
                'token' => env('LOG_TELEGRAM_BOT_TOKEN'),
                'chat_id' => env('LOG_TELEGRAM_CHAT_ID'),
                'topic_id' => env('LOG_TELEGRAM_TOPIC_ID',null),
                'bot_api' => env('LOG_TELEGRAM_BOT_API', 'https://api.telegram.org/bot'),
                'proxy' => env('LOG_TELEGRAM_BOT_PROXY', null),
            ],
            
            'formatter' => TheCoder\MonologTelegram\TelegramFormatter::class,
            'formatter_with' => [
                'tags' => env('LOG_TELEGRAM_TAGS', null),
            ],            
        ],
]

```

You can customize token, chat_id and topic_id in run time

```php

logger('message', [
    'token' => 'your bot token',
    'chat_id' => 'your chat id',
    'topic_id' => 'your topic id'
]);

```

Add the following variables to your .env file.

```php
LOG_TELEGRAM_BOT_TOKEN=
LOG_TELEGRAM_CHAT_ID=

# If chat groups are used instead of telegram channels,
# and the ability to set topics on groups is enabled,
# this configuration can be utilized.
LOG_TELEGRAM_TOPIC_ID=

#LOG_TELEGRAM_BOT_API='https://api.telegram.org/bot'
# add tor proxy for restricted country
#LOG_TELEGRAM_BOT_PROXY='socks5h://localhost:9050'
```
# ScreenShot

![image](https://user-images.githubusercontent.com/3877538/172431112-020d7a7c-f515-49bc-961a-3f63c9ff21af.png)

