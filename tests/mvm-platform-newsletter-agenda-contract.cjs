const fs = require('fs');

const content = fs.readFileSync('plugins/mvm-platform/src/newsletter/class-newsletter-content.php', 'utf8');
const template = fs.readFileSync('plugins/mvm-platform/src/newsletter/class-newsletter-template.php', 'utf8');
const bootstrap = fs.readFileSync('plugins/mvm-platform/bootstrap.php', 'utf8');

const requiredContent = [
  "'post_type'      => 'event_listing'",
  "'post_status'    => 'publish'",
  "'_event_start_date'",
  "'compare' => '>='",
  "SNAPSHOT_PREFIX",
  "update_option( $key, $ids, false )",
  "sanitize_text_field( (string) get_post_meta( $event_id, '_event_location', true ) )",
  "esc_html( implode( ' · ', $meta ) )",
  "Bekijk evenement",
  "Bekijk de volledige agenda",
  "home_url( '/evenementen/' )"
];

for (const needle of requiredContent) {
  if (!content.includes(needle)) {
    throw new Error(`Agenda renderer contract ontbreekt: ${needle}`);
  }
}

for (const forbidden of ['user_email', 'phone', 'telephone', 'owner_id', 'post_author']) {
  if (content.includes(forbidden)) {
    throw new Error(`Agenda renderer mag geen privéveld bevatten: ${forbidden}`);
  }
}

if (!template.includes("( '' !== $unsubscribe_link || $is_test )") || !template.includes('MvM_Newsletter_Content::append_agenda')) {
  throw new Error('Agenda moet alleen op campagne/test-rendering worden toegevoegd.');
}

if (!bootstrap.includes("src/newsletter/class-newsletter-content.php")) {
  throw new Error('Newsletter content module wordt niet geladen.');
}

console.log('MvM newsletter agenda renderer contract OK');
