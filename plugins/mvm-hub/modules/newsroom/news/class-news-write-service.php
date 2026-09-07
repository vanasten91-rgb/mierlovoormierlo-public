<?php

namespace MVM\Hub\Modules\Newsroom\News;

use MVM\Hub\Core\Audit;
use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Object_Access;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** WordPress-post based mutation service for Newsroom articles. */
final class News_Write_Service {
    public const META_STATE = '_mvm_newsroom_state';

    /** @return array<string,mixed>|\WP_Error */
    public function create( array $input ): array|\WP_Error {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( Capabilities::NEWS_CREATE ) ) {
            return new \WP_Error( 'mvm_news_create_forbidden', 'Je mag geen nieuwsartikel aanmaken.', array( 'status' => 403 ) );
        }

        $title = mb_substr( trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ), 0, 240 );
        if ( '' === $title ) {
            return new \WP_Error( 'mvm_news_title', 'Vul een titel in.', array( 'status' => 400 ) );
        }

        $state = sanitize_key( (string) ( $input['state'] ?? News_Workflow::IDEA ) );
        if ( ! in_array( $state, array( News_Workflow::IDEA, News_Workflow::DRAFT ), true ) ) {
            $state = News_Workflow::IDEA;
        }

        $post_id = wp_insert_post(
            array(
                'post_type'    => 'post',
                'post_status'  => 'draft',
                'post_title'   => $title,
                'post_content' => wp_kses_post( (string) ( $input['content'] ?? '' ) ),
                'post_excerpt' => sanitize_textarea_field( (string) ( $input['excerpt'] ?? '' ) ),
                'post_author'  => get_current_user_id(),
            ),
            true
        );
        if ( is_wp_error( $post_id ) ) {
            Audit::record( 'news.create', 'error', 'post', 0, array( 'error' => $post_id->get_error_code() ) );
            return $post_id;
        }

        update_post_meta( $post_id, self::META_STATE, $state );
        $relations = $this->apply_relations( $post_id, $input );
        if ( is_wp_error( $relations ) ) {
            wp_delete_post( $post_id, true );
            return $relations;
        }

        Audit::record( 'news.create', 'success', 'post', $post_id, array( 'state' => $state ) );
        return $this->detail( $post_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update( int $post_id, array $input ): array|\WP_Error {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return new \WP_Error( 'mvm_news_missing', 'Dit nieuwsartikel bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! Object_Access::can_edit_news_post( $post_id ) ) {
            Audit::record( 'news.update', 'denied', 'post', $post_id );
            return new \WP_Error( 'mvm_news_edit_forbidden', 'Je mag dit nieuwsartikel niet wijzigen.', array( 'status' => 403 ) );
        }

        $expected = trim( sanitize_text_field( (string) ( $input['expectedModifiedGmt'] ?? '' ) ) );
        $current_modified = get_post_modified_time( 'Y-m-d H:i:s', true, $post );
        if ( '' !== $expected && $expected !== $current_modified ) {
            return new \WP_Error(
                'mvm_news_edit_conflict',
                'Dit artikel is intussen gewijzigd. Herlaad het artikel voordat je opnieuw opslaat.',
                array( 'status' => 409, 'modifiedGmt' => $current_modified )
            );
        }

        $update = array( 'ID' => $post_id );
        if ( array_key_exists( 'title', $input ) ) {
            $title = mb_substr( trim( sanitize_text_field( (string) $input['title'] ) ), 0, 240 );
            if ( '' === $title ) {
                return new \WP_Error( 'mvm_news_title', 'De titel mag niet leeg zijn.', array( 'status' => 400 ) );
            }
            $update['post_title'] = $title;
        }
        if ( array_key_exists( 'content', $input ) ) {
            $update['post_content'] = wp_kses_post( (string) $input['content'] );
        }
        if ( array_key_exists( 'excerpt', $input ) ) {
            $update['post_excerpt'] = sanitize_textarea_field( (string) $input['excerpt'] );
        }

        if ( count( $update ) > 1 ) {
            $result = wp_update_post( wp_slash( $update ), true );
            if ( is_wp_error( $result ) ) {
                Audit::record( 'news.update', 'error', 'post', $post_id, array( 'error' => $result->get_error_code() ) );
                return $result;
            }
        }

        $relations = $this->apply_relations( $post_id, $input );
        if ( is_wp_error( $relations ) ) {
            return $relations;
        }

        Audit::record( 'news.update', 'success', 'post', $post_id, array( 'fields' => count( $update ) - 1 ) );
        clean_post_cache( $post_id );
        return $this->detail( $post_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function transition( int $post_id, string $to, array $input = array() ): array|\WP_Error {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
            return new \WP_Error( 'mvm_news_missing', 'Dit nieuwsartikel bestaat niet.', array( 'status' => 404 ) );
        }
        if ( ! Object_Access::can_edit_news_post( $post_id ) ) {
            return new \WP_Error( 'mvm_news_transition_forbidden', 'Je mag dit artikel niet wijzigen.', array( 'status' => 403 ) );
        }

        $from = self::state_for_post( $post );
        $to   = sanitize_key( $to );
        if ( ! News_Workflow::can_transition( $from, $to ) ) {
            Audit::record( 'news.transition', 'denied', 'post', $post_id, array(), $from, $to );
            return new \WP_Error( 'mvm_news_transition', 'Deze workflowstap is niet toegestaan.', array( 'status' => 403 ) );
        }

        $update = array( 'ID' => $post_id );
        if ( in_array( $to, array( News_Workflow::IDEA, News_Workflow::ASSIGNED, News_Workflow::DRAFT, News_Workflow::CHANGES_REQUESTED ), true ) ) {
            $update['post_status'] = 'draft';
        } elseif ( in_array( $to, array( News_Workflow::REVIEW, News_Workflow::READY ), true ) ) {
            $update['post_status'] = 'pending';
        } elseif ( News_Workflow::SCHEDULED === $to ) {
            if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'publish_post', $post_id ) ) {
                return new \WP_Error( 'mvm_news_native_publish', 'Je WordPress-account mag dit artikel niet inplannen.', array( 'status' => 403 ) );
            }
            $schedule = self::future_datetime( $input['publishAtUtc'] ?? null );
            if ( is_wp_error( $schedule ) ) {
                return $schedule;
            }
            $update['post_status']   = 'future';
            $update['post_date_gmt'] = $schedule;
            $update['post_date']     = get_date_from_gmt( $schedule );
        } elseif ( News_Workflow::PUBLISHED === $to ) {
            if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'publish_post', $post_id ) ) {
                return new \WP_Error( 'mvm_news_native_publish', 'Je WordPress-account mag dit artikel niet publiceren.', array( 'status' => 403 ) );
            }
            $update['post_status'] = 'publish';
        } elseif ( News_Workflow::CORRECTION === $to ) {
            if ( 'publish' !== $post->post_status ) {
                return new \WP_Error( 'mvm_news_correction_status', 'Alleen een gepubliceerd artikel kan naar correctie.', array( 'status' => 409 ) );
            }
            $update['post_status'] = 'publish';
        }

        $result = wp_update_post( $update, true );
        if ( is_wp_error( $result ) ) {
            Audit::record( 'news.transition', 'error', 'post', $post_id, array( 'error' => $result->get_error_code() ), $from, $to );
            return $result;
        }
        update_post_meta( $post_id, self::META_STATE, $to );
        Audit::record( 'news.transition', 'success', 'post', $post_id, array(), $from, $to );
        clean_post_cache( $post_id );
        return $this->detail( $post_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function detail( int $post_id ): array|\WP_Error {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type || ! Object_Access::can_read_news_post( $post_id ) ) {
            return new \WP_Error( 'mvm_news_detail_forbidden', 'Dit artikel is niet beschikbaar.', array( 'status' => 404 ) );
        }

        return array(
            'id'              => (int) $post->ID,
            'title'           => sanitize_text_field( $post->post_title ),
            'content'         => wp_kses_post( $post->post_content ),
            'excerpt'         => sanitize_textarea_field( $post->post_excerpt ),
            'state'           => self::state_for_post( $post ),
            'postStatus'      => sanitize_key( $post->post_status ),
            'authorId'        => (int) $post->post_author,
            'categoryIds'     => array_values( array_map( 'intval', wp_get_post_categories( $post_id ) ) ),
            'featuredMediaId' => (int) get_post_thumbnail_id( $post_id ),
            'modifiedGmt'     => get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
            'permalink'       => 'publish' === $post->post_status ? esc_url_raw( get_permalink( $post_id ) ) : '',
        );
    }

    public static function state_for_post( \WP_Post $post ): string {
        $stored = sanitize_key( (string) get_post_meta( $post->ID, self::META_STATE, true ) );
        if ( News_Workflow::is_known_state( $stored ) ) {
            return $stored;
        }
        return match ( $post->post_status ) {
            'publish' => News_Workflow::PUBLISHED,
            'future'  => News_Workflow::SCHEDULED,
            'pending' => News_Workflow::REVIEW,
            default   => News_Workflow::DRAFT,
        };
    }

    /** @return true|\WP_Error */
    private function apply_relations( int $post_id, array $input ): true|\WP_Error {
        if ( array_key_exists( 'categoryIds', $input ) ) {
            if ( ! is_array( $input['categoryIds'] ) ) {
                return new \WP_Error( 'mvm_news_categories', 'Categorieën zijn ongeldig.', array( 'status' => 400 ) );
            }
            $categories = array_values( array_unique( array_filter( array_map( 'absint', array_slice( $input['categoryIds'], 0, 20 ) ) ) ) );
            foreach ( $categories as $term_id ) {
                $term = get_term( $term_id, 'category' );
                if ( ! $term || is_wp_error( $term ) ) {
                    return new \WP_Error( 'mvm_news_category_missing', 'Een gekozen categorie bestaat niet.', array( 'status' => 400 ) );
                }
            }
            wp_set_post_categories( $post_id, $categories, false );
        }

        if ( array_key_exists( 'featuredMediaId', $input ) ) {
            $attachment_id = absint( $input['featuredMediaId'] );
            if ( 0 === $attachment_id ) {
                delete_post_thumbnail( $post_id );
            } else {
                $attachment = get_post( $attachment_id );
                if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
                    return new \WP_Error( 'mvm_news_featured_media', 'De gekozen uitgelichte afbeelding is ongeldig.', array( 'status' => 400 ) );
                }
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
        return true;
    }

    /** @return string|\WP_Error */
    private static function future_datetime( mixed $value ): string|\WP_Error {
        if ( null === $value || '' === trim( (string) $value ) ) {
            return new \WP_Error( 'mvm_news_schedule_time', 'Kies een publicatiemoment.', array( 'status' => 400 ) );
        }
        try {
            $date = new \DateTimeImmutable( (string) $value, new \DateTimeZone( 'UTC' ) );
            $date = $date->setTimezone( new \DateTimeZone( 'UTC' ) );
        } catch ( \Throwable ) {
            return new \WP_Error( 'mvm_news_schedule_time', 'Het publicatiemoment is ongeldig.', array( 'status' => 400 ) );
        }
        if ( $date->getTimestamp() <= time() + 60 ) {
            return new \WP_Error( 'mvm_news_schedule_past', 'Het publicatiemoment moet in de toekomst liggen.', array( 'status' => 400 ) );
        }
        return $date->format( 'Y-m-d H:i:s' );
    }
}
