#!/usr/bin/env bash
set -euo pipefail

ROOT="${GITHUB_WORKSPACE:-$(cd "$(dirname "$0")/.." && pwd)}"
WP_PATH="${RUNNER_TEMP:-/tmp}/mvm-encyclopedie-next-wp"
WP_CLI="${RUNNER_TEMP:-/tmp}/wp-cli.phar"
BASE_URL="http://127.0.0.1:8080"

rm -rf "$WP_PATH"
mkdir -p "$WP_PATH"

curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$WP_CLI"
chmod +x "$WP_CLI"
WP=(php "$WP_CLI" --path="$WP_PATH" --allow-root)

"${WP[@]}" core download --version=7.1 --force --quiet
"${WP[@]}" config create \
  --dbname=wordpress \
  --dbuser=root \
  --dbpass=root \
  --dbhost=127.0.0.1:3306 \
  --skip-check \
  --quiet
"${WP[@]}" core install \
  --url="$BASE_URL" \
  --title="MvM Encyclopedie Next Integration" \
  --admin_user=admin \
  --admin_password='integration-only-password' \
  --admin_email='integration@example.invalid' \
  --skip-email \
  --quiet

# The PHP built-in server serves WordPress at the web root even though the
# filesystem lives under RUNNER_TEMP. Pin public URL constants so plugins_url()
# cannot inherit the temporary directory name from the CLI/server environment.
"${WP[@]}" config set WP_HOME "$BASE_URL" --type=constant --quiet
"${WP[@]}" config set WP_SITEURL "$BASE_URL" --type=constant --quiet
"${WP[@]}" config set WP_CONTENT_URL "$BASE_URL/wp-content" --type=constant --quiet
"${WP[@]}" config set WP_PLUGIN_URL "$BASE_URL/wp-content/plugins" --type=constant --quiet

rm -rf "$WP_PATH/wp-content/plugins/mvm-encyclopedie-next"
cp -R "$ROOT/plugins/mvm-encyclopedie-next" "$WP_PATH/wp-content/plugins/mvm-encyclopedie-next"
"${WP[@]}" plugin activate mvm-encyclopedie-next --quiet
PLUGIN_URL=$("${WP[@]}" eval 'echo MVM_ENCYCLOPEDIE_NEXT_URL;')
EXPECTED_PLUGIN_URL="$BASE_URL/wp-content/plugins/mvm-encyclopedie-next/"
if [ "$PLUGIN_URL" != "$EXPECTED_PLUGIN_URL" ]; then
  echo "Isolated WordPress plugin URL mismatch: $PLUGIN_URL != $EXPECTED_PLUGIN_URL" >&2
  exit 1
fi
echo "Plugin asset base URL OK: $PLUGIN_URL"

"${WP[@]}" eval-file "$ROOT/tests/encyclopedie-next-wordpress-fixtures.php"
"${WP[@]}" rewrite structure '/%postname%/' --hard --quiet
"${WP[@]}" rewrite flush --hard --quiet
"${WP[@]}" eval-file "$ROOT/tests/encyclopedie-next-wordpress-smoke.php"
"${WP[@]}" eval-file "$ROOT/tests/encyclopedie-next-browser-fixtures.php"

cat > "$WP_PATH/router.php" <<'PHP'
<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = __DIR__ . $path;
if ( '/' !== $path && is_file( $file ) ) {
    return false;
}
require __DIR__ . '/index.php';
PHP

php -S 127.0.0.1:8080 -t "$WP_PATH" "$WP_PATH/router.php" >"${RUNNER_TEMP:-/tmp}/mvm-e3-server.log" 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" >/dev/null 2>&1 || true' EXIT

for _ in $(seq 1 30); do
  if curl -fsS "$BASE_URL/encyclopedie/" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

fetch_html() {
  local path="$1"
  local target="$2"
  local label="$3"
  if ! curl -fsS "$BASE_URL$path" -o "$target"; then
    echo "HTTP fetch failed: $label ($path)" >&2
    exit 1
  fi
  echo "HTTP page OK: $label ($path)"
}

assert_contains() {
  local file="$1"
  local needle="$2"
  local label="$3"
  if ! grep -F "$needle" "$file" >/dev/null; then
    echo "HTML assertion failed: $label -- missing: $needle" >&2
    exit 1
  fi
  echo "HTML assertion OK: $label"
}

assert_not_contains() {
  local file="$1"
  local needle="$2"
  local label="$3"
  if grep -F "$needle" "$file" >/dev/null; then
    echo "HTML assertion failed: $label -- unexpected: $needle" >&2
    exit 1
  fi
  echo "HTML assertion OK: $label"
}

