<?php
/**
 * Site footer: business details from Site Settings, footer menu, social links.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
$es_address = trim( implode( ', ', array_filter( array( es_setting( 'street' ), es_setting( 'city' ), trim( es_setting( 'region' ) . ' ' . es_setting( 'zip' ) ) ) ) ) );
?>
<footer class="es-footer">
	<div class="es-container es-footer__grid">
		<div>
			<p class="es-footer__name"><?php echo esc_html( es_setting( 'company' ) ); ?></p>
			<p><?php echo elroi_text( es_setting( 'description' ) ); // phpcs:ignore ?></p>
			<?php if ( es_social_links() ) : ?>
				<ul class="es-footer__social">
					<?php foreach ( es_social_links() as $es_label => $es_url ) : ?>
						<li><a href="<?php echo esc_url( $es_url ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $es_label ); ?><span class="es-sr"> <?php esc_html_e( '(opens in a new tab)', 'elroi-starter' ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<div>
			<p class="es-footer__label"><?php esc_html_e( 'Contact', 'elroi-starter' ); ?></p>
			<ul class="es-footer__list">
				<?php if ( es_setting( 'phone' ) ) : ?>
					<li><a href="tel:<?php echo esc_attr( es_tel( es_setting( 'phone' ) ) ); ?>"><?php echo es_icon( 'phone', 16 ); // phpcs:ignore ?> <?php echo esc_html( es_setting( 'phone' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( es_setting( 'email' ) ) : ?>
					<li><a href="mailto:<?php echo esc_attr( es_setting( 'email' ) ); ?>"><?php echo es_icon( 'mail', 16 ); // phpcs:ignore ?> <?php echo esc_html( es_setting( 'email' ) ); ?></a></li>
				<?php endif; ?>
				<?php if ( $es_address ) : ?>
					<li><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $es_address ) ); ?>" rel="noopener" target="_blank"><?php echo es_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $es_address ); ?><span class="es-sr"> <?php esc_html_e( '(opens Google Maps in a new tab)', 'elroi-starter' ); ?></span></a></li>
				<?php endif; ?>
			</ul>
			<?php foreach ( es_offices() as $es_office ) : ?>
				<p class="es-footer__label es-footer__label--office"><?php echo esc_html( $es_office['name'] ? $es_office['name'] : __( 'Office', 'elroi-starter' ) ); ?></p>
				<ul class="es-footer__list">
					<?php if ( $es_office['phone'] ) : ?>
						<li><a href="tel:<?php echo esc_attr( es_tel( $es_office['phone'] ) ); ?>"><?php echo es_icon( 'phone', 16 ); // phpcs:ignore ?> <?php echo esc_html( $es_office['phone'] ); ?></a></li>
					<?php endif; ?>
					<?php if ( $es_office['address'] ) : ?>
						<li><a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $es_office['address'] ) ); ?>" rel="noopener" target="_blank"><?php echo es_icon( 'pin', 16 ); // phpcs:ignore ?> <?php echo esc_html( $es_office['address'] ); ?><span class="es-sr"> <?php esc_html_e( '(opens Google Maps in a new tab)', 'elroi-starter' ); ?></span></a></li>
					<?php endif; ?>
				</ul>
			<?php endforeach; ?>
			<?php if ( es_setting( 'hours' ) ) : ?>
				<ul class="es-footer__hours">
					<?php foreach ( (array) es_setting( 'hours' ) as $es_h ) : ?>
						<li><?php echo esc_html( $es_h ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav aria-label="<?php esc_attr_e( 'Footer', 'elroi-starter' ); ?>">
				<p class="es-footer__label"><?php esc_html_e( 'Explore', 'elroi-starter' ); ?></p>
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'menu_class' => 'es-footer__list', 'depth' => 1 ) ); ?>
			</nav>
		<?php endif; ?>
	</div>
	<div class="es-container es-footer__base">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . es_setting( 'company' ) ); ?></p>
		<p><?php esc_html_e( 'Website by', 'elroi-starter' ); ?> <a href="https://elroi.marketing/">ElRoi Marketing</a></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
