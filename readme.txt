=== Plugin Name ===
Contributors: anty
Donate link: http://www.anty.info/blog-demographics
Tags: demographics, visitors, age, gender, mybloglog, blogcatalog, facebook
Requires at least: 2.8
Tested up to: 3.0.1
Stable tag: 0.4

Shows you what age and gender your visitors are. Based on various services like Facebook, BlogCatalog and MyBlogLog.

== Description ==

Blog Demographics uses 3rd-party-services (Facebook, MyBlogLog and BlogCatalog) to access the identity of your viewers and commentators.
In order to work at all, you need at least one account at such a service.

Blog Demographics retrieves a list of recent visitors from those accounts. If the visitor didn't specify an age or gender Blog Demographics tries to access associated accounts like Facebook or YouTube to get those informations. Facebook is also used to retrieve gender and age of your commentators.

== Installation ==

1. Upload the "blog-demographics"-folder to the "/wp-content/plugins/" directory
1. Make sure the cookies-folder is writable
1. Activate the plugin through the "Plugins" menu in WordPress
1. Register on MyBlogLog and/or BlogCatalog and add your blog to your sites, if you haven't yet
1. Fill in some information on the "Settings/Demographics" page
1. Go to the "Dashboard/Demographics" page and let Blog Demographics gather data. The page will have a long loading time the first time you access it!

== Frequently Asked Questions ==

= I don't see any data =

Two possible reasons:
1. You don't have any visitors using one of the 3rd-party-services. Or Blog Demographics couldn't find the gender and age for them. Please log in those services and check if you've had any visitors. If you have, recheck if you've entered the right data in the settings-page!
1. Your theme might not call wp_footer(). To solve this add <?php wp_footer(); ?> just before </body> if it's not already there.

= The number of male and female visitors don't add up in the age groups =

Not everyone who specifies their age specifies their gender, too.

= Why are all visitors using Facebook/MyBlogLog/BlogCatalog? =

They aren't all using this service. The scale of the service-chart is stretched to make it easier to see the services that aren't used as much.
Look at the bottom of the chart to see the scale.

== Screenshots ==

1. The demographics
2. The settings-page

== Changelog ==

= 0.4 =
* Added possibility to see age groups by gender
* Added services-chart, selectable by gender

= 0.3 =
* Added check of commentators on Facebook
* Added cookie-file creation and permission checking
* Fixed problem getting data from Facebook
* Changed settings-page layout
* Changed cURL usage to class-http.php usage. Still requires cURL for cookie handling, though

= 0.2 =
* Added full BlogCatalog support
* Added AppId-validation for MyBlogLog
* Fixed never-ending loading of the demographics-page
* Changed Snoopy-dependency to http.php-dependency

= 0.1 =
* First public version.

== Upgrade Notice ==

= 0.3 =
Plug-In should update out of the box. If it doesn't then deactivate and activate the plug-in again.
Warning: This will force you to wait again until all your visitors have been checked out!

= 0.2 =
Plug-In should update out of the box. If it doesn't then deactivate and activate the plug-in again.
Warning: This will force you to wait again until all your visitors have been checked out!

= 0.1 =
Initial release

== Credits ==
Plug-In and German translation by anty from http://www.anty.info
Farsi/Persian translation by Heam from http://www.hamidoffice.com