fetch_html '/encyclopedie/' /tmp/mvm-e3-home.html 'encyclopedia home'
fetch_html '/encyclopedie/themas/' /tmp/mvm-e3-themes.html 'themes overview'
fetch_html '/encyclopedie/themas/onderwijs-jeugd/' /tmp/mvm-e3-theme-group.html 'editorial theme group'
fetch_html '/encyclopedie/thema/onderwijs/' /tmp/mvm-e3-theme-taxonomy.html 'granular theme taxonomy'
fetch_html '/encyclopedie/locaties/arkweg/' /tmp/mvm-e3-arkweg.html 'Arkweg dossier'
fetch_html '/encyclopedie/artikel/moderne-basisscholen/' /tmp/mvm-e3-schools.html 'Moderne basisscholen dossier'
fetch_html '/encyclopedie/personen/' /tmp/mvm-e3-people.html 'people archive'

HIDDEN_STATUS=$(curl -sS -o /tmp/mvm-e3-hidden.html -w '%{http_code}' "$BASE_URL/encyclopedie/locaties/verborgen-locatie/")
if [ "$HIDDEN_STATUS" != '404' ]; then
  echo "Hidden dossier direct URL must return 404, got $HIDDEN_STATUS" >&2
  exit 1
fi
echo "Hidden singular URL boundary OK: 404"

for route in \
  encyclopedie/artikel/ \
  encyclopedie/personen/ \
  encyclopedie/locaties/ \
  encyclopedie/gebouwen/ \
  encyclopedie/gebeurtenissen/ \
  encyclopedie/verenigingen/ \
  encyclopedie/bedrijven/ \
  encyclopedie/beeldbank/ \
  encyclopedie/bronnen/; do
  curl -fsS "$BASE_URL/$route" >/dev/null
  echo "HTTP archive OK: /$route"
done

REWRITE_LIST=$(${WP[@]} rewrite list --fields=match --format=csv)
for prefix in \
  'encyclopedie/thema/' \
  'encyclopedie/periode/' \
  'encyclopedie/status/' \
  'encyclopedie/gebied/'; do
  if ! printf '%s\n' "$REWRITE_LIST" | grep -F "$prefix" >/dev/null; then
    echo "Missing taxonomy rewrite prefix: $prefix" >&2
    exit 1
  fi
  echo "Rewrite registered: /$prefix"
done

if ! curl -fsS "$BASE_URL/wp-content/plugins/mvm-encyclopedie-next/assets/frontend.css" >/dev/null; then
  echo "Static asset failed: frontend.css" >&2
  exit 1
fi
if ! curl -fsS "$BASE_URL/wp-content/plugins/mvm-encyclopedie-next/assets/frontend.js" >/dev/null; then
  echo "Static asset failed: frontend.js" >&2
  exit 1
fi
echo "Static assets OK"

assert_contains /tmp/mvm-e3-home.html 'data-mvm-encyclopedie-root' 'new homepage root rendered'
assert_contains /tmp/mvm-e3-home.html 'mvm-encyclopedie-next' 'new homepage styling scope rendered'
assert_contains /tmp/mvm-e3-theme-taxonomy.html 'mvm-encyclopedie-next' 'granular taxonomy uses new runtime template'
assert_contains /tmp/mvm-e3-theme-taxonomy.html '>Onderwijs</h1>' 'granular taxonomy heading preserved'
assert_contains /tmp/mvm-e3-theme-taxonomy.html 'Moderne basisscholen' 'granular taxonomy contains assigned dossier'
assert_contains /tmp/mvm-e3-theme-group.html 'data-mvm-theme-group="onderwijs-jeugd"' 'editorial theme page uses composite group bridge'
assert_contains /tmp/mvm-e3-theme-group.html 'Onderwijs' 'editorial theme page keeps Onderwijs title text'
assert_contains /tmp/mvm-e3-theme-group.html 'jeugd' 'editorial theme page keeps jeugd title text'
assert_contains /tmp/mvm-e3-theme-group.html 'Moderne basisscholen' 'editorial theme group finds dossier through granular term'
assert_not_contains /tmp/mvm-e3-theme-group.html 'niet gekoppeld aan de bestaande kennislaag' 'editorial theme group is not using fallback'
assert_contains /tmp/mvm-e3-arkweg.html 'mvm-encyclopedie-next' 'Arkweg uses new dossier template'
assert_contains /tmp/mvm-e3-schools.html 'mvm-e3-hotlink' 'dossier hotlink rendered'
assert_contains /tmp/mvm-e3-schools.html '/encyclopedie/locaties/arkweg/' 'hotlink keeps Arkweg canonical URL'
assert_not_contains /tmp/mvm-e3-home.html 'Verborgen locatie' 'hidden location excluded from home'
assert_not_contains /tmp/mvm-e3-people.html 'Verborgen persoon' 'hidden person excluded from archive'

