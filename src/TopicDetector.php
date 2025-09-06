<?php

namespace TheCoder\MonologTelegram;

use ReflectionMethod;
use TheCoder\MonologTelegram\Attributes\TopicLogInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Route;

class TopicDetector
{
    private array $topicsLevel;
    private mixed $exception;
    private mixed $trace;

    public function __construct(array $topicsLevel)
    {
        $this->topicsLevel = $topicsLevel;
    }

    public function getTopicByAttribute(mixed $record): string|null
    {
        if ($this->appRunningWithRequest()) {
            return $this->getTopicByRoute();
        }

        if (isset($record['context']['exception'])) {
            $this->exception = $record['context']['exception'];
            $this->trace = $this->exception->getTrace();

            if ($this->appRunningWithCommand()) {
                return $this->getTopicIdByCommand();
            }

            if ($this->appRunningWithJob()) {
                return $this->getTopicIdByJob();
            }
        }

        return null;
    }

    // Route Function
    private function appRunningWithRequest(): bool
    {
        return Route::current() !== null;
    }

    private function getTopicByRoute(): string|null
    {
        $topicId = null;
        $route = Route::current();

        if (!isset($route->getAction()['controller'])) {
            return null;
        }

        if ($this->isLivewire() && app('livewire')->isLivewireRequest()) {
            [$controller, $method] = $this->getMainLivewireClass();
        } else {
            [$controller, $method] = explode('@', $route->getAction()['controller']);
        }

        if ($controller !== null) {
            $topicId = $this->getTopicIdByReflection($controller, $method);

            if ($topicId === false) {
                $topicId = $this->getTopicIdByRegex($controller, $method);
            }
        }

        return $topicId;
    }

    // Job function
    private function appRunningWithJob(): bool
    {
        return (isset($e->job) || app()->bound('queue.worker'));
    }

    private function getJobClass(): string|null
    {
        if (!app()->bound('queue.worker')) {
            return null;
        }

        foreach ($this->trace as $frame) {
            if ($frame['function'] === 'handle' && isset($frame['class']) && str_contains($frame['class'], 'App\Jobs')) {
                return $frame['class'];
            }
        }

        return null;
    }

    private function getTopicIdByJob(): string|int|null
    {
        $topicId = null;
        $jobClass = $this->getJobClass();

        if ($jobClass !== null) {
            $topicId = $this->getTopicIdByReflection($jobClass, 'handle');

            if ($topicId === false) {
                $topicId = $this->getTopicIdByRegex($jobClass, 'handle');
            }
        }

        return $topicId;
    }

    // Command function
    private function appRunningWithCommand(): bool
    {
        return app()->runningInConsole();
    }

    private function getCommandClass(): string|null
    {
        $filePath = $this->exception->getFile();

        if (str_contains($filePath, 'Console\Commands')) {
            $appPosition = strpos($filePath, 'app');

            if ($appPosition !== false) {
                $appPath = substr($filePath, $appPosition);
                return str_replace(['/', 'app', '.php'], ['\\', 'App', ''], $appPath);
            }
        }

        foreach ($this->trace as $frame) {
            if ($frame['function'] === 'handle' && isset($frame['class']) && str_contains($frame['class'], 'Console\Commands')) {
                return $frame['class'];
            }
        }

        return null;
    }

    private function getTopicIdByCommand(): string|int|null
    {
        $topicId = null;
        $commandClass = $this->getCommandClass();

        if ($commandClass !== null) {
            $topicId = $this->getTopicIdByReflection($commandClass, 'handle');

            if ($topicId === false) {
                $topicId = $this->getTopicIdByRegex($commandClass, 'handle');
            }
        }

        return $topicId;
    }

    // General function
    private function getTopicIdByReflection(string $class, string $method): string|int|null|bool
    {
        try {
            $reflectionMethod = new ReflectionMethod($class, $method);

            $attributes = $reflectionMethod->getAttributes();

            if (count($attributes) !== 0 && $attributes[0] !== null) {
                /** @var TopicLogInterface $notifyException */
                $notifyException = $attributes[0]->newInstance() ?? null;
                return $notifyException->getTopicId($this->topicsLevel);
            }

        } catch (\Throwable $e) {

        }

        return false;
    }

    private function getTopicIdByRegex(string $class, string $method): string|int|null
    {
        try {
            $filePath = base_path(str_replace('App', 'app', $class) . '.php');
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

    private function isLivewire(): bool
    {
        return class_exists(\Livewire\Livewire::class);
    }

    protected function getMainLivewireClass(): array
    {
        $class = null;
        $method = null;

        try {
            $payload = request()->all();

            if (isset($payload['components'][0])) {
                $componentData = $payload['components'][0];
                $snapshot = json_decode($componentData['snapshot'], true);
                $componentName = $snapshot['memo']['name'] ?? null;
                $method = $componentData['calls'][0]['method'] ?? null;

                $rootNamespace = config('livewire.class_namespace');

                $class = collect(str($componentName)->explode('.'))
                    ->map(fn($segment) => (string)str($segment)->studly())
                    ->join('\\');

                if (!empty($rootNamespace)) {
                    $class = '\\' . $rootNamespace . '\\' . $class;
                }
            }
        } catch (\Throwable $exception) {
            //report($exception);
        }

        return [$class, $method];
    }
}
