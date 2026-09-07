<?php

namespace MVM\Hub\Modules\Communications;

use MVM\Hub\Core\Capabilities;
use MVM\Hub\Core\Runtime_Gates;
use MVM\Hub\Integrations\PeepSo\Legacy_Readonly_Internal_Message_Provider;

if ( ! defined( 'ABSPATH' ) ) exit;

/** Standalone /hub/ Communications UI. Server remains authority for all actions. */
final class Communications_Runtime_Renderer {
    public static function render( string $section ): string {
        return match ( sanitize_key( $section ) ) {
            'mail'     => self::mail(),
            'messages' => self::messages(),
            default    => '',
        };
    }

    private static function mail(): string {
        $service = Communications_Service_Factory::mail_read_service();
        $mailbox = self::mailbox_id();
        $folders = $service->folders( $mailbox );
        if ( is_wp_error( $folders ) ) return self::error_state( 'De redactionele mailbox is niet beschikbaar.' );

        $folder_items = is_array( $folders['folders'] ?? null ) ? $folders['folders'] : array();
        $inbox = '';
        foreach ( $folder_items as $folder ) {
            if ( is_array( $folder ) && 'inbox' === sanitize_key( (string) ( $folder['specialUse'] ?? '' ) ) ) {
                $inbox = (string) ( $folder['id'] ?? '' );
                break;
            }
        }

        $payload = '' !== $inbox
            ? $service->messages( $mailbox, $inbox, array( 'page' => 1, 'per_page' => 30, 'sort' => 'date', 'direction' => 'desc', 'state' => 'all' ) )
            : new \WP_Error( 'mvm_mail_inbox', 'Inbox ontbreekt.' );
        $items = ! is_wp_error( $payload ) && is_array( $payload['messages'] ?? null ) ? $payload['messages'] : array();

        $writes = Runtime_Gates::communications_writes_enabled()
            && ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) || current_user_can( Capabilities::MAIL_COMPOSE ) );
        $delivery = Runtime_Gates::mail_writes_enabled()
            && ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) || current_user_can( Capabilities::MAIL_SEND ) );
        $manage_folders = $writes
            && ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) || current_user_can( Capabilities::MAIL_MANAGE_FOLDERS ) );
        $manage_messages = $writes
            && ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) || current_user_can( Capabilities::MAIL_MANAGE_MESSAGES ) );

        ob_start();
        ?>
