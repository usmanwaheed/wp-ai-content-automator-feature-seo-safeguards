<?php
namespace AICA\Content;

/**
 * Builds the complete OpenAI prompt for blog generation,
 * with advanced guardrails for quality, truthfulness, linking, and images.
 */
final class PromptBuilder
{
    public function build(array $strategy, array $domainProfile, array $analysis): string
    {
        /* ── 1. Enhanced Expertise Assembly ─────────────────────────────────────────── */
        $settings  = get_option(\AICA\Plugin::OPTION_KEY, []);
        $expertise = array_unique(array_filter(array_merge(
            $settings['expertise_tags']     ?? [],
            $domainProfile['expertise_tags'] ?? [],
            ['AI Content Strategy', 'SEO Optimization']
        )));
        $audience  = array_unique(array_filter(array_merge(
            $settings['audience_tags']     ?? [],
            $domainProfile['audience_tags'] ?? [],
            ['digital marketers', 'content strategists']
        )));

        $expertiseCtx = sprintf(
            "You are a team of Pulitzer-level content creators specializing in %s. Create groundbreaking content for %s that:"
            . "\n- Demonstrates original research and unique insights"
            . "\n- Uses data-driven arguments with concrete examples"
            . "\n- Maintains narrative flow between sections"
            . "\n- Avoids over-reliance on bullet points (max 1 list per 500 words)"
            . "\n- Includes at least 3 substantive paragraphs per H2 section",
            implode(', ', $expertise),
            implode(', ', $audience)
        );

        /* ── 2. SEO & Conversion Optimization ───────────────────────────────────────── */
        $primary   = $strategy['kw_primary']   ?? $analysis['primary_kw'];
        $secondary = $strategy['kw_secondary'] ?? $analysis['secondary_kw'];

        $seoCtx = <<<SEO
# SEO & Conversion Requirements:
- Primary keyword: {$primary} (use naturally in first 100 words)
- Secondary keywords: {$secondary} (distribute evenly)
- Meta description ≤ 155 characters with value proposition
- Include 2-3 FAQ schema items addressing: "How does [topic] impact [audience]?"
- Embed 1 conversion-focused CTA every 300-400 words
- E-E-A-T signals: Reference 3 credible sources minimum
SEO;

        /* ── 3. Content Depth Enforcement ─────────────────────────────────────────── */
        $tone     = $strategy['tone']      ?? 'professional';
        $minWords = $strategy['min_words'] ?? 1200;
        $maxWords = $strategy['max_words'] ?? 1800;

        $depthGuard = <<<DEPTH
# Content Depth Safeguards:
- Paragraph-to-headline ratio: Minimum 3:1
- Substantive content density: ≥85% paragraph text
- Thin content prevention: If section has <150 words, expand with:
  • Case studies
  • Statistical evidence
  • Expert quotations
  • Comparative analysis
DEPTH;

        /* ── 4. Truthfulness & Legal Safeguards ──────────────────────────────────── */
        $truthGuard = <<<SAFEGUARD
# Truthfulness Protocols:
- NEVER invent statistics, studies, or quotes
- ALWAYS qualify uncertain statements with "may" or "could"
- FLAG hypotheticals with "For example:" or "Scenario:"
- CITE sources for all non-common-knowledge claims
- VERIFY claims against {$strategy['source_url']} content
- REJECT content if factual certainty <95%

# Legal Safeguards:
- ADD "This is not professional advice" disclaimer
- AVOID absolute claims about outcomes/results
- USE "according to our analysis" for interpretations
SAFEGUARD;

        /* ── 5. Contextual Linking Strategy ───────────────────────────────────────── */
        $linkingStrategy = <<<LINKING
# Contextual Linking Protocol:
1. Include 3-5 contextual links to:
   - Pillar URL: {$strategy['source_url']}
   - Related spoke content (use your /blog/ archives)
2. Placement rules:
   - First mention of primary keyword → link to pillar
   - Solution-oriented phrases → link to product pages
   - Technical terms → link to definition posts
3. Anchor text distribution:
   - 40% exact-match keywords
   - 40% partial-match phrases
   - 20% natural language ("learn more here")
LINKING;

        /* ── 6. Visual Content Requirements ──────────────────────────────────────── */
        $imageSection = '';
        if (!empty($strategy['enable_images'])) {
            $cnt = (int)($strategy['image_count'] ?? 3);
            $aud = $domainProfile['audience_tags'][0] ?? 'readers';
            $tone = $strategy['tone'] ?? 'professional';

            $imageSection = <<<IMG
# IMAGE GUIDELINES:
You may use UP TO {$cnt} contextual images for {$aud}.
Tone: {$tone}; Core concept: {$analysis['primary_kw']}

PLACEMENT RULES:
- [IMAGE_1] = Featured image (can be placed anywhere, will also be post thumbnail)
- [IMAGE_2], [IMAGE_3], etc. = Supporting images (system will auto-place if you don't)
- Only include images that genuinely enhance the content
- For shorter posts (<800 words), consider using fewer images
- Place images after major sections or key points, not mid-paragraph

QUALITY OVER QUANTITY: Use fewer high-quality, relevant images rather than padding with unnecessary ones.
IMG;
        }

        /* ── 7. Required Structure & Originality Enforcement ─────────────────────── */
        $h2       = $analysis['h2_topics'];
        $angleTag = !empty($analysis['angle'])
                    ? "Unique perspective: {$analysis['angle']}\n"
                    : '';

        $structure = <<<STRUCT
{$angleTag}
# Required Structure:
1. [INTRODUCTION] - Problem hook + statistics + primary keyword
2. [CORE ARGUMENT] - Thesis statement with unique POV
3. {$h2} (Develop each as mini-essays with:)
   - Topic sentence
   - Supporting evidence
   - Practical applications
4. [CONCLUSION] - Synthesis of insights + 3 actionable takeaways

# Originality Enforcement:
- Cross-verify against {$analysis['similarity_threshold']}% similarity threshold
- Include 1 proprietary framework/concept per 800 words
STRUCT;

        /* ── 8. Output Format ────────────────────────────────────────────────────── */
        $output = <<<OUTPUT
# Output Format:
[TITLE] <70 characters including primary keyword>
[SLUG] <URL-friendly version>
[SEO_DESCRIPTION] <155 characters with secondary keywords>
[TAGS] comma,separated,list (include 1 audience-specific tag)
[BODY] <HTML with semantic structure>
OUTPUT;

        return implode("\n\n", [
            $expertiseCtx,
            $seoCtx,
            $depthGuard,
            $truthGuard,
            $linkingStrategy,
            $imageSection,
            $structure,
            $output,
        ]);
    }
}
