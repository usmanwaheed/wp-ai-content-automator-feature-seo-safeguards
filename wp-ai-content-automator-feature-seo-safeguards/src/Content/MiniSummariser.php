<?php
namespace AICA\Content;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MiniSummariser
{
    /** Summarise text to ≈150 words. Never throws: falls back to wp_trim_words(). */
    public function summarise(string $text): string
    {
        $text = mb_substr($text, 0, 4000);   // keep token cost low
        $prompt = "Summarise the following text in ~150 words:\n\n" . $text;

        try {
            $client = new Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'headers'  => [
                    'Authorization' => 'Bearer ' . $this->getApiKey(),
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 25,
            ]);

            $res = $client->post('chat/completions', [
                'json' => [
                    'model'    => 'gpt-3.5-turbo-0125',   // widely available, cheap
                    'messages' => [
                        ['role'=>'user', 'content'=> $prompt],
                    ],
                    'max_tokens' => 220,
                ],
            ]);

            $data = json_decode($res->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            if (!empty($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }
            throw new \RuntimeException('Empty response');
        } catch (\Throwable $e) {
            // Log and fall back to simple trim so workflow never breaks
            error_log('[AICA] MiniSummariser fallback: ' . $e->getMessage());
            return wp_trim_words($text, 150);
        }
    }

    private function getApiKey(): string
    {
        $opts = get_option(\AICA\Plugin::OPTION_KEY, []);
        return $opts['openai_key'] ?? '';
    }
}