THEME_CARD_COUNT=$(grep -o 'mvm-e3-theme-card' /tmp/mvm-e3-themes.html | wc -l | tr -d ' ')
if [ "$THEME_CARD_COUNT" -ne 18 ]; then
  echo "Expected 18 theme cards, got $THEME_CARD_COUNT" >&2
  exit 1
fi
echo "Theme overview count OK: 18"

PEOPLE_CARD_COUNT=$(grep -o 'class="mvm-e3-card"' /tmp/mvm-e3-people.html | wc -l | tr -d ' ')
if [ "$PEOPLE_CARD_COUNT" -ne 24 ]; then
  echo "Expected 24 people on first archive page, got $PEOPLE_CARD_COUNT" >&2
  exit 1
fi
echo "People pagination count OK: 24"

curl -fsS "$BASE_URL/wp-json/mvm-encyclopedie/v1/search?q=Persoon&type=mvm_persoon&per_page=100" -o /tmp/mvm-e3-search.json
curl -fsS "$BASE_URL/wp-json/mvm-encyclopedie/v1/items/1977/relations?per_page=24" -o /tmp/mvm-e3-relations.json
HIDDEN_REST_STATUS=$(curl -sS -o /tmp/mvm-e3-hidden-relations.json -w '%{http_code}' "$BASE_URL/wp-json/mvm-encyclopedie/v1/items/2055/relations")
if [ "$HIDDEN_REST_STATUS" != '404' ]; then
  echo "Hidden dossier relation REST must return 404, got $HIDDEN_REST_STATUS" >&2
  exit 1
fi
php -r '
$search=json_decode(file_get_contents("/tmp/mvm-e3-search.json"),true,512,JSON_THROW_ON_ERROR);
if (count($search["items"] ?? []) !== 24 || (int)($search["total"] ?? 0) !== 30) { fwrite(STDERR,"REST search bound failed\n"); exit(1); }
$relations=json_decode(file_get_contents("/tmp/mvm-e3-relations.json"),true,512,JSON_THROW_ON_ERROR);
if ((int)($relations["total"] ?? 0) !== 15 || count($relations["items"] ?? []) !== 15 || ($relations["items"][0]["title"] ?? "") !== "Arkweg") { fwrite(STDERR,"REST relation visibility/lazy fixture failed\n"); exit(1); }
'
echo "REST search/relations/hidden boundary OK"

HOME_BYTES=$(wc -c < /tmp/mvm-e3-home.html)
ARKWEG_BYTES=$(wc -c < /tmp/mvm-e3-arkweg.html)
if [ "$HOME_BYTES" -gt 500000 ]; then
  echo "Homepage response grew beyond 500 KB: $HOME_BYTES bytes" >&2
  exit 1
fi
if [ "$ARKWEG_BYTES" -gt 350000 ]; then
  echo "Dossier response grew beyond 350 KB: $ARKWEG_BYTES bytes" >&2
  exit 1
fi
echo "HTML size gates OK: home=${HOME_BYTES}B arkweg=${ARKWEG_BYTES}B"

assert_ttfb_under() {
  local url="$1"
  local limit="$2"
  local measured
  measured=$(curl -fsS -o /dev/null -w '%{time_starttransfer}' "$url")
  php -r '$m=(float)$argv[1]; $l=(float)$argv[2]; if ($m >= $l) { fwrite(STDERR,"TTFB gate failed: {$m}s >= {$l}s\n"); exit(1); }' "$measured" "$limit"
  echo "TTFB $url: ${measured}s"
}

assert_ttfb_under "$BASE_URL/encyclopedie/" 3.0
assert_ttfb_under "$BASE_URL/encyclopedie/locaties/arkweg/" 3.0

node "$ROOT/tests/encyclopedie-next-browser-smoke.cjs" "$BASE_URL"

if grep -E 'Fatal error|Uncaught Error|Parse error' "${RUNNER_TEMP:-/tmp}/mvm-e3-server.log"; then
  echo "Fatal PHP output detected in isolated WordPress server log." >&2
  exit 1
fi

echo "Encyclopedie Next isolated WordPress 7.1 integration: OK"
