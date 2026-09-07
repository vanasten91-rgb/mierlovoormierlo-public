<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Newsletter_Content {
	private const AGENDA_LIMIT          = 8;
	private const SNAPSHOT_PREFIX       = 'mvm_newsletter_agenda_';
	private const FORUM_LIMIT           = 5;
	private const FORUM_RECENCY_DAYS    = 30;
	private const FORUM_SNAPSHOT_PREFIX = 'mvm_newsletter_forum_';

	/**
	 * Append the standard MvM Agenda and current public forum sections.
	 *
	 * Snapshots are keyed to the stable campaign content fingerprint. The first
	 * preview/test/send freezes the selected IDs so later delivery cannot
	 * silently pick up a different set of events or forum discussions.
	 */
	public static function append_agenda( string $body, string $preheader = '' ): string {
		$source_body = $body;
		$key         = self::snapshot_key( $source_body, $preheader );
		$ids         = get_option( $key, null );
		if ( ! is_array( $ids ) ) {
			$ids = self::upcoming_event_ids();
			update_option( $key, $ids, false );
		}

		$events = self::event_cards( $ids );
		if ( ! empty( $events ) ) {
			$body .= self::render_agenda( $events );
		}

		return self::append_forum_topics( $body, $source_body, $preheader );
	}

	private static function snapshot_key( string $body, string $preheader ): string {
		$fingerprint = hash( 'sha256', wp_strip_all_tags( $body ) . '|' . sanitize_text_field( $preheader ) );
		return self::SNAPSHOT_PREFIX . substr( $fingerprint, 0, 32 );
	}

	private static function upcoming_event_ids(): array {
		$today = wp_date( 'Y-m-d', null, wp_timezone() );
		$posts = get_posts(
			array(
				'post_type'      => 'event_listing',
				'post_status'    => 'publish',
				'posts_per_page' => self::AGENDA_LIMIT,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_event_start_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => '_event_start_date',
						'value'   => $today,
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
			)
		);

		return array_values( array_filter( array_map( 'absint', (array) $posts ) ) );
	}

	private static function event_cards( array $ids ): array {
		$cards = array();
		foreach ( array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, self::AGENDA_LIMIT ) as $event_id ) {
			$post = get_post( $event_id );
			if ( ! ( $post instanceof WP_Post ) || 'event_listing' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}

			$start_date = sanitize_text_field( (string) get_post_meta( $event_id, '_event_start_date', true ) );
			$end_date   = sanitize_text_field( (string) get_post_meta( $event_id, '_event_end_date', true ) );
			$start_time = sanitize_text_field( (string) get_post_meta( $event_id, '_event_start_time', true ) );
			$end_time   = sanitize_text_field( (string) get_post_meta( $event_id, '_event_end_time', true ) );
			$location   = sanitize_text_field( (string) get_post_meta( $event_id, '_event_location', true ) );
			$image      = get_the_post_thumbnail_url( $event_id, 'medium_large' );

			$cards[] = array(
				'id'       => $event_id,
				'title'    => get_the_title( $event_id ),
				'url'      => get_permalink( $event_id ),
				'image'    => is_string( $image ) ? $image : '',
				'date'     => self::format_date_range( $start_date, $end_date ),
				'time'     => self::format_time_range( $start_time, $end_time ),
				'location' => $location,
			);
		}
		return $cards;
	}

	private static function format_date_range( string $start, string $end ): string {
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		if ( false === $start_ts ) {
			return '';
		}
		$start_label = wp_date( 'D j M', $start_ts, wp_timezone() );
		if ( false !== $end_ts && gmdate( 'Y-m-d', $end_ts ) !== gmdate( 'Y-m-d', $start_ts ) ) {
			return $start_label . ' – ' . wp_date( 'D j M', $end_ts, wp_timezone() );
		}
		return $start_label;
	}

	private static function format_time_range( string $start, string $end ): string {
		$start = trim( $start );
		$end   = trim( $end );
		if ( '' === $start ) {
			return 'Hele dag';
		}
		return '' !== $end ? $start . ' – ' . $end : $start;
	}

	private static function render_agenda( array $events ): string {
		$html  = '<div style="margin:34px 0 0;padding-top:26px;border-top:3px solid #1966AE">';
		$html .= '<h2 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.25;color:#17222d">Agenda</h2>';
		$html .= '<p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#536779">Wat is er binnenkort te doen in Mierlo?</p>';

		foreach ( $events as $event ) {
			$html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 16px;border-collapse:separate;border-spacing:0;border:1px solid #cbd8e2;border-radius:12px">';
			$html .= '<tr><td style="padding:18px">';
			if ( '' !== $event['image'] ) {
				$html .= '<img src="' . esc_url( $event['image'] ) . '" alt="" width="180" style="display:block;width:180px;max-width:100%;height:auto;margin:0 0 14px;border:0;border-radius:8px">';
			}
			$html .= '<h3 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:19px;line-height:1.35;color:#17222d">' . esc_html( $event['title'] ) . '</h3>';
			$meta = array_filter( array( $event['date'], $event['time'], $event['location'] ) );
			if ( $meta ) {
				$html .= '<p style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#536779">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
			}
			$html .= '<a href="' . esc_url( $event['url'] ) . '" style="display:inline-block;background:#1966AE;color:#ffffff;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;padding:10px 15px;border-radius:8px">Bekijk evenement</a>';
			$html .= '</td></tr></table>';
		}

		$html .= '<p style="margin:4px 0 0"><a href="' . esc_url( home_url( '/evenementen/' ) ) . '" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#1966AE;text-decoration:underline">Bekijk de volledige agenda</a></p>';
		$html .= '</div>';
		return $html;
	}

	private static function append_forum_topics( string $body, string $source_body, string $preheader ): string {
		$key = self::forum_snapshot_key( $source_body, $preheader );
		$ids = get_option( $key, null );
		if ( ! is_array( $ids ) ) {
			$ids = self::recent_public_forum_topic_ids();
			update_option( $key, $ids, false );
		}

		$topics = self::forum_topic_cards( $ids );
		return empty( $topics ) ? $body : $body . self::render_forum_topics( $topics );
	}

	private static function forum_snapshot_key( string $body, string $preheader ): string {
		$fingerprint = hash( 'sha256', wp_strip_all_tags( $body ) . '|' . sanitize_text_field( $preheader ) );
		return self::FORUM_SNAPSHOT_PREFIX . substr( $fingerprint, 0, 32 );
	}

	private static function recent_public_forum_topic_ids(): array {
		global $wpdb;

		$topics_table = $wpdb->prefix . 'wpforo_topics';
		$forums_table = $wpdb->prefix . 'wpforo_forums';
		$cutoff       = gmdate( 'Y-m-d H:i:s', time() - self::FORUM_RECENCY_DAYS * DAY_IN_SECONDS );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.topicid,f.permissions
				FROM {$topics_table} t
				INNER JOIN {$forums_table} f ON f.forumid = t.forumid
				WHERE t.private = 0 AND t.status = 0 AND f.status = 1 AND f.is_cat = 0
				AND COALESCE(NULLIF(t.modified,'0000-00-00 00:00:00'),t.created) >= %s
				ORDER BY COALESCE(NULLIF(t.modified,'0000-00-00 00:00:00'),t.created) DESC,t.topicid DESC
				LIMIT 25",
				$cutoff
			)
		);

		$ids = array();
		foreach ( (array) $rows as $row ) {
			if ( ! self::forum_is_public_for_guests( (string) $row->permissions ) ) {
				continue;
			}
			$ids[] = absint( $row->topicid );
			if ( count( $ids ) >= self::FORUM_LIMIT ) {
				break;
			}
		}

		return $ids;
	}

	private static function forum_topic_cards( array $ids ): array {
		global $wpdb;

		$topics_table = $wpdb->prefix . 'wpforo_topics';
		$forums_table = $wpdb->prefix . 'wpforo_forums';
		$topic_ids   = array_slice( array_values( array_filter( array_unique( array_map( 'absint', $ids ) ) ) ), 0, self::FORUM_LIMIT );
		if ( empty( $topic_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $topic_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.topicid,t.title,t.slug,t.created,t.modified,t.posts,t.views,t.private,t.status,f.title AS forum_title,f.slug AS forum_slug,f.status AS forum_status,f.is_cat,f.permissions
				FROM {$topics_table} t INNER JOIN {$forums_table} f ON f.forumid = t.forumid
				WHERE t.topicid IN ({$placeholders})",
				...$topic_ids
			)
		);
		$rows_by_id = array();
		foreach ( (array) $rows as $row ) {
			$rows_by_id[ absint( $row->topicid ) ] = $row;
		}

		$cards = array();
		foreach ( $topic_ids as $topic_id ) {
			$row = $rows_by_id[ $topic_id ] ?? null;
			if ( ! $row || 0 !== (int) $row->private || 0 !== (int) $row->status || 1 !== (int) $row->forum_status || 0 !== (int) $row->is_cat || ! self::forum_is_public_for_guests( (string) $row->permissions ) ) {
				continue;
			}

			$cards[] = array(
				'title'   => sanitize_text_field( (string) $row->title ),
				'forum'   => sanitize_text_field( (string) $row->forum_title ),
				'url'     => self::forum_topic_url( $row ),
				'updated' => self::format_forum_date( (string) ( $row->modified ?: $row->created ) ),
				'replies' => max( 0, (int) $row->posts - 1 ),
				'views'   => max( 0, (int) $row->views ),
			);
		}

		return $cards;
	}

	private static function forum_is_public_for_guests( string $permissions ): bool {
		$permissions = maybe_unserialize( $permissions );
		return is_array( $permissions ) && isset( $permissions[4] ) && 'no_access' !== (string) $permissions[4];
	}

	private static function forum_topic_url( object $row ): string {
		if ( function_exists( 'wpforo_topic' ) ) {
			$topic = wpforo_topic( absint( $row->topicid ) );
			if ( is_array( $topic ) && ! empty( $topic['url'] ) ) {
				return esc_url_raw( (string) $topic['url'] );
			}
		}

		return esc_url_raw( home_url( '/forum/' . sanitize_title( (string) $row->forum_slug ) . '/' . sanitize_title( (string) $row->slug ) . '/' ) );
	}

	private static function format_forum_date( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );
		return false === $timestamp ? '' : wp_date( 'j M Y', $timestamp, wp_timezone() );
	}

	private static function render_forum_topics( array $topics ): string {
		$html  = '<div style="margin:34px 0 0;padding-top:26px;border-top:3px solid #1966AE">';
		$html .= '<h2 style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.25;color:#17222d">Actueel op het forum</h2>';
		$html .= '<p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#536779">De nieuwste openbare gesprekken op het online dorpsplein van Mierlo.</p>';

		foreach ( $topics as $topic ) {
			$meta  = array_filter( array( $topic['forum'], $topic['updated'], sprintf( _n( '%d reactie', '%d reacties', $topic['replies'], 'mvm-platform' ), $topic['replies'] ) ) );
			$html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 12px;border-collapse:separate;border-spacing:0;border:1px solid #cbd8e2;border-radius:12px">';
			$html .= '<tr><td style="padding:16px 18px">';
			$html .= '<h3 style="margin:0 0 7px;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:1.35;color:#17222d">' . esc_html( $topic['title'] ) . '</h3>';
			$html .= '<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.6;color:#536779">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
			$html .= '<a href="' . esc_url( $topic['url'] ) . '" style="display:inline-block;background:#1966AE;color:#ffffff;text-decoration:none;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;padding:9px 14px;border-radius:8px">Lees en praat mee</a>';
			$html .= '</td></tr></table>';
		}

		$html .= '<p style="margin:4px 0 0"><a href="' . esc_url( home_url( '/forum/' ) ) . '" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#1966AE;text-decoration:underline">Bekijk alle forumonderwerpen</a></p>';
		$html .= '</div>';
		return $html;
	}
}
