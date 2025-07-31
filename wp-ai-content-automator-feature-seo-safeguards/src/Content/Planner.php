<?php
namespace AICA\Content;

use AICA\Domain\ContentStrategy;
use AICA\Utils\TopicQueue;
use AICA\Utils\EmbeddingService;
use AICA\Utils\ContentHelper;
use AICA\Utils\ImageGenerator;
use WP_Error;

class Planner
{
    private const MAX_DUP_RETRY = 2;
    private const MAX_ANGLE_DEPTH = 4;
    private const MAX_TOTAL_RETRY = 3;
    private const ADMIN_NOTICE_OPTION = 'aica_show_ai_errors';

    public function execute(): void
    {
        $strategiesRaw = get_option('aica_strategies', []);
        $strategies = array_map(
            fn(array $raw): ContentStrategy => new ContentStrategy($raw),
            is_array($strategiesRaw) ? $strategiesRaw : []
        );

        foreach ($strategies as $idx => $s) {
            if ($this->shouldRunNow($s)) {
                $this->runForStrategy($s, $idx);
            }
        }
    }

    public function execute_strategy(ContentStrategy $s): void
    {
        $this->runForStrategy($s, 0);
    }

    private function shouldRunNow(ContentStrategy $s): bool
    {
        $now = new \DateTime('now', wp_timezone());
        $timeGood = $now->format('H:i') === $s->publish_time;

        return $timeGood && (
            $s->recurrence === 'daily' ||
            ($s->recurrence === 'weekly' && in_array(strtolower($now->format('D')), $s->weekdays, true)) ||
            ($s->recurrence === 'monthly' && (int)$now->format('j') === $s->month_day) ||
            ($s->recurrence === 'interval' && $this->isIntervalToday($s, $now))
        );
    }

    private function isIntervalToday(ContentStrategy $s, \DateTime $today): bool
    {
        if (empty($s->start_date) || $s->interval_days === 0) {
            return false;
        }
        $start = \DateTime::createFromFormat('Y-m-d', $s->start_date, wp_timezone());
        if (!$start) return false;
        $diff = $start->diff($today)->days;
        return $diff % $s->interval_days === 0;
    }

    private function runForStrategy(ContentStrategy $s, int $strategyId, int $depth = 0): void
    {
        if ($depth > self::MAX_ANGLE_DEPTH) {
            error_log("[AICA] Recursion depth exceeded for strategy {$strategyId}");
            return;
        }

        $angle = TopicQueue::getNextTopic($strategyId);
        $analysis = $this->analysePillar($s, $angle);

        $attempts = 0;
        $success = false;

        while (!$success && $attempts < self::MAX_TOTAL_RETRY) {
            $attempts++;

            $draft = (new Generator())->createPost($analysis, [
                'category_id' => $s->category_id,
                'min_words'   => $s->min_words,
                'max_words'   => $s->max_words,
                'tone'        => $s->tone,
                'model'       => get_option(\AICA\Plugin::OPTION_KEY)['model'] ?? 'gpt-4.1-mini',
            ]);

            if (empty($draft)) {
                continue;
            }

            $h1h2 = ContentHelper::extractHeadings($draft['post_content'] ?? '');
            $emb  = EmbeddingService::embedText($h1h2);

            if (!EmbeddingService::isUnique($s->category_id, $emb)) {
                continue;
            }

            $mainPrompt = $this->createImagePrompt($draft['post_title'], $s, 1);
            $mainImageUrl = (new ImageGenerator())->generate($mainPrompt);

            if (empty($mainImageUrl)) {
                continue;
            }

            $mainImageId = $this->uploadImageToMedia($mainImageUrl, 0, $draft['post_title']);
            if (!$mainImageId) {
                continue;
            }

            // Replace IMAGE_1 placeholder
            $draft['post_content'] = str_replace('[IMAGE_1]', wp_get_attachment_image($mainImageId, 'large', false, [
                'class' => 'aica-generated-image aica-featured-inline',
                'alt' => 'Featured image'
            ]), $draft['post_content'] ?? '');

            $postId = wp_insert_post($draft, true);
            if (is_wp_error($postId)) {
                error_log('[AICA] Post insert error: ' . $postId->get_error_message());
                continue;
            }

            update_post_meta($postId, '_aica_generated', current_time('mysql'));
            set_post_thumbnail($postId, $mainImageId);
            do_action('aica_content_published', $postId);

            if (!empty($s->enable_images)) {
                $this->processImages($postId, $draft, $s);
            }

            $success = true;
        }

        if (!$success) {
            $msg = "Strategy {$strategyId}: Failed after retries (content or image).";
            error_log('[AICA] ' . $msg);
            if (get_option(self::ADMIN_NOTICE_OPTION)) {
                set_transient('aica_angle_error', $msg, 3600);
            }

            if ($angle) {
                $this->runForStrategy($s, $strategyId, $depth + 1);
            }
        }
    }

