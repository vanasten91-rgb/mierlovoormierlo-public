<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main id="primary" class="mvm-e3-page-shell">
    <?php
    while ( have_posts() ) {
        the_post();
        the_content();
    }
    ?>
</main>
<?php
get_footer();
