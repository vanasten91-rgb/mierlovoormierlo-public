'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const designSystem = fs.readFileSync(
  path.join(root, 'plugins', 'mvm-platform', 'assets', 'design-system.css'),
  'utf8'
);
const featuredAlt = fs.readFileSync(
  path.join(root, 'plugins', 'mvm-platform', 'src', 'site', 'class-featured-image-alt.php'),
  'utf8'
);

let assertions = 0;
const check = (condition, message) => {
  assert.ok(condition, message);
  assertions += 1;
};

check(designSystem.includes(':focus-visible'), 'canonical design system must preserve explicit keyboard focus styling');
check(
  designSystem.includes('outline: 3px solid var(--mvm-color-sky-500)') && designSystem.includes('outline-offset: 3px'),
  'focus-visible contract must remain visibly distinct from the control boundary'
);
check(
  designSystem.includes('@media (prefers-reduced-motion: reduce)'),
  'canonical design system must respect reduced-motion user preference'
);
check(
  designSystem.includes('transition-duration: 0.01ms !important') &&
    designSystem.includes('animation-duration: 0.01ms !important') &&
    designSystem.includes('animation-iteration-count: 1 !important'),
  'reduced-motion mode must minimize transitions and animations'
);
check(
  designSystem.includes('min-height: 3rem'),
  'canonical button primitive must retain a usable minimum control height'
);
check(
  designSystem.includes('min-height: 3.375rem'),
  'canonical input/select primitive must retain a usable minimum control height'
);

check(
  featuredAlt.includes("function_exists( 'mvm_featured_alt_fallback_v1' )"),
  'featured-image alt migration must keep the legacy coexistence guard during staged cutover'
);

const setIfMissing = (featuredAlt.split('private static function set_if_missing')[1] || '');
check(
  setIfMissing.includes("get_post_meta( $attachment_id, '_wp_attachment_image_alt', true )"),
  'featured-image alt helper must inspect stored attachment alt text before writing'
);
const existingGuardAt = setIfMissing.indexOf("if ( '' !== $current )");
const updateAt = setIfMissing.indexOf('update_post_meta(');
check(
  existingGuardAt >= 0 && updateAt >= 0 && existingGuardAt < updateAt,
  'existing non-empty attachment alt text must be protected before any update_post_meta call'
);
check(
  setIfMissing.includes("if ( 'publish' !== get_post_status( $post_id ) )"),
  'automatic alt backfill must remain limited to published source posts'
);

console.log(`Platform accessibility contract: ${assertions} assertions passed.`);