    private function processImages(int $postId, array $draft, ContentStrategy $s): void
    {
        $content = $draft['post_content'] ?? '';
        if (empty($content)) return;

        $generator = new ImageGenerator();
        preg_match_all('/\\[IMAGE_(\\d+)\\]/', $content, $matches, PREG_SET_ORDER);
        if (empty($matches)) return;

        $processedImages = [];

        foreach ($matches as $match) {
            $imageNumber = (int)$match[1];
            if ($imageNumber === 1) continue; // Already handled

            $prompt = $this->createImagePrompt($draft['post_title'], $s, $imageNumber);
            $imageUrl = $generator->generate($prompt);

            if (!$imageUrl) continue;

            $imageId = $this->uploadImageToMedia($imageUrl, $postId, $draft['post_title']);
            if (!$imageId) continue;

            $processedImages[$imageNumber] = $imageId;
        }

        $content = $this->applySmartImagePlacement($content, $processedImages);
        wp_update_post([
            'ID' => $postId,
            'post_content' => $content,
        ]);
    }

    private function applySmartImagePlacement(string $content, array $processedImages): string
    {
        foreach ($processedImages as $imageNumber => $imageId) {
            $placeholder = "[IMAGE_{$imageNumber}]";
            $imageHtml = wp_get_attachment_image($imageId, 'large', false, [
                'class' => 'aica-generated-image',
                'alt' => "Illustration {$imageNumber}"
            ]);
            $content = str_replace($placeholder, $imageHtml, $content);
        }

        return $content;
    }

    private function createImagePrompt(string $title, ContentStrategy $s, int $imageNumber = 1): string
    {
        $prompt = "Create a professional illustration for a blog post titled '{$title}'";
        if (!empty($s->kw_primary)) {
            $prompt .= " focusing on {$s->kw_primary}";
        }
        $prompt .= $imageNumber === 1
            ? " (main featured image)"
            : " (supporting illustration #{$imageNumber})";
        $prompt .= ". Style: clean, modern, suitable for {$s->tone} business content.";

        return $prompt;
    }

    private function uploadImageToMedia(string $imageUrl, int $postId, string $title): ?int
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $imageId = media_sideload_image($imageUrl, $postId, $title, 'id');

        if (is_wp_error($imageId)) {
            error_log('[AICA] Failed to upload image: ' . $imageId->get_error_message());
            return null;
        }

        return (int)$imageId;
    }

    private function analysePillar(ContentStrategy $s, ?string $angle): array
    {
        $html = wp_remote_retrieve_body(wp_remote_get($s->source_url));
        $pageText = $html ? wp_strip_all_tags($html) : '';

        if (empty($s->kw_primary)) {
            $kw = (new KeywordExtractor())->extract($pageText);
            $s->kw_primary = $kw['primary'];
            $s->kw_secondary = implode(', ', $kw['secondary']);
        }

        $summary = (new MiniSummariser())->summarise($pageText);
        $similarityThreshold = $this->getSimilarityThreshold();

        return [
            'primary_kw' => $s->kw_primary,
            'secondary_kw' => $s->kw_secondary,
            'h2_topics' => $angle ?: 'Key benefits, How it works, FAQ',
            'angle' => $angle ?? '',
            'page_summary' => $summary,
            'similarity_threshold' => $similarityThreshold,
        ];
    }

    private function getSimilarityThreshold(): int
    {
        $settings = get_option('aica_settings', []);
        return isset($settings['similarity_threshold'])
            ? (int)$settings['similarity_threshold']
            : 85;
    }
}
