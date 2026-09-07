<?php

defined( 'ABSPATH' ) || exit;

final class MvM_Newsletter_Delivery {
	private const BATCH_SIZE   = 25;
	private const MAX_ATTEMPTS = 3;

	public static function dispatch(): void {
		self::queue_due_campaigns();
		self::send_batch();
	}

	public static function queue_due_campaigns(): void {
		global $wpdb;
		$campaigns = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . MvM_Newsletter::table( 'campaigns' ) . " WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT 5",
				MvM_Newsletter::now_mysql()
			)
		);
		foreach ( array_map( 'absint', (array) $campaigns ) as $campaign_id ) {
			self::queue_campaign( $campaign_id );
		}
	}

	public static function queue_campaign( int $campaign_id ): bool {
		global $wpdb;
		$campaign = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MvM_Newsletter::table( 'campaigns' ) . ' WHERE id = %d LIMIT 1', $campaign_id ) );
		if ( ! $campaign || ! in_array( (string) $campaign->status, array( 'scheduled', 'sending' ), true ) ) {
			return false;
		}

		$claimed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . MvM_Newsletter::table( 'campaigns' ) . " SET status = 'sending', updated_at = %s WHERE id = %d AND status IN ('scheduled','sending')",
				MvM_Newsletter::now_mysql(),
				$campaign_id
			)
		);
		if ( false === $claimed ) {
			return false;
		}

		$campaign_topics = MvM_Newsletter::decode_topics( $campaign->topics );
		$subscribers = $wpdb->get_results( "SELECT id,topics FROM " . MvM_Newsletter::table( 'subscribers' ) . " WHERE status = 'active' ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$now = MvM_Newsletter::now_mysql();
		foreach ( (array) $subscribers as $subscriber ) {
			if ( ! array_intersect( $campaign_topics, MvM_Newsletter::decode_topics( $subscriber->topics ) ) ) {
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO ' . MvM_Newsletter::table( 'queue' ) . " (campaign_id,subscriber_id,status,attempts,available_at) VALUES (%d,%d,'pending',0,%s)",
					$campaign_id,
					absint( $subscriber->id ),
					$now
				)
			);
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MvM_Newsletter::table( 'queue' ) . ' WHERE campaign_id = %d', $campaign_id ) );
		$wpdb->update(
			MvM_Newsletter::table( 'campaigns' ),
			array( 'total_queued' => $total, 'updated_at' => $now ),
			array( 'id' => $campaign_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( 0 === $total ) {
			$wpdb->update(
				MvM_Newsletter::table( 'campaigns' ),
				array( 'status' => 'sent', 'sent_at' => $now, 'updated_at' => $now ),
				array( 'id' => $campaign_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
		return true;
	}

	public static function send_batch(): void {
		global $wpdb;
		$now = MvM_Newsletter::now_mysql();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT q.id AS queue_id,q.campaign_id,q.subscriber_id,q.attempts,s.email,s.email_hash,s.consent_at,s.status AS subscriber_status,c.subject,c.preheader,c.body,c.status AS campaign_status
				FROM ' . MvM_Newsletter::table( 'queue' ) . ' q
				INNER JOIN ' . MvM_Newsletter::table( 'subscribers' ) . ' s ON s.id = q.subscriber_id
				INNER JOIN ' . MvM_Newsletter::table( 'campaigns' ) . " c ON c.id = q.campaign_id
				WHERE q.status IN ('pending','failed') AND q.attempts < %d AND q.available_at <= %s AND s.status = 'active' AND c.status = 'sending'
				ORDER BY q.id ASC LIMIT " . self::BATCH_SIZE,
				self::MAX_ATTEMPTS,
				$now
			)
		);

		$campaign_ids = array();
		foreach ( (array) $rows as $row ) {
			$queue_id = absint( $row->queue_id );
			$claimed = $wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . MvM_Newsletter::table( 'queue' ) . " SET status = 'sending', attempts = attempts + 1 WHERE id = %d AND status IN ('pending','failed') AND attempts < %d",
					$queue_id,
					self::MAX_ATTEMPTS
				)
			);
			if ( 1 !== (int) $claimed ) {
				continue;
			}
			$campaign_ids[ absint( $row->campaign_id ) ] = true;
			$subscriber = (object) array(
				'id'         => absint( $row->subscriber_id ),
				'email'      => (string) $row->email,
				'email_hash' => (string) $row->email_hash,
				'consent_at' => (string) $row->consent_at,
			);
			$sent = self::send_campaign_message( $row, $subscriber );
			if ( $sent ) {
				$wpdb->update(
					MvM_Newsletter::table( 'queue' ),
					array( 'status' => 'sent', 'sent_at' => MvM_Newsletter::now_mysql(), 'last_error' => '' ),
					array( 'id' => $queue_id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$current_attempts = (int) $row->attempts + 1;
				$retry_at = gmdate( 'Y-m-d H:i:s', time() + ( 15 * MINUTE_IN_SECONDS * max( 1, $current_attempts ) ) );
				$wpdb->update(
					MvM_Newsletter::table( 'queue' ),
					array( 'status' => 'failed', 'available_at' => $retry_at, 'last_error' => 'delivery_failed' ),
					array( 'id' => $queue_id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		foreach ( array_keys( $campaign_ids ) as $campaign_id ) {
			self::refresh_campaign( (int) $campaign_id );
		}
	}

	private static function send_campaign_message( object $campaign, object $subscriber ): bool {
		$unsubscribe_token = MvM_Newsletter::unsubscribe_token( $subscriber );
		$human_link = home_url( '/nieuwsbrief/afmelden/#token=' . rawurlencode( $unsubscribe_token ) );
		$one_click = add_query_arg(
			'token',
			$unsubscribe_token,
			rest_url( MvM_Platform_REST::NAMESPACE . '/newsletter/unsubscribe' )
		);
		$body = self::wrap_html( (string) $campaign->body, (string) $campaign->preheader, $human_link );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'List-Unsubscribe: <' . esc_url_raw( $one_click ) . '>',
			'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
		);
		return self::send( (string) $subscriber->email, (string) $campaign->subject, $body, $headers );
	}

	public static function send_confirmation( string $email, string $token ): bool {
		$link = home_url( '/nieuwsbrief/bevestigen/#token=' . rawurlencode( $token ) );
		$body = self::wrap_html(
			'<p style="margin:0 0 18px">Je hebt gevraagd om de Mierlo voor Mierlo-nieuwsbrief te ontvangen.</p><p style="margin:0 0 22px"><a href="' . esc_url( $link ) . '" style="display:inline-block;background:#1966AE;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:8px">Bevestig mijn nieuwsbriefinschrijving</a></p><p style="margin:0">Deze link is 24 uur geldig. Heb je dit niet aangevraagd? Dan hoef je niets te doen.</p>',
			'Bevestig je nieuwsbriefinschrijving',
			''
		);
		return self::send( $email, 'Bevestig je inschrijving – Mierlo voor Mierlo', $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function send_test( object $campaign, string $email ): bool {
		$body = self::wrap_html( (string) $campaign->body, (string) $campaign->preheader, '', true );
		return self::send( $email, '[TEST] ' . (string) $campaign->subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	private static function wrap_html( string $body, string $preheader, string $unsubscribe_link, bool $is_test = false ): string {
		return MvM_Newsletter_Template::render( $body, $preheader, $unsubscribe_link, $is_test );
	}

	private static function send( string $email, string $subject, string $body, array $headers ): bool {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$provider = apply_filters( 'mvm_newsletter_delivery_provider', null );
		if ( is_callable( $provider ) ) {
			return (bool) call_user_func( $provider, $email, $subject, $body, $headers );
		}
		return (bool) wp_mail( $email, sanitize_text_field( $subject ), $body, $headers );
	}

	private static function refresh_campaign( int $campaign_id ): void {
		global $wpdb;
		$queue = MvM_Newsletter::table( 'queue' );
		$sent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND status = 'sent'", $campaign_id ) );
		$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND status = 'failed' AND attempts >= %d", $campaign_id, self::MAX_ATTEMPTS ) );
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue} WHERE campaign_id = %d AND (status IN ('pending','sending') OR (status = 'failed' AND attempts < %d))",
				$campaign_id,
				self::MAX_ATTEMPTS
			)
		);
		$data = array(
			'total_sent'   => $sent,
			'total_failed' => $failed,
			'updated_at'   => MvM_Newsletter::now_mysql(),
		);
		$formats = array( '%d', '%d', '%s' );
		if ( 0 === $remaining ) {
			$data['status']  = 'sent';
			$data['sent_at'] = MvM_Newsletter::now_mysql();
			$formats[] = '%s';
			$formats[] = '%s';
		}
		$wpdb->update( MvM_Newsletter::table( 'campaigns' ), $data, array( 'id' => $campaign_id ), $formats, array( '%d' ) );
	}
}
