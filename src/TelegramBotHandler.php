<?php

namespace TheCoder\MonologTelegram;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use ReflectionMethod;
use TheCoder\MonologTelegram\Attributes\TopicLogInterface;

class TelegramBotHandler extends AbstractProcessingHandler
{

    protected Router $router;

    /**
     * text parameter in sendMessage method
     * @see https://core.telegram.org/bots/api#sendmessage
     */
    protected const TELEGRAM_MESSAGE_SIZE = 4096;

    /**
     * bot api url
     * @var string
     */
    protected $botApi;

    /**
     * Telegram bot access token provided by BotFather.
     * Create telegram bot with https://telegram.me/BotFather and use access token from it.
     * @var string
     */
    protected $token;

    /**
     * if telegram is blocked in your region you can use proxy
     * @var null
     */
    protected $proxy;

    /**
     * Telegram channel name.
     * Since to start with '@' symbol as prefix.
     * @var string
     */
    protected $chatId;

    /**
     * If chat groups are used instead of telegram channels,
     * and the ability to set topics on groups is enabled,
     * this configuration can be utilized.
     * @var string|null
     */
    protected $topicId;

    protected $queue = null;

    protected $topicsLevel;

    /**
     * @param string $token Telegram bot access token provided by BotFather
     * @param string $channel Telegram channel name
     * @inheritDoc
     */
    public function __construct(
        Router  $router,
        string  $token,
        string  $chat_id,
        ?string $topic_id = null,
        ?string $queue = null,
                $topics_level = [],
                $level = Logger::DEBUG,
        bool    $bubble = true,
                $bot_api = 'https://api.telegram.org/bot',
                $proxy = null)
    {
        parent::__construct($level, $bubble);

        $this->router = $router;
        $this->token = $token;
        $this->botApi = $bot_api;
        $this->chatId = $chat_id;
        $this->topicId = $topic_id;
        $this->queue = $queue;
        $this->topicsLevel = $topics_level;
        $this->level = $level;
        $this->bubble = $bubble;
        $this->proxy = $proxy;
    }

    /**
     * @inheritDoc
     * @throws \ReflectionException
     */
    protected function write($record): void
    {
        $topicId = $this->getTopicByAttribute($record);
        $token = $record['context']['token'] ?? null;
        $chatId = $record['context']['chat_id'] ?? null;
        $topicId = $topicId ?? $record['context']['topic_id'] ?? null;

        $this->send($record['formatted'], $token, $chatId, $topicId);
    }

    private function truncateTextToTelegramLimit(string $textMessage): string
    {
        if (mb_strlen($textMessage) <= self::TELEGRAM_MESSAGE_SIZE) {
            return $textMessage;
        }

        return mb_substr($textMessage, 0, self::TELEGRAM_MESSAGE_SIZE, 'UTF-8');
    }

    /**
     * Send request to @link https://api.telegram.org/bot on SendMessage action.
     * @param string $message
     * @param null $token
     * @param null $chatId
     * @param null $topicId
     * @throws GuzzleException
     */
    protected function send(string $message, $token = null, $chatId = null, $topicId = null): void
    {
        $token = $token ?? $this->token;
        $chatId = $chatId ?? $this->chatId;
        $topicId = $topicId ?? $this->topicId;

        $url = !str_contains($this->botApi, 'https://api.telegram.org')
            ? $this->botApi
            : $this->botApi . $token . '/SendMessage';

        $message = $this->truncateTextToTelegramLimit($message);

        if (empty($this->queue)) {
            dispatch_sync(new SendJob($url, $message, $chatId, $topicId, $this->proxy));
        } else {
            dispatch(new SendJob($url, $message, $chatId, $topicId, $this->proxy))->onQueue($this->queue);
        }
    }

    protected function getTopicByAttribute($record): string|null
    {
        if (isset($record['context']['exception'])) {
            $trace = $record['context']['exception']->getTrace();

            $commandClass = $this->getClassForCommand($trace);
            if ($commandClass) {
                return $this->getTopicIdByReflection($commandClass, 'handle');
            }

            $jobClass = $this->getClassForJob($trace);
            if ($jobClass) {
                return $this->getTopicIdByReflection($jobClass, 'handle');
            }
        }

        return $this->getTopicByRoute();
    }

    protected function getClassForCommand(array $trace): ?string
    {
        if (!app()->runningInConsole()) {
            return null;
        }

        foreach ($trace as $frame) {
            if ($frame['function'] === 'handle' && isset($frame['class']) && str_contains($frame['class'], 'Console\Commands')) {
                return $frame['class'];
            }
        }

        return null;
    }

    protected function getClassForJob(array $trace): ?string
    {
        if (!app()->bound('queue.worker')) {
            return null;
        }

        foreach ($trace as $frame) {
            if ($frame['function'] === 'handle' && isset($frame['class']) && str_contains($frame['class'], 'App\Jobs')) {
                return $frame['class'];
            }
        }

        return null;
    }

    protected function getTopicByRoute(): string|null
    {
        $route = Route::current();
        if (!$route || !isset($route->getAction()['controller'])) {
            return null;
        }

        [$controller, $method] = explode('@', $route->getAction()['controller']);

        $topicId = $this->getTopicIdByReflection($controller, $method);
        if ($topicId === false) {
            $topicId = $this->getTopicIdByRegex($route->getAction());
        }

        return $topicId;
    }

    protected function getTopicIdByReflection($class, $method): bool|string|null
    {
        try {
            $reflectionMethod = new ReflectionMethod($class, $method);

            $attributes = $reflectionMethod->getAttributes();

            if ($attributes[0] !== null) {
                /** @var TopicLogInterface $notifyException */
                $notifyException = $attributes[0]->newInstance() ?? null;
                return $notifyException->getTopicId($this->topicsLevel);
            }

        } catch (\Throwable $e) {

        }
        return false;
    }

    protected function getTopicIdByRegex($action)
    {
        try {
            // if reflection could not get attribute use regex instead
            [$controller, $method] = explode('@', $action['controller']);

            $filePath = base_path(str_replace('App', 'app', $controller) . '.php');
            $fileContent = file_get_contents($filePath);
            $allAttributes = [];

            // Regex to match attributes and methods
            $regex = '/\#\[\s*(.*?)\s*\]\s*public\s*function\s*(\w+)/';
            if (preg_match_all($regex, $fileContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $attributeString = $match[1];
                    $methodName = $match[2];

                    $attributes = array_map('trim', explode(',', $attributeString));
                    foreach ($attributes as $attribute) {
                        $attributeName = preg_replace('/\(.*/', '', $attribute);
                        $allAttributes[$methodName][] = $attributeName;
                    }
                }
            }

            if (empty($allAttributes)) {
                return null;
            }

            if (isset($allAttributes[$method][0])) {
                foreach ($this->topicsLevel as $key => $_topicLevel) {
                    if (str_contains($key, $allAttributes[$method][0])) {
                        return $_topicLevel;
                    }
                }
            }

        } catch (\Throwable $e) {
        }
        return null;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function setChatId(string $chatId): static
    {
        $this->chatId = $chatId;

        return $this;
    }

    public function setTopicId(string $topicId): static
    {
        $this->topicId = $topicId;

        return $this;
    }
}
