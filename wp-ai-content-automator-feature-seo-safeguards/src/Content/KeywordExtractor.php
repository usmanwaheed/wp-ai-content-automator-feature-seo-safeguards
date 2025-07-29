<?php
namespace AICA\Content;

use GuzzleHttp\Client;

/**
 * Extracts primary and secondary keywords from plain text.
 * 1. Tries GPT-3.5-turbo (cheap / reliable) with a strict JSON schema.
 * 2. Falls back to simple TF-IDF heuristic.
 */
final class KeywordExtractor
{
    public function extract(string $text): array
    {
        // Limit to ~4k chars for token cost
        $text = mb_substr($text, 0, 4000);
        $prompt = <<<PROMPT
Extract the single best primary keyword and up to three secondary keywords
from the text below. Return JSON EXACTLY like:
{
  "primary": "one keyword",
  "secondary": ["kw1","kw2","kw3"]
}

TEXT:
{$text}
PROMPT;

        try {
            $client = new Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'headers'  => [
                    'Authorization' => 'Bearer ' . $this->getApiKey(),
                    'Content-Type'  => 'application/json',
                ],
                'timeout'  => 20,
            ]);

            $res = $client->post('chat/completions', [
                'json' => [
                    'model'    => 'gpt-3.5-turbo-0125',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 120,
                ],
            ]);

            $data = json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $json = json_decode($data['choices'][0]['message']['content'] ?? '', true);

            if (!empty($json['primary'])) {
                return [
                    'primary'   => $json['primary'],
                    'secondary' => $json['secondary'] ?? [],
                ];
            }
            throw new \RuntimeException('GPT response invalid');
        } catch (\Throwable $e) {
            // Fallback: naive TF-IDF
            return $this->tfidf($text);
        }
    }

    /*──────────  Private  ──────────*/
    private function tfidf(string $text): array
    {
        $words = array_count_values(
            array_filter(
                preg_split('/[^a-z0-9]+/i', strtolower($text)),
                fn($w) => strlen($w) > 3
            )
        );
        arsort($words);
        $top = array_keys(array_slice($words, 0, 4, true));
        return [
            'primary'   => $top[0] ?? '',
            'secondary' => array_slice($top, 1),
        ];
    }

    private function getApiKey(): string
    {
        $opts = get_option(\AICA\Plugin::OPTION_KEY, []);
        return $opts['openai_key'] ?? '';
    }
}
