<?php

namespace TheCoder\MonologTelegram;

use GuzzleHttp\Client;
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
        $topicId = $this->getTopicByAttribute();
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
     * @param array $option
     * @throws GuzzleException
     */
    protected function send(string $message, $token = null, $chatId = null, $topicId = null, array $option = []): void
    {
        try {

            $token = $token ?? $this->token;
            $chatId = $chatId ?? $this->chatId;
            $topicId = $topicId ?? $this->topicId;

            if (!isset($option['verify'])) {
                $option['verify'] = false;
            }

            if (!is_null($this->proxy)) {
                $option['proxy'] = $this->proxy;
            }

            $httpClient = new Client($option);

            $url = !str_contains($this->botApi, 'https://api.telegram.org')
                ? $this->botApi
                : $this->botApi . $token . '/SendMessage';

            $message = $this->truncateTextToTelegramLimit($message);

            $params = [
                'text' => $message,
                'chat_id' => $chatId,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
            ];

            $options = [
                'form_params' => $topicId !== null ? $params + ['message_thread_id' => $topicId] : $params
            ];

            $response = $httpClient->post($url, $options);
        } catch (\Exception $e) {
            $a = 1;
        }
    }

    protected function getTopicByAttribute(): string|null
    {
        $route = Route::current();
        if ($route == null) {
            return null;
        }

        $action = $route->getAction();

        if (!isset($action['controller'])) {
            return null;
        }

        $topicId = $this->getTopicIdByReflection($action);
        if ($topicId === false) {
            $topicId = $this->getTopicIdByRegex($action);
        }

        return $topicId;
    }

    protected function getTopicIdByReflection($action): bool|string|null
    {
        try {
            [$controller, $method] = explode('@', $action['controller']);
            $reflectionMethod = new ReflectionMethod($controller, $method);

            $attributes = $reflectionMethod->getAttributes();
                $attributes[0]?->newInstance() ?? null;

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
            // if reflection coud not get attribute use reagex instead
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
