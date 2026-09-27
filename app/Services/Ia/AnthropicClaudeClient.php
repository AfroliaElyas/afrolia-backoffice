<?php

namespace App\Services\Ia;

use Anthropic\Client;

class AnthropicClaudeClient implements ClaudeClientInterface
{
    private const MODELE = 'claude-haiku-4-5';
    private const MAX_TOKENS = 1024;

    public function __construct(private readonly Client $client)
    {
    }

    public function repondre(string $promptSysteme, string $question): string
    {
        $message = $this->client->messages->create(
            model: self::MODELE,
            maxTokens: self::MAX_TOKENS,
            system: $promptSysteme,
            messages: [
                ['role' => 'user', 'content' => $question],
            ],
        );

        foreach ($message->content as $bloc) {
            if ($bloc->type === 'text') {
                return $bloc->text;
            }
        }

        return '';
    }
}
