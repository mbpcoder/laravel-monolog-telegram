<?php

namespace TheCoder\MonologTelegram;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class TelegramBotHandler extends AbstractProcessingHandler implements HandlerInterface
{
    /**
     * text parameter in sendMessage method
     * @see https://core.telegram.org/bots/api#sendmessage
     */
    private const TELEGRAM_MESSAGE_SIZE = 4096;

    /**
     * bot api url
     * @var string
     */
    private $botApi;

    /**
     * Telegram bot access token provided by BotFather.
     * Create telegram bot with https://telegram.me/BotFather and use access token from it.
     * @var string
     */
    private $token;

    /**
     * if telegram is blocked in your region you can use proxy
     * @var null
     */
    private $proxy;

    /**
     * Telegram channel name.
     * Since to start with '@' symbol as prefix.
     * @var string
     */
    private $chatId;

    /**
     * If chat groups are used instead of telegram channels,
     * and the ability to set topics on groups is enabled,
     * this configuration can be utilized.
     * @var string|null
     */
    private $topicId;

    /**
     * @param string $token Telegram bot access token provided by BotFather
     * @param string $channel Telegram channel name
     * @inheritDoc
     */
    public function __construct(
        string      $token,
        string      $chat_id,
        ?string $topic_id = null,
                    $level = Logger::DEBUG,
        bool        $bubble = true,
                    $bot_api = 'https://api.telegram.org/bot',
                    $proxy = null)
    {
        parent::__construct($level, $bubble);

        $this->token = $token;
        $this->botApi = $bot_api;
        $this->chatId = $chat_id;
        $this->topicId = $topic_id;
        $this->level = $level;
        $this->bubble = $bubble;
        $this->proxy = $proxy;
    }

    /**
     * @inheritDoc
     */
    protected function write($record): void
    {
        $this->send($record['formatted']);
    }

    private function truncateTextToTelegramLimit(string $textMessage): string
    {
        if (mb_strlen($textMessage) <= self::TELEGRAM_MESSAGE_SIZE) {
            return $textMessage;
        }

        return mb_substr($textMessage, 0, self::TELEGRAM_MESSAGE_SIZE,'UTF-8');
    }

    /**
     * Send request to @link https://api.telegram.org/bot on SendMessage action.
     * @param string $message
     * @param array $option
     */
    protected function send(string $message, $option = []): void
    {
        try {

            if (!isset($option['verify'])) {
                $option['verify'] = false;
            }

            if (!is_null($this->proxy)) {
                $option['proxy'] = $this->proxy;
            }

            $httpClient = new Client($option);

            $url = !str_contains($this->botApi, 'https://api.telegram.org')
                ? $this->botApi
                : $this->botApi . $this->token . '/SendMessage';

            $message = $this->truncateTextToTelegramLimit($message);

            $params = [
                'text' => $message,
                'chat_id' => $this->chatId,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
            ];

            $options = [
                'form_params' => $this->topicId !== null ? $params + ['message_thread_id' => $this->topicId] : $params
            ];

            $response = $httpClient->post($url, $options);
        } catch (\Exception $e) {

        }
    }
}
