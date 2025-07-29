<?php
namespace AICA\SEO;

class RobotsManager {

    private const OPT_BLOCKED = 'aica_blocked_slugs';
    private const OPT_DIRTY   = 'aica_rewrite_dirty';

    /** Flag a slug for 48 h no-index. */
    public function add_block( int $post_id ): void {
        $slug = get_post_field( 'post_name', $post_id );
        if ( ! $slug ) {
            return;
        }
        $blocked         = get_option( self::OPT_BLOCKED, [] );
        $blocked[ $slug] = time() + 48 * HOUR_IN_SECONDS;

        update_option( self::OPT_BLOCKED, $blocked );
        update_option( self::OPT_DIRTY,   true );

        $this->notify_search_console( home_url( "/$slug/" ) );
    }

    /** Purge expired entries. */
    public function purge_expired_blocks(): void {
        $blocked = get_option( self::OPT_BLOCKED, [] );
        $now     = time();
        $changed = false;

        foreach ( $blocked as $slug => $exp ) {
            if ( $now > $exp ) {
                unset( $blocked[ $slug ] );
                $changed = true;
            }
        }
        if ( $changed ) {
            update_option( self::OPT_BLOCKED, $blocked );
            update_option( self::OPT_DIRTY,   true );
        }
    }

    /** Batch-update rewrite rules (called hourly if DIRTY). */
    public function update_rewrite_rules(): void {
        $blocked = get_option( self::OPT_BLOCKED, [] );
        $rules   = get_option( 'rewrite_rules', [] );

        foreach ( $blocked as $slug => $expiry ) {
            $rules[ '^' . $slug . '/?$' ] = 'index.php?aica_blocked=1';
        }
        update_option( 'rewrite_rules', $rules );
    }

    /** Send runtime X-Robots header. */
    public static function add_robots_header(): void {
        if ( get_query_var( 'aica_blocked' ) ) {
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }
    }

    /** Optional Indexing API ping. */
    private function notify_search_console( string $url ): void {
        if ( ! get_option( 'aica_robots_ping_gsc' ) ) {
            return;
        }
        if ( ! class_exists( \Google_Client::class ) ) {
            return;
        }
        try {
            $client = new \Google_Client();
            $client->setAuthConfig( get_option( 'aica_gsc_oauth_json' ) );
            $client->addScope( 'https://www.googleapis.com/auth/indexing' );
            $client->setSubject( get_option( 'aica_gsc_service_account_email' ) );

            $svc  = new \Google_Service_Indexing( $client );
            $item = new \Google_Service_Indexing_UrlNotification(
                ['url' => $url, 'type' => 'URL_UPDATED']
            );
            $svc->urlNotifications->publish( $item );
        } catch ( \Throwable $e ) {
            error_log( 'AICA GSC ping failed: ' . $e->getMessage() );
        }
    }
}
