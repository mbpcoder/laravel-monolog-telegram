

monolog-telegram
=============

🔔 Telegram Handler for Monolog


# Installation
-----------
Install using composer:

```bash
composer require thecoder/monolog-telegram  
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
        'driver'  => 'custom',
		'level' => 'debug',
        'via'     => TheCoder\MonologTelegram\TelegramBotHandler::class,
		'formatter' => TheCoder\MonologTelegram\TelegramFormatter::class,
        'token'   => env('LOG_TELEGRAM_BOT_TOKEN'),
        'channel' => env('LOG_TELEGRAM_CHAT_ID'),
		'botApi' => env('LOG_TELEGRAM_BOT_API', 'https://api.telegram.org/bot'),
		'proxy' => env('LOG_TELEGRAM_BOT_PROXY', 'socks5h://localhost:9050'),		
    ],
]

```

Add the following variables to your .env file.

```php
LOG_TELEGRAM_BOT_TOKEN=
LOG_TELEGRAM_CHAT_ID=
LOG_TELEGRAM_BOT_API=
LOG_TELEGRAM_BOT_PROXY=

