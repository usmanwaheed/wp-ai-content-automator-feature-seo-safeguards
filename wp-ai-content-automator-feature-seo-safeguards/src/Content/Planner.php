<?php
namespace AICA\Content;

use AICA\Domain\ContentStrategy;
use AICA\Utils\TopicQueue;
use AICA\Utils\EmbeddingService;
use AICA\Utils\ContentHelper;
use AICA\Utils\ImageGenerator;
use WP_Error;

/**
 * Generates posts based on content strategies.
 */
class Planner
{
    private const MAX_DUP_RETRY = 2;
    private const MAX_ANGLE_DEPTH = 4;   // recursion guard
    private const ADMIN_NOTICE_OPTION = 'aica_show_ai_errors';

    public function execute(): void
    {
        $strategies = array_map(
            fn (array $raw) => new ContentStrategy($raw),
            get_option('aica_strategies', [])
        );

        foreach ($strategies as $idx => $s) {
            if ($this->shouldRunNow($s)) {
                $this->runForStrategy($s, $idx);
            }
        }
    }

    /**
     * Execute a single strategy (called by new scheduler)
     */
    public function execute_strategy(ContentStrategy $s): void
    {
        $this->runForStrategy($s, 0); // Use 0 as default strategy ID
    }

    /*──────────  Helpers  ──────────*/
    private function shouldRunNow(ContentStrategy $s): bool
    {
        $now      = new \DateTime('now', wp_timezone());
        $timeGood = $now->format('H:i') === $s->publish_time;

        return $timeGood && (
            $s->recurrence === 'daily' ||
            ($s->recurrence === 'weekly'  && in_array(strtolower($now->format('D')), $s->weekdays, true)) ||
            ($s->recurrence === 'monthly' && (int)$now->format('j') === $s->month_day) ||
            ($s->recurrence === 'interval' && $this->isIntervalToday($s, $now))
        );
    }

    private function isIntervalToday(ContentStrategy $s, \DateTime $today): bool
    {
        if (!$s->start_date || $s->interval_days === 0) {
            return false;
        }
        $start = \DateTime::createFromFormat('Y-m-d', $s->start_date, wp_timezone());
        if (!$start) return false;
        $diff = $start->diff($today)->days;
        return $diff % $s->interval_days === 0;
    }

    /**
     * Core runner with duplicate-retry & angle cycling.
     */
    private function runForStrategy(ContentStrategy $s, int $strategyId, int $depth = 0): void
    {
        if ($depth > self::MAX_ANGLE_DEPTH) {
            error_log("[AICA] Recursion depth exceeded for strategy {$strategyId}");
            return;
        }

        /* ── 1. Pull next unused angle ─────────────────────────── */
        $angle = TopicQueue::getNextTopic($strategyId);   // null if queue empty

        /* ── 2. Build analysis payload ─────────────────────────── */
        $analysis = $this->analysePillar($s, $angle);

        /* ── 3. Duplicate-retry loop ───────────────────────────── */
        $attempts = 0;
        $uniqueDraft = null;

        while ($attempts <= self::MAX_DUP_RETRY) {
            // Pre-flight check before generate
            if (!$s->tone || !$s->source_url) {
                error_log('[AICA] Strategy missing required fields: tone=' . ($s->tone ?? 'null') . ', source_url=' . ($s->source_url ?? 'null'));
                return;
            }
            
            $draft = (new Generator())->createPost($analysis, [
                'category_id' => $s->category_id,
                'min_words'   => $s->min_words,
                'max_words'   => $s->max_words,
                'tone'        => $s->tone,
                'model'       => get_option(\AICA\Plugin::OPTION_KEY)['model'] ?? 'gpt-4o-mini',
            ]);

            if (!$draft) {
                error_log('[AICA] Empty draft payload—aborting.');
                return;
            }

            $h1h2 = ContentHelper::extractHeadings($draft['post_content'] ?? '');
            $emb  = EmbeddingService::embedText($h1h2);

            if (EmbeddingService::isUnique($s->category_id, $emb)) {
                $uniqueDraft = $draft;
                break;
            }
            $attempts++;
        }

        /* ── 4. Handle failure ─────────────────────────────────── */
        if (!$uniqueDraft) {
            // exhausted retries for this angle
            if ($angle) {
                // cycle to next angle and recurse
                $this->runForStrategy($s, $strategyId, $depth + 1);
            } else {
                // no angles left – log and maybe show admin notice
                $msg = "Strategy {$strategyId}: Failed to produce unique content.";
                error_log('[AICA] ' . $msg);
                if (get_option(self::ADMIN_NOTICE_OPTION)) {
                    set_transient('aica_angle_error', $msg, 3600);
                }
            }
            return;
        }

        /* ── 5. Insert post & flag embedding ───────────────────── */
        $postId = wp_insert_post($uniqueDraft, true);
        if (!is_wp_error($postId)) {
            update_post_meta($postId, '_aica_generated', current_time('mysql'));
            
            // Fire content published hook for Phase 2-3 integrations
            do_action('aica_content_published', $postId);
            
            // Process images if enabled
            if (!empty($s->enable_images)) {
                $this->processImages($postId, $uniqueDraft, $s);
            }
        }
    }

