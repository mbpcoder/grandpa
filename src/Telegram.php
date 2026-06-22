<?php

declare(strict_types=1);

namespace Grandpa;

class Telegram
{
    private string $message = '';
    private string|null $chatId = null;
    private string|null $topicId = null;

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        private readonly Http $http = new Http(),
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            (string) env('GRANDPA_TELEGRAM_BOT_TOKEN', ''),
            (string) env('GRANDPA_TELEGRAM_BASE_URL', 'https://api.telegram.org'),
        );
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function to(string $chatId): self
    {
        $this->chatId = $chatId;

        return $this;
    }

    public function topic(string $topicId): self
    {
        $this->topicId = $topicId;

        return $this;
    }

    public function send(): string
    {
        if ($this->token === '') {
            throw new \RuntimeException('GRANDPA_TELEGRAM_BOT_TOKEN is not configured.');
        }

        $chatId = $this->chatId ?? (string) env('GRANDPA_TELEGRAM_CHAT_ID', '');

        if ($chatId === '') {
            throw new \RuntimeException('Telegram chat id is not configured.');
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $this->message,
        ];

        $topicId = $this->topicId ?? (string) env('GRANDPA_TELEGRAM_TOPIC_ID', '');

        if ($topicId !== '') {
            $payload['message_thread_id'] = $topicId;
        }

        return $this->http->post("{$this->baseUrl}/bot{$this->token}/sendMessage", [
            'form_params' => $payload,
        ]);
    }
}
