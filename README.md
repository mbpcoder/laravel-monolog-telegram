

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

### Run log in a Queue

If a queue name is set, the job will run in the specified queue; otherwise, it will run synchronously.

```php

use TheCoder\MonologTelegram\Attributes\EmergencyAttribute;
use TheCoder\MonologTelegram\Attributes\CriticalAttribute;
use TheCoder\MonologTelegram\Attributes\ImportantAttribute;
use TheCoder\MonologTelegram\Attributes\DebugAttribute;
use TheCoder\MonologTelegram\Attributes\InformationAttribute;
use TheCoder\MonologTelegram\Attributes\LowPriorityAttribute;

    ....

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
                'queue' => env('LOG_TELEGRAM_QUEUE', null),
                'topics_level' => [
                    EmergencyAttribute::class => env('LOG_TELEGRAM_EMERGENCY_ATTRIBUTE_TOPIC_ID', null),
                    CriticalAttribute::class => env('LOG_TELEGRAM_CRITICAL_ATTRIBUTE_TOPIC_ID', null),
                    ImportantAttribute::class => env('LOG_TELEGRAM_IMPORTANT_ATTRIBUTE_TOPIC_ID', null),
                    DebugAttribute::class => env('LOG_TELEGRAM_DEBUG_ATTRIBUTE_TOPIC_ID', null),
                    InformationAttribute::class => env('LOG_TELEGRAM_INFORMATION_ATTRIBUTE_TOPIC_ID', null),
                    LowPriorityAttribute::class => env('LOG_TELEGRAM_LOWPRIORITY_ATTRIBUTE_TOPIC_ID', null),
                ]
            ],
            
            'formatter' => TheCoder\MonologTelegram\TelegramFormatter::class,
            'formatter_with' => [
                'tags' => env('LOG_TELEGRAM_TAGS', null),
            ],            
        ],
]

```

With topics_level you can set PHP Attribute(Annotation) to controller function

and change the topic id base on this attributes

Note: this package will only process first attribute

Example:

```php
namespace App\Http\Controllers\NewWeb;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use TheCoder\MonologTelegram\Attributes\EmergencyAttribute;

class HomeController extends Controller
{
    #[EmergencyAttribute]
    public function index(Request $request)
    {
        //
    }
}
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

```dotenv
LOG_TELEGRAM_BOT_TOKEN=
LOG_TELEGRAM_CHAT_ID=

# If chat groups are used instead of telegram channels,
# and the ability to set topics on groups is enabled,
# this configuration can be utilized.
LOG_TELEGRAM_TOPIC_ID=

#LOG_TELEGRAM_BOT_API='https://api.telegram.org/bot'
# add tor proxy for restricted country
#LOG_TELEGRAM_BOT_PROXY='socks5h://localhost:9050'

# Topic Level 
LOG_TELEGRAM_EMERGENCY_ATTRIBUTE_TOPIC_ID=
LOG_TELEGRAM_CRITICAL_ATTRIBUTE_TOPIC_ID=
LOG_TELEGRAM_IMPORTANT_ATTRIBUTE_TOPIC_ID=
LOG_TELEGRAM_DEBUG_ATTRIBUTE_TOPIC_ID=
LOG_TELEGRAM_INFORMATION_ATTRIBUTE_TOPIC_ID=
LOG_TELEGRAM_LOWPRIORITY_ATTRIBUTE_TOPIC_ID=

```
# ScreenShot

![image](https://user-images.githubusercontent.com/3877538/172431112-020d7a7c-f515-49bc-961a-3f63c9ff21af.png)

