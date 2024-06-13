<?php

namespace TheCoder\MonologTelegram\Attributes;

interface TopicLogInterface
{
    public function getTopicID(array $topicsLevel): string|null;
}
