media2post
Contributors: Jondor
Tags: post,media, menu, batch, phototools
Requires at least: 3.0.1
Tested up to: 5.2
Requires PHP: 5.6
Donate link: https://gerhardhoogterp.nl/plugins/
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Quickly create a post with the media item as featured image. Single or in
batch. Part of the phototools plugins.

== Description ==
This plugin handles a number of things:

* An extra "create post" option in the row menu for mediaitems in the listview
* An new "create post featured image" bulk option
* add some code to make sure that when you delete a post, the image is cleared properly (post_parent = 0) so it shows up in the "unattached" filter
* add MediaRSS to your feed. 

By default it create "private" posts as I needed this plugin to deal with older photos. As I use the image postdate as 
default for the postdate, private leaves this date alone which publish and draft->publish by default moves the postdate 
the date of publishing. Private is also easy to find in the "posts" window.
They also get an "media2post" tag so they are easy to find. 
De owner of the post equals the owner of the image.     

Special thanks go to mr. Jeremy Felt who wrote "Automatic Featured Image Posts" and who's code heavily
"influenced" this plugin. I learned a lot from his code! 
(https://jeremyfelt.com/wordpress/plugins/automatic-featured-image-posts/)

    
== Installation ==

1. Upload `plugin-name.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. go to the media list, filter on "unattached" and check out the menu and the bulk pulldown. 

== Frequently Asked Questions ==

= Why did you write this widget? =
I run a photo site and after a number of merges and changes to the site I got stuck with a few hundered unattached 
images. As I didn't feel like dealing with this by hand I wrote this image.

== Screenshots ==

1. "create" in the media row menu
2. "Create post with featured image" in the bulk menu
3. Settings screen
4. MediaRSS in feed

== Changelog ==

= 1.0 =
First release

== Upgrade Notice ==

Nothing yet.
