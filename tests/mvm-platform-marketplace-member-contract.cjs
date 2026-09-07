'use strict';

const fs = require('fs');
const assert = require('assert');

const marketplace = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace.php', 'utf8');
const rest = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace-rest.php', 'utf8');
const frontend = fs.readFileSync('plugins/mvm-platform/src/marketplace/class-marketplace-frontend.php', 'utf8');

assert.match(marketplace, /can_create_listing/);
assert.match(marketplace, /user_can\(\s*\$user,\s*'read'\s*\)/, 'ordinary WordPress members with read capability must be eligible');
assert.match(marketplace, /mvm_marketplace_user_can_list/, 'site must retain a per-user suspension hook');

assert.match(rest, /require_authenticated/, 'marketplace writes must require login');
assert.match(rest, /MvM_Marketplace::can_create_listing\(\)/, 'create permission must use member eligibility');
assert.match(rest, /MvM_Marketplace::is_owner\(\s*\$post_id\s*\)/, 'editing must remain owner-scoped');
assert.match(rest, /MARKETPLACE_MODERATE/, 'moderation must remain a separate capability');
assert.match(rest, /marketplace\/mine/, 'members need a private own-listings endpoint');
assert.match(rest, /marketplace\/media/, 'members need an authenticated photo-upload endpoint');
assert.match(rest, /marketplace\/\(\?P<id>\\d\+\)\/status/, 'members need owner status controls');

assert.match(frontend, /is_user_logged_in\(\)/, 'member create UI must be shown when logged in');
assert.match(frontend, /Advertentie plaatsen/, 'member create action must be present');
assert.match(frontend, /Mijn advertenties/, 'members must have their own listings view');

assert.ok(!/current_user_can\(\s*'publish_posts'\s*\)/.test(rest), 'ordinary members must not need WordPress publishing privileges');
assert.ok(!/current_user_can\(\s*'upload_files'\s*\)/.test(rest), 'ordinary members must not need broad media-library privileges');

console.log('MvM marketplace ordinary-member contract: OK');
