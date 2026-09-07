<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MVM_Encyclopedie_Next_Hotlinks {
    private const MAX_RELATION_TARGETS = 48;
    private const MAX_ALIASES = 120;
    private const MAX_LINKS = 6;

    public static function init(): void {
        add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
    }

    public static function filter_content( string $content ): string {
        if ( is_admin() || is_feed() || ! is_singular( MVM_Encyclopedie_Next_Content_Model::post_type_slugs() ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();
        if ( $post_id <= 0 || false === stripos( $content, '<p' ) ) {
            return $content;
        }

        $targets = self::targets_for( $post_id );
        if ( ! $targets ) {
            return $content;
        }

        $aliases = array_keys( $targets );
        usort(
            $aliases,
            static function ( string $a, string $b ): int {
                return self::strlen( $b ) <=> self::strlen( $a );
            }
        );

        $pattern_parts = array_map(
            static function ( string $alias ): string {
                return preg_quote( $alias, '~' );
            },
            $aliases
        );

        if ( ! $pattern_parts ) {
            return $content;
        }

        $pattern = '~(?<![\p{L}\p{N}])(' . implode( '|', $pattern_parts ) . ')(?![\p{L}\p{N}])~iu';
        $chunks = preg_split( '~(<[^>]+>)~', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
        if ( ! is_array( $chunks ) ) {
            return $content;
        }

        $blocked = array();
        $links_added = 0;
        $linked_targets = array();

        foreach ( $chunks as $index => $chunk ) {
            if ( $links_added >= self::MAX_LINKS ) {
                break;
            }

            if ( '' === $chunk ) {
                continue;
            }

            if ( '<' === $chunk[0] ) {
                self::track_html_context( $chunk, $blocked );
                continue;
            }

            if ( $blocked ) {
                continue;
            }

            $chunks[ $index ] = preg_replace_callback(
                $pattern,
                static function ( array $match ) use ( $targets, &$links_added, &$linked_targets ): string {
                    if ( $links_added >= self::MAX_LINKS ) {
                        return $match[0];
                    }

                    $key = self::lower( (string) $match[1] );
                    if ( ! isset( $targets[ $key ] ) ) {
                        return $match[0];
                    }

                    $target = $targets[ $key ];
                    $target_id = (int) $target['id'];
                    if ( isset( $linked_targets[ $target_id ] ) ) {
                        return $match[0];
                    }

                    $linked_targets[ $target_id ] = true;
                    ++$links_added;

                    return '<a class="mvm-e3-hotlink" href="' . esc_url( (string) $target['url'] ) . '">' . esc_html( (string) $match[0] ) . '</a>';
                },
                $chunk
            );
        }

        return implode( '', $chunks );
    }

    /** @return array<string,array{id:int,url:string}> */
    private static function targets_for( int $post_id ): array {
        $relation_ids = array_slice( MVM_Encyclopedie_Next_Query::relation_ids( $post_id ), 0, self::MAX_RELATION_TARGETS );
        if ( ! $relation_ids ) {
            return array();
        }

        $posts = get_posts(
            array(
                'post_type' => MVM_Encyclopedie_Next_Content_Model::post_type_slugs(),
                'post_status' => 'publish',
                'post__in' => $relation_ids,
                'posts_per_page' => self::MAX_RELATION_TARGETS,
                'orderby' => 'post__in',
                'no_found_rows' => true,
                'ignore_sticky_posts' => true,
                'update_post_term_cache' => false,
            )
        );

        $targets = array();
        foreach ( $posts as $post ) {
            $url = get_permalink( $post );
            if ( ! $url ) {
                continue;
            }

            $labels = array( get_the_title( $post ) );
            $stored_aliases = maybe_unserialize( get_post_meta( $post->ID, '_mvm_aliases', true ) );
            if ( is_array( $stored_aliases ) ) {
                $labels = array_merge( $labels, array_slice( $stored_aliases, 0, 4 ) );
            } elseif ( is_string( $stored_aliases ) && '' !== trim( $stored_aliases ) ) {
                $labels[] = $stored_aliases;
            }

            foreach ( $labels as $label ) {
                $label = trim( wp_strip_all_tags( (string) $label ) );
                if ( self::strlen( $label ) < 4 || self::strlen( $label ) > 90 ) {
                    continue;
                }

                $key = self::lower( $label );
                if ( isset( $targets[ $key ] ) ) {
                    continue;
                }

                $targets[ $key ] = array(
                    'id' => (int) $post->ID,
                    'url' => (string) $url,
                );

                if ( count( $targets ) >= self::MAX_ALIASES ) {
                    break 2;
                }
            }
        }

        return $targets;
    }

    /** @param string[] $blocked */
    private static function track_html_context( string $tag, array &$blocked ): void {
        if ( ! preg_match( '~^<\s*(/?)\s*([a-z0-9]+)\b[^>]*>~i', $tag, $match ) ) {
            return;
        }

        $name = strtolower( (string) $match[2] );
        $blocked_tags = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'code', 'pre', 'textarea', 'button' );
        if ( ! in_array( $name, $blocked_tags, true ) ) {
            return;
        }

        $closing = '/' === (string) $match[1];
        $self_closing = str_ends_with( trim( $tag ), '/>' );
        if ( $closing ) {
            for ( $i = count( $blocked ) - 1; $i >= 0; --$i ) {
                if ( $blocked[ $i ] === $name ) {
                    array_splice( $blocked, $i, 1 );
                    break;
                }
            }
            return;
        }

        if ( ! $self_closing ) {
            $blocked[] = $name;
        }
    }

    private static function lower( string $value ): string {
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
    }

    private static function strlen( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }
}
