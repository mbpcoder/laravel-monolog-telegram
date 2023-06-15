<?php

namespace TheCoder\MonologTelegram;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;

class TelegramBotHandler extends AbstractProcessingHandler implements HandlerInterface
{
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
     *
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
        string|null $topic_id = null,
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

            $params = [
                'text' => $message,
                'chat_id' => $this->chatId,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
            ];

            $options = [
                'form_params' => $this->topicId === null ? $params + ['message_thread_id' => $this->topicId] : $params
            ];

            $response = $httpClient->post($url, $options);
        } catch (\Exception $e) {

        }
    }
}
