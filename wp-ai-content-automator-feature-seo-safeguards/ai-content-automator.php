<?php
/**
 * Plugin Name: AI Content Automator
 * Description: Schedule & publish AI-generated, SEO-optimised blog posts.
 * Version: 0.3.0
 * Requires PHP: 8.1
 * Author: Roman
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

/*──────────────────────────
  1. Composer autoloader (handles all dependencies)
  ──────────────────────────*/
require_once __DIR__ . '/vendor/autoload.php';

/*──────────────────────────
  3. Action Scheduler loader
  ──────────────────────────*/
if ( ! class_exists('\ActionScheduler') ) {
    require_once __DIR__ .
        '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

/*──────────────────────────
  4. TopicQueue table setup
──────────────────────────*/
register_activation_hook(__FILE__, function() {
    \AICA\Utils\TopicQueue::createTable();
});

/*──────────────────────────
  5. Embedding Hooks
──────────────────────────*/
require_once __DIR__ . '/src/Hooks/EmbeddingHooks.php';

/*──────────────────────────
  6. Boot the plugin
──────────────────────────*/
if (class_exists('\AICA\Plugin')) {
    AICA\Plugin::boot(__FILE__);
}

/*──────────────────────────
  7. Phase 2-3 hooks with dependency checking
──────────────────────────*/
use AICA\Core\DependencyChecker;
use AICA\SEO\SchemaInjector;
use AICA\SEO\RobotsManager;
use AICA\API\AuditEndpoint;

// Initialize SEO components only after dependency check
add_action('plugins_loaded', function() {
    // Always initialize basic components
    add_action('template_redirect', [RobotsManager::class, 'add_robots_header']);
    // add_action('rest_api_init', [AuditEndpoint::class, 'register']); // Commented out - Phase 3 not ready
    
    // Only initialize schema components if dependencies are available
    if (DependencyChecker::check()) {
        try {
            $schemaInjector = new SchemaInjector();
            add_action('aica_content_published', [$schemaInjector, 'inject'], 20);
            add_filter('rank_math/json_ld', [$schemaInjector, 'inject_to_rankmath'], 20);
            add_action('wp_head', [$schemaInjector, 'output_json_ld'], 30);
        } catch (\Exception $e) {
            error_log('[AICA] Failed to initialize SchemaInjector: ' . $e->getMessage());
        }
    }
}, 5);

// Register query-var for blocked content
add_action('init', function () {
    add_rewrite_tag('%aica_blocked%', '1');
});
