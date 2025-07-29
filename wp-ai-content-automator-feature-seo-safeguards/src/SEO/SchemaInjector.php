<?php
namespace AICA\SEO;

use Opis\JsonSchema\Validator;

class SchemaInjector {

    private const META_SCHEMAS = '_aica_schemas';
    private const META_HASH    = '_aica_schema_hash';

    private Validator $validator;

    public function __construct() {
        if (!class_exists('\Opis\JsonSchema\Validator')) {
            throw new \RuntimeException(
                'Missing opis/json-schema dependency. Please run "composer install" from the plugin directory.'
            );
        }
        $this->validator = new Validator();
    }

    /** Main entry — run on `aica_content_published`. */
    public function inject( int $post_id ): void {
        if ( 'publish' !== get_post_status( $post_id ) ) {
            return;
        }

        $html  = get_post_field( 'post_content', $post_id );
        $hash  = md5( $html );
        $old   = get_post_meta( $post_id, self::META_HASH, true );

        if ( $hash === $old ) {
            return; // no changes
        }

        $opts    = get_option( 'aica_settings', ['schema_article' => 1, 'schema_faq' => 1] );
        $schemas = [];

        if ( ! empty( $opts['schema_article'] ) ) {
            $schemas[] = SchemaBuilder::build_article( $post_id );
        }

        if ( ! empty( $opts['schema_faq'] ) ) {
            $pairs = SchemaBuilder::extract_faq( $html );
            if ( $pairs ) {
                $schemas[] = SchemaBuilder::build_faq( $pairs );
            }
        }

        if ( $schemas ) {
            update_post_meta( $post_id, self::META_SCHEMAS, $schemas );
            update_post_meta( $post_id, self::META_HASH, $hash );
        }
    }

    /** Rank-Math filter. */
    public function inject_to_rankmath( array $entities ): array {
        global $post;
        $extra = get_post_meta( $post->ID ?? 0, self::META_SCHEMAS, true );
        return $extra ? array_merge( $entities, $extra ) : $entities;
    }

    /** Fallback JSON-LD when Rank Math not present. */
    public function output_json_ld(): void {
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            return;
        }
        global $post;
        if ( ! $post ) {
            return;
        }
        $schemas = get_post_meta( $post->ID, self::META_SCHEMAS, true );
        if ( ! $schemas ) {
            return;
        }
        foreach ( $schemas as $schema ) {
            echo '<script type="application/ld+json">' .
                 wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) .
                 "</script>\n";
        }
    }
}
