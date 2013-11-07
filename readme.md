BP Activity Notitifications
===========================

Experimental BuddyPress plugin using the activity component to record notifications.

Requires 1.9-bleeding r7522 or greater.

Proof-of-concept, alpha and totally unsupported.  Subject to be removed at any time.


#### How to use
* Grab the latest 1.9-bleeding version (at least r7522 or higher)
* Activate this plugin
* At-mention yourself, PM another user or send /receive a friend request.
* Click on the "!" mark in the WP Toolbar.  The notification will show up using AJAX.  The timestamp links to the relevant content.


#### What isn't implemented
* Screen to show all notifications for the logged-in user (not hard to implement)
* Mark as read functionality needs to be completed
* Toolbar AJAX UI needs refining
* Format activity notifications for the groups component (ran out of time)
* Removing notification activity items on deactivation or through an uninstall method so these activity items won't show up when this plugin is deactivated.
* Finish abstraction functions.


#### Notes
* This plugin brute-force disables the new notifications component from loading and uses the activity component in its place to record and display notifications.
* Still requires reformatting the activity action, user ID and primary link for custom plugins hooked into the existing notifications component. (Not great!)
* I moved the notification's 'is_new' value to the activity component's 'secondary_item_id', so the notification's 'secondary_item_id' needs to move to activity meta or vice versa ('is_new' moves into activity meta).  I think this is okay and not really pertinent since the only time this information is important is during activity action generation.