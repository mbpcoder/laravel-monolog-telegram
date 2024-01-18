<?php

namespace TheCoder\MonologTelegram;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;

/**
 * Formats a message to output suitable for Telegram chat
 */
class TelegramFormatter implements FormatterInterface
{
    const MESSAGE_FORMAT = "<b>%level_name%</b> (%channel%) [%date%]\n\n%message%\n\n%context%%extra%";
    const DATE_FORMAT = 'Y-m-d H:i:s e';

    /**
     * @var bool
     */
    private $html;

    /**
     * @var string
     */
    private $format;

    /**
     * @var string
     */
    private $dateFormat;

    /**
     * @var string
     */
    private $separator;

    /**
     * @var string
     */
    private $tags;

    /**
     * Formatter constructor
     *
     * @param bool $html Format as HTML or not
     * @param string $format The format of the message
     * @param string $dateFormat The format of the timestamp: one supported by DateTime::format
     * @param string $separator Record separator used when sending batch of logs in one message
     * @param string $tags Tags to be added to the message
     */
    public function __construct($html = true, $format = null, $dateFormat = null, $separator = '-', $tags = '')
    {
        $this->html = $html;
        $this->format = $format ?: self::MESSAGE_FORMAT;
        $this->dateFormat = $dateFormat ?: self::DATE_FORMAT;
        $this->separator = $separator;
        if (is_null($tags)) {
            $tags = '';
        }
        $this->tags = explode(',', $tags);
    }

    /**
     * {@inheritdoc}
     */
    public function format($record)
    {
        $message = '';
        if (isset($record['context']) && isset($record['context']['exception'])) {
            $exception = $record['context']['exception'];
            try {
                $message = $this->getMessageForException($exception);
            } catch (\Exception $e) {
                //
            }
            return $message;
        }

        return $this->getMessageForLog($record);

    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records)
    {
        $message = '';
        foreach ($records as $record) {
            if (!empty($message)) {
                $message .= str_repeat($this->separator, 15) . "\n";
            }

            $message .= $this->format($record);
        }

        return $message;
    }

    private function getMessageForException($exception)
    {
        $severity = '';
        $request = app('request');
        if (method_exists($exception, 'getSeverity')) {
            $severity = $this->getSeverityName($exception->getSeverity());
        }

        $code = $exception->getCode();
        if (method_exists($exception, 'getStatusCode')) {
            $code = $exception->getStatusCode();
        }

        $message = $severity . ' <b>Time: </b> ' . date('Y-m-d H:i:s') . PHP_EOL
            . '<b>On: </b> ' . app()->environment() . PHP_EOL
            . '<b>Message:</b> ' . $exception->getMessage() . PHP_EOL
            . '<b>Exception:</b> ' . get_class($exception) . PHP_EOL
            . '<b>Code:</b> ' . $code . PHP_EOL
            . '<b>Tags:</b> ' . $this->getTags() . PHP_EOL
            . '<b>File:</b> ' . $exception->getFile() . PHP_EOL
            . '<b>Line:</b> ' . $exception->getLine() . PHP_EOL
            . '<b>Url:</b> ' . urldecode($request->url()) . PHP_EOL
            . '<b>Ip:</b> ' . $request->getClientIp();

        try {
            if (strpos($exception->getMessage(), 'Telegram') !== false && isset($exception->getTrace()[1]['args'][1]['chat_id'])) {
                $message .= '<b>Chat Id: </b> ' . $exception->getTrace()[1]['args'][1]['chat_id'] . PHP_EOL;
            }
        } catch (\Exception $e) {
            // do noting
        }


        if (!is_null($request->user())) {
            $message .= PHP_EOL . '<b>User:</b> ' . $request->user()->id . ' / <b>Name:</b> ' . $request->user()->fullName;
        }

        if (!empty($request->headers->get('referer'))) {
            $message .= PHP_EOL . '<b>Referer:</b> ' . $request->headers->get('referer');
        }

        if (!empty($request->getMethod())) {
            $message .= PHP_EOL . '<b>Request Method:</b> ' . $request->getMethod();
            if ($request->ajax()) {
                $message .= ' <b>(Ajax)</b> ';
            }
        }

        $message .= PHP_EOL . '<b>Request Inputs:</b> <pre>' . str_replace(
            ["\n", " "], ['',''], json_encode($request->except('password', 'password_confirmation'), JSON_UNESCAPED_UNICODE)
            ) . '</pre>';

        $message .= PHP_EOL . PHP_EOL . '<b>Trace: </b> ' . PHP_EOL . '<b> => </b> => ' . substr($exception->getTraceAsString(), 0, 1000) . ' ...';

        return $message;
    }

    private function getTags()
    {
        $message = '';
        foreach ($this->tags as $tag) {
            if (!empty($tag)) {
                $message .= '#' . $tag . ' ';
            }
        }

        return $message;
    }

    private function getMessageForLog($record)
    {
        $message = $this->format;
        $lineFormatter = new LineFormatter();

        if (strpos($record['message'], 'Stack trace') !== false) {
            // Replace '<' and '>' with their special codes
            $record['message'] = preg_replace('/<([^<]+)>/', '&lt;$1&gt;', $record['message']);

            // Put the stack trace inside <code></code> tags
            $record['message'] = preg_replace('/^Stack trace:\n((^#\d.*\n?)*)$/m', "\n<b>Stack trace:</b>\n<code>$1</code>", $record['message']);
        }

        $message = str_replace('%message%', $record['message'], $message);

        if ($record['context']) {
            $context = '<b>Context:</b> ';
            $context .= $lineFormatter->stringify($record['context']);
            $message = str_replace('%context%', $context . "\n", $message);
        } else {
            $message = str_replace('%context%', '', $message);
        }

        if ($record['extra']) {
            $extra = '<b>Extra:</b> ';
            $extra .= $lineFormatter->stringify($record['extra']);
            $message = str_replace('%extra%', $extra . "\n", $message);
        } else {
            $message = str_replace('%extra%', '', $message);
        }

        $message = str_replace(['%level_name%', '%channel%', '%date%'], [$record['level_name'], $record['channel'], $record['datetime']->format($this->dateFormat)], $message);

        if ($this->html === false) {
            $message = strip_tags($message);
        }

        return $message;
    }

    private function getSeverityName($key)
    {
        $severities = [
            1 => 'ERROR',
            2 => 'WARNING',
            4 => 'PARSE',
            8 => 'NOTICE',
            16 => 'CORE_ERROR',
            32 => 'CORE_WARNING',
            64 => 'COMPILE_ERROR',
            128 => 'COMPILE_WARNING',
            256 => 'USER_ERROR',
            512 => 'USER_WARNING',
            1024 => 'USER_NOTICE',
            2048 => 'STRICT',
            4096 => 'RECOVERABLE_ERROR',
            8192 => 'DEPRECATED',
            16384 => 'USER_DEPRECATED',
        ];
        if (isset($severities[$key])) {
            return $severities[$key];
        }
        return '';
    }
}
