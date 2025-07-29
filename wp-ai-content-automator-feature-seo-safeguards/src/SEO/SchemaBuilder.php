<?php
namespace AICA\SEO;

class SchemaBuilder {

    /**
     * Extract Q/A pairs for FAQ schema.
     *
     * @return array Each item: ['q'=> string, 'a'=> string]
     */
    public static function extract_faq( string $html ): array {
        // Main pattern  <h3>Q</h3><h4>A</h4>
        preg_match_all(
            '/<h3[^>]*>(.*?)<\/h3>\s*<h4[^>]*>(.*?)<\/h4>/is',
            $html,
            $m
        );

        if ( empty( $m[0] ) ) {
            // Fallback pattern  <div class="faq-q">Q</div><div class="faq-a">A</div>
            preg_match_all(
                '/<div[^>]*class="[^"]*faq-q[^"]*"[^>]*>(.*?)<\/div>\s*<div[^>]*class="[^"]*faq-a[^"]*"[^>]*>(.*?)<\/div>/is',
                $html,
                $m
            );
        }

        $pairs = [];
        if ( ! empty( $m[1] ) ) {
            foreach ( $m[1] as $i => $q ) {
                $pairs[] = [
                    'q' => wp_strip_all_tags( $q ),
                    'a' => wp_kses_post( $m[2][ $i ] ?? '' ),
                ];
            }
        }
        return $pairs;
    }

    /** Build minimal Article schema. */
    public static function build_article( int $post_id ): array {
        $post   = get_post( $post_id );
        $author = get_user_by( 'id', $post->post_author );

        return [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => wp_strip_all_tags( $post->post_title ),
            'author'           => [
                '@type' => 'Person',
                'name'  => $author ? $author->display_name : get_bloginfo( 'name' ),
            ],
            'datePublished'    => get_the_date( DATE_ATOM, $post ),
            'dateModified'     => get_post_modified_time( DATE_ATOM, false, $post ),
            'mainEntityOfPage' => get_permalink( $post ),
            'image'            => self::featured_image( $post_id ),
        ];
    }

    /** Build FAQPage schema from Q/A pairs. */
    public static function build_faq( array $pairs ): array {
        $items = [];
        foreach ( $pairs as $p ) {
            $items[] = [
                '@type'          => 'Question',
                'name'           => $p['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $p['a'],
                ],
            ];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];
    }

    /** Helper: get featured-image URL or empty string. */
    private static function featured_image( int $post_id ): string {
        $id = get_post_thumbnail_id( $post_id );
        if ( ! $id ) {
            return '';
        }
        $src = wp_get_attachment_image_src( $id, 'full' );
        return $src[0] ?? '';
    }
}
