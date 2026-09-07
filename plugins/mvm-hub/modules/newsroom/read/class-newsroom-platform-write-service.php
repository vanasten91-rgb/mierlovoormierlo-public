<?php

namespace MVM\Hub\Modules\Newsroom\Read;

use MVM\Hub\Core\Audit;
use MVM\Hub\Core\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mutation services for existing Hub4 platform tables.
 * No schema ownership or migrations are performed here.
 */
final class Newsroom_Platform_Write_Service {
    private const SOURCE_CATEGORIES = array( 'sport','verenigingen','nieuwssites','politiek','officieel','onderwijs','cultuur','veiligheid','ondernemers','lokaal_sociaal','overig' );
    private const SOURCE_FREQUENCIES = array( 'four_daily'=>6,'twice_daily'=>12,'daily'=>24,'three_weekly'=>48,'twice_weekly'=>72,'weekly'=>168,'biweekly'=>336,'monthly'=>720,'seasonal'=>0,'on_demand'=>0 );
    private const SOURCE_STATUSES = array( 'active','paused','stopped' );
    private const SIGNAL_KINDS = array( 'news','photo','event','source','safety','correction','general' );
    private const SIGNAL_STATUSES = array( 'new','triage','assigned','in_progress','converted','closed','rejected' );
    private const VERIFICATION = array( 'unverified','verified','conflicting' );
    private const INCIDENT = array( 'unknown','ongoing','resolved' );
    private const AGENDA_KINDS = array( 'news','event','photo','review','deadline','distribution','safety' );
    private const AGENDA_STATUSES = array( 'planned','confirmed','ready','done','cancelled' );
    private const MEDIA_KINDS = array( 'photo','video','audio','document' );
    private const MEDIA_STATUSES = array( 'requested','assigned','uploaded','review','approved','rejected','used' );
    private const CONSENT = array( 'unknown','not_required','obtained','restricted' );
    private const DOSSIER_STATUSES = array( 'open','research','writing','review','ready','published','archived' );
    private const CORRECTION_STATUSES = array( 'new','reviewing','accepted','rejected','published' );
    private const DISTRIBUTION_CHANNELS = array( 'facebook','whatsapp','email','peepso','other' );
    private const DISTRIBUTION_STATUSES = array( 'draft','review','approved','sent','cancelled' );

