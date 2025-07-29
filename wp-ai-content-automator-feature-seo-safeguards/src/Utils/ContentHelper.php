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
        
        // Extract H1 tags
        if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/i', $html, $matches)) {
            foreach ($matches[1] as $heading) {
                $headings[] = wp_strip_all_tags($heading);
            }
        }
        
        // Extract H2 tags
        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $html, $matches)) {
            foreach ($matches[1] as $heading) {
                $headings[] = wp_strip_all_tags($heading);
            }
        }
        
        return implode(' ', $headings);
    }
}
