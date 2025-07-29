<?php
// In src/Hooks/EmbeddingHooks.php
namespace AICA\Hooks;

use AICA\Utils\EmbeddingService;

// Save OpenAI embedding for each published post
add_action('save_post', function(int $postId) {
    if (wp_is_post_revision($postId) || get_post_status($postId) !== 'publish') return;
    if (get_post_meta($postId, '_aica_embedding', true)) return;

    $content = get_post_field('post_content', $postId);
    $h1h2    = \AICA\Utils\ContentHelper::extractHeadings($content);
    $emb     = EmbeddingService::embedText($h1h2);
    EmbeddingService::storeEmbedding($postId, $emb);
});