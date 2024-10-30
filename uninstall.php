<?php
/* Uninstall file for the media2post.
 *
 * 1 option is added for media2post
 *
 * media2post_options controlled the post status and post type
 *
 * Both are deleted when the plugin is uninstalled.
*/
if( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

delete_option( 'media2post_options' );
