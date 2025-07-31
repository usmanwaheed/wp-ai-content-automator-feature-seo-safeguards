<?php
namespace AICA\Content;

use GuzzleHttp\Client;
use RuntimeException;

/**
 * Turns a free-text summary into structured domain tags.
 * 1. GPT-3.5-turbo: returns {"expertise":["algo trading",...],"audience":["retail traders",...]}
 * 2. Fallback: simple TF-IDF to guess top nouns.
 */
final class DomainInsightsExtractor
{
    public function extract(string $summary): array
    {
        $prompt = <<<PROMPT
Identify up to six expertise or domain keywords and up to three target-audience descriptors
from the text below. Respond with JSON\n{"expertise":[...],"audience":[...]} ONLY.

TEXT:
{$summary}
PROMPT;

        try {
            $client = new Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'headers'  => [
                    'Authorization' => 'Bearer ' . $this->getApiKey(),
                    'Content-Type'  => 'application/json',
                ],
                'timeout'  => 3000,
            ]);

            $res = $client->post('chat/completions', [
                'json' => [
                    'model'    => 'gpt-4.1-mini',
                    'messages' => [['role'=>'user','content'=>$prompt]],
                    'max_tokens' => 120,
                ],
            ]);

            $body = $res->getBody()->getContents();
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            $jsonContent = $data['choices'][0]['message']['content'] ?? '';
            $json = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

            if (
                is_array($json)
                && !empty($json['expertise'])
                && !empty($json['audience'])
            ) {
                return [
                    'expertise' => $json['expertise'],
                    'audience'  => $json['audience'],
                ];
            }

            throw new RuntimeException('GPT extraction failed');
        } catch (\Throwable $e) {
            return $this->fallback($summary);
        }
    }

    /*──────────  Helpers  ──────────*/
    private function fallback(string $txt): array
    {
        $words = array_filter(
            preg_split('/[^a-z0-9]+/i', strtolower($txt)),
            fn(string $w): bool => strlen($w) > 3
        );

        $freq = array_count_values($words);
        arsort($freq);
        $top = array_slice(array_keys($freq), 0, 9);

        return [
            'expertise' => array_slice($top, 0, 6),
            'audience'  => array_slice($top, 6, 3),
        ];
    }

    private function getApiKey(): string
    {
        $opts = get_option(\AICA\Plugin::OPTION_KEY, []);
        return is_array($opts) && isset($opts['openai_key'])
            ? $opts['openai_key']
            : '';
    }
}