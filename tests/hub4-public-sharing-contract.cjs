'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', 'plugins', 'mvm-hub4-rc-direct');
const main = fs.readFileSync(path.join(root, 'mvm-hub4.php'), 'utf8');
const sharing = fs.readFileSync(path.join(root, 'src/class-public-sharing.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'assets/public-sharing-v1.css'), 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(main.includes("src/class-public-sharing.php"), 'compatibility shim must remain bootstrapped');
assert(main.includes('MvM_Hub4_Public_Sharing::init();'), 'compatibility shim must still initialize safely');
assert(sharing.includes('Intentionally no-op'), 'generic share module must be explicitly disabled sitewide');
assert(!sharing.includes("add_filter( 'the_content'"), 'generic share module must not attach to the content pipeline');
assert(!sharing.includes('data-mvm-share-v1="1"'), 'generic share block markup must not be emitted');
assert(!sharing.includes('facebook.com/sharer') && !sharing.includes('api.whatsapp.com/send') && !sharing.includes('mailto:?subject='), 'disabled generic share module must not build share actions');
assert(sharing.includes('return $content;'), 'compatibility method must leave content unchanged');

assert(css.includes('.wp-embed-aspect-16-9 iframe { aspect-ratio: 16 / 9 !important; }'), '16:9 event videos must preserve their source ratio');
assert(css.includes('.wp-embed-aspect-1-1 iframe { aspect-ratio: 1 / 1 !important; }'), 'square event videos must preserve their source ratio');
assert(css.includes('video.wp-video-shortcode'), 'native event video must remain responsive');
assert(css.includes('object-fit: contain !important'), 'native event video must not be cropped or stretched');

console.log('Hub 4 sitewide generic share removal contract: all assertions passed.');
