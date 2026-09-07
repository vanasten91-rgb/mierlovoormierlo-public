<?php
/**
 * MvM Theme 1.0 — native hidden source for Mierlo Vandaag / greeting grid.
 * The visual grid reads this semantic source; it no longer depends on legacy Newsup/Hub markup.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mvm_theme1_today_source_image( $post_id, $size = 'large' ) {
    $post_id = absint( $post_id );
    if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
        return '';
    }

    $image = function_exists( 'mvm_theme1_home_thumbnail' )
        ? mvm_theme1_home_thumbnail( $post_id, $size, false )
        : get_the_post_thumbnail( $post_id, $size, array( 'decoding' => 'async', 'alt' => get_the_title( $post_id ) ) );

    return '<a class="mvm-today-photo-link" href="' . esc_url( get_permalink( $post_id ) ) . '">' .
        $image .
        '</a>';
}

function mvm_theme1_today_clean_excerpt( $post_id, $words = 22 ) {
    $text  = trim( wp_strip_all_tags( get_the_excerpt( $post_id ) ) );
    $title = trim( wp_strip_all_tags( get_the_title( $post_id ) ) );

    if ( '' !== $title && 0 === stripos( $text, $title ) ) {
        $text = trim( substr( $text, strlen( $title ) ) );
    }

    return wp_trim_words( $text, $words, '…' );
}

function mvm_theme1_today_news() {
    return get_posts(
        array(
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 3,
            'ignore_sticky_posts' => false,
            'no_found_rows'       => true,
        )
    );
}

function mvm_theme1_today_events() {
    $now_mysql = current_time( 'mysql' );
    $now       = current_datetime()->getTimestamp();
    $events    = get_posts(
        array(
            'post_type'      => 'event_listing',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_event_end_date',
                    'value'   => $now_mysql,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
                array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_event_end_date',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key'     => '_event_start_date',
                        'value'   => $now_mysql,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
            ),
            'no_found_rows'  => true,
        )
    );

    $event_timestamp = static function ( $event_id, $meta_key ) {
        $value = trim( (string) get_post_meta( $event_id, $meta_key, true ) );
        if ( '' === $value ) {
            return 0;
        }

        try {
            return ( new DateTimeImmutable( $value, wp_timezone() ) )->getTimestamp();
        } catch ( Exception $exception ) {
            return 0;
        }
    };

    usort(
        $events,
        static function ( $left, $right ) use ( $now, $event_timestamp ) {
            $left_start  = $event_timestamp( $left->ID, '_event_start_date' );
            $left_end    = $event_timestamp( $left->ID, '_event_end_date' );
            $right_start = $event_timestamp( $right->ID, '_event_start_date' );
            $right_end   = $event_timestamp( $right->ID, '_event_end_date' );

            $left_ongoing  = $left_start && $left_start <= $now && ( ! $left_end || $left_end >= $now );
            $right_ongoing = $right_start && $right_start <= $now && ( ! $right_end || $right_end >= $now );

            if ( $left_ongoing !== $right_ongoing ) {
                return $left_ongoing ? -1 : 1;
            }

            if ( $left_ongoing ) {
                $left_sort  = $left_end ?: PHP_INT_MAX;
                $right_sort = $right_end ?: PHP_INT_MAX;
            } else {
                $left_sort  = $left_start ?: PHP_INT_MAX;
                $right_sort = $right_start ?: PHP_INT_MAX;
            }

            return $left_sort <=> $right_sort;
        }
    );

    return array_slice( $events, 0, 3 );
}

function mvm_theme1_today_history() {
    $history = get_page_by_path( 'dekzandlandschap', OBJECT, 'mvm_encyclopedie' );
    if ( $history && 'publish' === $history->post_status ) {
        return $history;
    }

    $items = get_posts(
        array(
            'post_type'      => 'mvm_encyclopedie',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        )
    );

    return $items ? $items[0] : null;
}

function mvm_theme1_today_photo() {
    $eligible_ids = get_posts(
        array(
            'post_type'      => 'mvm_beeld',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ),
            ),
            'no_found_rows'  => true,
        )
    );

    if ( empty( $eligible_ids ) ) {
        return null;
    }

    $offset  = (int) wp_date( 'z' ) % count( $eligible_ids );
    $post_id = absint( $eligible_ids[ $offset ] );

    return $post_id ? get_post( $post_id ) : null;
}

function mvm_theme1_today_help( $exclude_ids = array() ) {
    $exclude_ids = array_values( array_filter( array_map( 'absint', (array) $exclude_ids ) ) );
    $after       = wp_date( 'Y-m-d', current_datetime()->modify( '-120 days' )->getTimestamp() );
    $base_args   = array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 1,
        'post__not_in'        => $exclude_ids,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'date_query'          => array(
            array(
                'after'     => $after,
                'inclusive' => true,
            ),
        ),
        'no_found_rows'       => true,
    );

    $items = get_posts( array_merge( $base_args, array( 'tag' => 'hulp' ) ) );

    if ( empty( $items ) ) {
        $items = get_posts( array_merge( $base_args, array( 's' => 'AutoMaatje' ) ) );
    }

    return $items ? $items[0] : null;
}

function mvm_theme1_today_visual_media( $post_id, $href = '', $priority = false ) {
    $post_id = absint( $post_id );
    if ( ! $post_id || ! has_post_thumbnail( $post_id ) ) {
        return '';
    }

    $href  = $href ? $href : get_permalink( $post_id );
    $image = function_exists( 'mvm_theme1_home_thumbnail' )
        ? mvm_theme1_home_thumbnail( $post_id, 'large', $priority )
        : get_the_post_thumbnail( $post_id, 'large', array( 'decoding' => 'async', 'alt' => get_the_title( $post_id ) ) );

    return '<a class="mvm-gm-media" href="' . esc_url( $href ) . '">' . $image . '</a>';
}

function mvm_theme1_today_photo_title( $photo ) {
    if ( ! $photo instanceof WP_Post ) {
        return '';
    }

    if ( 8962 === (int) $photo->ID ) {
        return 'Het voormalige gemeentewapen van Mierlo';
    }

    $thumb_id = get_post_thumbnail_id( $photo->ID );
    $title    = $thumb_id ? trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ) : '';
    if ( '' === $title ) {
        $title = preg_replace( '/\.(?:jpe?g|png|webp)$/i', '', get_the_title( $photo ) );
    }

    return rtrim( trim( $title ), ". \t\n\r\0\x0B" );
}

function mvm_theme1_today_visual_card( $args ) {
    $defaults = array(
        'type'    => 'generic',
        'slot'    => 'story-left',
        'kicker'  => '',
        'title'   => '',
        'href'    => '',
        'media'   => '',
        'text'    => '',
        'items'   => array(),
        'details' => array(),
        'cta'     => '',
        'cta_url' => '',
    );
    $args = wp_parse_args( $args, $defaults );

    $classes = 'mvm-gm-card mvm-gm-card--' . sanitize_html_class( $args['type'] ) . ' mvm-gm-card--' . sanitize_html_class( $args['slot'] );
    if ( $args['media'] ) {
        $classes .= ' has-media';
    } else {
        $classes .= ' no-media';
    }

    echo '<article class="' . esc_attr( $classes ) . '">';
    if ( $args['media'] ) {
        echo $args['media'];
    }
    echo '<div class="mvm-gm-card__content">';
    echo '<span class="mvm-gm-card__kicker">' . esc_html( $args['kicker'] ) . '</span>';
    echo '<h3>';
    if ( $args['href'] ) {
        echo '<a href="' . esc_url( $args['href'] ) . '">' . esc_html( $args['title'] ) . '</a>';
    } else {
        echo esc_html( $args['title'] );
    }
    echo '</h3>';

    if ( ! empty( $args['items'] ) ) {
        echo '<div class="mvm-gm-list">';
        foreach ( $args['items'] as $item ) {
            echo '<a class="mvm-gm-item" href="' . esc_url( $item['href'] ) . '"><strong>' . esc_html( $item['title'] ) . '</strong>';
            if ( ! empty( $item['meta'] ) ) {
                echo '<span>' . esc_html( $item['meta'] ) . '</span>';
            }
            echo '</a>';
        }
        echo '</div>';
    }

    if ( ! empty( $args['details'] ) ) {
        echo '<dl class="mvm-gm-event-facts">';
        foreach ( $args['details'] as $detail ) {
            if ( empty( $detail['label'] ) || empty( $detail['value'] ) ) {
                continue;
            }
            echo '<div><dt>' . esc_html( $detail['label'] ) . '</dt><dd>' . esc_html( $detail['value'] ) . '</dd></div>';
        }
        echo '</dl>';
    }

    if ( $args['text'] ) {
        echo '<p>' . esc_html( $args['text'] ) . '</p>';
    }

    if ( $args['cta'] && $args['cta_url'] ) {
        echo '<a class="mvm-gm-cta" href="' . esc_url( $args['cta_url'] ) . '">' . esc_html( $args['cta'] ) . ' →</a>';
    }
    echo '</div></article>';
}

function mvm_theme1_today_event_place( $event ) {
    $event_id  = is_object( $event ) ? absint( $event->ID ) : absint( $event );
    $venue_ids = maybe_unserialize( get_post_meta( $event_id, '_event_venue_ids', true ) );
    if ( is_array( $venue_ids ) && ! empty( $venue_ids[0] ) ) {
        $venue_title = trim( wp_strip_all_tags( get_the_title( absint( $venue_ids[0] ) ) ) );
        if ( '' !== $venue_title ) {
            return $venue_title;
        }
    }

    $location = trim( wp_strip_all_tags( (string) get_post_meta( $event_id, '_event_location', true ) ) );
    return '' !== $location ? $location : 'Nog niet vermeld';
}

function mvm_theme1_today_event_price( $event ) {
    $event_id = is_object( $event ) ? absint( $event->ID ) : absint( $event );
    $price    = trim( (string) get_post_meta( $event_id, '_event_ticket_price', true ) );
    if ( '' !== $price ) {
        return false !== strpos( $price, '€' ) ? $price : '€' . $price;
    }

    $ticket_option = strtolower( trim( (string) get_post_meta( $event_id, '_event_ticket_options', true ) ) );
    if ( 'free' === $ticket_option ) {
        return 'Gratis';
    }

    $content = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) get_post_field( 'post_content', $event_id ) ) ) );
    if ( preg_match( '/(?:inschrijfgeld|entree|prijs)\s*:?\s*€\s*([0-9]+(?:[\.,][0-9]{1,2})?(?:\s+per\s+[[:alpha:]-]+)?)/iu', $content, $match ) ) {
        return '€' . trim( $match[1] );
    }

    return 'Niet vermeld';
}

function mvm_theme1_today_event_description( $event ) {
    $event_id = is_object( $event ) ? absint( $event->ID ) : absint( $event );
    $text     = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $event_id ) ) );
    if ( '' === $text ) {
        $text = trim( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $event_id ) ) ) );
    }
    $text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
    if ( '' === $text ) {
        return 'Bekijk de evenementpagina voor meer informatie over dit evenement in Mierlo.';
    }

    $sentences = preg_split( '/(?<=[.!?])\s+(?=[A-ZÀ-ÖØ-Ý0-9])/u', $text, 3, PREG_SPLIT_NO_EMPTY );
    $sentences = array_values( array_filter( array_map( 'trim', (array) $sentences ) ) );
    $summary   = trim( implode( ' ', array_slice( $sentences, 0, 2 ) ) );

    if ( count( $sentences ) < 2 ) {
        if ( '' !== $summary && ! preg_match( '/[.!?]$/u', $summary ) ) {
            $summary .= '.';
        }
        $place = mvm_theme1_today_event_place( $event_id );
        if ( 'Nog niet vermeld' !== $place ) {
            $summary .= ' Het evenement vindt plaats bij ' . $place . '.';
        }
    }

    return wp_trim_words( $summary, 42, '…' );
}

function mvm_theme1_today_event_details( $event ) {
    $event_id = is_object( $event ) ? absint( $event->ID ) : absint( $event );
    $start    = trim( (string) get_post_meta( $event_id, '_event_start_date', true ) );
    $time     = trim( (string) get_post_meta( $event_id, '_event_start_time', true ) );
    $end_time = trim( (string) get_post_meta( $event_id, '_event_end_time', true ) );
    $time     = preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ? substr( $time, 0, 5 ) : $time;
    $end_time = preg_match( '/^\d{2}:\d{2}:\d{2}$/', $end_time ) ? substr( $end_time, 0, 5 ) : $end_time;
    $all_day  = '1' === (string) get_post_meta( $event_id, '_mvm_all_day', true ) || ( '00:00' === $time && '23:59' === $end_time );
    $time_label = $all_day ? 'Hele dag' : ( ( $time && '00:00' !== $time ) ? $time . ' uur' : 'Nog niet vermeld' );

    return array(
        array( 'label' => 'Datum', 'value' => $start ? wp_date( 'j F Y', strtotime( $start ) ) : 'Nog niet vermeld' ),
        array( 'label' => 'Tijd', 'value' => $time_label ),
        array( 'label' => 'Plaats', 'value' => mvm_theme1_today_event_place( $event_id ) ),
        array( 'label' => 'Prijs', 'value' => mvm_theme1_today_event_price( $event_id ) ),
    );
}

function mvm_theme1_render_today_visual() {
    $news      = mvm_theme1_today_news();
    $events    = mvm_theme1_today_events();
    $history   = mvm_theme1_today_history();
    $photo     = mvm_theme1_today_photo();
    $news_item = $news ? $news[0] : null;
    $help      = mvm_theme1_today_help( $news_item ? array( $news_item->ID ) : array() );

    $greeting = 'Goedemiddag Mierlo';
    $hour     = (int) current_time( 'G' );
    if ( $hour < 12 ) {
        $greeting = 'Goedemorgen Mierlo';
    } elseif ( $hour >= 18 ) {
        $greeting = 'Goedenavond Mierlo';
    }

    echo '<section class="mvm-gm-lab" aria-label="' . esc_attr( $greeting ) . '"><div class="mvm-gm-lab__shell">';
    echo '<header class="mvm-gm-lab__top"><div class="mvm-gm-lab__copy"><span class="mvm-gm-lab__eyebrow">Mierlo Vandaag</span><h2>' . esc_html( $greeting ) . '</h2><p>Dit speelt er vandaag in Mierlo</p></div></header>';
    echo '<section class="mvm-gm-panel is-active" data-variant="visual" data-layout="editorial-grid"><div class="mvm-gm-grid">';

    if ( $news_item ) {
        mvm_theme1_today_visual_card( array(
            'type'    => 'news',
            'slot'    => 'hero',
            'kicker'  => 'Nieuws',
            'title'   => get_the_title( $news_item ),
            'href'    => get_permalink( $news_item ),
            'media'   => mvm_theme1_today_visual_media( $news_item->ID, '', true ),
            'cta'     => 'Naar het laatste nieuws',
            'cta_url' => home_url( '/category/algemeen/' ),
        ) );
    }

    if ( $photo ) {
        mvm_theme1_today_visual_card( array(
            'type'    => 'photo',
            'slot'    => 'feature-left',
            'kicker'  => 'Foto van de dag',
            'title'   => mvm_theme1_today_photo_title( $photo ),
            'href'    => get_permalink( $photo ),
            'media'   => mvm_theme1_today_visual_media( $photo->ID ),
            'cta'     => 'Bekijk in de Encyclopedie',
            'cta_url' => get_permalink( $photo ),
        ) );
    }

    if ( $help ) {
        mvm_theme1_today_visual_card( array(
            'type'    => 'help',
            'slot'    => 'feature-right',
            'kicker'  => 'Mierlo helpt Mierlo',
            'title'   => get_the_title( $help ),
            'href'    => get_permalink( $help ),
            'media'   => mvm_theme1_today_visual_media( $help->ID ),
            'cta'     => 'Bekijk de oproep',
            'cta_url' => get_permalink( $help ),
        ) );
    }

    $event_slots = array( 'story-left', 'story-center', 'story-right' );
    foreach ( array_slice( $events, 0, 3 ) as $index => $event ) {
        mvm_theme1_today_visual_card( array(
            'type'    => 'agenda',
            'slot'    => $event_slots[ $index ],
            'kicker'  => 'Agenda',
            'title'   => get_the_title( $event ),
            'href'    => get_permalink( $event ),
            'media'   => mvm_theme1_today_visual_media( $event->ID ),
            'details' => mvm_theme1_today_event_details( $event ),
            'text'    => mvm_theme1_today_event_description( $event ),
            'cta'     => 'Bekijk evenement',
            'cta_url' => get_permalink( $event ),
        ) );
    }

    echo '</div></section></div></section>';
}

function mvm_theme1_render_today_source() {
    if ( ! is_front_page() && ! is_home() ) {
        return;
    }

    $lab_mode = isset( $_GET['mvm_design_lab'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['mvm_design_lab'] ) );
    if ( ! $lab_mode ) {
        return;
    }

    $news      = mvm_theme1_today_news();
    $events    = mvm_theme1_today_events();
    $history   = mvm_theme1_today_history();
    $photo     = mvm_theme1_today_photo();
    $news_item = $news ? $news[0] : null;
    $help      = mvm_theme1_today_help( $news_item ? array( $news_item->ID ) : array() );

    echo '<section class="mvm-today-section" hidden aria-hidden="true">';
    echo '<header class="mvm-today-head"><h2>Mierlo Vandaag</h2><p>Dit speelt er vandaag in Mierlo</p></header>';

    echo '<article class="mvm-today-card mvm-today-news"><span class="mvm-today-kicker">Nieuws</span><h3>Actueel in Mierlo</h3><div class="mvm-today-card-body">';
    if ( $news ) {
        echo mvm_theme1_today_source_image( $news[0]->ID );
        foreach ( $news as $item ) {
            echo '<a href="' . esc_url( get_permalink( $item ) ) . '"><strong>' . esc_html( get_the_title( $item ) ) . '</strong><span>' . esc_html( get_the_date( 'j F', $item ) ) . '</span></a>';
        }
    }
    echo '</div><a class="mvm-today-cta" href="' . esc_url( home_url( '/category/algemeen/' ) ) . '">Naar het laatste nieuws →</a></article>';

    echo '<article class="mvm-today-card mvm-today-agenda"><span class="mvm-today-kicker">Agenda</span><h3>Vandaag & binnenkort</h3><div class="mvm-today-card-body">';
    if ( $events ) {
        echo mvm_theme1_today_source_image( $events[0]->ID );
        foreach ( $events as $event ) {
            $start    = (string) get_post_meta( $event->ID, '_event_start_date', true );
            $time     = trim( (string) get_post_meta( $event->ID, '_event_start_time', true ) );
            $location = trim( (string) get_post_meta( $event->ID, '_event_location', true ) );
            $date     = $start ? wp_date( 'j M', strtotime( $start ) ) : '';
            $meta     = implode( ' · ', array_filter( array( $date, ( $time && '00:00' !== $time ? $time . ' uur' : '' ), $location ) ) );
            echo '<a href="' . esc_url( get_permalink( $event ) ) . '"><strong>' . esc_html( get_the_title( $event ) ) . '</strong><span>' . esc_html( $meta ) . '</span></a>';
        }
    }
    echo '</div><a class="mvm-today-cta" href="' . esc_url( home_url( '/evenementen/' ) ) . '">Bekijk de agenda →</a></article>';

    echo '<article class="mvm-today-card mvm-today-history"><span class="mvm-today-kicker">Uit de geschiedenis</span>';
    if ( $history ) {
        echo '<h3>' . esc_html( get_the_title( $history ) ) . '</h3><div class="mvm-today-card-body">';
        echo mvm_theme1_today_source_image( $history->ID );
        $history_excerpt = mvm_theme1_today_clean_excerpt( $history->ID, 24 );
        if ( $history_excerpt ) {
            echo '<p>' . esc_html( $history_excerpt ) . '</p>';
        }
        echo '</div><a class="mvm-today-cta" href="' . esc_url( get_permalink( $history ) ) . '">Ontdek dit verhaal →</a>';
    } else {
        echo '<h3>Ontdek Mierlo</h3><div class="mvm-today-card-body"><p>Verhalen, plekken en mensen uit de Mierlose geschiedenis.</p></div>';
    }
    echo '</article>';

    echo '<article class="mvm-today-card mvm-today-photo"><span class="mvm-today-kicker">Foto van de dag</span>';
    if ( $photo ) {
        $thumb_id = get_post_thumbnail_id( $photo->ID );
        $alt      = $thumb_id ? trim( (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ) : '';
        if ( '' === $alt ) {
            $alt = get_the_title( $photo );
        }
        echo '<h3>' . esc_html( get_the_title( $photo ) ) . '</h3><div class="mvm-today-card-body">';
        $photo_image = function_exists( 'mvm_theme1_home_thumbnail' )
            ? mvm_theme1_home_thumbnail( $photo->ID, 'large', false )
            : get_the_post_thumbnail( $photo->ID, 'large', array( 'decoding' => 'async', 'alt' => $alt ) );
        echo '<a class="mvm-today-photo-link" href="' . esc_url( get_permalink( $photo ) ) . '">' . $photo_image . '</a>';
        echo '<p class="mvm-today-photo-caption">Uit de Mierlose Encyclopedie</p></div><a class="mvm-today-cta" href="' . esc_url( get_permalink( $photo ) ) . '">Bekijk in de Encyclopedie →</a>';
    } else {
        echo '<h3>Uit de beeldbank</h3><div class="mvm-today-card-body"></div>';
    }
    echo '</article>';

    echo '<article class="mvm-today-card mvm-today-help"><span class="mvm-today-kicker">Mierlo helpt Mierlo</span>';
    if ( $help ) {
        echo '<h3>' . esc_html( get_the_title( $help ) ) . '</h3><div class="mvm-today-card-body">';
        echo mvm_theme1_today_source_image( $help->ID );
        $help_excerpt = mvm_theme1_today_clean_excerpt( $help->ID, 20 );
        if ( $help_excerpt ) {
            echo '<p>' . esc_html( $help_excerpt ) . '</p>';
        }
        echo '</div><a class="mvm-today-cta" href="' . esc_url( get_permalink( $help ) ) . '">Bekijk de oproep →</a>';
    } else {
        echo '<h3>Samen helpen</h3><div class="mvm-today-card-body"><p>Bekijk hulpvragen, vrijwilligerswerk en lokale oproepen.</p></div><a class="mvm-today-cta" href="' . esc_url( home_url( '/tag/hulp/' ) ) . '">Bekijk hulpvragen →</a>';
    }
    echo '</article>';

    echo '<article class="mvm-today-card mvm-today-forum"><span class="mvm-today-kicker">Mierlo praat mee</span><h3>Actieve forumonderwerpen</h3><div class="mvm-today-card-body"><p>Praat mee over wat er speelt in Mierlo.</p></div><a class="mvm-today-cta" href="' . esc_url( home_url( '/forum/recent/' ) ) . '">Meer forumonderwerpen →</a></article>';

    echo '</section>';
}
add_action( 'mvm_theme1_home_sections', 'mvm_theme1_render_today_source', 19 );
