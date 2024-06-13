<?php

namespace TheCoder\MonologTelegram\Attributes;

abstract class AbstractTopicLevelAttribute implements TopicLogInterface
{
    public function getTopicID(array $topicsLevel): string|null
    {
        return $topicsLevel[static::class] ?? null;
    }
}
