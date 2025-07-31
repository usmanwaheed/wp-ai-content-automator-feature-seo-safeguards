<?php
namespace AICA\Utils;

class ContentHelper
{
    /**
     * Extract H1 and H2 headings from HTML content for embedding purposes
     */
    public static function extractHeadings(string $html): string
    {
        $headings = [];

        // Combine H1 and H2 extraction in a single loop for clarity
        foreach (['h1', 'h2'] as $tag) {
            if (preg_match_all('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/i', $html, $matches)) {
                foreach ($matches[1] as $heading) {
                    $clean = wp_strip_all_tags($heading);
                    if (!empty($clean)) {
                        $headings[] = $clean;
                    }
                }
            }
        }

        return implode(' ', $headings);
    }
}
