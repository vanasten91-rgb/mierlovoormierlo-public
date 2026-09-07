<?php

declare(strict_types=1);

$root = dirname( __DIR__ );

define( 'ABSPATH', $root . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['mvm_test_actions'] = array();
$GLOBALS['mvm_test_filters'] = array();

function plugin_dir_path( string $file ): string {
    return rtrim( dirname( $file ), '/\\' ) . '/';
}

function plugin_dir_url( string $file ): string {
    return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    unset( $priority, $accepted_args );
    $GLOBALS['mvm_test_actions'][ $hook ][] = $callback;
    return true;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
    unset( $priority, $accepted_args );
    $GLOBALS['mvm_test_filters'][ $hook ][] = $callback;
    return true;
}

require $root . '/plugins/mvm-hub/mvm-hub.php';

$plugins_loaded = $GLOBALS['mvm_test_actions']['plugins_loaded'] ?? array();
if ( 1 !== count( $plugins_loaded ) ) {
    fwrite( STDERR, "FAIL: expected exactly one plugins_loaded bootstrap callback\n" );
    exit( 1 );
}

call_user_func( $plugins_loaded[0] );

$required_classes = array(
    'MVM\\Hub\\Core\\Kernel',
    'MVM\\Hub\\Core\\Runtime_Gates',
    'MVM\\Hub\\Core\\Hub_Runtime',
    'MVM\\Hub\\Core\\Workspace_Sections',
    'MVM\\Hub\\Core\\Module_Descriptors',
    'MVM\\Hub\\Core\\Shell_Renderer',
    'MVM\\Hub\\Core\\Shell_Preview_REST_Controller',
    'MVM\\Hub\\Integrations\\Encyclopedie\\Smart_Links_Target_Search',
    'MVM\\Hub\\Integrations\\Mail\\Legacy_Readonly_Mail_Provider',
    'MVM\\Hub\\Integrations\\Mail\\Legacy_Editorial_Mailbox_Access',
    'MVM\\Hub\\Integrations\\Mail\\Configured_Mailbox_Access',
    'MVM\\Hub\\Integrations\\Mail\\Dedicated_SMTP_Mail_Provider',
    'MVM\\Hub\\Integrations\\Mail\\Dedicated_IMAP_SMTP_Mail_Provider',
    'MVM\\Hub\\Integrations\\Mail\\Folder_Scoped_IMAP_Mail_Provider',
    'MVM\\Hub\\Integrations\\Mail\\Encrypted_Filesystem_Draft_Store',
    'MVM\\Hub\\Integrations\\Mail\\Filesystem_Private_Attachment_Store',
    'MVM\\Hub\\Integrations\\Mail\\ClamAV_Attachment_Scanner',
    'MVM\\Hub\\Integrations\\Mail\\Default_Communications_Action_Security',
    'MVM\\Hub\\Integrations\\Mail\\Default_Mail_Delivery_Security',
    'MVM\\Hub\\Integrations\\PeepSo\\Legacy_Readonly_Internal_Message_Provider',
    'MVM\\Hub\\Integrations\\PeepSo\\PeepSo8_Internal_Message_Provider',
    'MVM\\Hub\\Modules\\Communications\\Communications_Service_Factory',
    'MVM\\Hub\\Modules\\Communications\\Mail_Read_Projector',
    'MVM\\Hub\\Modules\\Communications\\Mail_Read_Service',
    'MVM\\Hub\\Modules\\Communications\\Mail_Read_REST_Controller',
    'MVM\\Hub\\Modules\\Communications\\Mail_Delivery_Service',
    'MVM\\Hub\\Modules\\Communications\\Internal_Message_Projector',
    'MVM\\Hub\\Modules\\Communications\\Internal_Message_Read_Service',
    'MVM\\Hub\\Modules\\Communications\\Internal_Message_Read_REST_Controller',
    'MVM\\Hub\\Modules\\Communications\\Internal_Message_Service',
    'MVM\\Hub\\Modules\\Communications\\Communications_Preview_Renderer',
    'MVM\\Hub\\Modules\\Communications\\Communications_Runtime_Renderer',
    'MVM\\Hub\\Modules\\Newsroom\\News\\Smart_Links_Target_Search_REST_Controller',
    'MVM\\Hub\\Modules\\Newsroom\\Newsroom_Preview_Renderer',
    'MVM\\Hub\\Modules\\Newsroom\\Newsroom_Runtime_Renderer',
    'MVM\\Hub\\Modules\\Newsroom\\Newsroom_Module',
);

foreach ( $required_classes as $class ) {
    if ( ! class_exists( $class ) ) {
        fwrite( STDERR, "FAIL: bootstrap did not load {$class}\n" );
        exit( 1 );
    }
}

if ( ! isset( $GLOBALS['mvm_test_actions']['rest_api_init'] ) || count( $GLOBALS['mvm_test_actions']['rest_api_init'] ) < 2 ) {
    fwrite( STDERR, "FAIL: kernel did not register expected read-side REST module callbacks\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::shell_preview_enabled() ) {
    fwrite( STDERR, "FAIL: shell preview must be disabled by default\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::route_takeover_enabled() ) {
    fwrite( STDERR, "FAIL: /hub/ route takeover must be disabled by default\n" );
    exit( 1 );
}

if ( isset( $GLOBALS['mvm_test_actions']['template_redirect'] ) ) {
    fwrite( STDERR, "FAIL: default plugin boot must not register a template_redirect Hub takeover\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::communications_writes_enabled() ) {
    fwrite( STDERR, "FAIL: communications writes must be disabled by default\n" );
    exit( 1 );
}

if ( \MVM\Hub\Core\Runtime_Gates::mail_writes_enabled() ) {
    fwrite( STDERR, "FAIL: mail writes must be disabled by default\n" );
    exit( 1 );
}

echo "PASS: MvM Hub executable bootstrap smoke\n";