    /**
     * Get the similarity threshold used by EmbeddingService
     */
    private function getSimilarityThreshold(): int
    {
        $settings = get_option('aica_settings', []);
        // Return the configured threshold or default to 85%
        return isset($settings['similarity_threshold']) 
            ? (int)$settings['similarity_threshold'] 
            : 85;
    }

    /**
     * Process image generation and insertion for a post
     */
    private function processImages(int $postId, array $draft, ContentStrategy $s): void
    {
        $content = $draft['post_content'];
        $generator = new ImageGenerator();
        
        // Find image placeholders
        preg_match_all('/\[IMAGE_(\d+)\]/', $content, $matches, PREG_SET_ORDER);
        
        if (empty($matches)) {
            return;
        }
        
        $firstImageId = null;
        $processedImages = [];
        
        // Process each placeholder
        foreach ($matches as $match) {
            $placeholder = $match[0];
            $imageNumber = (int)$match[1];
            
            // Generate contextual prompt based on post title and content
            $prompt = $this->createImagePrompt($draft['post_title'], $s, $imageNumber);
            
            $imageUrl = $generator->generate($prompt);
            if (!$imageUrl) {
                // Remove placeholder if generation failed
                $content = str_replace($placeholder, '', $content);
                continue;
            }
            
            // Download and upload to WordPress media library
            $imageId = $this->uploadImageToMedia($imageUrl, $postId, $draft['post_title']);
            if (!$imageId) {
                $content = str_replace($placeholder, '', $content);
                continue;
            }
            
            // Store first image for featured image (IMAGE_1)
            if ($imageNumber === 1 && $firstImageId === null) {
                $firstImageId = $imageId;
            }
            
            $processedImages[$imageNumber] = $imageId;
        }
        
        // Apply smart placement for images 2+ if AI didn't place them optimally
        $content = $this->applySmartImagePlacement($content, $processedImages);
        
        // Update post content with images
        wp_update_post([
            'ID' => $postId,
            'post_content' => $content
        ]);
        
        // Set featured image
        if ($firstImageId) {
            set_post_thumbnail($postId, $firstImageId);
        }
    }
    
    /**
     * Apply smart placement algorithm for images 2+ that weren't placed by AI
     */
    private function applySmartImagePlacement(string $content, array $processedImages): string
    {
        // Replace IMAGE_1 first (featured image, can be anywhere AI placed it)
        if (isset($processedImages[1])) {
            $imageHtml = wp_get_attachment_image($processedImages[1], 'large', false, [
                'class' => 'aica-generated-image aica-featured-inline',
                'alt' => 'Featured illustration'
            ]);
            $content = str_replace('[IMAGE_1]', $imageHtml, $content);
        }
        
        // For images 2+, apply smart placement if AI didn't place them well
        $remainingImages = array_filter($processedImages, fn($key) => $key > 1, ARRAY_FILTER_USE_KEY);
        
        if (empty($remainingImages)) {
            return $content;
        }
        
        // Find optimal placement positions
        $placements = $this->findOptimalImagePlacements($content, count($remainingImages));
        
        $imageIndex = 0;
        foreach ($remainingImages as $imageNumber => $imageId) {
            $placeholder = "[IMAGE_{$imageNumber}]";
            
            // If AI already placed it, replace with HTML
            if (strpos($content, $placeholder) !== false) {
                $imageHtml = wp_get_attachment_image($imageId, 'large', false, [
                    'class' => 'aica-generated-image',
                    'alt' => "Illustration {$imageNumber}"
                ]);
                $content = str_replace($placeholder, $imageHtml, $content);
            } else {
                // AI didn't place it, use smart placement
                if (isset($placements[$imageIndex])) {
                    $imageHtml = wp_get_attachment_image($imageId, 'large', false, [
                        'class' => 'aica-generated-image aica-smart-placed',
                        'alt' => "Illustration {$imageNumber}"
                    ]);
                    $content = $this->insertImageAtPosition($content, $imageHtml, $placements[$imageIndex]);
                }
                $imageIndex++;
            }
        }
        
        return $content;
    }
    