    /** @return array<string,mixed>|\WP_Error */
    public function update_source( int $source_post_id, array $input ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::SOURCES_MANAGE ) ) {
            return self::forbidden( 'mvm_source_write_forbidden', 'Je mag bronnen niet beheren.' );
        }
        $post = get_post( $source_post_id );
        if ( ! $post instanceof \WP_Post || 'mvm_bron' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new \WP_Error( 'mvm_source_missing', 'Deze bron bestaat niet of is niet gepubliceerd.', array( 'status' => 404 ) );
        }

        $table = self::table( 'sources' );
        $current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_post_id = %d", $source_post_id ), ARRAY_A );
        $current = is_array( $current ) ? $current : array();
        $frequency = self::allowed( $input['frequency'] ?? ( $current['frequency'] ?? 'weekly' ), self::SOURCE_FREQUENCIES, 'weekly' );
        $status = self::allowed( $input['status'] ?? ( $current['status'] ?? 'active' ), self::SOURCE_STATUSES, 'active' );
        $monitor = array_key_exists( 'monitorEnabled', $input ) ? (int) (bool) $input['monitorEnabled'] : (int) ( $current['monitor_enabled'] ?? 0 );
        $next = $monitor && 'active' === $status ? self::next_check( $frequency ) : null;
        if ( ! array_key_exists( 'frequency', $input ) && ! array_key_exists( 'monitorEnabled', $input ) && ! array_key_exists( 'status', $input ) ) {
            $next = $current['next_check_utc'] ?? $next;
        }
        $data = array(
            'source_post_id'   => $source_post_id,
            'monitor_enabled'  => $monitor,
            'category'         => self::allowed( $input['category'] ?? ( $current['category'] ?? 'overig' ), self::SOURCE_CATEGORIES, 'overig' ),
            'frequency'        => $frequency,
            'status'           => $status,
            'last_checked_utc' => $current['last_checked_utc'] ?? null,
            'last_checked_by'  => (int) ( $current['last_checked_by'] ?? 0 ),
            'next_check_utc'   => $next,
            'private_note'     => array_key_exists( 'privateNote', $input ) ? mb_substr( sanitize_textarea_field( (string) $input['privateNote'] ), 0, 8000 ) : ( $current['private_note'] ?? null ),
            'updated_at_utc'   => current_time( 'mysql', true ),
        );
        $ok = $current
            ? $wpdb->update( $table, $data, array( 'source_post_id' => $source_post_id ), array( '%d','%s','%s','%s','%s','%d','%s','%s','%s' ), array( '%d' ) )
            : $wpdb->insert( $table, $data, array( '%d','%d','%s','%s','%s','%s','%d','%s','%s','%s' ) );
        if ( false === $ok ) {
            return new \WP_Error( 'mvm_source_save', 'De broninstellingen konden niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        Audit::record( 'source.update', 'success', 'source', $source_post_id, array( 'monitor_enabled' => (bool) $monitor, 'frequency' => $frequency, 'status' => $status ) );
        return self::source_detail( $source_post_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function mark_source_checked( int $source_post_id ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::SOURCES_MANAGE ) ) {
            return self::forbidden( 'mvm_source_check_forbidden', 'Je mag bronnen niet afvinken.' );
        }
        $detail = self::source_detail( $source_post_id );
        if ( is_wp_error( $detail ) ) {
            return $detail;
        }
        $frequency = (string) $detail['frequency'];
        $now = current_time( 'mysql', true );
        $ok = $wpdb->update(
            self::table( 'sources' ),
            array( 'last_checked_utc' => $now, 'last_checked_by' => get_current_user_id(), 'next_check_utc' => self::next_check( $frequency ), 'updated_at_utc' => $now ),
            array( 'source_post_id' => $source_post_id ),
            array( '%s','%d','%s','%s' ),
            array( '%d' )
        );
        if ( false === $ok ) {
            return new \WP_Error( 'mvm_source_check_save', 'De broncontrole kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        Audit::record( 'source.checked', 'success', 'source', $source_post_id );
        return self::source_detail( $source_post_id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_signal( array $input ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::NEWS_CREATE ) && ! self::can( Capabilities::RADAR_TRIAGE ) ) {
            return self::forbidden( 'mvm_signal_create_forbidden', 'Je mag geen signaal toevoegen.' );
        }
        $title = mb_substr( trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ), 0, 190 );
        if ( mb_strlen( $title ) < 3 ) {
            return new \WP_Error( 'mvm_signal_title', 'Vul een duidelijke signaaltitel in.', array( 'status' => 400 ) );
        }
        $kind = self::allowed( $input['kind'] ?? 'general', self::SIGNAL_KINDS, 'general' );
        $now = current_time( 'mysql', true );
        $data = array(
            'kind'                => $kind,
            'status'              => 'new',
            'priority'            => 'safety' === $kind ? 1 : min( 4, max( 1, absint( $input['priority'] ?? 2 ) ) ),
            'title'               => $title,
            'summary'             => mb_substr( sanitize_textarea_field( (string) ( $input['summary'] ?? '' ) ), 0, 8000 ),
            'source_url'          => self::url( $input['sourceUrl'] ?? '' ),
            'location'            => mb_substr( sanitize_text_field( (string) ( $input['location'] ?? '' ) ), 0, 190 ),
            'incident_at_utc'     => self::datetime( $input['incidentAtUtc'] ?? null ),
            'verification_status' => self::allowed( $input['verificationStatus'] ?? 'unverified', self::VERIFICATION, 'unverified' ),
            'incident_status'     => self::allowed( $input['incidentStatus'] ?? ( 'safety' === $kind ? 'ongoing' : 'unknown' ), self::INCIDENT, 'unknown' ),
            'submitted_via'       => 'hub',
            'submitter_user_id'   => get_current_user_id(),
            'contact_name'        => mb_substr( sanitize_text_field( (string) ( $input['contactName'] ?? '' ) ), 0, 190 ),
            'contact_email'       => sanitize_email( (string) ( $input['contactEmail'] ?? '' ) ),
            'contact_phone'       => mb_substr( (string) preg_replace( '/[^0-9+() .-]/', '', (string) ( $input['contactPhone'] ?? '' ) ), 0, 80 ),
            'assignee_user_id'    => 0,
            'dossier_id'          => 0,
            'assignment_id'       => 0,
            'created_by_user_id'  => get_current_user_id(),
            'created_at_utc'      => $now,
            'updated_at_utc'      => $now,
            'closed_at_utc'       => null,
        );
        if ( 'safety' === $kind && ( '' === $data['location'] || ! $data['incident_at_utc'] ) ) {
            return new \WP_Error( 'mvm_signal_safety_fields', 'Voor veiligheidssignalen zijn locatie en incidenttijd verplicht.', array( 'status' => 400 ) );
        }
        $ok = $wpdb->insert( self::table( 'signals' ), $data );
        if ( false === $ok ) {
            return new \WP_Error( 'mvm_signal_save', 'Het signaal kon niet worden opgeslagen.', array( 'status' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        Audit::record( 'signal.create', 'success', 'signal', $id, array( 'kind' => $kind, 'priority' => $data['priority'] ) );
        return $this->signal_detail( $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_signal( int $id, array $input ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::RADAR_TRIAGE ) ) {
            return self::forbidden( 'mvm_signal_triage_forbidden', 'Je mag signalen niet triëren.' );
        }
        $raw = self::row( 'signals', $id );
        if ( ! $raw ) {
            return new \WP_Error( 'mvm_signal_missing', 'Dit signaal bestaat niet.', array( 'status' => 404 ) );
        }
        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        foreach ( array( 'status'=>self::SIGNAL_STATUSES, 'verificationStatus'=>self::VERIFICATION, 'incidentStatus'=>self::INCIDENT ) as $key => $allowed ) {
            if ( array_key_exists( $key, $input ) ) {
                $db = 'verificationStatus' === $key ? 'verification_status' : ( 'incidentStatus' === $key ? 'incident_status' : 'status' );
                $changes[ $db ] = self::allowed( $input[ $key ], $allowed, (string) $raw[ $db ] );
            }
        }
        if ( array_key_exists( 'priority', $input ) ) $changes['priority'] = min( 4, max( 1, absint( $input['priority'] ) ) );
        if ( array_key_exists( 'assigneeUserId', $input ) ) $changes['assignee_user_id'] = self::valid_staff_user( absint( $input['assigneeUserId'] ) );
        if ( array_key_exists( 'dossierId', $input ) ) $changes['dossier_id'] = self::existing_row_id( 'dossiers', absint( $input['dossierId'] ) );
        if ( array_key_exists( 'assignmentId', $input ) ) $changes['assignment_id'] = self::existing_row_id( 'assignments', absint( $input['assignmentId'] ) );
        if ( isset( $changes['status'] ) && in_array( $changes['status'], array( 'converted','closed','rejected' ), true ) ) $changes['closed_at_utc'] = current_time( 'mysql', true );
        if ( self::contains_error( $changes ) ) return self::first_error( $changes );
        $ok = $wpdb->update( self::table( 'signals' ), $changes, array( 'id' => $id ) );
        if ( false === $ok ) return new \WP_Error( 'mvm_signal_update', 'Het signaal kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        Audit::record( 'signal.update', 'success', 'signal', $id, array( 'changed_fields' => count( $changes ) - 1 ) );
        return $this->signal_detail( $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_agenda( array $input ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::AGENDA_MANAGE ) ) return self::forbidden( 'mvm_agenda_write_forbidden', 'Je mag de redactiekalender niet wijzigen.' );
        $validated = $this->agenda_input( $input, false );
        if ( is_wp_error( $validated ) ) return $validated;
        $now = current_time( 'mysql', true );
        $validated['created_by_user_id'] = get_current_user_id();
        $validated['created_at_utc'] = $now;
        $validated['updated_at_utc'] = $now;
        $ok = $wpdb->insert( self::table( 'editorial_calendar' ), $validated );
        if ( false === $ok ) return new \WP_Error( 'mvm_agenda_save', 'Het kalenderitem kon niet worden opgeslagen.', array( 'status' => 500 ) );
        $id = (int) $wpdb->insert_id;
        Audit::record( 'calendar.create', 'success', 'calendar_item', $id, array( 'kind' => $validated['kind'] ) );
        return self::detail_row( 'editorial_calendar', $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_agenda( int $id, array $input ): array|\WP_Error {
        global $wpdb;
        $raw = self::row( 'editorial_calendar', $id );
        if ( ! $raw ) return new \WP_Error( 'mvm_agenda_missing', 'Dit kalenderitem bestaat niet.', array( 'status' => 404 ) );
        if ( ! self::can_edit_owned( $raw, 'owner_user_id', Capabilities::AGENDA_MANAGE ) ) return self::forbidden( 'mvm_agenda_forbidden', 'Je mag dit kalenderitem niet wijzigen.' );
        $changes = $this->agenda_input( $input, true );
        if ( is_wp_error( $changes ) ) return $changes;
        if ( ! $changes ) return self::detail_row( 'editorial_calendar', $id );
        $changes['updated_at_utc'] = current_time( 'mysql', true );
        $ok = $wpdb->update( self::table( 'editorial_calendar' ), $changes, array( 'id' => $id ) );
        if ( false === $ok ) return new \WP_Error( 'mvm_agenda_update', 'Het kalenderitem kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        Audit::record( 'calendar.update', 'success', 'calendar_item', $id, array( 'changed_fields' => count( $changes ) - 1 ) );
        return self::detail_row( 'editorial_calendar', $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_media( array $input ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::MEDIA_MANAGE ) ) return self::forbidden( 'mvm_media_forbidden', 'Je mag geen media-item maken.' );
        $title = mb_substr( trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ), 0, 190 );
        if ( mb_strlen( $title ) < 3 ) return new \WP_Error( 'mvm_media_title', 'Vul een duidelijke mediatitel in.', array( 'status' => 400 ) );
        $kind = self::allowed( $input['kind'] ?? 'photo', self::MEDIA_KINDS, '' );
        if ( '' === $kind ) return new \WP_Error( 'mvm_media_kind', 'Ongeldig mediatype.', array( 'status' => 400 ) );
        $photographer = absint( $input['photographerUserId'] ?? get_current_user_id() );
        $staff = self::valid_staff_user( $photographer );
        if ( is_wp_error( $staff ) ) return $staff;
        if ( ! self::can_team_edit() && $photographer !== get_current_user_id() ) return self::forbidden( 'mvm_media_owner', 'Je mag alleen media voor jezelf beheren.' );
        $attachment = self::attachment_id( $input['attachmentId'] ?? 0 );
        if ( is_wp_error( $attachment ) ) return $attachment;
        $assignment = self::existing_row_id( 'assignments', absint( $input['assignmentId'] ?? 0 ) );
        $dossier = self::existing_row_id( 'dossiers', absint( $input['dossierId'] ?? 0 ) );
        if ( is_wp_error( $assignment ) ) return $assignment;
        if ( is_wp_error( $dossier ) ) return $dossier;
        $now = current_time( 'mysql', true );
        $data = array(
            'status' => $attachment > 0 ? 'uploaded' : 'requested', 'kind' => $kind, 'title' => $title,
            'attachment_id' => $attachment, 'assignment_id' => $assignment, 'dossier_id' => $dossier,
            'photographer_user_id' => $photographer,
            'credit' => mb_substr( sanitize_text_field( (string) ( $input['credit'] ?? '' ) ), 0, 190 ),
            'location' => mb_substr( sanitize_text_field( (string) ( $input['location'] ?? '' ) ), 0, 190 ),
            'captured_at_utc' => self::datetime( $input['capturedAtUtc'] ?? null ),
            'consent_status' => self::allowed( $input['consentStatus'] ?? 'unknown', self::CONSENT, 'unknown' ),
            'alt_text' => mb_substr( sanitize_text_field( (string) ( $input['altText'] ?? '' ) ), 0, 500 ),
            'created_by_user_id' => get_current_user_id(), 'created_at_utc' => $now, 'updated_at_utc' => $now,
        );
        $ok = $wpdb->insert( self::table( 'media_items' ), $data );
        if ( false === $ok ) return new \WP_Error( 'mvm_media_save', 'Het media-item kon niet worden opgeslagen.', array( 'status' => 500 ) );
        $id = (int) $wpdb->insert_id;
        Audit::record( 'media.create', 'success', 'media_item', $id, array( 'kind' => $kind, 'status' => $data['status'] ) );
        return self::detail_row( 'media_items', $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_media( int $id, array $input ): array|\WP_Error {
        global $wpdb;
        $raw = self::row( 'media_items', $id );
        if ( ! $raw ) return new \WP_Error( 'mvm_media_missing', 'Dit media-item bestaat niet.', array( 'status' => 404 ) );
        if ( ! self::can_edit_owned( $raw, 'photographer_user_id', Capabilities::MEDIA_MANAGE ) ) return self::forbidden( 'mvm_media_forbidden', 'Je mag dit media-item niet wijzigen.' );
        $changes = array( 'updated_at_utc' => current_time( 'mysql', true ) );
        if ( array_key_exists( 'status', $input ) ) {
            $status = self::allowed( $input['status'], self::MEDIA_STATUSES, '' );
            if ( '' === $status ) return new \WP_Error( 'mvm_media_status', 'Ongeldige mediastatus.', array( 'status' => 400 ) );
            if ( in_array( $status, array( 'approved','rejected','used' ), true ) && ! self::can_team_edit() ) return self::forbidden( 'mvm_media_review', 'Alleen een reviewer mag deze status instellen.' );
            $changes['status'] = $status;
        }
        if ( array_key_exists( 'attachmentId', $input ) ) { $attachment = self::attachment_id( $input['attachmentId'] ); if ( is_wp_error( $attachment ) ) return $attachment; $changes['attachment_id'] = $attachment; }
        foreach ( array( 'credit'=>190,'location'=>190,'altText'=>500 ) as $key=>$max ) if ( array_key_exists( $key, $input ) ) $changes[ 'altText' === $key ? 'alt_text' : $key ] = mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, $max );
        if ( array_key_exists( 'consentStatus', $input ) ) $changes['consent_status'] = self::allowed( $input['consentStatus'], self::CONSENT, (string) $raw['consent_status'] );
        if ( array_key_exists( 'capturedAtUtc', $input ) ) $changes['captured_at_utc'] = self::datetime( $input['capturedAtUtc'] );
        $ok = $wpdb->update( self::table( 'media_items' ), $changes, array( 'id' => $id ) );
        if ( false === $ok ) return new \WP_Error( 'mvm_media_update', 'Het media-item kon niet worden bijgewerkt.', array( 'status' => 500 ) );
        Audit::record( 'media.update', 'success', 'media_item', $id, array( 'changed_fields' => count( $changes ) - 1 ) );
        return self::detail_row( 'media_items', $id );
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_dossier( array $input ): array|\WP_Error {
        global $wpdb;
        if ( ! self::can( Capabilities::DOSSIERS_MANAGE ) ) return self::forbidden( 'mvm_dossier_forbidden', 'Je mag geen dossier maken.' );
        $title = mb_substr( trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) ), 0, 190 );
        if ( mb_strlen( $title ) < 3 ) return new \WP_Error( 'mvm_dossier_title', 'Vul een duidelijke dossiertitel in.', array( 'status' => 400 ) );
        $lead = absint( $input['leadUserId'] ?? get_current_user_id() );
        $staff = self::valid_staff_user( $lead ); if ( is_wp_error( $staff ) ) return $staff;
        if ( ! self::can_team_edit() && $lead !== get_current_user_id() ) return self::forbidden( 'mvm_dossier_lead', 'Je mag alleen jezelf als dossierhouder instellen.' );
        $category = self::category_id( $input['categoryTermId'] ?? 0 ); if ( is_wp_error( $category ) ) return $category;
        $now = current_time( 'mysql', true );
        $data = array(
            'status'=>'open','visibility'=>'internal','title'=>$title,'slug'=>self::unique_dossier_slug( sanitize_title( (string) ( $input['slug'] ?? $title ) ) ),
            'summary'=>mb_substr( sanitize_textarea_field( (string) ( $input['summary'] ?? '' ) ),0,8000 ),
            'internal_brief'=>mb_substr( sanitize_textarea_field( (string) ( $input['internalBrief'] ?? '' ) ),0,20000 ),
            'category_term_id'=>$category,'lead_user_id'=>$lead,'created_by_user_id'=>get_current_user_id(),
            'created_at_utc'=>$now,'updated_at_utc'=>$now,'published_at_utc'=>null,
        );
        $ok=$wpdb->insert(self::table('dossiers'),$data); if(false===$ok)return new \WP_Error('mvm_dossier_save','Het dossier kon niet worden opgeslagen.',array('status'=>500));
        $id=(int)$wpdb->insert_id; Audit::record('dossier.create','success','dossier',$id); return $this->dossier_detail($id);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_dossier( int $id, array $input ): array|\WP_Error {
        global $wpdb; $raw=self::row('dossiers',$id); if(!$raw)return new \WP_Error('mvm_dossier_missing','Dit dossier bestaat niet.',array('status'=>404));
        if(!self::can_edit_dossier($raw))return self::forbidden('mvm_dossier_forbidden','Je mag dit dossier niet wijzigen.');
        $changes=array('updated_at_utc'=>current_time('mysql',true));
        if(array_key_exists('title',$input)){ $title=mb_substr(trim(sanitize_text_field((string)$input['title'])),0,190); if(mb_strlen($title)<3)return new \WP_Error('mvm_dossier_title','De dossiertitel is ongeldig.',array('status'=>400)); $changes['title']=$title; }
        if(array_key_exists('summary',$input))$changes['summary']=mb_substr(sanitize_textarea_field((string)$input['summary']),0,8000);
        if(array_key_exists('internalBrief',$input))$changes['internal_brief']=mb_substr(sanitize_textarea_field((string)$input['internalBrief']),0,20000);
        if(array_key_exists('categoryTermId',$input)){ $category=self::category_id($input['categoryTermId']); if(is_wp_error($category))return $category; $changes['category_term_id']=$category; }
        if(array_key_exists('leadUserId',$input)){ $lead=absint($input['leadUserId']); $staff=self::valid_staff_user($lead); if(is_wp_error($staff))return $staff; if(!self::can_team_edit()&&$lead!==get_current_user_id())return self::forbidden('mvm_dossier_lead','Je mag alleen jezelf als dossierhouder instellen.'); $changes['lead_user_id']=$lead; }
        if(array_key_exists('status',$input)){ $status=self::allowed($input['status'],self::DOSSIER_STATUSES,''); if(''===$status)return new \WP_Error('mvm_dossier_status','Ongeldige dossierstatus.',array('status'=>400)); if('published'===$status&&!self::can(Capabilities::NEWS_PUBLISH))return self::forbidden('mvm_dossier_publish','Je mag dossiers niet publiceren.'); $changes['status']=$status; if('published'===$status){$changes['visibility']='public';$changes['published_at_utc']=current_time('mysql',true);} }
        if(array_key_exists('visibility',$input)){ $visibility=self::allowed($input['visibility'],array('internal','public'),'internal'); if('public'===$visibility&&!self::can(Capabilities::NEWS_PUBLISH))return self::forbidden('mvm_dossier_visibility','Je mag dossiers niet publiek maken.'); $changes['visibility']=$visibility; }
        $ok=$wpdb->update(self::table('dossiers'),$changes,array('id'=>$id)); if(false===$ok)return new \WP_Error('mvm_dossier_update','Het dossier kon niet worden bijgewerkt.',array('status'=>500)); Audit::record('dossier.update','success','dossier',$id,array('changed_fields'=>count($changes)-1)); return $this->dossier_detail($id);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_correction( array $input ): array|\WP_Error {
        global $wpdb; if(!self::can(Capabilities::CORRECTIONS_MANAGE))return self::forbidden('mvm_correction_forbidden','Je mag geen correctieverzoek toevoegen.');
        $post_id=absint($input['postId']??0); $post=get_post($post_id); if(!$post instanceof \WP_Post||'post'!==$post->post_type||'publish'!==$post->post_status)return new \WP_Error('mvm_correction_post','Het artikel is niet beschikbaar.',array('status'=>400));
        $message=mb_substr(trim(sanitize_textarea_field((string)($input['message']??''))),0,8000); if(mb_strlen($message)<10)return new \WP_Error('mvm_correction_message','Beschrijf de mogelijke fout duidelijk.',array('status'=>400));
        $now=current_time('mysql',true); $data=array('post_id'=>$post_id,'status'=>'new','message'=>$message,'public_note'=>'','submitted_via'=>'hub','contact_name'=>'','contact_email'=>'','reviewer_user_id'=>0,'created_at_utc'=>$now,'updated_at_utc'=>$now,'resolved_at_utc'=>null);
        $ok=$wpdb->insert(self::table('corrections'),$data); if(false===$ok)return new \WP_Error('mvm_correction_save','Het correctieverzoek kon niet worden opgeslagen.',array('status'=>500)); $id=(int)$wpdb->insert_id; Audit::record('correction.create','success','correction',$id,array('post_id'=>$post_id)); return $this->correction_detail($id);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_correction( int $id, array $input ): array|\WP_Error {
        global $wpdb; if(!self::can(Capabilities::CORRECTIONS_MANAGE))return self::forbidden('mvm_correction_forbidden','Je mag correctieverzoeken niet afhandelen.'); $raw=self::row('corrections',$id); if(!$raw)return new \WP_Error('mvm_correction_missing','Dit correctieverzoek bestaat niet.',array('status'=>404));
        $changes=array('reviewer_user_id'=>get_current_user_id(),'updated_at_utc'=>current_time('mysql',true)); if(array_key_exists('status',$input)){ $status=self::allowed($input['status'],self::CORRECTION_STATUSES,''); if(''===$status)return new \WP_Error('mvm_correction_status','Ongeldige correctiestatus.',array('status'=>400)); $changes['status']=$status; if(in_array($status,array('accepted','rejected','published'),true))$changes['resolved_at_utc']=current_time('mysql',true); } if(array_key_exists('publicNote',$input))$changes['public_note']=mb_substr(sanitize_textarea_field((string)$input['publicNote']),0,4000);
        $ok=$wpdb->update(self::table('corrections'),$changes,array('id'=>$id)); if(false===$ok)return new \WP_Error('mvm_correction_update','Het correctieverzoek kon niet worden bijgewerkt.',array('status'=>500)); Audit::record('correction.update','success','correction',$id,array('status'=>$changes['status']??$raw['status'])); return $this->correction_detail($id);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function create_distribution( array $input ): array|\WP_Error {
        global $wpdb; if(!self::can(Capabilities::DISTRIBUTION_MANAGE))return self::forbidden('mvm_distribution_forbidden','Je mag geen distributietekst voorbereiden.');
        $post_id=absint($input['postId']??0); $post=get_post($post_id); if(!$post instanceof \WP_Post||'publish'!==$post->post_status||!current_user_can('read_post',$post_id))return new \WP_Error('mvm_distribution_post','Kies een gepubliceerd bericht dat je mag bekijken.',array('status'=>400));
        $channel=self::allowed($input['channel']??'other',self::DISTRIBUTION_CHANNELS,''); if(''===$channel)return new \WP_Error('mvm_distribution_channel','Ongeldig distributiekanaal.',array('status'=>400)); $copy=mb_substr(trim(sanitize_textarea_field((string)($input['copyText']??''))),0,10000); if(mb_strlen($copy)<3)return new \WP_Error('mvm_distribution_copy','Vul een geldige distributietekst in.',array('status'=>400)); $url=self::url($input['targetUrl']??get_permalink($post_id)); $dossier=self::existing_row_id('dossiers',absint($input['dossierId']??0)); if(is_wp_error($dossier))return $dossier;
        $now=current_time('mysql',true); $data=array('post_id'=>$post_id,'dossier_id'=>$dossier,'channel'=>$channel,'status'=>'draft','copy_text'=>$copy,'target_url'=>$url,'prepared_by_user_id'=>get_current_user_id(),'approved_by_user_id'=>0,'created_at_utc'=>$now,'updated_at_utc'=>$now,'sent_at_utc'=>null);
        $ok=$wpdb->insert(self::table('distribution_items'),$data); if(false===$ok)return new \WP_Error('mvm_distribution_save','De distributietekst kon niet worden opgeslagen.',array('status'=>500)); $id=(int)$wpdb->insert_id; Audit::record('distribution.create','success','distribution_item',$id,array('channel'=>$channel,'post_id'=>$post_id)); return $this->distribution_detail($id);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function update_distribution( int $id, array $input ): array|\WP_Error {
        global $wpdb; $raw=self::row('distribution_items',$id); if(!$raw)return new \WP_Error('mvm_distribution_missing','Dit distributie-item bestaat niet.',array('status'=>404)); if(!self::can_edit_distribution($raw))return self::forbidden('mvm_distribution_forbidden','Je mag dit distributie-item niet wijzigen.');
        $changes=array('updated_at_utc'=>current_time('mysql',true)); if(array_key_exists('copyText',$input)){ if(in_array((string)$raw['status'],array('approved','sent'),true)&&!self::can_team_edit())return self::forbidden('mvm_distribution_locked','Een goedgekeurde tekst mag alleen door een reviewer worden gewijzigd.'); $copy=mb_substr(trim(sanitize_textarea_field((string)$input['copyText'])),0,10000); if(mb_strlen($copy)<3)return new \WP_Error('mvm_distribution_copy','De distributietekst is ongeldig.',array('status'=>400)); $changes['copy_text']=$copy; }
        if(array_key_exists('targetUrl',$input))$changes['target_url']=self::url($input['targetUrl']); if(array_key_exists('status',$input)){ $status=self::allowed($input['status'],self::DISTRIBUTION_STATUSES,''); if(''===$status)return new \WP_Error('mvm_distribution_status','Ongeldige distributiestatus.',array('status'=>400)); if(in_array($status,array('approved','sent','cancelled'),true)&&!self::can_team_edit())return self::forbidden('mvm_distribution_approve','Alleen een bevoegde reviewer mag deze status instellen.'); $changes['status']=$status; if(in_array($status,array('approved','sent'),true))$changes['approved_by_user_id']=get_current_user_id(); if('sent'===$status)$changes['sent_at_utc']=current_time('mysql',true); }
        $ok=$wpdb->update(self::table('distribution_items'),$changes,array('id'=>$id)); if(false===$ok)return new \WP_Error('mvm_distribution_update','Het distributie-item kon niet worden bijgewerkt.',array('status'=>500)); Audit::record('distribution.update','success','distribution_item',$id,array('status'=>$changes['status']??$raw['status'])); return $this->distribution_detail($id);
    }

    /** @return array<string,mixed>|\WP_Error */
    public function source_detail( int $source_post_id ): array|\WP_Error { return self::source_detail_static($source_post_id); }
    /** @return array<string,mixed>|\WP_Error */
    public function signal_detail( int $id ): array|\WP_Error { $row=self::row('signals',$id); if(!$row)return new \WP_Error('mvm_signal_missing','Dit signaal bestaat niet.',array('status'=>404)); $data=self::camel_row($row); if(!self::can(Capabilities::RADAR_TRIAGE)){unset($data['contactName'],$data['contactEmail'],$data['contactPhone'],$data['summary'],$data['sourceUrl']);} return $data; }
    /** @return array<string,mixed>|\WP_Error */
    public function dossier_detail( int $id ): array|\WP_Error { $row=self::row('dossiers',$id); if(!$row)return new \WP_Error('mvm_dossier_missing','Dit dossier bestaat niet.',array('status'=>404)); if(!self::can_view_dossier($row))return self::forbidden('mvm_dossier_forbidden','Je mag dit dossier niet bekijken.'); $data=self::camel_row($row); if(!self::can_edit_dossier($row))unset($data['internalBrief']); return $data; }
    /** @return array<string,mixed>|\WP_Error */
    public function correction_detail( int $id ): array|\WP_Error { if(!self::can(Capabilities::CORRECTIONS_MANAGE))return self::forbidden('mvm_correction_forbidden','Je mag dit correctieverzoek niet bekijken.'); $row=self::row('corrections',$id); return $row?self::camel_row($row):new \WP_Error('mvm_correction_missing','Dit correctieverzoek bestaat niet.',array('status'=>404)); }
    /** @return array<string,mixed>|\WP_Error */
    public function distribution_detail( int $id ): array|\WP_Error { $row=self::row('distribution_items',$id); if(!$row)return new \WP_Error('mvm_distribution_missing','Dit distributie-item bestaat niet.',array('status'=>404)); if(!self::can_edit_distribution($row)&&!self::can(Capabilities::DISTRIBUTION_VIEW))return self::forbidden('mvm_distribution_forbidden','Je mag dit distributie-item niet bekijken.'); return self::camel_row($row); }

    /** @return array<string,mixed>|\WP_Error */
    private function agenda_input( array $input, bool $partial ): array|\WP_Error {
        $out=array(); foreach(array('kind'=>self::AGENDA_KINDS,'status'=>self::AGENDA_STATUSES)as$key=>$allowed){if(!$partial||array_key_exists($key,$input)){$v=self::allowed($input[$key]??('kind'===$key?'news':'planned'),$allowed,'');if(''===$v)return new \WP_Error('mvm_agenda_'.$key,'Ongeldige kalenderwaarde.',array('status'=>400));$out[$key]=$v;}}
        if(!$partial||array_key_exists('title',$input)){ $title=mb_substr(trim(sanitize_text_field((string)($input['title']??''))),0,190);if(mb_strlen($title)<3)return new \WP_Error('mvm_agenda_title','Vul een duidelijke titel in.',array('status'=>400));$out['title']=$title; }
        if(!$partial||array_key_exists('startsAtUtc',$input)){ $start=self::datetime($input['startsAtUtc']??null);if(!$start)return new \WP_Error('mvm_agenda_start','Kies een geldig startmoment.',array('status'=>400));$out['starts_at_utc']=$start; }
        if(!$partial||array_key_exists('endsAtUtc',$input))$out['ends_at_utc']=self::datetime($input['endsAtUtc']??null);
        foreach(array('dossierId'=>'dossiers','assignmentId'=>'assignments')as$key=>$table){if(!$partial||array_key_exists($key,$input)){ $id=self::existing_row_id($table,absint($input[$key]??0));if(is_wp_error($id))return $id;$out['dossierId'===$key?'dossier_id':'assignment_id']=$id;}}
        if(!$partial||array_key_exists('postId',$input)){ $post_id=absint($input['postId']??0);if($post_id){$post=get_post($post_id);if(!$post instanceof \WP_Post||!in_array($post->post_type,array('post','event_listing'),true))return new \WP_Error('mvm_agenda_post','De gekoppelde content is ongeldig.',array('status'=>400));}$out['post_id']=$post_id; }
        if(!$partial||array_key_exists('ownerUserId',$input)){ $owner=absint($input['ownerUserId']??get_current_user_id());$staff=self::valid_staff_user($owner);if(is_wp_error($staff))return $staff;if(!self::can_team_edit()&&$owner!==get_current_user_id())return self::forbidden('mvm_agenda_owner','Je mag alleen jezelf als eigenaar instellen.');$out['owner_user_id']=$owner; }
        if(isset($out['starts_at_utc'],$out['ends_at_utc'])&&$out['ends_at_utc']&&$out['ends_at_utc']<$out['starts_at_utc'])return new \WP_Error('mvm_agenda_range','Het eindmoment ligt vóór het startmoment.',array('status'=>400)); return $out;
    }

    private static function source_detail_static( int $id ): array|\WP_Error { global $wpdb; $post=get_post($id); if(!$post instanceof \WP_Post||'mvm_bron'!==$post->post_type)return new \WP_Error('mvm_source_missing','Deze bron bestaat niet.',array('status'=>404)); $table=self::table('sources');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE source_post_id=%d",$id),ARRAY_A);$row=is_array($row)?$row:array();$data=array('sourcePostId'=>$id,'title'=>sanitize_text_field($post->post_title),'monitorEnabled'=>(bool)($row['monitor_enabled']??false),'category'=>sanitize_key((string)($row['category']??'overig')),'frequency'=>sanitize_key((string)($row['frequency']??'weekly')),'status'=>sanitize_key((string)($row['status']??'active')),'lastCheckedUtc'=>$row['last_checked_utc']??null,'nextCheckUtc'=>$row['next_check_utc']??null,'updatedAtUtc'=>$row['updated_at_utc']??null);if(self::can(Capabilities::SOURCES_MANAGE))$data['privateNote']=sanitize_textarea_field((string)($row['private_note']??''));return $data; }
    private static function can( string $cap ): bool { return current_user_can('manage_options')||current_user_can($cap); }
    private static function can_team_edit(): bool { return current_user_can('manage_options')||current_user_can(Capabilities::NEWS_EDIT_TEAM)||current_user_can(Capabilities::NEWS_REVIEW); }
    /** @param array<string,mixed> $row */ private static function can_edit_owned(array $row,string $owner_field,string $cap):bool{return self::can($cap)&&(self::can_team_edit()||get_current_user_id()===(int)($row[$owner_field]??0)||get_current_user_id()===(int)($row['created_by_user_id']??0));}
    /** @param array<string,mixed> $row */ private static function can_edit_dossier(array $row):bool{return self::can(Capabilities::DOSSIERS_MANAGE)&&(self::can_team_edit()||get_current_user_id()===(int)$row['lead_user_id']||get_current_user_id()===(int)$row['created_by_user_id']);}
    /** @param array<string,mixed> $row */ private static function can_view_dossier(array $row):bool{return self::can(Capabilities::DOSSIERS_VIEW)&&(self::can_edit_dossier($row)||('public'===(string)$row['visibility']&&'published'===(string)$row['status'])||self::can_team_edit());}
    /** @param array<string,mixed> $row */ private static function can_edit_distribution(array $row):bool{return self::can(Capabilities::DISTRIBUTION_MANAGE)&&(self::can_team_edit()||get_current_user_id()===(int)$row['prepared_by_user_id']);}
    private static function table(string $suffix):string{global $wpdb;$allowed=array('sources','signals','assignments','editorial_calendar','media_items','dossiers','corrections','distribution_items');if(!in_array($suffix,$allowed,true))throw new \InvalidArgumentException('Unknown Hub table.');return $wpdb->prefix.'mvm_hub4_'.$suffix;}
    /** @return array<string,mixed>|null */ private static function row(string $suffix,int $id):?array{global $wpdb;$table=self::table($suffix);$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id),ARRAY_A);return is_array($row)?$row:null;}
    /** @return array<string,mixed>|\WP_Error */ private static function detail_row(string $suffix,int $id):array|\WP_Error{$row=self::row($suffix,$id);return $row?self::camel_row($row):new \WP_Error('mvm_newsroom_item_missing','Het item bestaat niet.',array('status'=>404));}
    /** @param array<string,mixed> $row @return array<string,mixed> */ private static function camel_row(array $row):array{$out=array();foreach($row as $key=>$value){$camel=preg_replace_callback('/_([a-z])/',static fn($m)=>strtoupper($m[1]),$key);if(in_array($key,array('id','post_id','dossier_id','assignment_id','attachment_id','owner_user_id','lead_user_id','created_by_user_id','photographer_user_id','reviewer_user_id','prepared_by_user_id','approved_by_user_id','assignee_user_id','submitter_user_id'),true))$value=(int)$value;$out[(string)$camel]=$value;}return $out;}
    private static function allowed(mixed $value,array $allowed,string $fallback):string{$key=sanitize_key((string)$value);$keys=array_is_list($allowed)?$allowed:array_keys($allowed);return in_array($key,$keys,true)?$key:$fallback;}
    private static function next_check(string $frequency):?string{$hours=(int)(self::SOURCE_FREQUENCIES[$frequency]??0);return $hours>0?gmdate('Y-m-d H:i:s',time()+$hours*HOUR_IN_SECONDS):null;}
    private static function datetime(mixed $value):?string{if(null===$value||''===trim((string)$value))return null;try{$d=new \DateTimeImmutable((string)$value,new \DateTimeZone('UTC'));return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');}catch(\Throwable){return null;}}
    private static function url(mixed $value):string{$raw=trim((string)$value);if(''===$raw)return '';$url=esc_url_raw($raw,array('http','https'));return is_string($url)?mb_substr($url,0,2000):'';}
    /** @return int|\WP_Error */ private static function valid_staff_user(int $id):int|\WP_Error{if(0===$id)return 0;$user=get_userdata($id);if(!$user instanceof \WP_User||(!user_can($user,'manage_options')&&!user_can($user,Capabilities::NEWSROOM_ACCESS)))return new \WP_Error('mvm_newsroom_user','De gekozen medewerker heeft geen Nieuwsroomtoegang.',array('status'=>400));return $id;}
    /** @return int|\WP_Error */ private static function existing_row_id(string $suffix,int $id):int|\WP_Error{if(0===$id)return 0;return self::row($suffix,$id)?$id:new \WP_Error('mvm_newsroom_relation','Een gekoppeld redactierecord bestaat niet.',array('status'=>400));}
    /** @return int|\WP_Error */ private static function attachment_id(mixed $value):int|\WP_Error{$id=absint($value);if(0===$id)return 0;$post=get_post($id);return $post instanceof \WP_Post&&'attachment'===$post->post_type?$id:new \WP_Error('mvm_media_attachment','De gekozen bijlage is ongeldig.',array('status'=>400));}
    /** @return int|\WP_Error */ private static function category_id(mixed $value):int|\WP_Error{$id=absint($value);if(0===$id)return 0;$term=get_term($id,'category');return $term&&!is_wp_error($term)?$id:new \WP_Error('mvm_dossier_category','De gekozen categorie bestaat niet.',array('status'=>400));}
    private static function unique_dossier_slug(string $base):string{global $wpdb;$base=''!==$base?$base:'dossier';$table=self::table('dossiers');$slug=$base;$i=2;while((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug=%s",$slug))>0){$slug=$base.'-'.$i++;if($i>1000)break;}return mb_substr($slug,0,190);}
    /** @param array<string,mixed> $values */ private static function contains_error(array $values):bool{foreach($values as $value)if(is_wp_error($value))return true;return false;}
    /** @param array<string,mixed> $values */ private static function first_error(array $values):\WP_Error{foreach($values as $value)if(is_wp_error($value))return $value;return new \WP_Error('mvm_newsroom_error','Onbekende validatiefout.');}
    private static function forbidden(string $code,string $message):\WP_Error{return new \WP_Error($code,$message,array('status'=>403));}
}
