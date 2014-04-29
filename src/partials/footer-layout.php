<?php
/**
 * @package Make
 */

// Footer Options
$footer_layout = (int) get_theme_mod( 'footer-layout', ttfmake_get_default( 'footer-layout' ) );
$sidebar_count = (int) get_theme_mod( 'footer-widget-areas', ttfmake_get_default( 'footer-widget-areas' ) );
$social_links  = ttfmake_get_social_links();
$show_social   = (int) get_theme_mod( 'footer-show-social', ttfmake_get_default( 'footer-show-social' ) );
?>

<footer id="site-footer" class="site-footer footer-layout-<?php echo esc_attr( $footer_layout ); ?>" role="contentinfo">
	<div class="container">
		<?php // Footer widget areas
		if ( $sidebar_count > 0 ) : ?>
		<div class="footer-widget-container columns-<?php echo esc_attr( $sidebar_count ); ?>">
			<?php
			$current_sidebar = 1;
			while ( $current_sidebar <= $sidebar_count ) :
				get_sidebar( 'footer-' . $current_sidebar );
				$current_sidebar++;
			endwhile; ?>
		</div>
		<?php endif; ?>

		<?php // Footer text and credit line
		get_template_part( 'partials/footer', 'credit' ); ?>

		<?php ttfmake_maybe_show_social_links( 'footer' ); ?>
	</div>
</footer>