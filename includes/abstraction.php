<?php

function bp_notifications_add_notification( $args = array() ) {

        $r = wp_parse_args( $args, array(
                'user_id'           => 0,
                'item_id'           => 0,
                'secondary_item_id' => 0,
                'component_name'    => '',
                'component_action'  => '',
                'date_notified'     => bp_core_current_time(),
        ) );

        // Setup the new notification
        $notification                    = new stdClass;
        $notification->user_id           = $r['user_id'];
        $notification->item_id           = $r['item_id'];
        $notification->secondary_item_id = $r['secondary_item_id'];
        $notification->component_name    = $r['component_name'];
        $notification->component_action  = $r['component_action'];
        $notification->date_notified     = $r['date_notified'];
        $notification->is_new            = 1;

        // add our custom do action hook
        do_action( 'bp_notifications_before_insert', $notification );
}

function bp_notifications_toolbar_menu() {
	global $wp_admin_bar;

	if ( !is_user_logged_in() )
		return false;

	// @todo - redo this
	//$notifications = bp_core_get_notifications_for_user( bp_loggedin_user_id(), 'object' );
	//$count         = !empty( $notifications ) ? count( $notifications ) : 0;
	//$alert_class   = (int) $count > 0 ? 'pending-count alert' : 'count no-alert';
	$menu_title    = '!';

	// Add the top-level Notifications button
	$wp_admin_bar->add_menu( array(
		'parent' => 'top-secondary',
		'id'     => 'bp-activity-notifications',
		'title'  => $menu_title,
		'href'   => bp_loggedin_user_domain(),
	) );
}

// @todo support screen
function bp_notifications_buddybar_menu() {}

// @todo flesh these out with actual functionality
function bp_notifications_delete_notifications_by_type( $user_id, $component_name, $component_action ) {}
function bp_notifications_delete_notifications_from_user( $user_id, $component_name, $component_action ) {}
function bp_notifications_delete_notifications_by_item_id( $user_id, $item_id, $component_name, $component_action, $secondary_item_id ) {}
function bp_notifications_delete_all_notifications_by_type( $item_id, $component_name, $component_action, $secondary_item_id ) {}

// @todo perhaps use an activity loop for this?
//       will not support the $format parameter
function bp_notifications_get_notifications_for_user( $user_id, $format ) {}

// not supported
function bp_notifications_get_notification( $id ) {}
function bp_notifications_check_notification_access( $user_id, $notification_id ) {}