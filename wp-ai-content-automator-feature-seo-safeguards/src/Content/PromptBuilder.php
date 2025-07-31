<?php
declare(strict_types=1);

namespace AICA\Content;

final class PromptBuilder
{
    public function build(array $strategy, array $domainProfile, array $analysis): string
    {
        /* ── 1. Enhanced Expertise Assembly ─────────────────────────────────────── */
        $settings = get_option(\AICA\Plugin::OPTION_KEY, []);

        $expertise = array_unique(array_filter(array_merge(
            $settings['expertise_tags'] ?? [],
            $domainProfile['expertise_tags'] ?? [],
            ['AI Content Strategy', 'SEO Optimization']
        )));

        $audience = array_unique(array_filter(array_merge(
            $settings['audience_tags'] ?? [],
            $domainProfile['audience_tags'] ?? [],
            ['digital marketers', 'content strategists']
        )));

        $expertiseCtx = sprintf(
            "You are a team of Pulitzer-level content creators specializing in %s. Create groundbreaking content for %s that:" .
            "\n- Demonstrates original research and unique insights" .
            "\n- Uses data-driven arguments with concrete examples" .
            "\n- Maintains narrative flow between sections" .
            "\n- Avoids over-reliance on bullet points (max 1 list per 500 words)" .
            "\n- Includes at least 3 substantive paragraphs per H2 section",
            implode(', ', $expertise),
            implode(', ', $audience)
        );

        /* ── 2. SEO & Conversion Optimization ───────────────────────────────────── */
        $primary = $strategy['kw_primary'] ?? ($analysis['primary_kw'] ?? '');
        $secondary = $strategy['kw_secondary'] ?? ($analysis['secondary_kw'] ?? '');

        $seoCtx = sprintf(
            "# SEO & Conversion Requirements:\n" .
            "- Primary keyword: %s (use naturally in first 100 words)\n" .
            "- Secondary keywords: %s (distribute evenly)\n" .
            "- Meta description ≤ 155 characters with value proposition\n" .
            "- Include 2-3 FAQ schema items addressing: \"How does [topic] impact [audience]?\"\n" .
            "- Embed 1 conversion-focused CTA every 300-400 words\n" .
            "- E-E-A-T signals: Reference 3 credible sources minimum",
            esc_html($primary),
            esc_html($secondary)
        );

        /* ── 3. Content Depth Enforcement ───────────────────────────────────────── */
        $tone = $strategy['tone'] ?? 'professional';
        $minWords = (int)($strategy['min_words'] ?? 1200);
        $maxWords = (int)($strategy['max_words'] ?? 1800);

        $depthGuard = sprintf(
            "# Content Depth Safeguards:\n" .
            "- Target word count: %d-%d words\n" .
            "- Paragraph-to-headline ratio: Minimum 3:1\n" .
            "- Substantive content density: ≥85%% paragraph text\n" .
            "- Thin content prevention: If section has <150 words, expand with:\n" .
            "  • Case studies\n" .
            "  • Statistical evidence\n" .
            "  • Expert quotations\n" .
            "  • Comparative analysis",
            $minWords,
            $maxWords
        );

        /* ── 4. Truthfulness & Legal Safeguards ─────────────────────────────────── */
        $sourceUrl = $strategy['source_url'] ?? 'trusted sources';

        $truthGuard = sprintf(
            "# Truthfulness Protocols:\n" .
            "- NEVER invent statistics, studies, or quotes\n" .
            "- ALWAYS qualify uncertain statements with \"may\" or \"could\"\n" .
            "- FLAG hypotheticals with \"For example:\" or \"Scenario:\"\n" .
            "- CITE sources for all non-common-knowledge claims\n" .
            "- VERIFY claims against %s content\n" .
            "- REJECT content if factual certainty <95%%\n\n" .
            "# Legal Safeguards:\n" .
            "- ADD \"This is not professional advice\" disclaimer\n" .
            "- AVOID absolute claims about outcomes/results\n" .
            "- USE \"according to our analysis\" for interpretations",
            esc_html($sourceUrl)
        );

        /* ── 5. Contextual Linking Strategy ─────────────────────────────────────── */
        $linkUrl = $strategy['source_url'] ?? '#';

        $linkingStrategy = sprintf(
            "# Contextual Linking Protocol:\n" .
            "1. Include 3-5 contextual links to:\n" .
            "   - Pillar URL: %s\n" .
            "   - Related spoke content (use your /blog/ archives)\n" .
            "2. Placement rules:\n" .
            "   - First mention of primary keyword → link to pillar\n" .
            "   - Solution-oriented phrases → link to product pages\n" .
            "   - Technical terms → link to definition posts\n" .
            "3. Anchor text distribution:\n" .
            "   - 40%% exact-match keywords\n" .
            "   - 40%% partial-match phrases\n" .
            "   - 20%% natural language (\"learn more here\")",
            esc_html($linkUrl)
        );

        /* ── 6. Visual Content Requirements ────────────────────────────────────── */
        $imageSection = '';
        if (!empty($strategy['enable_images']) && filter_var($strategy['enable_images'], FILTER_VALIDATE_BOOLEAN)) {
            $imageCount = max(1, min(5, (int)($strategy['image_count'] ?? 3))); // Limit 1-5 images
            $imageAudience = !empty($domainProfile['audience_tags']) && is_array($domainProfile['audience_tags']) 
                ? $domainProfile['audience_tags'][0] 
                : 'readers';

            $imageSection = sprintf(
                "# MANDATORY IMAGE REQUIREMENTS:\n" .
                "YOU MUST INCLUDE EXACTLY %d IMAGES IN YOUR CONTENT for %s.\n" .
                "Tone: %s; Core concept: %s\n\n" .
                "REQUIRED IMAGE PLACEHOLDERS:\n" .
                "- [IMAGE_1] = MANDATORY Featured image - place after introduction\n" .
                "- [IMAGE_2] = MANDATORY Supporting image - place after first H2 section\n" .
                "- [IMAGE_3] = MANDATORY Supporting image - place after second H2 section\n" .
                "- Continue with [IMAGE_4], [IMAGE_5] etc. up to %d total images\n\n" .
                "CRITICAL RULES:\n" .
                "- You MUST include ALL %d image placeholders in your content\n" .
                "- Place [IMAGE_1] immediately after the introduction paragraph\n" .
                "- Place other images after major H2 sections\n" .
                "- NEVER skip image placeholders - they are REQUIRED\n" .
                "- Example placement:\n" .
                "  <p>Introduction content here...</p>\n" .
                "  [IMAGE_1]\n" .
                "  <h2>First Section</h2>\n" .
                "  <p>Content...</p>\n" .
                "  [IMAGE_2]\n\n" .
                "FAILURE TO INCLUDE ALL IMAGE PLACEHOLDERS WILL RESULT IN CONTENT REJECTION.",
                $imageCount,
                esc_html($imageAudience),
                esc_html($tone),
                esc_html($primary),
                $imageCount,
                $imageCount
            );
        }

        /* ── 7. Required Structure & Originality Enforcement ────────────────────── */
        $h2Topics = $analysis['h2_topics'] ?? '[Missing H2 Topics]';
        $similarity = (int)($analysis['similarity_threshold'] ?? 85);
        $angleTag = !empty($analysis['angle']) ? sprintf("Unique perspective: %s\n", esc_html($analysis['angle'])) : '';

        $structure = sprintf(
            "%s# Required Structure:\n" .
            "1. [INTRODUCTION] - Problem hook + statistics + primary keyword\n" .
            "2. [CORE ARGUMENT] - Thesis statement with unique POV\n" .
            "3. %s (Develop each as mini-essays with:)\n" .
            "   - Topic sentence\n" .
            "   - Supporting evidence\n" .
            "   - Practical applications\n" .
            "4. [CONCLUSION] - Synthesis of insights + 3 actionable takeaways\n\n" .
            "# Originality Enforcement:\n" .
            "- Cross-verify against %d%% similarity threshold\n" .
            "- Include 1 proprietary framework/concept per 800 words",
            $angleTag,
            esc_html($h2Topics),
            $similarity
        );

        /* ── 8. Output Format ───────────────────────────────────────────────────── */
        $output = "# Output Format:\n" .
                 "[TITLE] <70 characters including primary keyword>\n" .
                 "[SLUG] <URL-friendly version>\n" .
                 "[SEO_DESCRIPTION] <155 characters with secondary keywords>\n" .
                 "[TAGS] comma,separated,list (include 1 audience-specific tag)\n" .
                 "[BODY] <HTML with semantic structure>";

        /* ── 9. Combine All Parts ──────────────────────────────────────────────── */
        $prompt = implode("\n\n", array_filter([
            $expertiseCtx,
            $seoCtx,
            $depthGuard,
            $truthGuard,
            $linkingStrategy,
            $imageSection,
            $structure,
            $output,
        ]));

        // Optional debug log (safe with PHP 8.2)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($prompt);
            error_log("dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd");
        }

        return $prompt;
    }
}