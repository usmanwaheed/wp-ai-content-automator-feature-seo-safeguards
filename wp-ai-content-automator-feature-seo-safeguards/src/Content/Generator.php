<?php
namespace AICA\Content;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use AICA\Content\PromptBuilder;

final class Generator
{
    private const MAX_RETRIES = 3;
    private Client $http;
    private static int $failCount = 0;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type'  => 'application/json',
            ],
            'timeout'  => 60,
        ]);
    }

    public function createPost(array $analysis, array $settings): array
    {
        try {
            $body = $this->callOpenAI($analysis, $settings);
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            $content = $data['choices'][0]['message']['content'] ?? '';
            if (!$content) {
                throw new \RuntimeException('OpenAI response empty');
            }

            $title = $this->extract($content, 'TITLE');
            $slug  = sanitize_title($this->extract($content, 'SLUG'));
            $body  = $this->sanitizeContent($this->extract($content, 'BODY'));
            $tags  = array_map('trim', explode(',', $this->extract($content, 'TAGS')));
            $desc  = $this->extract($content, 'SEO_DESCRIPTION');

            $catID = intval($settings['category_id'] ?? get_option('default_category'));

            return [
                'post_title'    => $title,
                'post_name'     => $slug,
                'post_content'  => $body,
                'post_status'   => 'draft',
                'post_category' => [$catID],
                'comment_status'=> 'closed',
                'ping_status'   => 'closed',
                'tags_input'    => $tags,
                'meta_input'    => [
                    'rank_math_description'          => $desc,
                    'rank_math_facebook_description' => $desc,
                    'rank_math_twitter_description'  => $desc,
                ],
            ];
        } catch (\Throwable $e) {
            error_log('[AICA] Generator error: ' . $e->getMessage());
            return [];
        }
    }

    /*────────  PRIVATE  ────────*/
    private function callOpenAI(array $analysis, array $settings): string
    {
        if (self::$failCount > 3) {
            throw new \RuntimeException('OpenAI breaker - too many failures');
        }

        $prompt = $this->buildPrompt($analysis, $settings);

        $model = $settings['model'] ?? 'gpt-4.1-mini';
        if (strpos($model, 'gpt-') !== 0) {
            $model = 'gpt-4.1-mini';
        }

        for ($retry = 0; $retry < self::MAX_RETRIES; $retry++) {
            try {
                $res = $this->http->post('chat/completions', [
                    'json' => [
                        'model'    => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are an expert SEO blogger…'],
                            ['role' => 'user',   'content' => $prompt],
                        ],
                    ],
                ]);

                self::$failCount = 0;
                return $res->getBody()->getContents();
            } catch (RequestException $e) {
                if ($e->getCode() === 429) {
                    sleep(1 << $retry);
                    continue;
                }
                ++self::$failCount;
                throw $e;
            }
        }

        ++self::$failCount;
        throw new \RuntimeException('Exceeded retry limit');
    }

    private function buildPrompt(array $analysis, array $settings): string
    {
        $domainProfile = get_option('aica_domain_profile', []);
        return (new PromptBuilder())->build($settings, $domainProfile, $analysis);
    }

    private function sanitizeContent(string $html): string
    {
        $allowed       = wp_kses_allowed_html('post');
        $allowed['h2'] = ['id' => true];
        return wp_kses(wp_unslash($html), $allowed);
    }

    private function extract(string $txt, string $tag): string
    {
        return preg_match("/\\[$tag]\\s*(.*?)\\s*(?=\\[|$)/s", $txt, $m) ? trim($m[1]) : '';
    }

    private function getApiKey(): string
    {
        $opts = get_option(\AICA\Plugin::OPTION_KEY, []);
        return $opts['openai_key'] ?? '';
    }
}
