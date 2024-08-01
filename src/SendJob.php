<?php

namespace TheCoder\MonologTelegram;

use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class SendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string      $url,
        private string      $message,
        private string      $chatId,
        private string|null $topicId = null,
        private string|null $proxy = null,
    )
    {
    }

    public function handle(): void
    {
        $httpClientOption = [];
        $httpClientOption['verify'] = false;

        if (!is_null($this->proxy)) {
            $httpClientOption['proxy'] = $this->proxy;
        }

        $params = [
            'text' => $this->message,
            'chat_id' => $this->chatId,
            'parse_mode' => 'html',
            'disable_web_page_preview' => true,
        ];

        $requestOptions = [
            'form_params' => $this->topicId !== null ? $params + ['message_thread_id' => $this->topicId] : $params
        ];

        $httpClient = new Client($httpClientOption);

        try {
            $response = $httpClient->post($this->url, $requestOptions);
        } catch (\Throwable $exception) {
            $this->fail($exception);
        }
    }
}
