<?php

namespace TheCoder\MonologTelegram;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class TelegramBotHandler extends AbstractProcessingHandler
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
	private $channel;

	/**
	 * @param string $token Telegram bot access token provided by BotFather
	 * @param string $channel Telegram channel name
	 * @inheritDoc
	 */
	public function __construct(string $token, string $channel, $level = Logger::DEBUG, bool $bubble = true, $botApi = 'https://api.telegram.org/bot', $proxy = null)
	{
		parent::__construct($level, $bubble);

		$this->token = $token;
		$this->botApi = $botApi;
		$this->channel = $channel;
		$this->level = $level;
		$this->bubble = $bubble;
		$this->proxy = $proxy;
	}

	/**
	 * @inheritDoc
	 */
	protected function write(array $record): void
	{
		$this->send($record['formatted']);
	}

	/**
	 * Send request to @link https://api.telegram.org/bot on SendMessage action.
	 * @param string $message
	 */
	protected function send(string $message, $option = []): void
	{
		try {
			if (!is_null($this->proxy)) {
				$option['proxy'] = $this->proxy;
			}
			$httpClient = new Client($option);

			$url = $this->botApi . $this->token . '/SendMessage';
			$options = [
				'form_params' => [
					'text' => $message,
					'chat_id' => $this->channel,
					'parse_mode' => 'html',
					'disable_web_page_preview' => true,
				]
			];
			$response = $httpClient->post($url, $options);
		} catch (\Exception $e) {
			//
            $a =1;
		}
	}
}


