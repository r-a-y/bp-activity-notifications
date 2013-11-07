<?php
/**
 * BP Activity Notifications Core
 *
 * @package BP_AN
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core class for BP Activity Notifications
 *
 * @package BP_AN
 * @subpackage Classes
 */
class BP_Activity_Notifications {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// fool BP into thinking the notifications component is enabled
		//
		// needed so we can access some notification functions that we'll later
		// manipulate through abstraction
		buddypress()->active_components['notifications'] = 1;

		// setup hooks
		$this->setup_hooks();
	}

	/**
	 * Hooks.
	 */
	protected function setup_hooks() {
		/** RECORD ACTIVITY NOTIFICATIONS ********************************/

		// abracadabra! abstraction FTW!
		add_action( 'bp_notifications_before_insert',   array( $this, 'save_notification' ) );

		// cache PM data so we can reference it in our notification
		// ugly...
		add_action( 'messages_message_after_save',      array( $this, 'save_message_data' ) );

		/** AJAX *********************************************************/

		add_action( 'wp_ajax_activity_notifications',   array( $this, 'ajax' ) );

		/** ACTIVITY MISC HOOKS ******************************************/

		// requires r7467
		add_action( 'bp_activity_user_can_delete',      array( $this, 'activity_can_delete' ), 10, 2 );

		// handle related deletions
		add_action( 'bp_before_activity_delete',        array( $this, 'activity_deletion' ) );

		// filter out notifications from activity stream
		add_filter( 'bp_activity_get_user_join_filter', array( $this, 'hide_notifications_from_stream' ),       10, 4 );
		add_filter( 'bp_activity_total_activities_sql', array( $this, 'hide_notifications_from_stream_count' ), 10, 2 );

		/** CSS / JS *****************************************************/

		add_action( 'wp_head',                          array( $this, 'inline_css' ) );
		add_action( 'wp_after_admin_bar_render',        array( $this, 'inline_js' ) );
	}

	/**
	 * Hide 'notifications' component activity items from the activity stream.
	 */
	public function hide_notifications_from_stream( $retval, $select_sql, $from_sql, $where_sql ) {
		// if we're querying for notifications, we don't want to omit them, so stop!
		if ( ! empty( buddypress()->activity->notifications->query ) )
			return $retval;

		$where_addition = " AND a.component != 'notifications'";

		return str_replace( $where_sql, $where_sql . $where_addition, $retval );
	}

	/**
	 * Adjust count when hiding 'notifications' component activity items.
	 */
	public function hide_notifications_from_stream_count( $retval, $where_sql ) {
		// if we're querying for notifications, we don't want to omit them, so stop!
		if ( ! empty( buddypress()->activity->notifications->query ) )
			return $retval;

		$where_addition = " AND a.component != 'notifications'";

		return str_replace( $where_sql, $where_sql . $where_addition, $retval );
	}

	/**
	 * Records a notification in our new activity component format.
	 *
	 * Piggybacks off the existing notifications component to ensure backwards-
	 * compatibility with plugins already using the existing notifications
	 * component... well kind-of.
	 */
	public function save_notification( $notification ) {
		buddypress()->activity->notifications->activity_args = array(
			// the notification to add
			// eg. John Doe mentioned you in an update
			'action'            => $this->get_action( $notification ),

			'component'         => 'notifications',

			// use the notification action as the activity type
			'type'              => $notification->component_action,

			// the person adding the notification
			'user_id'           => $this->get_user_id( $notification ),

			// the person receiving the notification
			'item_id'           => $notification->user_id,

			// this is used as a replacement for "is_new" from the notifications table
			// when the notification is read we'll update this to 0
			// @todo frontend "Mark as read" button
			'secondary_item_id' => 1,

			// set the primary link
			'primary_link'      => $this->get_primary_link( $notification ),

			// hide this from the sitewide activity stream
			'hide_sitewide'     => true
		);

		buddypress()->activity->notifications->activity_id = bp_activity_add( buddypress()->activity->notifications->activity_args );

		// @todo move notification's 'secondary_item_id' into activity meta
	}

	/**
	 * Helper method to format the activity action for notification activity
	 * before saving.
	 *
	 * In an ideal world, I would use the callbacks from the older notification
	 * component, but most of the strings there lack user-specificity.
	 *
	 * @see BP_Activity_Notifications::save_notification()
	 */
	protected function get_action( $notification ) {
		switch ( $notification->component_action ) {
			// activity
			case 'new_at_mention' :
				return sprintf( __( '%s mentioned you in a post', 'buddypress' ), bp_core_get_userlink( $this->get_user_id( $notification ) ) );

				break;

			// PM
			case 'new_message' :
				return sprintf( __( '%s sent you a private message', 'buddypress' ), bp_core_get_userlink( $this->get_user_id( $notification ) ) );

				break;

			// friends
			case 'friendship_request' :
				return sprintf( __( '%s is requesting to be your friend', 'buddypress' ), bp_core_get_userlink( $this->get_user_id( $notification ) ) );

				break;

			case 'friendship_accepted' :
				return sprintf( __( '%s accepted your friend request', 'buddypress' ), bp_core_get_userlink( $this->get_user_id( $notification ) ) );

				break;

			// groups
			case 'new_membership_request' :
				$group = groups_get_group( array( 'group_id' => $notification->secondary_item_id ) );

				return sprintf(
					__( '%s wants to join the group "%s"', 'buddypress' ),
					bp_core_get_userlink( $this->get_user_id( $notification ) ),
					$group->name
				);

				break;

			// @todo
			case 'membership_request_accepted' :
			case 'membership_request_rejected' :
			case 'member_promoted_to_admin' :
			case 'member_promoted_to_mod' :
			case 'group_invite' :
				return '';

				break;

			// plugin devs will need to re-add support to format notifications here
			// yeah... sucks
			// @todo
			default :
				return '';

				break;
		}

	}

	/**
	 * Helper method to set the user ID for notification activity items.
	 *
	 * @see BP_Activity_Notifications::save_notification()
	 */
	protected function get_user_id( $notification ) {
		// check if we've already cached the notification user id somewhere
		if ( ! empty( buddypress()->activity->notifications->user_id ) ) {
			return buddypress()->activity->notifications->user_id;
		}

		switch ( $notification->component_action ) {
			// activity
			case 'new_at_mention' :
				return $notification->secondary_item_id;

				break;

			// friends
			case 'friendship_request' :
			case 'friendship_accepted' :
				return $notification->item_id;

				break;

			// groups
			case 'new_membership_request' :
				return $notification->item_id;

				break;

			// groups - @todo
			case 'membership_request_accepted' :
			case 'membership_request_rejected' :
			case 'member_promoted_to_admin' :
			case 'member_promoted_to_mod' :
			case 'group_invite' :
				return bp_loggedin_user_id();

				break;

			// @todo plugin hook
			default :
				return bp_loggedin_user_id();

				break;
		}
	}

	/**
	 * Helper method to set the primary link for notification activity items.
	 *
	 * @see BP_Activity_Notifications::save_notification()
	 */
	protected function get_primary_link( $notification ) {

		switch ( $notification->component_action ) {
			case 'new_at_mention' :
				return bp_activity_get_permalink( $notification->item_id );
				break;

			case 'new_message' :
				return trailingslashit( bp_core_get_user_domain( $notification->user_id ) . buddypress()->messages->slug . '/view/' . buddypress()->activity->notifications->thread_id );

				break;

			case 'friendship_request' :
				return trailingslashit( bp_core_get_user_domain( $notification->user_id ) . buddypress()->friends->slug . '/requests/' );

				break;

			case 'friendship_accepted' :
				return bp_core_get_userlink( $this->get_user_id( $notification ) );

				break;

			case 'new_membership_request' :
				$group = groups_get_group( array( 'group_id' => $notification->secondary_item_id ) );

				return bp_get_group_permalink( $group ) . 'admin/membership-requests';

				break;

			// groups - @todo
			case 'membership_request_accepted' :
			case 'membership_request_rejected' :
			case 'member_promoted_to_admin' :
			case 'member_promoted_to_mod' :
			case 'group_invite' :
				return '';

				break;

			default :
				return '';
				break;
		}

		return '';

	}


	/**
	 * Cache PM data for use with notification activity items.
	 *
	 * @see BP_Activity_Notifications::get_user_id()
	 * @see BP_Activity_Notifications::get_primary_link()
	 */
	public function save_message_data( $message ) {
		buddypress()->activity->notifications->user_id   = $message->user_id;
		buddypress()->activity->notifications->thread_id = $message->thread_id;
	}

	/**
	 * Delete notification activity items when the corresponding activity item
	 * is deleted.
	 */
	public function activity_deletion( $args ) {
		if ( empty( $args['id'] ) )
			return;

		// delete notifications with the same activity permalink
		bp_activity_delete( array(
			'component'    => 'notifications',
			'primary_link' => bp_activity_get_permalink( $args['id'] )
		) );
	}

	/**
	 * Temporary inline CSS.
	 */
	public function inline_css() {
		if ( ! is_user_logged_in() )
			return;
	?>

		<style type="text/css">
		#wp-admin-bar-bp-activity-notifications .activity-list {min-width:320px;}

		#wp-admin-bar-bp-activity-notifications #message {min-width:200px;}

		#wpadminbar .activity-list *,
		#wpadminbar #message p {
			text-shadow:none; line-height:1; color:#888;
		}

		#wpadminbar #message p {padding:8px;}

		#wpadminbar .activity-list li {clear:both;}

		#wpadminbar .activity-list a {display:inline; padding:0;}

		#wpadminbar .activity-list .activity-avatar {
			float: left;
			margin:0 8px;
		}

		#wpadminbar .activity-avatar img {width:50px; height:50px;}

		#wpadminbar .activity-list .activity-content {
			margin:8px 8px 8px 65px;
		}

		#wpadminbar .activity-list .activity-inner,
		#wpadminbar .activity-list .activity-header .avatar,
		#wpadminbar .activity-list .activity-meta a,
		#wpadminbar .activity-list .activity-comments {
			display:none;
		}

		#wpadminbar .activity-list .activity-meta a {font-size:.8em;}
		#wpadminbar .activity-list a.delete-activity {display:inline;}

		#wpadminbar .activity-meta {margin-top:-10px;}

		#wpadminbar a.activity-time-since {display:block; margin:8px 0 0 0;}
		#wpadminbar .time-since {font-size:.85em; color:#999;}

		#wpadminbar .load-more a {
			background-color: #fbfbfb;
			border-top: 1px solid #DDD;
			display: block;
			height: 18px;
			padding: 5px 0 0;
			text-align: center;
		}
			#wpadminbar .load-more a:hover {
				background-color: #EAF2FA;
			}

		#wpadminbar a.loading {
			background-image: url( <?php echo BP_PLUGIN_URL . 'bp-themes/bp-default/_inc/images/ajax-loader.gif'; ?> );
			background-position: 95% 50%;
			background-repeat: no-repeat;
			padding-right: 22px;
		}
		</style>

	<?php
	}

	/**
	 * Temporary inline JS.
	 */
	public function inline_js() {
		if ( ! is_user_logged_in() )
			return;
	?>

		<script type="text/javascript">
		jq(document).ready( function() {
			var bp_an = jq("#wp-admin-bar-bp-activity-notifications");

			bp_an.addClass('menupop');

			bp_an.on( 'click', function(event) {
				var target = jq(event.target);
				var elem   = target.get(0);

				// let the following links passthrough
				if ( elem.nodeName == 'SPAN' || elem.nodeName == 'IMG' )
					return true;

				if ( elem.nodeName == 'A' && ! target.hasClass( 'ab-item' ) )
					return true;

				jq(this).toggleClass('hover');
				if ( jq(this).hasClass('hover') ) {
					//target.css({'background-color': '#fff'});

					jq.post( ajaxurl, {
						action: 'activity_notifications'
					},
					function(response) {
						bp_an.append(response);
						bp_an_delete();
					});
				} else {
					bp_an.find('.ab-sub-wrapper').remove();
				}

				return false;
			});
		});

		// a copy-n-paste from buddypress.js
		// need to run this after AJAX
		//
		// preferably we should start extrapolating all JS into functions so we can
		// hook them when needed
		function bp_an_delete() {
			jq('div.activity').on( 'click', function(event) {
				var target = jq(event.target);

				/* Delete activity stream items */
				if ( target.hasClass('delete-activity') ) {
					var li        = target.parents('div.activity ul li');
					var id        = li.attr('id').substr( 9, li.attr('id').length );
					var link_href = target.attr('href');
					var nonce     = link_href.split('_wpnonce=');

					nonce = nonce[1];

					target.addClass('loading');

					jq.post( ajaxurl, {
						action: 'delete_activity',
						'cookie': bp_get_cookies(),
						'id': id,
						'_wpnonce': nonce
					},
					function(response) {

						if ( response[0] + response[1] == '-1' ) {
							li.prepend( response.substr( 2, response.length ) );
							li.children('#message').hide().fadeIn(300);
						} else {
							li.slideUp(300);
						}
					});

					return false;
				}
			});
		}

		</script>

	<?php
	}

	/**
	 * AJAX callback to query notification activity items.
	 *
	 * This occurs when clicking on the "!" link in the WP toolbar.
	 */
	public function ajax() {
		// add marker to query for notifications
		buddypress()->activity->notifications->query = 1;

		// save the logged in user ID
		$loggedin_user_id = bp_loggedin_user_id();

		// hack! when we're on a member profile page, bp_has_activities()
		// automatically populates the 'user_id' parameter with the displayed user ID.
		//
		// this will not work for our custom activity loop, so we have to wipe out
		// the displayed and logged-in user IDs temporarily
		//
		// don't worry! this is only done in this ajax request
		if ( bp_displayed_user_id() ) {
			add_filter( 'bp_displayed_user_id', array( $this, 'return_zero' ) );
			add_filter( 'bp_loggedin_user_id',  array( $this, 'return_zero' ) );

			// restore user IDs before entry template is rendered
			add_action( 'bp_before_activity_entry', array( $this, 'remove_user_id_hacks' ) );
		}

		// filter the activity loop with our custom arguments
		$filter = create_function( '', "
			return 'page=1&per_page=5&user_id=0&object=notifications&show_hidden=true&primary_id=' . $loggedin_user_id;
		" );

		add_filter( 'bp_ajax_querystring', $filter );

		// make sure activity permalinks always use the primary link
		add_filter( 'bp_activity_get_permalink', array( $this, 'activity_permalink' ), 10, 2 );

		// change some strings to reflect notifications instead of activity
		add_filter( 'gettext', array( $this, 'notifications_gettext' ), 10, 3 );

		echo '<div class="ab-sub-wrapper">';
		echo '<div class="activity">';

		// get object-buffered activity loop contents
		$loop = bp_buffer_template_part( 'activity/activity-loop', null, false );

		// replace 'load more' hyperlink with our custom notifications link
		// @todo notification screen doesn't exist yet
		$loop = str_replace( '#more', bp_loggedin_user_domain(), $loop );

		echo $loop;
		echo '</div>';
		echo '</div>';
		exit();
	}

	/**
	 * Make sure activity permalinks always use the primary link.
	 */
	public function activity_permalink( $retval, $activity ) {
		return $activity->primary_link;
	}

	/**
	 * Change some strings when in the notification activity loop.
	 */
	public function notifications_gettext( $retval, $untranslated, $domain ) {
		if ( $domain != 'buddypress' ) {
			return $retval;
		}

		switch ( $untranslated ) {
			case 'Sorry, there was no activity found. Please try a different filter.' :
				return __( 'No new notifications', 'bp-an' );

				break;

			case 'Load More' :
				return __( 'See All Notifications', 'bp-an' );

				break;

			default :
				return $retval;

				break;
		}

	}

	/**
	 * Change some strings when in the notification activity loop.
	 */
	public function activity_can_delete( $retval, $activity ) {
		if ( $activity->component != 'notifications' )
			return $retval;

		if ( ! is_user_logged_in() )
			return $retval;

		if ( $activity->item_id == bp_loggedin_user_id() )
			return true;

		return $retval;
	}

	public function remove_user_id_hacks() {
		remove_filter( 'bp_displayed_user_id', array( $this, 'return_zero' ) );
		remove_filter( 'bp_loggedin_user_id',  array( $this, 'return_zero' ) );
	}

	public function return_zero() {
		return 0;
	}
}

/**
 * Load Activity Notifications into the buddypress() singleton.
 */
function bp_an_loader() {
	if ( bp_is_active( 'activity' ) ) {
		buddypress()->activity->notifications = new BP_Activity_Notifications;
	}
}
add_action( 'bp_loaded', 'bp_an_loader' );
