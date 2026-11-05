<?php
/**
 * Backups before changes. Tools > Site Health warns when the site has no recent off-site backup,
 * and es_backup_status() lets an agent (or a deploy script) check before a big change:
 *
 *   $b = es_backup_status();  // [ plugin, last (timestamp), ok, remote, keeps, problems[] ]
 *   if ( ! $b['ok'] ) { ... take a backup first: UpdraftPlus > Backup Now ... }
 *
 * Works with UpdraftPlus (the ElRoi default). Bigger changes (a redesign, a theme switch, a
 * plugin swap) should also be tried on the host's staging copy first.
 *
 * @package ElRoiStarter
 */

defined( 'ABSPATH' ) || exit;

/**
 * Backup state: which plugin, when the last good backup finished, where it is stored, how many are kept.
 *
 * @param int $max_age Seconds a backup stays "recent" (default 8 days, so a weekly schedule passes).
 */
function es_backup_status( $max_age = 8 * DAY_IN_SECONDS ) {
	$out = array( 'plugin' => '', 'last' => 0, 'ok' => false, 'remote' => array(), 'keeps' => 0, 'problems' => array() );
	if ( ! class_exists( 'UpdraftPlus' ) && ! defined( 'UPDRAFTPLUS_DIR' ) ) {
		$out['problems'][] = __( 'No backup plugin is active. Install UpdraftPlus and send backups to Google Drive or Dropbox.', 'elroi-starter' );
		return $out;
	}
	$out['plugin'] = 'UpdraftPlus';
	$last          = (array) get_option( 'updraft_last_backup', array() );
	$out['last']   = ! empty( $last['success'] ) ? (int) ( $last['backup_time'] ?? 0 ) : 0;
	$out['remote'] = array_values( array_diff( array_filter( (array) get_option( 'updraft_service', array() ) ), array( 'none' ) ) );
	$out['keeps']  = (int) get_option( 'updraft_retain', 2 );
	if ( ! $out['last'] ) {
		$out['problems'][] = __( 'No finished backup yet. Run UpdraftPlus > Backup Now.', 'elroi-starter' );
	} elseif ( time() - $out['last'] > $max_age ) {
		/* translators: %s: time since the last backup, e.g. "2 weeks". */
		$out['problems'][] = sprintf( __( 'The last backup finished %s ago. Check the schedule under UpdraftPlus > Settings.', 'elroi-starter' ), human_time_diff( $out['last'] ) );
	}
	if ( ! $out['remote'] ) {
		$out['problems'][] = __( 'Backups stay on this server, so they are lost if the server is. Choose Google Drive or Dropbox under UpdraftPlus > Settings > Remote storage.', 'elroi-starter' );
	}
	if ( $out['keeps'] < 3 ) {
		$out['problems'][] = __( 'Only one or two backups are kept, so a mistake noticed a few days late may already be in every copy. Keep at least 7 under UpdraftPlus > Settings.', 'elroi-starter' );
	}
	$out['ok'] = ! $out['problems'];
	return $out;
}

/**
 * Site Health test.
 */
function es_backup_health_test() {
	$b      = es_backup_status();
	$result = array(
		'label'       => __( 'Off-site backups are running', 'elroi-starter' ),
		'status'      => 'good',
		'badge'       => array( 'label' => __( 'Security', 'elroi-starter' ), 'color' => 'blue' ),
		'description' => '<p>' . esc_html(
			/* translators: 1: time since the last backup, 2: storage names. */
			$b['last'] ? sprintf( __( 'Last backup %1$s ago, stored in %2$s.', 'elroi-starter' ), human_time_diff( $b['last'] ), implode( ', ', $b['remote'] ) ) : ''
		) . '</p>',
		'actions'     => '',
		'test'        => 'es_backups',
	);
	if ( ! $b['ok'] ) {
		$result['label']       = __( 'Backups need attention', 'elroi-starter' );
		$result['status']      = 'recommended';
		$result['description'] = '<p>' . implode( '</p><p>', array_map( 'esc_html', $b['problems'] ) ) . '</p>';
		if ( $b['plugin'] ) {
			$result['actions'] = '<a href="' . esc_url( admin_url( 'options-general.php?page=updraftplus' ) ) . '">' . esc_html__( 'Open UpdraftPlus', 'elroi-starter' ) . '</a>';
		}
	}
	return $result;
}

add_filter(
	'site_status_tests',
	function ( $tests ) {
		$tests['direct']['es_backups'] = array(
			'label' => __( 'Backups', 'elroi-starter' ),
			'test'  => 'es_backup_health_test',
		);
		return $tests;
	}
);
