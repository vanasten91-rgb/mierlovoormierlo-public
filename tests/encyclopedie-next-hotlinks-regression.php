<?php

declare( strict_types=1 );

$root = dirname( __DIR__ );
$hotlinks = file_get_contents( $root . '/plugins/mvm-encyclopedie-next/src/class-hotlinks.php' );
$bootstrap = file_get_contents( $root . '/plugins/mvm-encyclopedie-next/mvm-encyclopedie-next.php' );

if ( false === $hotlinks || false === $bootstrap ) {
    fwrite( STDERR, "Unable to read encyclopedia hotlink sources.\n" );
    exit( 1 );
}

$failures = array();

if ( false === strpos( $hotlinks, "preg_split( '~(<[^>]+>)~', \$content, -1, PREG_SPLIT_DELIM_CAPTURE )" ) ) {
    $failures[] = 'Hotlink parser must split HTML with a real capture group and safe regex delimiters.';
}

if ( false !== strpos( $hotlinks, "preg_split( '(<[^>]+>)'" ) ) {
    $failures[] = 'Broken delimiter form must never return.';
}

$sample = '<h2>Hoofdstuk</h2><p>Bezetting en <a href="/bestaand/">bestaande link</a>.</p><h3>Verdieping</h3><p>Volgende alinea.</p>';
$chunks = preg_split( '~(<[^>]+>)~', $sample, -1, PREG_SPLIT_DELIM_CAPTURE );
if ( ! is_array( $chunks ) || implode( '', $chunks ) !== $sample ) {
    $failures[] = 'Captured HTML chunks must reassemble byte-for-byte to the original markup.';
}

foreach ( array( '<h2>', '</h2>', '<p>', '</p>', '<h3>', '</h3>', '<a href="/bestaand/">', '</a>' ) as $tag ) {
    if ( ! in_array( $tag, $chunks, true ) ) {
        $failures[] = 'Expected structural tag missing from captured chunks: ' . $tag;
    }
}

if ( false === strpos( $bootstrap, '.mvm-encyclopedie-next a.mvm-e3-hotlink{font-weight:800;' ) ) {
    $failures[] = 'Every encyclopedia hotlink must be visually emphasized with font-weight 800.';
}

if ( false === strpos( $bootstrap, "Version: 3.0.0-alpha4" ) || false === strpos( $bootstrap, "MVM_ENCYCLOPEDIE_NEXT_VERSION', '3.0.0-alpha4'" ) ) {
    $failures[] = 'Hotfix must ship as MvM Digitale Encyclopedie Next 3.0.0-alpha4.';
}

if ( $failures ) {
    foreach ( $failures as $failure ) {
        fwrite( STDERR, "FAIL: {$failure}\n" );
    }
    exit( 1 );
}

fwrite( STDOUT, "Encyclopedie hotlink markup regression: OK\n" );
