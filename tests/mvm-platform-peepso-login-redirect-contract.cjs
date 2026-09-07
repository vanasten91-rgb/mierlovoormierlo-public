'use strict';

const fs = require('fs');
const assert = require('assert');

const redirect = fs.readFileSync('plugins/mvm-hub4-rc-direct/src/class-peepso-login-redirect.php', 'utf8');
const main = fs.readFileSync('plugins/mvm-hub4-rc-direct/mvm-hub4.php', 'utf8');

assert.match(main, /class-peepso-login-redirect\.php/);
assert.match(main, /MvM_Hub4_PeepSo_Login_Redirect::init\(\)/);
assert.match(redirect, /'post'/);
assert.match(redirect, /'event_listing'/);
assert.match(redirect, /home_url\( '\/' \)/, 'normal content login must target the homepage');
assert.ok(!redirect.includes('get_permalink( $content_id )'), 'content login must no longer return to the current article or event');
assert.match(redirect, /template_redirect/, 'redirect must be corrected before PeepSo initializes');
assert.match(redirect, /ob_start\( array\( __CLASS__, 'rewrite_markup' \) \)/);
assert.match(redirect, /preg_replace_callback/);
assert.match(redirect, /input\[name="redirect_to"\]/);
assert.match(redirect, /input\.value=canonical/);
assert.match(redirect, /input\.setAttribute\('value',canonical\)/, 'the serialized value must follow the runtime value');
assert.match(redirect, /MutationObserver/, 'late PeepSo form replacement must be observed');
assert.match(redirect, /addEventListener\(eventName,refresh,true\)/, 'login interaction must refresh redirect in capture phase');
assert.match(redirect, /window\.setInterval/, 'PeepSo delayed initialization must not win the startup race');
assert.match(redirect, /! is_singular\( self::ALLOWED_TYPES \)/, 'homepage and archive routes must be untouched');

console.log('MvM PeepSo homepage login redirect contract: OK');
