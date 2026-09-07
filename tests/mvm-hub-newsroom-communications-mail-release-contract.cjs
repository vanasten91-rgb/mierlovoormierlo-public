const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const must = (cond, msg) => { if (!cond) throw new Error(msg); };

const release = read('plugins/mvm-hub/release/class-release-control.php');
const gates = read('plugins/mvm-hub/core/class-runtime-gates.php');
const abilities = read('plugins/mvm-hub/core/class-release-control-abilities.php');
const promotion = read('plugins/mvm-hub/core/class-communications-mail-release.php');

// Historical signed scopes remain readable/rollback-compatible. Mail writes may
// open only for the exact staging clone under an explicit signed rehearsal.
must(release.includes("SCOPE_COMMUNICATIONS = 'communications'"), 'communications scope missing');
must(release.includes("SCOPE_MAIL = 'mail'"), 'historical mail scope missing');
must(release.includes("'communications_writes', 'mail_writes'"), 'historical release flags must remain parseable for compatibility');
must(release.includes("self::SCOPE_COMMUNICATIONS === $scope"), 'communications scope policy missing');
must(release.includes("return self::SCOPE_MAIL === $scope;"), 'historical mail scope policy missing');
must(gates.includes("Release_Control::flag_enabled( 'communications_writes' )"), 'communications signed gate missing');

const mailGate = gates.slice(gates.indexOf('public static function mail_writes_enabled'), gates.indexOf('private static function environment_gate'));
must(mailGate.includes('if ( ! self::verified_mail_staging_host() )') && mailGate.includes('return false;'), 'Mail gate must fail closed unless the exact staging clone matches');
must(mailGate.includes("return 'staging.mierlovoormierlo.nl' === $host;"), 'Mail rehearsal host must be pinned to the exact staging hostname');
must(!mailGate.includes('wp_get_environment_type'), 'Mail rehearsal eligibility must not broaden to arbitrary non-production hosts');
must(mailGate.includes("MVM_HUB_ENABLE_STAGING_MAIL_WRITES"), 'staging Mail opt-in constant missing');
must(mailGate.includes("true !== constant( 'MVM_HUB_ENABLE_STAGING_MAIL_WRITES' )"), 'staging Mail opt-in must require literal true');
must(mailGate.includes("Release_Control::flag_enabled( 'mail_writes' )"), 'signed mail release scope must be required');
must(!mailGate.includes('MVM_HUB_ALLOW_PRODUCTION_MAIL_WRITES'), 'production Mail override must not exist');
must(!mailGate.includes('MVM_HUB_ENABLE_EXTERNAL_MAIL_DELIVERY'), 'legacy configuration must not reopen Mail writes');

// Existing abilities/release service remain responsible for health, promotion,
// rollback and signed release-state management.
must(abilities.includes("'mvm-hub/promote-communications'"), 'communications ability missing');
must(abilities.includes("'mvm-hub/promote-mail'"), 'mail promotion ability missing');
must(abilities.includes("'mvm-hub/communications-health'"), 'health ability missing');
must(abilities.includes("'readonly' => true"), 'health ability must be readonly');
must(promotion.includes("preflight( 'full', false )"), 'communications must require full scope');
must(promotion.includes("preflight( 'communications', true )"), 'mail transition must require communications scope');
must(promotion.includes("restore( 'full'") && promotion.includes("restore( 'communications'"), 'rollback paths missing');
must(promotion.includes('Private_Storage_Root::path( true )'), 'private storage health missing');
must(promotion.includes('PeepSo8_Internal_Message_Provider::supported()'), 'internal message readiness missing');
must(promotion.includes("'smtpConfigured'"), 'SMTP readiness diagnostic missing');
must(!promotion.includes('MVM_HUB_SMTP_PASSWORD') && !promotion.includes('MVM_HUB_IMAP_PASSWORD'), 'mail secrets must not be exposed');
console.log('communications/mail release compatibility contract: ok');
