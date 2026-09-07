<?php

defined( 'ABSPATH' ) || exit;

MvM_Marketplace_Frontend::enqueue_assets();
get_header();
?>
<main id="primary" class="site-main mvm-marketplace-page">
	<div class="mvm-marketplace-page__inner">
		<?php MvM_Marketplace_Frontend::render_archive_shell(); ?>
	</div>
</main>
<?php
get_footer();