    /**
     * Find optimal positions for image placement in content
     */
    private function findOptimalImagePlacements(string $content, int $imageCount): array
    {
        if ($imageCount === 0) {
            return [];
        }
        
        // Find all H2 headings as potential placement points
        preg_match_all('/<h2[^>]*>.*?<\/h2>/i', $content, $h2Matches, PREG_OFFSET_CAPTURE);
        
        // Find all paragraph breaks as secondary placement points
        preg_match_all('/<\/p>\s*<p[^>]*>/i', $content, $pMatches, PREG_OFFSET_CAPTURE);
        
        $placements = [];
        $contentLength = strlen($content);
        
        if (!empty($h2Matches[0])) {
            // Strategy: Place images after H2 sections, distributed evenly
            $h2Positions = array_column($h2Matches[0], 1);
            
            // Skip first H2 (too early), distribute among remaining
            $usableH2s = array_slice($h2Positions, 1);
            
            if (count($usableH2s) >= $imageCount) {
                // We have enough H2s, distribute evenly
                $step = max(1, floor(count($usableH2s) / $imageCount));
                for ($i = 0; $i < $imageCount; $i++) {
                    $h2Index = $i * $step;
                    if (isset($usableH2s[$h2Index])) {
                        $placements[] = $usableH2s[$h2Index] + strlen($h2Matches[0][$h2Index + 1][0]);
                    }
                }
            } else {
                // Not enough H2s, use all available H2s then fill with paragraph breaks
                foreach ($usableH2s as $pos) {
                    $placements[] = $pos + strlen($h2Matches[0][array_search($pos, $h2Positions)][0]);
                }
                
                // Fill remaining with paragraph positions
                $remaining = $imageCount - count($placements);
                if ($remaining > 0 && !empty($pMatches[0])) {
                    $pPositions = array_column($pMatches[0], 1);
                    $step = max(1, floor(count($pPositions) / $remaining));
                    for ($i = 0; $i < $remaining && isset($pPositions[$i * $step]); $i++) {
                        $placements[] = $pPositions[$i * $step];
                    }
                }
            }
        } else {
            // No H2s found, distribute evenly through content using paragraph breaks
            if (!empty($pMatches[0])) {
                $pPositions = array_column($pMatches[0], 1);
                $step = max(1, floor(count($pPositions) / $imageCount));
                for ($i = 0; $i < $imageCount && isset($pPositions[$i * $step]); $i++) {
                    $placements[] = $pPositions[$i * $step];
                }
            }
        }
        
        return array_slice($placements, 0, $imageCount);
    }
    
    /**
     * Insert image HTML at specific position in content
     */
    private function insertImageAtPosition(string $content, string $imageHtml, int $position): string
    {
        // Wrap image in a paragraph for proper formatting
        $wrappedImage = "\n\n<p class=\"aica-image-container\">{$imageHtml}</p>\n\n";
        
        return substr_replace($content, $wrappedImage, $position, 0);
    }

    /**
     * Create an image generation prompt based on post context
     */
    private function createImagePrompt(string $title, ContentStrategy $s, int $imageNumber = 1): string
    {
        $basePrompt = "Create a professional illustration for a blog post titled '{$title}'";
        
        if (!empty($s->kw_primary)) {
            $basePrompt .= " focusing on {$s->kw_primary}";
        }
        
        // Vary prompts for different image numbers
        if ($imageNumber === 1) {
            $basePrompt .= " (main featured image)";
        } else {
            $basePrompt .= " (supporting illustration #{$imageNumber})";
        }
        
        $basePrompt .= ". Style: clean, modern, suitable for {$s->tone} business content.";
        
        return $basePrompt;
    }
    
    /**
     * Upload image from URL to WordPress media library
     */
    private function uploadImageToMedia(string $imageUrl, int $postId, string $title): ?int
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $imageId = media_sideload_image($imageUrl, $postId, $title, 'id');
        
        if (is_wp_error($imageId)) {
            error_log('[AICA] Failed to upload image: ' . $imageId->get_error_message());
            return null;
        }
        
        return $imageId;
    }

    /**
     * Crawl pillar, keywords, summary – existing logic extracted for clarity.
     */
    private function analysePillar(ContentStrategy $s, ?string $angle): array
    {
        $html = wp_remote_retrieve_body(wp_remote_get($s->source_url));
        $pageText = $html ? wp_strip_all_tags($html) : '';

        // keyword extraction
        if (!$s->kw_primary) {
            $kw = (new KeywordExtractor())->extract($pageText);
            $s->kw_primary   = $kw['primary'];
            $s->kw_secondary = implode(', ', $kw['secondary']);
        }

        $summary = (new MiniSummariser())->summarise($pageText);
        
        // Get similarity threshold from settings
        $similarityThreshold = $this->getSimilarityThreshold();

        return [
            'primary_kw'   => $s->kw_primary,
            'secondary_kw' => $s->kw_secondary,
            'h2_topics'    => $angle ?: 'Key benefits, How it works, FAQ',
            'angle'        => $angle ?? '',
            'page_summary' => $summary,
            'similarity_threshold' => $similarityThreshold,
        ];
    }
}
