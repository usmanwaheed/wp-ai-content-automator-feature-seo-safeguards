<?php
// File: src/Utils/EmbeddingService.php
namespace AICA\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class EmbeddingService
{
    private static ?Client $client = null;

    private static function getClient(): Client
    {
        if (self::$client === null) {
            $apiKey = self::getApiKey();
            self::$client = new Client([
                'base_uri' => 'https://api.openai.com/v1/',
                'headers'  => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'timeout'  => 30,
            ]);
        }
        return self::$client;
    }

    /**
     * Generate an embedding vector for given text.
     */
    public static function embedText(string $text): array
    {
        try {
            $response = self::getClient()->post('embeddings', [
                'json' => [
                    'model' => 'text-embedding-ada-002',
                    'input' => $text,
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            return $data['data'][0]['embedding'] ?? [];
        } catch (\Throwable $e) {
            error_log('[AICA] EmbeddingService error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get API key using consistent method
     */
    private static function getApiKey(): string
    {
        $opts = get_option(\AICA\Plugin::OPTION_KEY, []);
        return $opts['openai_key'] ?? '';
    }

    /**
     * Compute cosine similarity between two vectors.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] ** 2;
            $nb  += $b[$i] ** 2;
        }
        if ($na === 0.0 || $nb === 0.0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }

    /**
     * Store embedding vector in post meta.
     */
    public static function storeEmbedding(int $postId, array $embedding): void
    {
        update_post_meta($postId, '_aica_embedding', $embedding);
    }

    /**
     * Check if a new embedding is unique for a given pillar.
     */
    public static function isUnique(int $pillarId, array $newEmb, ?float $threshold = null): bool
    {
        // Get threshold from settings or use default 0.85
        if ($threshold === null) {
            $settings = get_option('aica_settings', []);
            $threshold = isset($settings['similarity_threshold']) 
                ? (float)($settings['similarity_threshold'] / 100) 
                : 0.85;
        }

        $posts = get_posts([
            'post_type'      => 'post',
            'post_parent'    => $pillarId,
            'meta_key'       => '_aica_embedding',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($posts as $pid) {
            $old = (array) get_post_meta($pid, '_aica_embedding', true);
            if ($old && self::cosineSimilarity($newEmb, $old) > $threshold) {
                return false;
            }
        }
        return true;
    }
}
