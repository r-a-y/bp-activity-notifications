<?php
/*
Plugin Name: BP Activity Notifications
Description: Use the activity component for notifications. Requires BP 1.9-bleeding.
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
Version: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * BP Activity Notifications
 *
 * @package BP_AN
 * @subpackage Loader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Only load the plugin if BuddyPress is loaded and initialized.
 */
function bp_an_init() {
	// requires newer version of BP
	if ( ! is_dir( constant( 'BP_PLUGIN_DIR' ) . 'bp-notifications' ) )
		return;

	// some pertinent defines
	define( 'BP_AN_DIR', dirname( __FILE__ ) );
	define( 'BP_AN_URL', plugin_dir_url( __FILE__ ) );

	// require some notification abstraction functions
	require BP_AN_DIR . '/includes/abstraction.php';

	// load up our custom activity notifications!
	require BP_AN_DIR . '/bp-an-core.php';
}
add_action( 'bp_include', 'bp_an_init' );

/**
 * Disables the new notifications component from being active and loaded.
 */
function bp_an_core_loaded() {
	add_filter( 'bp_active_components', 'bp_an_deactivate_notifications' );
}
add_action( 'bp_core_loaded', 'bp_an_core_loaded' );

/**
 * Filter that removes the notifications component from the active components
 * list.
 */
function bp_an_deactivate_notifications( $retval ) {
	unset( $retval['notifications'] );
	return $retval;
}