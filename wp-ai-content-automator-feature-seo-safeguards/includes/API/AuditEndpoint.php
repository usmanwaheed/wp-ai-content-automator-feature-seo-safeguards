<?php
namespace AICA\API;

use AICA\Audit\CompetitorScraper;
use WP_REST_Request;

class AuditEndpoint {

    public static function register(): void {
        register_rest_route(
            'aica/v1',
            '/audit',
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'url' => [
                        'required'          => true,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]
        );
    }

    public static function handle( WP_REST_Request $req ) {
        $url      = esc_url_raw( $req->get_param( 'url' ) );
        $scraper  = new CompetitorScraper();
        $result   = $scraper->analyze( $url );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }
}
