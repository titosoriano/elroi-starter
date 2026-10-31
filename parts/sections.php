<?php
/**
 * Content sections. Args: sections (list).
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;
foreach ( (array) ( $args['sections'] ?? array() ) as $i => $s ) :
	$has_image = ! empty( $s['image'] );
	?>
	<section class="es-section<?php echo $has_image ? ' es-section--media' : ''; ?><?php echo $i % 2 ? ' es-section--alt' : ''; ?>"<?php echo ! empty( $s['heading'] ) ? ' aria-labelledby="es-s' . (int) $i . '"' : ''; ?>>
		<div class="es-container es-section__grid">
			<div class="es-section__copy" data-es-reveal>
				<?php if ( ! empty( $s['eyebrow'] ) ) : ?>
					<p class="es-eyebrow"><?php echo elroi_text( $s['eyebrow'] ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $s['heading'] ) ) : ?>
					<h2 class="es-h2" id="es-s<?php echo (int) $i; ?>"><?php echo elroi_heading( $s['heading'] ); // phpcs:ignore ?></h2>
				<?php endif; ?>
				<?php echo elroi_paragraphs( $s['paragraphs'] ?? array(), '', true ); // phpcs:ignore ?>
				<?php if ( ! empty( $s['bullets'] ) ) : ?>
					<ul class="es-checks">
						<?php foreach ( $s['bullets'] as $b ) : ?>
							<li><?php echo es_icon( 'check', 18 ); // phpcs:ignore ?><span><?php echo elroi_text( $b ); // phpcs:ignore ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( $has_image ) : ?>
				<figure class="es-section__media" data-es-reveal><?php echo elroi_image( $s['image'], 'large', array( 'loading' => 'lazy', 'sizes' => es_sizes( 'half' ) ) ); // phpcs:ignore ?></figure>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $s['cards'] ) ) : ?>
			<div class="es-container"><ul class="es-cards">
				<?php foreach ( $s['cards'] as $c ) : ?>
					<li class="es-card" data-es-reveal>
						<?php if ( ! empty( $c['image'] ) ) : ?>
							<?php echo elroi_image( $c['image'], 'medium_large', array( 'class' => 'es-card__img', 'loading' => 'lazy', 'sizes' => es_sizes( 'card' ) ) ); // phpcs:ignore ?>
						<?php endif; ?>
						<h3 class="es-card__title">
							<?php if ( ! empty( $c['link'] ) ) : ?><a href="<?php echo elroi_link( $c['link'] ); // phpcs:ignore ?>"><?php echo elroi_text( $c['heading'] ); // phpcs:ignore ?></a><?php else : ?><?php echo elroi_text( $c['heading'] ); // phpcs:ignore ?><?php endif; ?>
						</h3>
						<?php if ( ! empty( $c['text'] ) ) : ?>
							<p><?php echo elroi_text( $c['text'] ); // phpcs:ignore ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
	</section>
	<?php
endforeach;
