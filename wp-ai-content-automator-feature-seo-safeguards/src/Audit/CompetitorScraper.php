<?php
namespace AICA\Audit;

class CompetitorScraper {

    private static array $ua = [
        'Mozilla/5.0 (compatible; AICA-Bot/0.2; +https://yourdomain.com)',
        'Mozilla/5.0 (X11; Linux x86_64) AICA-Bot/0.2',
        'AICA-Bot/0.2 (+https://yourdomain.com)',
    ];

    /** Main analyser – returns array or WP_Error. */
    public function analyze( string $url ) {
        // simple 2-second rate-limit
        $last = (int) get_option( 'aica_last_audit', 0 );
        if ( time() - $last < 2 ) {
            return new \WP_Error( 'rate_limited', __( 'Slow down', 'aica' ), ['status' => 429] );
        }
        update_option( 'aica_last_audit', time() );

        // cache
        $ckey = 'aica_audit_' . md5( $url );
        if ( $cached = get_transient( $ckey ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => self::$ua[ array_rand( self::$ua ) ],
                    'Accept'     => 'text/html',
                ],
            ]
        );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $html  = wp_remote_retrieve_body( $response );
        $data  = $this->scrape_metrics( $html );
        $data['url'] = $url;

        set_transient( $ckey, $data, DAY_IN_SECONDS );
        return $data;
    }

    /** Extract simple SEO / accessibility metrics. */
    private function scrape_metrics( string $html ): array {
        preg_match( '/<title>(.*?)<\/title>/is', $html, $mt );
        $title = trim( $mt[1] ?? '' );

        preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $mh );
        $heads = array_count_values( $mh[1] );

        $metaLen = 0;
        if ( preg_match( '/<meta[^>]*name=[\'"]description[\'"][^>]*content=[\'"](.*?)[\'"]/is', $html, $md ) ) {
            $metaLen = strlen( $md[1] );
        }

        preg_match_all( '/<img[^>]*>/is', $html, $imgs );
        $totalImg = count( $imgs[0] );
        preg_match_all( '/<img[^>]*alt=[\'"][^\'"]+[\'"][^>]*>/is', $html, $alted );
        $altPct = $totalImg ? round( count( $alted[0] ) / $totalImg * 100, 1 ) : 0;

        // stub CWV + Pa11y (optional)
        $cwv  = [];
        $axe  = [];

        return [
            'title'                    => $title,
            'word_count'               => str_word_count( strip_tags( $html ) ),
            'heading_structure'        => $heads,
            'meta_description_length'  => $metaLen,
            'image_alt_percent'        => $altPct,
            'core_web_vitals'          => $cwv,
            'accessibility_issues'     => $axe,
        ];
    }
}