<div class="mvm-communications mvm-mail-client"
     data-mvm-mail
     data-rest-base="<?php echo esc_url( rest_url( 'mvm-hub/v1' ) ); ?>"
     data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
     data-write-enabled="<?php echo $writes ? '1' : '0'; ?>"
     data-delivery-enabled="<?php echo $delivery ? '1' : '0'; ?>"
     data-manage-folders="<?php echo $manage_folders ? '1' : '0'; ?>"
     data-manage-messages="<?php echo $manage_messages ? '1' : '0'; ?>">
  <div class="mvm-mail-client__toolbar">
    <div>
      <p class="mvm-hub__eyebrow">Communicatie</p>
      <h2>Mail</h2>
      <p class="mvm-newsroom-preview__muted">IMAP mailbox · private concepten · veilige bijlagen · dedicated SMTP</p>
    </div>
    <div class="mvm-mail-client__toolbar-actions">
      <?php if ( $writes ) : ?><button type="button" class="mvm-button mvm-button--primary" data-mail-new>Nieuw bericht</button><?php endif; ?>
      <button type="button" class="mvm-button mvm-button--secondary" data-mail-refresh>Vernieuwen</button>
    </div>
  </div>

  <div class="mvm-alert" hidden data-mail-notice role="status" aria-live="polite"></div>
  <?php if ( ! $writes ) : ?>
    <div class="mvm-alert mvm-alert--warning">Schrijven staat in deze deployment uit. Lezen blijft beschikbaar.</div>
  <?php elseif ( ! $delivery ) : ?>
    <div class="mvm-alert mvm-alert--warning">Concepten en mailboxacties zijn beschikbaar; externe verzending is nog niet vrijgegeven.</div>
  <?php endif; ?>

  <div class="mvm-mail-client__layout">
    <aside class="mvm-mail-folders" aria-label="Mailmappen">
      <div class="mvm-mail-folders__head">
        <strong>Mappen</strong>
        <?php if ( $manage_folders ) : ?><button type="button" class="mvm-icon-button" data-folder-create aria-label="Nieuwe map">+</button><?php endif; ?>
      </div>
      <nav data-mail-folders>
        <?php foreach ( $folder_items as $folder ) :
            if ( ! is_array( $folder ) ) continue;
            $id = (string) ( $folder['id'] ?? '' );
            if ( '' === $id ) continue;
            $system = ! empty( $folder['system'] );
            ?>
          <div class="mvm-mail-folder-row" data-folder-row data-folder-id="<?php echo esc_attr( $id ); ?>" data-folder-name="<?php echo esc_attr( (string) ( $folder['name'] ?? 'Map' ) ); ?>" data-folder-special="<?php echo esc_attr( sanitize_key( (string) ( $folder['specialUse'] ?? '' ) ) ); ?>" data-folder-system="<?php echo $system ? '1' : '0'; ?>">
            <button type="button" class="mvm-mail-folder<?php echo $id === $inbox ? ' is-active' : ''; ?>" data-folder-select>
              <span><?php echo esc_html( (string) ( $folder['name'] ?? 'Map' ) ); ?></span>
              <span class="mvm-badge"><?php echo esc_html( (string) max( 0, (int) ( $folder['unreadCount'] ?? 0 ) ) ); ?></span>
            </button>
            <?php if ( $manage_folders && ! $system ) : ?>
              <button type="button" class="mvm-icon-button" data-folder-rename aria-label="Map hernoemen">✎</button>
              <button type="button" class="mvm-icon-button mvm-icon-button--danger" data-folder-delete aria-label="Map verwijderen">×</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </nav>
    </aside>

    <section class="mvm-mail-list-panel" aria-label="Berichten">
      <form class="mvm-mail-search" data-mail-search-form>
        <label class="screen-reader-text" for="mvm-mail-search">Zoeken</label>
        <input id="mvm-mail-search" type="search" placeholder="Zoek in mail" maxlength="120" data-mail-search>
        <select data-mail-state aria-label="Filter">
          <option value="all">Alles</option>
          <option value="unread">Ongelezen</option>
          <option value="read">Gelezen</option>
          <option value="flagged">Met vlag</option>
        </select>
        <select data-mail-sort aria-label="Sorteren op datum">
          <option value="desc">Nieuwste eerst</option>
          <option value="asc">Oudste eerst</option>
        </select>
        <button class="mvm-button mvm-button--secondary" type="submit">Zoek</button>
      </form>
      <div class="mvm-mail-list" data-mail-list aria-live="polite">
        <?php if ( ! $items ) : ?><div class="mvm-empty">Geen berichten in deze map.</div><?php endif; ?>
        <?php foreach ( $items as $item ) : if ( ! is_array( $item ) ) continue; ?>
          <button type="button" class="mvm-mail-row<?php echo empty( $item['seen'] ) ? ' is-unread' : ''; ?>" data-mail-message-id="<?php echo esc_attr( (string) ( $item['id'] ?? '' ) ); ?>">
            <span class="mvm-mail-row__from"><?php echo esc_html( (string) ( $item['fromLabel'] ?? '' ) ); ?></span>
            <strong class="mvm-mail-row__subject"><?php echo esc_html( (string) ( $item['subject'] ?? '(Geen onderwerp)' ) ); ?></strong>
            <time><?php echo esc_html( (string) ( $item['date'] ?? '' ) ); ?></time>
            <span class="mvm-mail-row__status">
              <?php if ( ! empty( $item['pinned'] ) ) : ?><span aria-label="Vastgezet" title="Vastgezet">●</span><?php endif; ?>
              <?php if ( ! empty( $item['hasAttachments'] ) ) : ?><span aria-label="Met bijlage" title="Met bijlage">📎</span><?php endif; ?>
              <span aria-label="<?php echo ! empty( $item['flagged'] ) ? 'Met vlag' : 'Geen vlag'; ?>"><?php echo ! empty( $item['flagged'] ) ? '★' : '☆'; ?></span>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="mvm-mail-pagination">
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-prev disabled>Vorige</button>
        <span data-mail-page>1</span>
        <button type="button" class="mvm-button mvm-button--secondary" data-mail-next <?php echo empty( $payload['hasMore'] ) ? 'disabled' : ''; ?>>Volgende</button>
      </div>
    </section>

    <section class="mvm-mail-detail" data-mail-detail aria-live="polite">
      <div class="mvm-empty">Selecteer een bericht om het te lezen.</div>
    </section>
  </div>

  <?php if ( $writes ) : ?>
  <dialog class="mvm-mail-composer" data-mail-composer>
    <form method="dialog" class="mvm-mail-composer__frame" data-mail-compose-form>
      <header>
        <div><p class="mvm-hub__eyebrow">Veilige composer</p><h2 data-compose-heading>Nieuw bericht</h2></div>
        <div class="mvm-mail-composer__header-actions">
          <label>Concept <select data-compose-drafts><option value="">Nieuw concept</option></select></label>
          <button type="button" class="mvm-icon-button" data-compose-close aria-label="Sluiten">×</button>
        </div>
      </header>
      <div class="mvm-form-grid">
        <label>Aan<input type="text" inputmode="email" autocomplete="off" data-compose-to placeholder="naam@voorbeeld.nl" maxlength="2000"></label>
        <details><summary>CC/BCC</summary>
          <label>CC<input type="text" inputmode="email" autocomplete="off" data-compose-cc maxlength="2000"></label>
          <label>BCC<input type="text" inputmode="email" autocomplete="off" data-compose-bcc maxlength="2000"></label>
        </details>
        <label>Onderwerp<input type="text" data-compose-subject maxlength="255"></label>
        <div class="mvm-mail-editor-toolbar" role="toolbar" aria-label="Tekstopmaak">
          <button type="button" data-format="bold"><strong>B</strong></button>
          <button type="button" data-format="italic"><em>I</em></button>
          <button type="button" data-format="insertUnorderedList">• Lijst</button>
          <button type="button" data-format-link>Link</button>
        </div>
        <div class="mvm-mail-editor" contenteditable="true" role="textbox" aria-label="Berichttekst" aria-multiline="true" data-compose-editor></div>
        <div class="mvm-mail-attachments">
          <label class="mvm-button mvm-button--secondary">Bijlage toevoegen<input type="file" hidden multiple data-compose-files></label>
          <ul data-compose-attachments></ul>
        </div>
      </div>
      <footer>
        <span class="mvm-newsroom-preview__muted" data-compose-status>Concept nog niet opgeslagen.</span>
        <div>
          <button type="button" class="mvm-button mvm-button--secondary" data-compose-delete>Concept verwijderen</button>
          <button type="button" class="mvm-button mvm-button--secondary" data-compose-save>Opslaan</button>
          <button type="button" class="mvm-button mvm-button--primary" data-compose-send <?php echo $delivery ? '' : 'disabled'; ?>>Verzenden</button>
        </div>
      </footer>
    </form>
  </dialog>

  <dialog class="mvm-step-up" data-step-up-dialog>
    <form method="dialog" class="mvm-step-up__frame" data-step-up-form>
      <header><div><p class="mvm-hub__eyebrow">Extra beveiliging</p><h2>Bevestig je identiteit</h2></div><button type="button" class="mvm-icon-button" data-step-up-cancel aria-label="Sluiten">×</button></header>
      <p>Voer je huidige WordPress-wachtwoord opnieuw in. Het wachtwoord wordt niet opgeslagen of gelogd.</p>
      <label>Wachtwoord<input type="password" autocomplete="current-password" data-step-up-password maxlength="4096" required></label>
      <div class="mvm-alert mvm-alert--warning" hidden data-step-up-error></div>
      <footer><button type="button" class="mvm-button mvm-button--secondary" data-step-up-cancel>Annuleren</button><button type="submit" class="mvm-button mvm-button--primary">Bevestigen</button></footer>
    </form>
  </dialog>
  <?php if ( $manage_messages ) : ?>
  <dialog class="mvm-mail-report-dialog" data-mail-report-dialog>
    <form method="dialog" class="mvm-mail-report-dialog__frame" data-mail-report-form>
      <header><div><p class="mvm-hub__eyebrow">Veiligheidsmelding</p><h2>Bericht rapporteren</h2></div><button type="button" class="mvm-icon-button" data-mail-report-close aria-label="Sluiten">×</button></header>
      <div class="mvm-mail-report-dialog__body">
        <p>Kies waarom dit bericht aandacht nodig heeft. De melding bevat alleen een afgeschermde berichtreferentie en geen onderwerp, afzender of mailinhoud.</p>
        <label>Reden
          <select data-mail-report-reason required>
            <option value="suspicious">Verdacht bericht</option>
            <option value="phishing">Mogelijke phishing</option>
            <option value="abuse">Misbruik of intimidatie</option>
            <option value="other">Anders</option>
          </select>
        </label>
      </div>
      <footer><button type="button" class="mvm-button mvm-button--secondary" data-mail-report-close>Annuleren</button><button type="submit" class="mvm-button mvm-button--primary">Melding vastleggen</button></footer>
    </form>
  </dialog>
  <?php endif; ?>
  <?php endif; ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    private static function messages(): string {
        $payload = Communications_Service_Factory::internal_message_read_service()->list( 1, 30 );
        if ( is_wp_error( $payload ) ) return self::error_state( 'Interne berichten zijn niet beschikbaar.' );
        $items = is_array( $payload['items'] ?? null ) ? $payload['items'] : array();
        $provider = Communications_Service_Factory::internal_message_provider();
        $provider_writable = ! ( $provider instanceof Legacy_Readonly_Internal_Message_Provider );
        $writes = Runtime_Gates::communications_writes_enabled()
            && $provider_writable
            && ( current_user_can( 'manage_options' ) || current_user_can( Capabilities::COMMUNICATIONS_ADMIN ) || current_user_can( Capabilities::MESSAGES_SEND ) );

        ob_start();
        ?>
<div class="mvm-communications mvm-messages-client"
     data-mvm-messages
     data-rest-base="<?php echo esc_url( rest_url( 'mvm-hub/v1' ) ); ?>"
     data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
     data-write-enabled="<?php echo $writes ? '1' : '0'; ?>">
  <header class="mvm-newsroom-preview__section-head">
    <div><p class="mvm-hub__eyebrow">Communicatie</p><h2>Interne berichten</h2><p class="mvm-newsroom-preview__muted">Privégesprekken zijn uitsluitend zichtbaar voor deelnemers.</p></div>
    <?php if ( $writes ) : ?><button class="mvm-button mvm-button--primary" type="button" data-message-new>Nieuw gesprek</button><?php else : ?><span class="mvm-badge">PeepSo 8 read-only connector</span><?php endif; ?>
  </header>
  <div class="mvm-alert" hidden data-message-notice role="status" aria-live="polite"></div>
  <div class="mvm-messages-client__layout">
    <div class="mvm-message-thread-list" data-message-list>
      <?php if ( ! $items ) : ?><div class="mvm-empty">Geen gesprekken.</div><?php endif; ?>
      <?php foreach ( $items as $item ) : if ( ! is_array( $item ) ) continue; ?>
        <button type="button" class="mvm-message-thread" data-thread-id="<?php echo esc_attr( (string) ( $item['id'] ?? 0 ) ); ?>">
          <strong><?php echo esc_html( (string) ( $item['title'] ?? 'Gesprek' ) ); ?></strong>
          <span><?php echo esc_html( self::participant_names( $item['participants'] ?? array() ) ); ?></span>
          <span class="mvm-badge"><?php echo esc_html( (string) max( 0, (int) ( $item['unreadCount'] ?? 0 ) ) ); ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <section class="mvm-message-detail" data-message-detail><div class="mvm-empty">Selecteer een gesprek.</div></section>
  </div>
  <?php if ( ! $provider_writable ) : ?><div class="mvm-alert mvm-alert--warning">Nieuwe berichten en antwoorden blijven geblokkeerd totdat de PeepSo 8 writeprovider versie-gevalideerd is.</div><?php endif; ?>

  <?php if ( $writes ) : ?>
  <dialog class="mvm-message-composer" data-message-composer>
    <form method="dialog" class="mvm-message-composer__frame" data-message-compose-form>
      <header><div><p class="mvm-hub__eyebrow">Privébericht</p><h2>Nieuw gesprek</h2></div><button type="button" class="mvm-icon-button" data-message-compose-close aria-label="Sluiten">×</button></header>
      <label>Zoek deelnemer<input type="search" maxlength="80" autocomplete="off" data-participant-search placeholder="Naam"></label>
      <div class="mvm-participant-results" data-participant-results></div>
      <div class="mvm-participant-selection" data-participant-selection></div>
      <label>Bericht<textarea rows="6" maxlength="10000" data-message-compose-body></textarea></label>
      <footer><button type="button" class="mvm-button mvm-button--secondary" data-message-compose-close>Annuleren</button><button type="submit" class="mvm-button mvm-button--primary">Gesprek starten</button></footer>
    </form>
  </dialog>
  <?php endif; ?>
</div>
        <?php
        return (string) ob_get_clean();
    }

    private static function error_state( string $message ): string {
        return '<div class="mvm-alert mvm-alert--warning" role="status">' . esc_html( $message ) . '</div>';
    }

    private static function participant_names( mixed $participants ): string {
        if ( ! is_array( $participants ) ) return '';
        $names = array();
        foreach ( array_slice( $participants, 0, 25 ) as $participant ) {
            if ( ! is_array( $participant ) ) continue;
            $value = sanitize_text_field( (string) ( $participant['displayName'] ?? '' ) );
            if ( '' !== $value ) $names[] = $value;
        }
        return implode( ', ', array_values( array_unique( $names ) ) );
    }

    private static function mailbox_id(): string {
        return defined( 'MVM_HUB_MAILBOX_ID' ) ? sanitize_key( (string) MVM_HUB_MAILBOX_ID ) : 'editorial';
    }

    private function __construct() {}
}
