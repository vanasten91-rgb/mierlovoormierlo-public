<?php

namespace MVM\Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Deterministic, explainable priority scoring for the V5 My Work dashboard.
 *
 * This class does not query or persist work items. It only scores already
 * authorized records supplied by a read model. Authorization must therefore
 * happen before an item reaches this prioritizer.
 */
final class My_Work_Prioritizer {
    private const PRIORITY_WEIGHT = array(
        Work_Item_Schema::PRIORITY_LOW    => 0,
        Work_Item_Schema::PRIORITY_NORMAL => 100,
        Work_Item_Schema::PRIORITY_HIGH   => 250,
        Work_Item_Schema::PRIORITY_URGENT => 500,
    );

    /**
     * @param array<string,mixed> $item Normalized Work_Item_Schema record.
     * @return array{score:int,reasons:list<string>}
     */
    public static function score( array $item, ?\DateTimeImmutable $now = null ): array {
        $now = $now ?: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

        $score   = 0;
        $reasons = array();

        $classification = Data_Classification::normalize( (string) ( $item['classification'] ?? '' ) );
        if ( Data_Classification::SOURCE_PROTECTED === $classification ) {
            $score += 700;
            $reasons[] = 'Beveiligde bron/case vereist tijdige opvolging.';
        } elseif ( Data_Classification::CONFIDENTIAL === $classification ) {
            $score += 75;
            $reasons[] = 'Vertrouwelijke taak.';
        }

        $priority = sanitize_key( (string) ( $item['priority'] ?? Work_Item_Schema::PRIORITY_NORMAL ) );
        $priority_weight = self::PRIORITY_WEIGHT[ $priority ] ?? self::PRIORITY_WEIGHT[ Work_Item_Schema::PRIORITY_NORMAL ];
        $score += $priority_weight;

        if ( Work_Item_Schema::PRIORITY_URGENT === $priority ) {
            $reasons[] = 'Als urgent gemarkeerd.';
        } elseif ( Work_Item_Schema::PRIORITY_HIGH === $priority ) {
            $reasons[] = 'Hoge prioriteit.';
        }

        $status = sanitize_key( (string) ( $item['status'] ?? Work_Item_Schema::STATUS_OPEN ) );
        if ( Work_Item_Schema::STATUS_BLOCKED === $status ) {
            $score += 275;
            $reasons[] = 'Geblokkeerd; kan andere werkzaamheden ophouden.';
        }

        $dependency_ids = is_array( $item['dependency_ids'] ?? null ) ? $item['dependency_ids'] : array();
        if ( ! empty( $dependency_ids ) ) {
            $score += min( 150, count( $dependency_ids ) * 25 );
            $reasons[] = 'Heeft gekoppelde afhankelijkheden.';
        }

        $deadline = self::parse_deadline( $item['deadline_utc'] ?? '' );
        if ( null !== $deadline ) {
            $seconds = $deadline->getTimestamp() - $now->getTimestamp();

            if ( $seconds < 0 ) {
                $score += 650;
                $reasons[] = 'Deadline verstreken.';
            } elseif ( $seconds <= HOUR_IN_SECONDS * 4 ) {
                $score += 500;
                $reasons[] = 'Deadline binnen 4 uur.';
            } elseif ( $seconds <= DAY_IN_SECONDS ) {
                $score += 350;
                $reasons[] = 'Deadline binnen 24 uur.';
            } elseif ( $seconds <= DAY_IN_SECONDS * 3 ) {
                $score += 175;
                $reasons[] = 'Deadline binnen 3 dagen.';
            }
        }

        if ( '' !== trim( (string) ( $item['blocker_reason'] ?? '' ) ) ) {
            $score += 100;
            if ( ! in_array( 'Geblokkeerd; kan andere werkzaamheden ophouden.', $reasons, true ) ) {
                $reasons[] = 'Heeft een expliciete blocker.';
            }
        }

        if ( empty( $reasons ) ) {
            $reasons[] = 'Normale werkvolgorde.';
        }

        return array(
            'score'   => $score,
            'reasons' => array_values( array_unique( $reasons ) ),
        );
    }

    /**
     * Stable comparison: highest score first, then earliest valid deadline,
     * then lowest positive ID for deterministic output.
     *
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    public static function compare( array $left, array $right, ?\DateTimeImmutable $now = null ): int {
        $left_score  = self::score( $left, $now )['score'];
        $right_score = self::score( $right, $now )['score'];

        if ( $left_score !== $right_score ) {
            return $right_score <=> $left_score;
        }

        $left_deadline  = self::parse_deadline( $left['deadline_utc'] ?? '' );
        $right_deadline = self::parse_deadline( $right['deadline_utc'] ?? '' );

        if ( null !== $left_deadline || null !== $right_deadline ) {
            if ( null === $left_deadline ) {
                return 1;
            }
            if ( null === $right_deadline ) {
                return -1;
            }
            if ( $left_deadline->getTimestamp() !== $right_deadline->getTimestamp() ) {
                return $left_deadline->getTimestamp() <=> $right_deadline->getTimestamp();
            }
        }

        $left_id  = max( 0, (int) ( $left['id'] ?? 0 ) );
        $right_id = max( 0, (int) ( $right['id'] ?? 0 ) );
        return $left_id <=> $right_id;
    }

    /** @param mixed $value */
    private static function parse_deadline( mixed $value ): ?\DateTimeImmutable {
        $value = is_string( $value ) ? trim( $value ) : '';
        if ( '' === $value ) {
            return null;
        }

        try {
            return ( new \DateTimeImmutable( $value ) )->setTimezone( new \DateTimeZone( 'UTC' ) );
        } catch ( \Throwable ) {
            return null;
        }
    }

    private function __construct() {}
}
