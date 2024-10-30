<?php
/**
Plugin Name: Blog Demographics
Plugin URI: http://www.anty.info/blog-demographics
Description: Shows you what age and gender your visitors are. Based on various services like Facebook, BlogCatalog and MyBlogLog.
Author: anty
Version: 0.4
Author URI: http://www.anty.info
*/
class BlogDemographics {
	/**
	 * Version number of this plug-in
	 * @var float
	 */
	var $version = 0.4;

	/**
	 * Defines the maximum number of viewers the query for MyBlogLog viewers should return. (max. 100)
	 * @var int
	 */
	var $mblViewerCount = 100;

	/**
	 * Last.Fm public API key (urlencoded)
	 * @var string
	 */
	var $lastFmPublicApiKey = '865dc78d8a127bd9f4506b398c19eda4';

	/**
	 * Identifies the service from an URL and extracts the corresponding username
	 * @var array
	 */
	var $serviceExtractor = array('technorati'	=> '/http:\/\/technorati\.com\/people\/technorati\/(.+)/s',
								  'digg'		=> '/http:\/\/www\.digg\.com\/users\/(.+)/s',
								  'facebook'	=> '/http:\/\/www\.facebook\.com\/profile\.php?id=(\d+)/s',
								  'myspace'		=> '/http:\/\/www\.myspace\.com\/(.+)/s',
								  'twitter'		=> '/http:\/\/twitter\.com\/(.+)/s',
								  'aim'			=> '/aim:goim?screenname=(.+)/s',
								  'delicious'	=> '/http:\/\/del\.icio\.us\/(.+)/s',
								  'stumbleupon'	=> '/http:\/\/([^\.]+)\.stumbleupon\.com\//s',
								  'youtube'		=> '/http:\/\/www\.youtube\.com\/(.+)/s',
								  'last.fm'		=> '/http:\/\/www\.last\.fm\/user\/(.+)/s',
								  'mybloglog'	=> '/http:\/\/www\.mybloglog\.com\/buzz\/members\/(.+)/s',
								  'flickr'		=> '/http:\/\/www\.flickr\.com\/photos\/(.+)/s',
							);

	/**
	 * User-Agent for Facebook Mobile requests
	 * @var string
	 */
	var $fbUserAgent = 'Opera/9.80 (S60; SymbOS; Opera Mobi/498; U; en-GB) Presto/2.4.18 Version/10.00';

	const fbCookieLocation = 'facebook.txt';

	/**
	 * Database table names without the Wordpress prefix.
	 * @var string
	 */
	var $_visitorTableWithoutWpPrefix = 'bdemo_visitor';
	var $_serviceTableWithoutWpPrefix = 'bdemo_service';
	var $_emailTableWithoutWpPrefix = 'bdemo_email';
	var $_visitorHasEmailTableWithoutWpPrefix = 'bdemo_v_has_e';

	function getVisitorTableName() {
		global $table_prefix;

		return $table_prefix . $this->_visitorTableWithoutWpPrefix;
	}

	function getServiceTableName() {
		global $table_prefix;

		return $table_prefix . $this->_serviceTableWithoutWpPrefix;
	}

	function getEmailTableName() {
		global $table_prefix;

		return $table_prefix . $this->_emailTableWithoutWpPrefix;
	}

	function getVisitorHasEmailTableName() {
		global $table_prefix;

		return $table_prefix . $this->_visitorHasEmailTableWithoutWpPrefix;
	}

	function getFacebookCookieLocation() {
		return WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)) .'cookies/'. BlogDemographics::fbCookieLocation;
	}

	/**
	 * Is called when the plugin is activated
	 *
	 * Creates database-tables for caching
	 */
	function activatePlugin() {
		// add tables if they do not exist already
		$blogDemographics = new BlogDemographics;

		$visitorTable = $blogDemographics->getVisitorTableName();
		$serviceTable = $blogDemographics->getServiceTableName();
		$emailTable = $blogDemographics->getEmailTableName();
		$visitorHasEmailTable = $blogDemographics->getVisitorHasEmailTableName();

		$query = 'CREATE TABLE `'. $visitorTable .'` ('
				.'`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
				.'`mblid` BIGINT(16) UNSIGNED NULL UNIQUE,'
				.'`bcname` VARCHAR(35) NULL UNIQUE,'
				.'`gender` ENUM("male","female") NULL,'
				.'`age` INT(3) UNSIGNED NULL,'
				.'PRIMARY KEY (`id`)'
				.') COLLATE utf8_general_ci;';

		require_once(ABSPATH .'wp-admin/install-helper.php');

		if (maybe_create_table($visitorTable, $query)) {
			$query = 'CREATE TABLE `'. $serviceTable .'` ('
					.'`visitor` INT UNSIGNED NOT NULL,'
					.'`name` VARCHAR(100) NOT NULL,'
					.'`id` VARCHAR(100) NOT NULL,'
					.'PRIMARY KEY (`name`, `id`),'
					.'FOREIGN KEY (`visitor`) REFERENCES `'. $visitorTable .'`(`id`)'
					.') COLLATE utf8_general_ci;';

			maybe_create_table($serviceTable, $query);

			$query = 'CREATE TABLE `'. $emailTable .'` ('
					.'`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,'
					.'`address` VARCHAR(100) NOT NULL UNIQUE,'
					.'PRIMARY KEY (`id`)'
					.') COLLATE utf8_general_ci;';
			if (maybe_create_table($emailTable, $query)) {
				$query = 'CREATE TABLE `'. $visitorHasEmailTable .'` ('
						.'`visitor` INT UNSIGNED NOT NULL,'
						.'`email` INT UNSIGNED NOT NULL,'
						.'PRIMARY KEY (`email`),'
						.'FOREIGN KEY (`visitor`) REFERENCES `'. $visitorTable .'`(`id`),'
						.'FOREIGN KEY (`email`) REFERENCES `'. $emailTable .'`(`id`)'
						.') COLLATE utf8_general_ci;';
				maybe_create_table($visitorHasEmailTable, $query);
			}
		}
	}

	/**
	 * Is called when the plugin is deactivated
	 *
	 * Removes database-tables created by this plugin
	 */
	function deactivatePlugin() {
		// remove tables
		global $wpdb;

		$blogDemographics = new BlogDemographics;

		$visitorTable = $blogDemographics->getVisitorTableName();
		$serviceTable = $blogDemographics->getServiceTableName();
		$emailTable = $blogDemographics->getEmailTableName();
		$visitorHasEmailTable = $blogDemographics->getVisitorHasEmailTableName();

		$wpdb->query('DROP TABLE IF EXISTS `'. $serviceTable .'`, `'. $visitorTable .'`, `'. $visitorHasEmailTable .'`, `'. $emailTable .'`;');
	}

	/**
	 * Is called after the plugin has been upgraded
	 */
	function upgradePlugin() {
		global $wpdb;

		$options = get_option('blog-demographics');

		$blogDemographics = new BlogDemographics;

		$visitorTable = $blogDemographics->getVisitorTableName();
		$serviceTable = $blogDemographics->getServiceTableName();
		$emailTable = $blogDemographics->getEmailTableName();
		$visitorHasEmailTable = $blogDemographics->getVisitorHasEmailTableName();

		if (!isset($options['db-version'])) {
			// pre 0.2: visitor-table: changed bcid to bcname and the datatype from INT UNSIGNED to VARCHAR(35), COLLATE added to utf8_general_ci

			$sql = 'CREATE TABLE '. $visitorTable .' (
					id INT UNSIGNED NOT NULL AUTO_INCREMENT,
					mblid BIGINT(16) UNSIGNED NULL UNIQUE,
					bcname VARCHAR(35) NULL UNIQUE,
					gender ENUM("male","female") NULL,
					age INT(3) UNSIGNED NULL,
					PRIMARY KEY  (id)
					) COLLATE utf8_general_ci;';

			require_once(ABSPATH .'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		if (isset($options['db-version']) && $options['db-version'] < 0.3) {
			// pre 0.3: added email facebook lookup cache and added Facebook Id to visitor table

			$sql = 'CREATE TABLE '. $emailTable .' (
					id INT UNSIGNED NOT NULL AUTO_INCREMENT,
					address VARCHAR(100) NOT NULL UNIQUE,
					PRIMARY KEY (id)
					) COLLATE utf8_general_ci;';

			require_once(ABSPATH .'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			$sql = 'CREATE TABLE '. $visitorHasEmailTable .' (
					visitor INT UNSIGNED NOT NULL,
					email INT UNSIGNED NOT NULL,
					PRIMARY KEY (visitor, email),
					FOREIGN KEY (visitor) REFERENCES '. $visitorTable .'(id),
					FOREIGN KEY (email) REFERENCES '. $emailTable .'(id)
					) COLLATE utf8_general_ci;';
			dbDelta($sql);
		}

		// update db-version number
		$options['db-version'] = $blogDemographics->version;
		update_option('blog-demographics', $options);
	}

	/**
	 * Specifies which settings the plugin needs to store
	 */
	function settingsInit() {
		// Facebook
		add_settings_section('blog-demographics-facebook-section', __('Facebook Settings', 'blog-demographics'), array('BlogDemographics', 'facebookSectionCallback'), __FILE__);

		add_settings_field('fb-email', '<label for="fb-email">'. __('Facebook Email or Telephone:', 'blog-demographics') .'</label>', array('BlogDemographics', 'fbEmailCallback'), __FILE__, 'blog-demographics-facebook-section');
		add_settings_field('fb-pass', '<label for="fb-pass">'. __('Facebook Password:', 'blog-demographics') .'</label>', array('BlogDemographics', 'fbPassCallback'), __FILE__, 'blog-demographics-facebook-section');

		// MyBlogLog
		add_settings_section('blog-demographics-mbl-section', __('MyBlogLog Settings', 'blog-demographics'), array('BlogDemographics', 'mblSectionCallback'), __FILE__);

		add_settings_field('mbl-community-id', '<label for="mbl-community-id">'. __('MyBlogLog-Community-Id:', 'blog-demographics') .'</label>', array('BlogDemographics', 'mblCommunityIdCallback'), __FILE__, 'blog-demographics-mbl-section');
		add_settings_field('mbl-app-id', '<label for="mbl-app-id">'. __('MyBlogLog AppId:', 'blog-demographics') .'</label>', array('BlogDemographics', 'mblAppIdCallback'), __FILE__, 'blog-demographics-mbl-section');

		// BlogCatalog
		add_settings_section('blog-demographics-bc-section', __('BlogCatalog Settings', 'blog-demographics'), array('BlogDemographics', 'bcSectionCallback'), __FILE__);

		add_settings_field('bc-api-key', '<label for="bc-api-key">'. __('BlogCatalog API key:', 'blog-demographics') .'</label>', array('BlogDemographics', 'bcApiKeyCallback'), __FILE__, 'blog-demographics-bc-section');

		register_setting('blog-demographics', 'blog-demographics', array('BlogDemographics', 'validateInput'));

		// register styles
		wp_register_style('jquery-ui-css-framework', WP_PLUGIN_URL . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)) .'css/ui-lightness/jquery-ui-1.8.4.custom.css', false, '1.8.4');

		// register scripts
		wp_register_script('jquery-ui-widget-progressbar', WP_PLUGIN_URL . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), "", plugin_basename(__FILE__)) .'js/jquery-ui-1.8.4.custom.min.js', array('jquery-ui-core'), '1.8.4');
	}

	/**
	 * Validates the input of the admin-menu options
	 *
	 * @param array $input admin-menu options
	 * @return array validated admin-menu options
	 */
	function validateInput($input) {
		foreach ($input as $i => $value) {
			$input[$i] = trim($value);
		}

		return $input;
	}

	/**
	 * Echoes content that appears before the input form
	 */
	function mblSectionCallback() {
		echo '<p>'. __('MyBlogLog tracks the identity of your visitors.', 'blog-demographics') .'</p>';
	}

	function bcSectionCallback() {
		echo '<p>'. __('BlogCatalog tracks the identity of your visitors like MyBlogLog.', 'blog-demographics') .'</p>';
	}

	/**
	 * Echoes content that appears before the facebook input form
	 */
	function facebookSectionCallback() {
		echo '<p>';
		if (!function_exists('curl_init')) {
			echo '<span style="color:red;font-weight:bold">'. __('You need to enable the cURL-module on your server to use Facebook!', 'blog-demographics') .'</span> ';
		}

		include_once(ABSPATH .'wp-admin/includes/class-wp-filesystem-base.php');
		include_once(ABSPATH .'wp-admin/includes/class-wp-filesystem-direct.php');
		$fsDirect = new WP_Filesystem_Direct('');

		if (!$fsDirect->exists(BlogDemographics::getFacebookCookieLocation())) {
			// file doesn't exist, try to create it
			$fsDirect->put_contents(BlogDemographics::getFacebookCookieLocation(), '', 0600);
		}

		if (!$fsDirect->is_writable(BlogDemographics::getFacebookCookieLocation()) || !$fsDirect->is_readable(BlogDemographics::getFacebookCookieLocation())) {
			echo '<span style="color:red;font-weight:bold">'. sprintf(__('You need to make the file %s read- and writable to use Facebook!', 'blog-demographics'), htmlentities(BlogDemographics::getFacebookCookieLocation())) .'</span> ';
		}
		_e('Facebook is used to access data that is only visible to logged-in users.', 'blog-demographics');
		echo '</p>';
	}

	/**
	 * Echoes the input-form for the MyBlogLog Community Id
	 */
	function mblCommunityIdCallback() {
		$options = get_option('blog-demographics');
		echo '<input id="mbl-community-id" name="blog-demographics[mbl-community-id]" type="text" value="'. $options['mbl-community-id'] .'" /> '. sprintf(__('Log into MyBlogLog, go to the settings-page of your site and copy the numbers from the "Link Tracking Code". It looks like this: %s', 'blog-demographics'), '<code>&lt;script type=\'text/javascript\' src=\'http://track3.mybloglog.com/js/jsserv.php?mblID=<strong>xxxxxxxxxxxxxxxx</strong>\'&gt;&lt;/script&gt;</code>');
	}

	/**
	 * Echoes the input-form for the MyBlogLog App Id
	 */
	function mblAppIdCallback() {
		$options = get_option('blog-demographics');

		if (isset($options['mbl-app-id']) && !empty($options['mbl-app-id'])) {
			$pluginUrl = WP_PLUGIN_URL . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));

			$blogDemographics = new BlogDemographics;
			if ($blogDemographics->validateMblAppId($options['mbl-app-id'])) {
				echo '<input id="mbl-app-id" name="blog-demographics[mbl-app-id]" type="text" value="'. $options['mbl-app-id'] .'" /> <img src="'. $pluginUrl .'images/accept.png" alt="'. __('Valid App Id', 'blog-demographics') .'"> <a href="https://developer.apps.yahoo.com/wsregapp/?view" target="_blank">'. __('View my app ids', 'blog-demographics') .'</a> '. __('or', 'blog-demographics') .' <a href="https://developer.apps.yahoo.com/wsregapp/" target="_blank">'. __('Apply for a "Generic, No user authenticaton required"-key here!', 'blog-demographics') .'</a>';
			} else {
				echo '<input style="border:2px solid #f00" id="mbl-app-id" name="blog-demographics[mbl-app-id]" type="text" value="'. $options['mbl-app-id'] .'" /> <img src="'. $pluginUrl .'images/exclamation.png" alt="'. __('Invalid App Id', 'blog-demographics') .'"> <a href="https://developer.apps.yahoo.com/wsregapp/?view" target="_blank">'. __('View my app ids', 'blog-demographics') .'</a> '. __('or', 'blog-demographics') .' <a href="https://developer.apps.yahoo.com/wsregapp/" target="_blank">'. __('Apply for a "Generic, No user authenticaton required"-key here!', 'blog-demographics') .'</a>';
			}
		} else {
			echo '<input id="mbl-app-id" name="blog-demographics[mbl-app-id]" type="text" value="'. $options['mbl-app-id'] .'" /> '. __('Tip: Put random letters in here, if you don\'t have an App Id. This seems to work.', 'blog-demographics') .' <a href="https://developer.apps.yahoo.com/wsregapp/?view" target="_blank">'. __('View my app ids', 'blog-demographics') .'</a> '. __('or', 'blog-demographics') .' <a href="https://developer.apps.yahoo.com/wsregapp/" target="_blank">'. __('Apply for a "Generic, No user authenticaton required"-key here!', 'blog-demographics') .'</a>';
		}
	}

	/**
	 * Returns true if the MyBlogLog app id is valid or false if it's not or the API didn't respond as expected
	 *
	 * @param boolean $appId Returns true if the app id is valid or false otherwise
	 */
	function validateMblAppId($appId) {
		include_once(ABSPATH .'wp-includes/http.php');
		$response = wp_remote_get('http://mybloglog.yahooapis.com/v1/test/echo?appid='. urlencode($appId) .'&format=xml');

		if(!is_wp_error($response)) {
			$blogInfo = wp_remote_retrieve_body($response);

			preg_match_all('/<yahoo:(error)\s/s', utf8_decode($blogInfo), $blogArray, PREG_PATTERN_ORDER);
			if (!isset($blogArray[1][0])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Echoes the input-form for the Facebook Email or Telephone
	 */
	function fbEmailCallback() {
		$options = get_option('blog-demographics');

		if (function_exists('curl_init')) {
			echo '<input id="fb-email" name="blog-demographics[fb-email]" type="text" value="'. $options['fb-email'] .'" />';
		} else {
			echo '<input disabled="disabled" id="fb-email" name="blog-demographics[fb-email]" type="text" value="'. $options['fb-email'] .'" />';
		}
	}

	/**
	 * Echoes the input-form for the Facebook Password
	 */
	function fbPassCallback() {
		$options = get_option('blog-demographics');

		if (function_exists('curl_init')) {
			echo '<input id="fb-pass" name="blog-demographics[fb-pass]" type="password" value="'. $options['fb-pass'] .'" />';
		} else {
			echo '<input disabled="disabled" id="fb-pass" name="blog-demographics[fb-pass]" type="password" value="'. $options['fb-pass'] .'" />';
		}
	}

	/**
	 * Echoes the input-form for the BlogCatalog Community Id
	 */
	function bcCommunityIdCallback() {
		$options = get_option('blog-demographics');
		echo '<input id="bc-community-id" name="blog-demographics[bc-community-id]" type="text" value="'. $options['bc-community-id'] .'" />';
	}

	/**
	 * Echoes the input-form for the BlogCatalog API key
	 */
	function bcApiKeyCallback() {
		$options = get_option('blog-demographics');

		$pluginUrl = WP_PLUGIN_URL . DIRECTORY_SEPARATOR . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));

		if (isset($options['bc-api-key']) && !empty($options['bc-api-key'])) {
			// check if the key is valid
			$blogDemographics = new BlogDemographics;
			if ($blogDemographics->validateBcApiKey($options['bc-api-key'])) {
				$bcCommunityId = $blogDemographics->getBcCommunityId();

				if ($bcCommunityId != 0) {
					$options['bc-community-id'] = $bcCommunityId;
					update_option('blog-demographics', $options);

					echo '<input id="bc-api-key" name="blog-demographics[bc-api-key]" type="text" value="'. $options['bc-api-key'] .'" /> <img src="'. $pluginUrl .'images/accept.png" alt="'. __('Valid key', 'blog-demographics') .'" /> <a href="http://www.blogcatalog.com/api/" target="_blank">'. __('View my API key', 'blog-demographics') .'</a>';
				} else {
					echo '<input id="bc-api-key" name="blog-demographics[bc-api-key]" type="text" value="'. $options['bc-api-key'] .'" /> <img src="'. $pluginUrl .'images/exclamation.png" alt="'. __('Error', 'blog-demographics') .'" /> <span style="font-weight:bold;color:#f00">'. __("API key is valid, but couldn't find community for this blog. Do you have one yet?", 'blog-demographics') .'</span> <a href="http://www.blogcatalog.com/api/" target="_blank">'. __('View my API key', 'blog-demographics') .'</a>';
				}
			} else {
				echo '<input style="border: 2px solid #f00" id="bc-api-key" name="blog-demographics[bc-api-key]" type="text" value="'. $options['bc-api-key'] .'" /> <img src="'. $pluginUrl .'images/exclamation.png" alt="'. __('Invalid key', 'blog-demographics') .'" /> <a href="http://www.blogcatalog.com/api/" target="_blank">'. __('View my API key', 'blog-demographics') .'</a>';
			}
		} else {
			echo '<input id="bc-api-key" name="blog-demographics[bc-api-key]" type="text" value="'. $options['bc-api-key'] .'" /> <a href="http://www.blogcatalog.com/api/" target="_blank">'. __('View my API key', 'blog-demographics') .'</a>';
		}
	}

	/**
	 * Returns true if the BlogCatalog API key is valid or false if it's not or the API didn't respond as expected
	 *
	 * @param boolean $apiKey Returns true if the app id is valid or false otherwise
	 */
	function validateBcApiKey($apiKey) {
		include_once(ABSPATH .'wp-includes/http.php');

		$response = wp_remote_get('http://api.blogcatalog.com/bloginfo?bcwsid='. urlencode($apiKey) .'&url='. urlencode(get_bloginfo('url')));
		if (!is_wp_error($response)) {
			$blogInfo = wp_remote_retrieve_body($response);

			preg_match_all('/(<error\scode="2)/s', utf8_decode($blogInfo), $blogArray, PREG_PATTERN_ORDER);
			if (!isset($blogArray[1][0]) || empty($blogArray[1][0])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns 0 or the community id of this blog
	 * Requires a valid BlogCatalog API key, will return false if it isn't valid or available
	 *
	 * @return mixed Returns 0 if an error occurred or returns the id for this blog
	 */
	function getBcCommunityId() {
		$id = 0;

		$options = get_option('blog-demographics');

		if (isset($options['bc-api-key']) && !empty($options['bc-api-key'])) {
			include_once(ABSPATH .'wp-includes/http.php');

			$response = wp_remote_get('http://api.blogcatalog.com/bloginfo?bcwsid='. urlencode($options['bc-api-key']) .'&url='. urlencode(get_bloginfo('url')));
			if(!is_wp_error($response)) {
				$blogInfo = wp_remote_retrieve_body($response);

				preg_match_all('/<weblog\s+id="(\d+)/s', utf8_decode($blogInfo), $blogArray, PREG_PATTERN_ORDER);
				if (isset($blogArray[1][0]) && !empty($blogArray[1][0])) {
					$id = $blogArray[1][0];
				}
			}
		}

		return $id;
	}

	/**
	 * Adds menu items to the admin-menu
	 */
	function pluginMenu() {
		add_options_page(__('Settings') .' &rsaquo; '. __('Demographics', 'blog-demographics'), __('Demographics', 'blog-demographics'), 'manage_options', __FILE__, array('BlogDemographics', 'pluginOptions'));
		$page = add_submenu_page('index.php', __('Blog Demographics', 'blog-demographics'), __('Demographics', 'blog-demographics'), 'publish_posts', __FILE__ .'/view', array('BlogDemographics', 'pluginView'));

		add_action('admin_print_scripts-'. $page, array('BlogDemographics', 'addAdminJS'));
		add_action('admin_print_styles-'. $page, array('BlogDemographics', 'addAdminCSS'));
	}

	/**
	 * Echoes the configuration page for the plugin
	 */
	function pluginOptions() {
		if (!current_user_can('manage_options'))  {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br></div>
			<h2><?php _e('Settings'); echo(' &rsaquo; '); _e('Demographics', 'blog-demographics'); ?></h2>
			<form action="options.php" method="post">
				<p><?php _e('You need to add Facebook, MyBlogLog or BlogCatalog to get demographics! Add all services to get the most out of this plug-in.', 'blog-demographics'); ?></p>
				<?php settings_fields('blog-demographics'); ?>
				<?php do_settings_sections(__FILE__); ?>
				<p class="submit">
					<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
				</p>
			</form>
		</div>
<?php
	}

	/**
	 * Extracts important data from MyBlogLog visitors
	 *
	 * @param $serviceProgressId int Id of the service for the progress-bar
	 * @return array Returns an array with age and gender of visitors (can be non-existent)
	 */
	function getRecentMblViewers($serviceProgressId) {
		$viewers = array();

		$options = get_option('blog-demographics');
		if ($options === false || !isset($options['mbl-community-id']) || empty($options['mbl-community-id']) || !isset($options['mbl-app-id']) || empty($options['mbl-app-id'])) {
			// display warning-message (API-keys missing)
?>
<div id="blog-demographics-missing-mbl-data-message" class="error">
	<p><strong><?php _e('Please add MyBlogLog-settings to get more accurate data!', 'blog-demographics'); ?></strong> <a href="<?php echo('options-general.php?page='. plugin_basename(__FILE__)); ?>"><?php _e('Click here to add those settings now!', 'blog-demographics'); ?></a></p>
</div>
<?php
			return $viewers;
		}

		include_once(ABSPATH .'wp-includes/http.php');
		$response = wp_remote_get('http://mybloglog.yahooapis.com/v1/community/'. $options['mbl-community-id'] .'/readers?appid='. $options['mbl-app-id'] .'&format=xml&count='. $this->mblViewerCount);
		if(!is_wp_error($response)) {
			$mblViewers = wp_remote_retrieve_body($response);

			preg_match_all('/<user(?:\s[^>]*)?>\s*<id(?:\s[^>]*)?>(\d+)</s', utf8_decode($mblViewers), $viewersArray, PREG_PATTERN_ORDER);
			preg_match_all('/<user(?:\s[^>]*)?>.*?<url(?:\s[^>]*)?>([^<]+)</s', utf8_decode($mblViewers), $viewersUrlArray, PREG_PATTERN_ORDER);

			if (count($viewersArray[1]) > 0) {
				$viewerWeight = 100 / count($viewersArray[1]);
			} else {
				$viewerWeight = 0;
			}

			foreach ($viewersArray[1] as $viewerCounter => $viewerId) {
				// lookup cache
				$cachedViewer = $this->getCachedMblViewer($viewerId);

				// lookup viewer id and extract data
				if ($cachedViewer == false) {
					$response = wp_remote_get('http://mybloglog.yahooapis.com/v1/user/'. $viewerId .'?appid='. $options['mbl-app-id'] .'&format=xml');
					if (!is_wp_error($response)) {
						$viewerProfile = wp_remote_retrieve_body($response);

						$viewer = array('mblid'		=> $viewerId,
										'services'	=> array(),
								  );
						$missingData = array();

						preg_match_all('/<age(?:\s[^>]*)?>(\d+)</s', utf8_decode($viewerProfile), $profileArray, PREG_PATTERN_ORDER);
						if (empty($profileArray[1][0])) {
							$missingData[] = 'age';
						} else {
							$viewer['age'] = $profileArray[1][0];
						}

						preg_match_all('/<sex(?:\s[^>]*)?>(\w+)</s', utf8_decode($viewerProfile), $profileArray, PREG_PATTERN_ORDER);
						if (empty($profileArray[1][0])) {
							$missingData[] = 'sex';
						} else {
							$viewer['sex'] = $profileArray[1][0];
						}

						// grab the services-part for further analysis
						preg_match_all('/<services(?:\s[^>]*)?>(.*)<\/services>/s', utf8_decode($viewerProfile), $servicesArray, PREG_PATTERN_ORDER);
						if (!empty($servicesArray[1][0])) {
							// extract a list of services and ids
							$xmlServices = $servicesArray[1][0];

							preg_match_all('/<service(?:\s[^>]*)?>\s*<name(?:\s[^>]*)?>([^<]+)<\/name>\s*<id>([^<]+)</s', utf8_decode($xmlServices), $serviceArray, PREG_PATTERN_ORDER);
							if (count($serviceArray[1]) > 0) {
								// add services
								$serviceNames = $serviceArray[1];
								$serviceIds = $serviceArray[2];
								foreach ($serviceNames as $serviceCounter => $serviceName) {
									$viewer['services'][$serviceName] = $serviceIds[$serviceCounter];
								}
							}
						}

						// remove "Yahoo! updates" because this will always have id 1 if it's enabled and is therefor pretty useless
						unset($viewer['services']['yahoo! updates']);

						// there's a bug, that the age is not showing up in the API, but it's in the web-profile
						if (in_array('age', $missingData)) {
							// Call the web-profile
							$response = wp_remote_get($viewersUrlArray[1][$viewerCounter]);
							if(!is_wp_error($response)) {
								$viewerWebProfile = wp_remote_retrieve_body($response);

								// extract age and gender
								preg_match_all('/<div(?:\s[^>])?\sclass="bioi"[^>]*>\s*(\d+)/s', utf8_decode($viewerWebProfile), $profileArray, PREG_PATTERN_ORDER);
								if (!empty($profileArray[1][0])) {
									$viewer['age'] = $profileArray[1][0];
									unset($missingData[array_search('age', $missingData)]);
								}
							}
						}

						// if something is missing, search for services and ask them for data
						$viewer = $this->askServicesForData($viewer, $missingData);

						// cache viewer
						if ($this->cacheMblViewer($viewer) == 'updated') {
							// get fresh data
							$viewer = $this->getCachedMblViewer($viewer['mblid']);
						}

						// add viewer to viewer-list
						$viewers[] = $viewer;
					}
				} else if ($cachedViewer != false) {
					// add cached viewer to viewer-list
					$viewers[] = $cachedViewer;
				}

				// update progress
				$options['baseServiceProgress'] = unserialize($options['baseServiceProgress']);
				$options['baseServiceProgress'][$serviceProgressId] = ($viewerCounter + 1) * $viewerWeight;
				$options['baseServiceProgress'] = serialize($options['baseServiceProgress']);
				update_option('blog-demographics', $options);
			}
		}

		return $viewers;
    }

    /**
     * Retrieves MyBlogLog viewer data from the cache
     *
     * @param $id int The MyBlogLog user id
	 * @return array Returns an array of viewer data, or false if the id could not be found
     */
    function getCachedMblViewer($id) {
    	global $wpdb;

    	$visitorTable = $this->getVisitorTableName();
		$serviceTable = $this->getServiceTableName();

    	$query = 'SELECT *, `'. $visitorTable .'`.`id` AS visitorid, `'. $serviceTable .'`.`id` AS serviceid FROM `'. $visitorTable .'` LEFT JOIN `'. $serviceTable .'` ON `'. $visitorTable .'`.`id` = `'. $serviceTable .'`.`visitor` WHERE mblid = "'. $id .'"';

    	$viewerData = $wpdb->get_results($query);

    	if (count($viewerData) > 0) {
	    	$viewer = array('id'		=> $viewerData[0]->visitorid,
	    					'mblid'		=> $viewerData[0]->mblid,
	    					'bcname'	=> $viewerData[0]->bcname,
	    					'sex'		=> $viewerData[0]->gender,
	    					'age'		=> $viewerData[0]->age,
	    					'services'	=> array(),
	    			  );
	    	foreach ($viewerData as $data) {
				$viewer['services'][$data->name] = $data->serviceid;
	    	}

	    	return $viewer;
    	}

    	return false;
    }

    /**
     * Caches a MyBlogLog viewer
     *
     * MyBlogLog user-id is not allowed to be in the DB already!
     *
     * @param $data array An array of viewer-data
     * @return string Returns "inserted" if all went well, "updated" if the user existed and has been updated
     */
    function cacheMblViewer($data) {
    	global $wpdb;

    	$status = 'inserted';

		$visitorTable = $this->getVisitorTableName();
		$serviceTable = $this->getServiceTableName();

		$services = $data['services'];

		$values = array('mblid' => $data['mblid']);
		$format = array('%s'); // this needs to be a string, because it's a very long number and gets cut off with "%d".
		if (isset($data['sex'])) {
			$values['gender'] = $data['sex'];
			$format[] = '%s';
		}
		if (isset($data['age'])) {
			$values['age'] = $data['age'];
			$format[] = '%d';
		}

		// check if visitor is already in the database
		$visitorId = $wpdb->get_var($wpdb->prepare('SELECT id FROM `'. $visitorTable .'` WHERE mblid=%s;', $data['mblid']));
		if (is_null($visitorId)) {
			if ($wpdb->insert($visitorTable, $values, $format) !== false) {
				$visitorId = $wpdb->insert_id;
			}
		}

		// insert services
		$ignoreDuplicateServices = false;
		foreach ($services as $serviceName => $serviceId) {
			// check if service is in the DB already
			$serviceNameInDatabase = $wpdb->get_var($wpdb->prepare('SELECT name FROM `'. $serviceTable .'` WHERE name=%s AND id=%s;', $serviceName, $serviceId));
			if (is_null($serviceNameInDatabase)) {

				$values = array('visitor'	=> $visitorId,
								'name'		=> $serviceName,
								'id'		=> $serviceId,
						  );
				$format = array('%d', '%s', '%s');

				$wpdb->insert($serviceTable, $values, $format);
			} else if (!$ignoreDuplicateServices) {
				// visitor is already in the database, find entry and new data

				// get visitor id
				$realVisitorId = $wpdb->get_var($wpdb->prepare('SELECT visitor FROM `'. $serviceTable .'` WHERE name=%s AND id=%s;', $serviceNameInDatabase, $serviceId));

				if (!is_null($realVisitorId)) {
					// remove duplicate (new) entry
					$wpdb->query($wpdb->prepare('DELETE FROM `'. $visitorTable .'` WHERE id=%d;', $visitorId));

					// update with MyBlogLog id and other data we determined
					$wpdb->update($visitorTable, $values, array('id' => $realVisitorId), $format, array('%s'));

					$status = 'updated';
				}

				// continue to add services, but don't try to add viewer-data, again
				$ignoreDuplicateServices = true;
			}
		}

		return $status;
    }

    /**
     * Asks 3rd party services for additional missing data
     *
     * @param $viewer array The viewer array
     * @param $missingData array List of missing data
     * @return array The viewer array with additional data
     */
    function askServicesForData($viewer, $missingData) {
    	$askedServices = array();
		$newData = array();
		while (count($missingData) > 0) {
			// add additional data
			foreach ($missingData as $i => $data) {
				if (isset($newData[$data])) {
					$viewer[$data] = $newData[$data];
					unset($missingData[$i]);
				}
			}

			// facebook
			if (array_key_exists('facebook', $viewer['services']) && !in_array('facebook', $askedServices)) {
				$newData = $this->getFacebookInfos($viewer['services']['facebook']);

				$askedServices[] = 'facebook';
				continue;
			}

			// digg
			if (array_key_exists('digg', $viewer['services']) && !in_array('digg', $askedServices)) {
				$newData = $this->getDiggInfos($viewer['services']['digg']);

				$askedServices[] = 'digg';
				continue;
			}

			// youtube
			if (array_key_exists('youtube', $viewer['services']) && !in_array('youtube', $askedServices)) {
				$newData = $this->getYouTubeInfos($viewer['services']['youtube']);

				$askedServices[] = 'youtube';
				continue;
			}

			// last.fm
			if (array_key_exists('last.fm', $viewer['services']) && !in_array('last.fm', $askedServices)) {
				$newData = $this->getLastFmInfos($viewer['services']['last.fm']);

				$askedServices[] = 'last.fm';
				continue;
			}

			// if we still miss data, but have no services left to ask, break the loop
			break;
		}

		return $viewer;
    }

    /**
	 * Extracts important data from BlogCatalog visitors
	 *
	 * @param $serviceProgressId int Id of the service for the progress-bar
	 * @return array Returns an array with age and gender of visitors (can be non-existent)
	 */
	function getRecentBcViewers($serviceProgressId) {
		$viewers = array();

		$options = get_option('blog-demographics');
		if ($options === false || !isset($options['bc-api-key']) || empty($options['bc-api-key'])) {
			// display warning-message (API-keys missing)
?>
<div id="blog-demographics-missing-mbl-data-message" class="error">
	<p><strong><?php _e('Please add BlogCatalog-settings to get more accurate data!', 'blog-demographics'); ?></strong> <a href="<?php echo('options-general.php?page='. plugin_basename(__FILE__)); ?>"><?php _e('Click here to add those settings now!', 'blog-demographics'); ?></a></p>
</div>
<?php
			return $viewers;
		}

		include_once(ABSPATH .'wp-includes/http.php');

		$response = wp_remote_get('http://api.blogcatalog.com/bloginfo?bcwsid='. urlencode($options['bc-api-key']) .'&url='. urlencode(get_bloginfo('url')));
		if(!is_wp_error($response)) {
			$bcViewers = wp_remote_retrieve_body($response);

			preg_match_all('/<recent_viewers(?:\s[^>*])?>(.*)<\/recent_viewers>/s', utf8_decode($bcViewers), $recentViewersArray, PREG_PATTERN_ORDER);
			preg_match_all('/<user(?:\s[^>]*)?>([^<]+)/s', $recentViewersArray[1][0], $viewersArray, PREG_PATTERN_ORDER);

			if (count($viewersArray[1]) > 0) {
				$viewerWeight = 100 / count($viewersArray[1]);
			} else {
				$viewerWeight = 0;
			}

			foreach ($viewersArray[1] as $viewerCounter => $viewerUsername) {
				// lookup cache
				$cachedViewer = $this->getCachedBcViewer($viewerUsername);

				// lookup viewer id and extract data
				if ($cachedViewer == false) {
					// since there's no gender or age in the API, we call the profile on the website
					$response = wp_remote_get('http://www.blogcatalog.com/user/'. urlencode($viewerUsername));
					if (!is_wp_error($response)) {
						$viewerProfile = wp_remote_retrieve_body($response);

						$viewer = array('bcname'	=> $viewerUsername,
										'services'	=> array(),
								  );
						$missingData = array();

						preg_match_all('/<h2 class="username">.*?<em>.*?(\d+,)?\s(Male|Female)\s.*?<\/em>/s', utf8_decode($viewerProfile), $profileArray, PREG_PATTERN_ORDER);

						if (empty($profileArray[1][0])) {
							$missingData[] = 'age';
						} else {
							$viewer['age'] = $profileArray[1][0];
						}

						if (empty($profileArray[2][0])) {
							$missingData[] = 'sex';
						} else {
							$viewer['sex'] = strtolower($profileArray[2][0]);
						}

						// services
						preg_match_all('/<ul\s+class="services(?:\s[^"]*)?">(.*?)<\/ul>/s', utf8_decode($viewerProfile), $servicesListArray, PREG_PATTERN_ORDER);

						if (isset($servicesListArray[1][0]) && !empty($servicesListArray[1][0])) {
							// extract services
							preg_match_all('/\shref="([^"]+)/s',$servicesListArray[1][0], $servicesArray, PREG_PATTERN_ORDER);

							foreach ($servicesArray[0] as $serviceUrl) {
								foreach ($this->serviceExtractor as $serviceName => $serviceUrlPattern) {
									preg_match_all($serviceUrlPattern, $serviceUrl, $serviceArray, PREG_PATTERN_ORDER);

									if (isset($serviceArray[1][0]) && !empty($serviceArray[1][0])) {
										// special treatment for MyBlogLog
										if ($serviceName == 'mybloglog') {
											$viewer['mblid'] = $this->mblUsernameToId($serviceArray[1][0]);
										} else {
											$viewer['services'][$serviceName] = $serviceArray[1][0];
										}

										break;
									}
								}
							}

							$viewer = $this->askServicesForData($viewer, $missingData);
						}

						// cache viewer
						if ($this->cacheBcViewer($viewer) == 'updated') {
							// get fresh data
							$viewer = $this->getCachedBcViewer($viewer['bcname']);
						}

						// add viewer to viewer list
						$viewers[] = $viewer;
					}
				} else if ($cachedViewer != false) {
					// add cached viewer to viewer list
					$viewers[] = $cachedViewer;
				}

				// update progress
				$options['baseServiceProgress'] = unserialize($options['baseServiceProgress']);
				$options['baseServiceProgress'][$serviceProgressId] = ($viewerCounter + 1) * $viewerWeight;
				$options['baseServiceProgress'] = serialize($options['baseServiceProgress']);
				update_option('blog-demographics', $options);
			}
		}

		return $viewers;
	}

	/**
     * Retrieves BlogCatalog viewer data from the cache
     *
     * @param $name string The BlogCatalog username
	 * @return array Returns an array of viewer data, or false if the id could not be found
     */
    function getCachedBcViewer($name) {
    	global $wpdb;

    	$visitorTable = $this->getVisitorTableName();
		$serviceTable = $this->getServiceTableName();

    	$query = 'SELECT *, `'. $visitorTable .'`.`id` AS visitorid, `'. $serviceTable .'`.`id` AS serviceid FROM `'. $visitorTable .'` LEFT JOIN `'. $serviceTable .'` ON `'. $visitorTable .'`.`id` = `'. $serviceTable .'`.`visitor` WHERE bcname = "'. $name .'"';

    	$viewerData = $wpdb->get_results($query);

    	if (count($viewerData) > 0) {
	    	$viewer = array('id'		=> $viewerData[0]->visitorid,
	    					'mblid'		=> $viewerData[0]->mblid,
	    					'bcname'	=> $viewerData[0]->bcname,
	    					'sex'		=> $viewerData[0]->gender,
	    					'age'		=> $viewerData[0]->age,
	    					'services'	=> array(),
	    			  );
	    	foreach ($viewerData as $data) {
				$viewer['services'][$data->name] = $data->serviceid;
	    	}

	    	return $viewer;
    	}

    	return false;
    }

    /**
     * Caches a BlogCatalog viewer
     *
     * BlogCatalog username is not allowed to be in the DB already!
     *
     * @param $data array An array of viewer-data
     * @return string Returns "inserted" if all went well, "updated" if the user existed and has been updated
     */
    function cacheBcViewer($data) {
    	global $wpdb;

    	$status = 'inserted';

		$visitorTable = $this->getVisitorTableName();
		$serviceTable = $this->getServiceTableName();

		$services = $data['services'];

		$values = array('bcname' => $data['bcname']);
		$format = array('%s');

		if (isset($data['mblid']) && !is_null($data['mblid'])) {
			$values['mblid'] = $data['mblid'];
			$format[] = '%s'; // needs to be a string because it's a very long int and will get cut off if it's %d.
		}

		if (isset($data['sex'])) {
			$values['gender'] = $data['sex'];
			$format[] = '%s';
		}
		if (isset($data['age'])) {
			$values['age'] = $data['age'];
			$format[] = '%d';
		}

    	// check if BlogCatalog user is already in the database
		$visitorId = $wpdb->get_var($wpdb->prepare('SELECT id FROM `'. $visitorTable .'` WHERE bcname=%s', $data['bcname']));
		if (is_null($visitorId)) {
			// check if MyBlogLog user is already in the database
			$visitorId = $wpdb->get_var($wpdb->prepare('SELECT id FROM `'. $visitorTable .'` WHERE mblid=%s', $data['mblid']));
			if (is_null($visitorId)) {
				if ($wpdb->insert($visitorTable, $values, $format) !== false) {
					$visitorId = $wpdb->insert_id;
				}
			} else {
				$wpdb->update($visitorTable, $values, array('mblid' => $data['mblid']), $format, array('%s'));
			}
		}

		// insert services
		$ignoreDuplicateServices = false;

		foreach ($services as $serviceName => $serviceId) {
			// check if service is in the DB already
			$serviceNameInDatabase = $wpdb->get_var($wpdb->prepare('SELECT name FROM `'. $serviceTable .'` WHERE name=%s AND id=%s;', $serviceName, $serviceId));
			if (is_null($serviceNameInDatabase)) {
				// not in database, add it
				$serviceValues = array('visitor'	=> $visitorId,
									   'name'		=> $serviceName,
									   'id'			=> $serviceId,
						  );
				$serviceFormat = array('%d', '%s', '%s');

				$wpdb->insert($serviceTable, $serviceValues, $serviceFormat);
			} else if (!$ignoreDuplicateServices) {
				// visitor is already in the database, find entry and new data

				// get visitor id
				$realVisitorId = $wpdb->get_var($wpdb->prepare('SELECT visitor FROM `'. $serviceTable .'` WHERE name=%s AND id=%s;', $serviceNameInDatabase, $serviceId));

				if (!is_null($realVisitorId)) {
					// remove duplicate (new) entry
					$wpdb->query($wpdb->prepare('DELETE FROM `'. $visitorTable .'` WHERE id=%d;', $visitorId));

					// update with BlogCatalog username and other data we determined
					$wpdb->update($visitorTable, $values, array('id' => $realVisitorId), $format, array('%s'));

					$status = 'updated';
				}

				// continue to add services, but don't try to add viewer-data, again
				$ignoreDuplicateServices = true;
			}
		}

		return $status;
    }

    /**
	 * Extracts important data from your blog commentators
	 *
	 * @param $serviceProgressId int Id of the service for the progress-bar
	 * @return array Returns an array with age and gender of commentators (can be non-existent)
	 */
	function getBlogCommentators($serviceProgressId) {
		$commentators = array();

		$options = get_option('blog-demographics');

		$comments = get_comments(array('status' => 'approve'));

		// get unique list of email addresses
		$emailAddresses = array();
		foreach ($comments as $comment) {
			if (!empty($comment->comment_author_email) && !in_array($comment->comment_author_email, $emailAddresses)) {
				$emailAddresses[] = $comment->comment_author_email;
			}
		}

		if (count($emailAddresses) > 0) {
			$commentorWeight = 100 / count($emailAddresses);
		} else {
			$commentorWeight = 0;
		}

		// get data
		foreach ($emailAddresses as $commentorCounter => $email) {
			$infos = $this->getFacebookInfosForEmail($email);
			if (!is_null($infos)) {
				$commentators[] = $infos;
			}

			// update progress
			$options['baseServiceProgress'] = unserialize($options['baseServiceProgress']);
			$options['baseServiceProgress'][$serviceProgressId] = ($commentorCounter + 1) * $commentorWeight;
			$options['baseServiceProgress'] = serialize($options['baseServiceProgress']);
			update_option('blog-demographics', $options);
		}

		return $commentators;
	}

    /**
     * Retrieves the MyBlogLog id for a given username
     *
     * @param $username string MyBlogLog username
     * @return int Returns the id or null if the id couldn't be determined
     */
    function mblUsernameToId($username) {
    	include_once(ABSPATH .'wp-includes/http.php');

    	$options = get_option('blog-demographics');

    	$response = wp_remote_get('http://mybloglog.yahooapis.com/v1/user/screen_name/'. urlencode($username) .'?appid='. urlencode($options['mbl-app-id']) .'&format=xml');
    	if (!is_wp_error($response)) {
    		$profile = wp_remote_retrieve_body($response);

    		preg_match_all('/<id>(\d+)/s', utf8_decode($profile), $profileArray, PREG_PATTERN_ORDER);

    		if (isset($profileArray[1][0]) && !empty($profileArray[1][0])) {
    			return $profileArray[1][0];
    		}
    	}

    	return null;
    }

    /**
     * Adds much needed cookie support to Facebook requests
     *
     * Assumes that cURL is installed
     *
     * @static
     * @param Resource $handle cURL handle
     */
    function facebookCurlHandler($handle) {
    	// only add cookies to facebook requests
    	if (strpos(parse_url(curl_getinfo($handle, CURLINFO_EFFECTIVE_URL), PHP_URL_HOST), 'facebook.com') !== false) {
	    	curl_setopt($handle, CURLOPT_COOKIEJAR, BlogDemographics::getFacebookCookieLocation());
		    curl_setopt($handle, CURLOPT_COOKIEFILE, BlogDemographics::getFacebookCookieLocation());
    	}
    }

    /**
     * Logs into Facebook and returns content
     *
     * @return string Returns the content of the page after the log-in or an empty string
     */
    function facebookLogIn() {
    	$options = get_option('blog-demographics');

    	add_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));

    	include_once(ABSPATH . WPINC .'/class-http.php');

    	if (isset($options['fb-email']) && !empty($options['fb-email']) && isset($options['fb-pass']) && !empty($options['fb-pass'])) {
    		// Mimic a browser, and log in
    		$header = array('user-agent'	=> $this->fbUserAgent,
    						'sslverify'		=> false,
    				  );

    		$http = new WP_Http;
    		$result = $http->request('http://m.facebook.com/?locale=en_US', $header);
    		if (!is_wp_error($result)) {

	    		// are we logged-in already (because of the cookie)?
				preg_match_all('/<textarea\s+(?:[^>]\s+)?name="status"/s', $result['body'], $loginArray, PREG_PATTERN_ORDER);
				if (empty($loginArray[0])) {
					// not logged in, log in now

	    			// extract form data
	    			// form[action]
	    			$formAction = '';
	    			preg_match_all('/<form(?:\s+[^>])?\s+action="([^"]+)"/s', $result['body'], $formActionArray, PREG_PATTERN_ORDER);
					if (!empty($formActionArray[1][0])) {
						$formAction = $formActionArray[1][0];
					}

					if ($formAction != '') {
						// input[name] and input[value]
						$formInputs = array();
						preg_match_all('/<input\s+(?:[^>]*\s)?name="([^"]+)"(?:\s+(?:[^>]*\s)?value="([^"]*)")?/s', $result['body'], $formInputsArray, PREG_PATTERN_ORDER);
						if (count($formInputsArray[1]) > 0) {
							$formInputs = array_combine($formInputsArray[1], $formInputsArray[2]);
						}

						// try it in reverse order, too
						preg_match_all('/<input\s+(?:[^>]*\s)?value="([^"]*)"\s+(?:[^>]*\s)?name="([^"]+)"/s', $result['body'], $formInputsArray, PREG_PATTERN_ORDER);
						if (count($formInputsArray[2]) > 0) {
							$formInputs = array_merge($formInputs, array_combine($formInputsArray[2], $formInputsArray[1]));
						}

		    			// log in
		    			$formInputs['email'] = $options['fb-email'];
		    			$formInputs['pass'] = $options['fb-pass'];

		    			// add header details for a post request
		    			$header['method'] = 'POST';
		    			$header['body'] = $formInputs;

		    			$result = $http->request($formAction, $header);
					}
				}

				if (!is_wp_error($result)) {
					remove_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));

					return $result['body'];
				}
    		}
		}

		remove_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));

    	return '';
    }

    /**
     * Finds the Facebook UID for a given email
     *
     * @param string $email Email to check
     * @return int Returns the UID or null if it couldn't be found
     */
    function getFacebookUidForEmail($email) {
    	$uid = null;

    	$site = $this->facebookLogIn();

    	add_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));

    	// extract search parameters
    	preg_match_all('/<div\s(?:[^>]*\s)?id="search"[^>]*>\s*<form[^>]+action="([^"]+)[^>]*>(.*?)<\/form>\s*<\/div>/s', $site, $actionArray, PREG_PATTERN_ORDER);

    	if (!empty($actionArray[1][0]) && !empty($actionArray[2][0])) {
    		$urlPart = $actionArray[1][0];
			$formContent = $actionArray[2][0];

			// get form-inputs
    		// input[name] and input[value]
			$formInputs = array();
			preg_match_all('/<input\s+(?:[^>]*\s)?name="([^"]+)"(?:\s+(?:[^>]*\s)?value="([^"]*)")?/s', $site, $formInputsArray, PREG_PATTERN_ORDER);
			if (count($formInputsArray[1]) > 0) {
				$formInputs = array_combine($formInputsArray[1], $formInputsArray[2]);
			}

			// try it in reverse order, too
			preg_match_all('/<input\s+(?:[^>]*\s)?value="([^"]*)"\s+(?:[^>]*\s)?name="([^"]+)"/s', $site, $formInputsArray, PREG_PATTERN_ORDER);
			if (count($formInputsArray[2]) > 0) {
				$formInputs = array_merge($formInputs, array_combine($formInputsArray[2], $formInputsArray[1]));
			}

			// search query
			$formInputs['query'] = $email;

			$header = array('user-agent'	=> $this->fbUserAgent,
    						'sslverify'		=> false,
							'method'		=> 'POST',
							'body'			=> $formInputs,
    				  );
			include_once(ABSPATH . WPINC .'/class-http.php');
    		$http = new WP_Http;
    		$result = $http->request('http://m.facebook.com'. html_entity_decode($urlPart), $header);

    		if (!is_wp_error($result)) {
				// extract uid
				preg_match_all('/\/addfriend.php\?(?:[^"]*&)?id=(\d+)/s', $result['body'], $uidArray, PREG_PATTERN_ORDER);
				if (!empty($uidArray[1][0])) {
					$uid = $uidArray[1][0];
				}
    		}
    	}

    	remove_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));

    	return $uid;
    }

    /**
     * Retrieves data from a facebook profile
     *
     * @param $id Facebook user-id
     * @return array Returns an array of profile data
     */
    function getFacebookInfos($id) {
		$data = array();

		if (!is_numeric($id)) {
    		return $data;
    	}

    	$options = get_option('blog-demographics');

    	if (isset($options['fb-email']) && !empty($options['fb-email']) && isset($options['fb-pass']) && !empty($options['fb-pass'])) {
    		$this->facebookLogIn();

    		add_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));

    		$header = array('user-agent'	=> $this->fbUserAgent,
    						//'cookies'		=> $this->getFacebookCookie(),
    				  );
			include_once(ABSPATH . WPINC .'/class-http.php');
    		$http = new WP_Http;
    		$result = $http->request('http://m.facebook.com/profile.php?id='. $id .'&locale=en_US', $header);
    		if (!is_wp_error($result)) {
				// extract data
				// extract birthday
				preg_match_all('/<tr><td(?:\s[^>]*)?><[^>]*>Birthday:<[^>]*><\/td><td(?:\s[^>]*)?><[^>]*>(\w+ \d{1,2}, \d{4})<[^>]*><\/td><\/tr>/s', $result['body'], $birthdayArray, PREG_PATTERN_ORDER);
				if (!empty($birthdayArray[1][0])) {
					$data['age'] = $this->calculateAge($this->verboseDateToSlashedDate($birthdayArray[1][0]));
				}

				// extract gender
				preg_match_all('/<tr><td(?:\s[^>]*)?><[^>]*>Sex:<[^>]*><\/td><td(?:\s[^>]*)?><[^>]*>(\w+)<[^>]*><\/td><\/tr>/s', $result['body'], $genderArray, PREG_PATTERN_ORDER);
				if (!empty($genderArray[1][0])) {
					$data['sex'] = strtolower($genderArray[1][0]);
				}
    		}

			remove_action('http_api_curl', array('BlogDemographics', 'facebookCurlHandler'));
    	} else {
			// This would be the right way to request information, but doesn't give us the birthday
	    	include_once(ABSPATH .'wp-includes/http.php');

	    	$response = wp_remote_get('http://graph.facebook.com/'. $id .'?locale=en_US&fields=gender,birthday');
			if(!is_wp_error($response)) {
				$fbData = wp_remote_retrieve_body($response);

				// gender
				preg_match_all('/"gender":\s*"(\w+)/s', utf8_decode($fbData), $fbDataArray, PREG_PATTERN_ORDER);
				if (!empty($fbDataArray[1][0])) {
					$data['sex'] = $fbDataArray[1][0];
				}

				// birthday (untested, permission required? (user_birthday))
				preg_match_all('/"birthday":\s*"([^"]+)"/s', utf8_decode($fbData), $fbDataArray, PREG_PATTERN_ORDER);
				if (!empty($fbDataArray[1][0])) {
					$data['age'] = $this->calculateAge($fbDataArray[1][0]);
				}
			}
		}

    	return $data;
    }

    /**
     * Retrieves data from a Facebook profile
     *
     * @param string $email Email address for a profile
     * @return array Returns an array of profile data
     */
    function getFacebookInfosForEmail($email) {
    	$data = array();

    	$cachedData = $this->getCachedEmailData($email);

    	if ($cachedData === false) {
	    	$uid = $this->getFacebookUidForEmail($email);
	    	if (!is_null($uid)) {
	    		$data = $this->getFacebookInfos($uid);

	    		// cache services, too
	    		if (!isset($data['services'])) {
	    			$data['services'] = array();
	    		}
	    		$data['services']['facebook'] = $uid;
	    	}

	    	// cache data
    		if (!isset($data['emails'])) {
    			$data['emails'] = array();
    		}
    		$data['emails'][] = $email;

    		$this->cacheData($data);
    	} else {
    		$data = $cachedData;
    	}

    	return $data;
    }

    /**
     * Retrieves data from the database for a given email address
     *
     * @param string $email An email address to look up
     * @return array Returns an array of data from the database
     */
    function getCachedEmailData($email) {
    	global $wpdb;

    	$visitorTable = $this->getVisitorTableName();
    	$serviceTable = $this->getServiceTableName();
		$emailTable = $this->getEmailTableName();
		$visitorHasEmailTable = $this->getVisitorHasEmailTableName();

    	$query = 'SELECT *, `'. $visitorTable .'`.`id` AS visitorid, `'. $serviceTable .'`.`id` AS serviceid
    			  FROM `'. $emailTable .'`
    			  LEFT JOIN `'. $visitorHasEmailTable .'` ON `'. $visitorHasEmailTable .'`.`email` = `'. $emailTable .'`.`id`
    			  LEFT JOIN `'. $visitorTable .'` ON `'. $visitorTable .'`.`id` = `'. $visitorHasEmailTable .'`.`visitor`
    			  LEFT JOIN `'. $serviceTable .'` ON `'. $visitorTable .'`.`id` = `'. $serviceTable .'`.`visitor`
    			  WHERE address = "'. $email .'"';

    	$viewerData = $wpdb->get_results($query);

    	if (count($viewerData) > 0) {
	    	$viewer = array('id'		=> $viewerData[0]->visitorid,
	    					'mblid'		=> $viewerData[0]->mblid,
	    					'bcname'	=> $viewerData[0]->bcname,
	    					'sex'		=> $viewerData[0]->gender,
	    					'age'		=> $viewerData[0]->age,
	    					'services'	=> array(),
	    			  );
	    	foreach ($viewerData as $data) {
				$viewer['services'][$data->name] = $data->serviceid;
	    	}

	    	return $viewer;
    	}

    	return false;
    }

    /**
     * Caches data
     *
     * @param $data array An array of viewer-data
     * @return string Returns "inserted" if all went well, "updated" if the user existed and has been updated
     */
    function cacheData($data) {
    	global $wpdb;

    	$status = 'inserted';

		$visitorTable = $this->getVisitorTableName();
		$serviceTable = $this->getServiceTableName();
		$emailTable = $this->getEmailTableName();
		$visitorHasEmailTable = $this->getVisitorHasEmailTableName();

		if (isset($data['services'])) {
			$services = $data['services'];
		} else {
			$services = array();
		}

		if (isset($data['emails'])) {
			$emails = $data['emails'];
		} else {
			$emails = array();
		}

		$values = array('id' => null);
		$format = array('%d');

    	if (isset($data['bcname']) && !is_null($data['bcname'])) {
			$values['bcname'] = $data['bcname'];
			$format[] = '%s';
		}
		if (isset($data['mblid']) && !is_null($data['mblid'])) {
			$values['mblid'] = $data['mblid'];
			$format[] = '%s'; // needs to be a string because it's a very long int and will get cut off if it's %d.
		}
		if (isset($data['sex'])) {
			$values['gender'] = $data['sex'];
			$format[] = '%s';
		}
		if (isset($data['age'])) {
			$values['age'] = $data['age'];
			$format[] = '%d';
		}

		$visitorId = null;
		if (count($values) > 0 && $wpdb->insert($visitorTable, $values, $format) !== false) {
			$visitorId = $wpdb->insert_id;

			// insert services
			$ignoreDuplicateServices = false;
			foreach ($services as $serviceName => $serviceId) {
				// check if service is in the DB already
				$serviceNameInDatabase = $wpdb->get_var($wpdb->prepare('SELECT name FROM `'. $serviceTable .'` WHERE name=%s AND id=%s;', $serviceName, $serviceId));
				if (is_null($serviceNameInDatabase)) {
					// not in database, add it
					$serviceValues = array('visitor'	=> $visitorId,
										   'name'		=> $serviceName,
										   'id'			=> $serviceId,
							  );
					$serviceFormat = array('%d', '%s', '%s');

					$wpdb->insert($serviceTable, $serviceValues, $serviceFormat);
				} else if (!$ignoreDuplicateServices) {
					// visitor is already in the database, find entry and new data

					// get visitor id
					$realVisitorId = $wpdb->get_var($wpdb->prepare('SELECT visitor FROM `'. $serviceTable .'` WHERE name=%s AND id=%s;', $serviceNameInDatabase, $serviceId));

					if (!is_null($realVisitorId)) {
						// remove duplicate (new) entry
						$wpdb->query($wpdb->prepare('DELETE FROM `'. $visitorTable .'` WHERE id=%d;', $visitorId));

						// update data we determined
						$wpdb->update($visitorTable, $values, array('id' => $realVisitorId), $format, array('%s'));

						$status = 'updated';
					}

					// continue to add services, but don't try to add viewer-data, again
					$ignoreDuplicateServices = true;
				}
			}
		}

		// insert emails
		foreach ($emails as $email) {
			// check if email is in the DB already
			$emailId = $wpdb->get_var($wpdb->prepare('SELECT id FROM `'. $emailTable .'` WHERE address=%s;', $email));
			if (is_null($emailId)) {
				// not in database, add it
				$emailValues = array('address' => $email);
				$emailFormat = array('%s');

				if ($wpdb->insert($emailTable, $emailValues, $emailFormat) !== false) {
					$emailId = $wpdb->insert_id;
				}
			}

			// check if relationship is in the DB already
			if (!is_null($emailId) && !is_null($visitorId)) {
				$visitorHasEmailInDatabase = $wpdb->get_var($wpdb->prepare('SELECT email FROM `'. $visitorHasEmailTable .'` WHERE email=%d;', $emailId));

				if (is_null($visitorHasEmailInDatabase)) {
					// insert email <-> visitor relationship
					$visitorHasEmailValues = array('visitor'	=> $visitorId,
												   'email'		=> $emailId,
											 );
					$visitorHasEmailFormat = array('%d', '%d');

					$wpdb->insert($visitorHasEmailTable, $visitorHasEmailValues, $visitorHasEmailFormat);
				}
			}
		}

		return $status;
    }

    /**
     * Returns some data gathered from a digg profile
     *
     * Sadly the Digg-API doesn't return the age or the gender, so we have to access the profile like a browser.
     *
     * @param $username Digg username
     * @return array Returns an array of profile data
     */
    function getDiggInfos($username) {
		$data = array();

		include_once(ABSPATH .'wp-includes/http.php');

		$args = array();
		$args['headers'] = array('User-Agent' => 'Mozilla/5.0 (X11; U; Linux x86_64; de; rv:1.9.2.7) Gecko/20100716 Ubuntu/10.04 (lucid) Firefox/3.6.7');

		$response = wp_remote_get('http://digg.com/users/'. urlencode($username), $args);
		if(!is_wp_error($response)) {
			$diggProfile = wp_remote_retrieve_body($response);

			preg_match_all('/<div class="profile-location">A(?: (\d+) year-old)? ([^\s]+)/s', utf8_decode($diggProfile), $userArray, PREG_PATTERN_ORDER);

			if (!empty($userArray[1][0])) {
				$data['age'] = $userArray[1][0];
			}

			if (!empty($userArray[1][1])) {
				// digg uses random words for describing the gender
				$maleDescriptions = array('male',
										  'guy',
										  'gentleman',
										  'dude',
										  'chap',
										  'fellow',
										  'beau',
									);
				$femaleDescriptions = array('girl',
											'lady',
											'female',
											'grrrl',
											'belle',
									  );
				if (in_array($userArray[1][1], $femaleDescriptions)) {
					$data['sex'] = 'female';
				} elseif (in_array($userArray[1][1], $maleDescriptions)) {
					$data['sex'] = 'male';
				}
			}
		}

		return $data;
    }

    /**
     * Returns some data gathered from a YouTube profile
     *
     * @param $username YouTube username
     * @return array Returns an array of profile data
     */
    function getYouTubeInfos($username) {
    	$data = array();

    	include_once(ABSPATH .'wp-includes/http.php');

    	$response = wp_remote_get('http://gdata.youtube.com/feeds/api/users/'. urlencode($username) .'?v=2');
		if(!is_wp_error($response)) {
			$youtubeProfile = wp_remote_retrieve_body($response);

			preg_match_all('/<yt:age>(\d+)<\/yt:age>.*?<yt:gender>(m|f)<\/yt:gender>/s', utf8_decode($youtubeProfile), $userArray, PREG_PATTERN_ORDER);

			if (!empty($userArray[1][0])) {
				$data['age'] = $userArray[1][0];
			}

			preg_match_all('/<yt:gender>(m|f)<\/yt:gender>/s', utf8_decode($youtubeProfile), $userArray, PREG_PATTERN_ORDER);

			if (!empty($userArray[1][0])) {
				if ($userArray[1][0] == 'm') {
					$data['sex'] = 'male';
				} else {
					$data['sex'] = 'female';
				}
			}
		}

    	return $data;
    }

    function getLastFmInfos($username) {
    	$data = array();

    	include_once(ABSPATH .'wp-includes/http.php');

    	$response = wp_remote_get('http://ws.audioscrobbler.com/2.0/?method=user.getinfo&user='. urlencode($username) .'&api_key='. $this->lastFmPublicApiKey);
		if (!is_wp_error($response)) {
			$lastFmProfile = wp_remote_retrieve_body($response);

			preg_match_all('/<age>(\d+)<\/age>/s', utf8_decode($lastFmProfile), $userArray, PREG_PATTERN_ORDER);

			if (!empty($userArray[1][0])) {
				$data['age'] = $userArray[1][0];
			}

			preg_match_all('/<gender>(m|f)<\/gender>/s', utf8_decode($lastFmProfile), $userArray, PREG_PATTERN_ORDER);

			if (!empty($userArray[1][0])) {
				if ($userArray[1][0] == 'm') {
					$data['sex'] = 'male';
				} else {
					$data['sex'] = 'female';
				}
			}
		}

    	return $data;
    }

    /**
     * Calculates the current age from a birthdate
     *
     * @author Andrew Pociu
     * @link http://www.geekpedia.com/code79_Calculate-age-from-birth-date.html
     * @param $birthdate string Birthday in form of "month/day/year"
     * @return int Returns the current age
     */
    function calculateAge($birthdate) {
    	// explode the date into meaningful variables
    	list($birthMonth, $birthDay, $birthYear) = explode('/', $birthdate);

    	// find the differences
    	$yearDiff = date("Y") - $birthYear;
    	$monthDiff = date("m") - $birthMonth;
    	$dayDiff = date("d") - $birthDay;

    	// if the birthday has not yet occured this year
    	if ($dayDiff < 0 || $monthDiff < 0) {
    		$yearDiff--;
    	}

    	return $yearDiff;
    }

    /**
     * Converts verbose dates like "March 27, 1931" to slashed dates like "3/27/1931"
     *
     * @param $date string date in the form of "March 27, 1931"
     * @return string Returns the date in the form of "3/27/1931"
     */
    function verboseDateToSlashedDate($date) {
    	$months = array('January',
    					'February',
    					'March',
    					'April',
    					'May',
						'June',
    					'July',
    					'August',
    					'September',
    					'October',
    					'November',
    					'December',
    			  );

    	$parts = explode(' ', $date);
    	$slashedDate = array_search($parts[0], $months) + 1;
    	$slashedDate .= '/'. substr($parts[1], 0, strlen($parts[1]) - 1);
    	$slashedDate .= '/'. $parts[2];

    	return $slashedDate;
    }

    /**
     * Adds JavaScript to the blog
     */
	function addBlogJS() {
		$options = get_option('blog-demographics');

		if (!empty($options['mbl-community-id'])) {
			// add MyBlogLog code to track viewers
			echo('<script type="text/javascript" src="http://track3.mybloglog.com/js/jsserv.php?mblID='. htmlentities($options['mbl-community-id']) .'"></script>');
		}

		if (!empty($options['bc-community-id'])) {
			// add BlogCatalog widget to track viewers
			echo('<script type="text/javascript" src="http://www.blogcatalog.com/w/recent.php?id='. htmlentities($options['bc-community-id']) .'"></script>');
		}
	}

	/**
	 * Adds CSS to the blog
	 */
	function addBlogCSS() {
		$options = get_option('blog-demographics');

		if (!empty($options['bc-community-id'])) {
			// hide BlogCatalog widget. This way it still tracks viewers, but it isn't visible to visitors
			echo('<style type="text/css">#bc_widget {display:none}</style>');
		}
	}

	/**
	 * Adds JavaScript to the admin GUI
	 */
	function addAdminJS() {
		wp_enqueue_script('jquery-ui-widget-progressbar');
	}

	/**
	 * Adds styles to the admin GUI
	 */
	function addAdminCSS() {
		wp_enqueue_style('jquery-ui-css-framework');
	}

	/**
	 * Echoes the progress of the data gathering for an ajax-request
	 */
	function loadingProgress() {
		$options = get_option('blog-demographics');
		if (isset($options['baseServiceCount']) && $options['baseServiceCount'] > 0) {
			if (isset($options['baseServiceProgress'])) {
				$options['baseServiceProgress'] = unserialize($options['baseServiceProgress']);

				// calculate overall progress in percent
				$baseServiceWeight = $options['baseServiceCount'];
				$progress = array_sum($options['baseServiceProgress']) / $baseServiceWeight;

				echo $progress;
			} else {
				// error
				die(0);
			}
		} else {
			// error
			die(0);
		}

		die();
	}

	/**
	 * Called from AJAX-request, starts gathering data and caches it. Returns the page when it's done
	 */
	function getDemographics() {
		// set execution time of this script to infinite
		set_time_limit(0);

		$options = get_option('blog-demographics');

		// we ask this number of services for recent viewers (for ajax-progress-bar)
		$options['baseServiceCount'] = 3;
		// initialize the progress of all services with 0
		$options['baseServiceProgress'] = serialize(array_fill(0, $options['baseServiceCount'], 0));
		update_option('blog-demographics', $options);

		$blogDemographics = new BlogDemographics;
		$mblViewers = $blogDemographics->getRecentMblViewers(0);
		foreach ($mblViewers as $index => $viewer) {
			$viewer['services']['mybloglog'] = $viewer['mblid'];
			$mblViewers[$index] = $viewer;
		}
		$bcViewers = $blogDemographics->getRecentBcViewers(1);
		foreach ($bcViewers as $index => $viewer) {
			$viewer['services']['blogcatalog'] = $viewer['bcname'];
			$bcViewers[$index] = $viewer;
		}
		$commentators = $blogDemographics->getBlogCommentators(2);

		$viewers = array_merge($mblViewers, $bcViewers, $commentators);

		// remove double-entries
		$viewers = super_unique($viewers);

		// count gender, ages and different services
		$servicesMale = array();
		$servicesMaleCount = 0;
		$servicesFemale = array();
		$servicesFemaleCount = 0;
		$servicesCount = 0;
		$services = array();
		$agesMale = array();
		$maleCount = 0;
		$maleAgeCount = 0;
		$agesFemale = array();
		$femaleCount = 0;
		$femaleAgeCount = 0;
		$ages = array();
		$ageCount = 0;
		foreach ($viewers as $viewer) {
			// gender
			if (isset($viewer['sex'])) {
				if ($viewer['sex'] == 'male') {
					$maleCount++;

					// gender specific age counter
					if (isset($viewer['age'])) {
						$maleAgeCount++;

						if (isset($agesMale[$viewer['age']])) {
							$agesMale[$viewer['age']]++;
						} else {
							$agesMale[$viewer['age']] = 1;
						}
					}

					// gender specific services counter
					if (isset($viewer['services']) && count($viewer['services']) > 0) {
						if (count($viewer['services']) > 1 || !isset($viewer['services'][""])) {
							foreach ($viewer['services'] as $service => $id) {
								if ($service == "") {
									continue;
								}

								if (isset($servicesMale[$service])) {
									$servicesMale[$service]++;
								} else {
									$servicesMale[$service] = 1;
								}
							}

							$servicesMaleCount++;
						}
					}
				} else {
					$femaleCount++;

					// gender specific age counter
					if (isset($viewer['age'])) {
						$femaleAgeCount++;

						if (isset($agesFemale[$viewer['age']])) {
							$agesFemale[$viewer['age']]++;
						} else {
							$agesFemale[$viewer['age']] = 1;
						}
					}

					// gender specific services counter
					if (isset($viewer['services']) && count($viewer['services']) > 0) {
						if (count($viewer['services']) > 1 || !isset($viewer['services'][""])) {
							foreach ($viewer['services'] as $service => $id) {
								if ($service == "") {
									continue;
								}

								if (isset($servicesFemale[$service])) {
									$servicesFemale[$service]++;
								} else {
									$servicesFemale[$service] = 1;
								}
							}

							$servicesFemaleCount++;
						}
					}
				}
			}

			// age
			if (isset($viewer['age'])) {
				if (isset($ages[$viewer['age']])) {
					$ages[$viewer['age']]++;
				} else {
					$ages[$viewer['age']] = 1;
				}

				$ageCount++;
			}

			// services
			if (isset($viewer['services'])) {
				if (count($viewer['services']) > 1 || !isset($viewer['services'][""])) {
					foreach ($viewer['services'] as $service => $id) {
						if ($service == "") {
							continue;
						}

						if (isset($services[$service])) {
							$services[$service]++;
						} else {
							$services[$service] = 1;
						}
					}

					$servicesCount++;
				}
			}
		}

		// calculate values for gender diagram
		$chartGenderCount = $maleCount + $femaleCount;
		if ($chartGenderCount == 0) {
			$chartWeight = 0;
		} else {
			$chartWeight = 100 / $chartGenderCount;
		}

		$chartMaleCount = $maleCount * $chartWeight;
		$chartFemaleCount = $femaleCount * $chartWeight;

		// calculate values for age diagram
		// TODO: would be cool to be able to select the grouping granularity in the GUI
		$ageGroupLimits = array(12, 17, 24, 34, 44, 54, 64, 999);
		$ageGroupDescriptions = array('0-12'	=> 0,
									  '13-17'	=> 0,
									  '18-24'	=> 0,
									  '25-34'	=> 0,
									  '35-44'	=> 0,
									  '45-54'	=> 0,
									  '55-64'	=> 0,
									  '65-%20'	=> 0,
								);
		$ageGroups = array();
		$maleAgeGroups = array();
		$femaleAgeGroups = array();
		// initialize each age group with 0
		for ($i = 0; $i < count($ageGroupDescriptions); $i++) {
			$ageGroups[$i] = 0;
			$maleAgeGroups[$i] = 0;
			$femaleAgeGroups[$i] = 0;
		}

		foreach ($ages as $age => $ageCounter) {
			foreach ($ageGroupLimits as $index => $upperLimit) {
				if ($age <= $upperLimit) {
					$ageGroups[$index] += $ageCounter;

					break;
				}
			}
		}

		foreach ($agesMale as $age => $ageCounter) {
			foreach ($ageGroupLimits as $index => $upperLimit) {
				if ($age <= $upperLimit) {
					$maleAgeGroups[$index] += $ageCounter;

					break;
				}
			}
		}

		foreach ($agesFemale as $age => $ageCounter) {
			foreach ($ageGroupLimits as $index => $upperLimit) {
				if ($age <= $upperLimit) {
					$femaleAgeGroups[$index] += $ageCounter;

					break;
				}
			}
		}

		// now make the age group counts percentage values
		if ($ageCount == 0) {
			$ageWeight = 0;
		} else {
			$ageWeight = 100 / $ageCount;
		}
		$relativeAgeGroups = array();
		foreach ($ageGroups as $i => $numberOfPersonsInGroup) {
			$relativeAgeGroups[$i] = $numberOfPersonsInGroup * $ageWeight;
		}

		$relativeAgeGroupsUnstackedForMales = $relativeAgeGroups;
		$maleRelativeAgeGroups = array();
		foreach ($maleAgeGroups as $i => $numberOfPersonsInGroup) {
			$maleRelativeAgeGroups[$i] = $numberOfPersonsInGroup * $ageWeight;
			$relativeAgeGroupsUnstackedForMales[$i] -= $numberOfPersonsInGroup * $ageWeight;
		}

		$relativeAgeGroupsUnstackedForFemales = $relativeAgeGroups;
		$femaleRelativeAgeGroups = array();
		foreach ($femaleAgeGroups as $i => $numberOfPersonsInGroup) {
			$femaleRelativeAgeGroups[$i] = $numberOfPersonsInGroup * $ageWeight;
			$relativeAgeGroupsUnstackedForFemales[$i] -= $numberOfPersonsInGroup * $ageWeight;
		}

		// Social media services
		arsort($services);

		$servicesGroupDescriptions = array_keys($services);
		// make the service descriptions prettier
		foreach ($servicesGroupDescriptions as $i => $description) {
			$servicesGroupDescriptions[$i] = ucwords($description);
		}

		if (count($services) > 0) {
			// set pointer to first element
			reset($services);
			// get number of users of the first service
			$relativeServicesCount = current($services);
		}

		if ($relativeServicesCount == 0) {
			$servicesWeight = 0;
		} else {
			$servicesWeight = 100 / $relativeServicesCount;
		}

		// calculate services-chart-indicators
		$indicators = array();
		if ($servicesCount == 0) {
			$indicators = array(0);
		} else {
			$maxPercentage = (100 / $servicesCount) * $relativeServicesCount;
			$steps = $maxPercentage / 9;
			for ($i = 0; $i < 10; $i++) {
				if ($maxPercentage < 10) {
					// add comma
					$indicators[] = round($steps * $i, 1);
				} else {
					$indicators[] = round($steps * $i);
				}
			}
		}

		$relativeServicesGroups = array();
		$maleRelativeServicesGroups = array();
		$relativeServicesGroupsUnstackedForMales = array();
		$femaleRelativeServicesGroups = array();
		$relativeServicesGroupsUnstackedForFemales = array();
		$i = 0;
		foreach ($services as $service => $numberOfServiceUsers) {
			$relativeServicesGroups[$i] = $numberOfServiceUsers * $servicesWeight;

			if (isset($servicesMale[$service])) {
				$maleRelativeServicesGroups[$i] = $servicesMale[$service] * $servicesWeight;
				$relativeServicesGroupsUnstackedForMales[$i] = $relativeServicesGroups[$i] - $maleRelativeServicesGroups[$i];
			} else {
				$maleRelativeServicesGroups[$i] = 0;
				$relativeServicesGroupsUnstackedForMales[$i] = $relativeServicesGroups[$i];
			}

			if (isset($servicesFemale[$service])) {
				$femaleRelativeServicesGroups[$i] = $servicesFemale[$service] * $servicesWeight;
				$relativeServicesGroupsUnstackedForFemales[$i] = $relativeServicesGroups[$i] - $femaleRelativeServicesGroups[$i];
			} else {
				$femaleRelativeServicesGroups[$i] = 0;
				$relativeServicesGroupsUnstackedForFemales[$i] = $relativeServicesGroups[$i];
			}

			$i++;

			if ($i == 30) {
				// when width x height gets over 300000 Google returns a bad request. That's a maximum of 30 entries

				// trim descriptions to only contain first 30 entries
				$servicesGroupDescriptions = array_slice($servicesGroupDescriptions, 0, 30, true);

				break;
			}
		}

		$serviceChartHeight = 31 + count($relativeServicesGroups) * 27;
?>
		<h2><?php _e('Blog Demographics', 'blog-demographics'); ?></h2>
		<p><?php printf(_n('The gender-diagram is based on %1$d visitor (%2$d male, %3$d female).', 'The gender-diagram is based on %1$d visitors (%2$d male, %3$d female).', $chartGenderCount, 'blog-demographics'), $chartGenderCount, $maleCount, $femaleCount); ?></p>
		<p><img id="gender-chart" usemap="#gender-chart-map" src="http://chart.apis.google.com/chart?cht=p&chco=FFCC33,FFE9A5&chd=t:<?php echo($chartMaleCount); ?>,<?php echo($chartFemaleCount); ?>&chs=250x100&chl=<?php echo(urlencode(sprintf(__('Male %d%%', 'blog-demographics'), round($chartMaleCount)))); ?>|<?php echo(urlencode(sprintf(__('Female %d%%', 'blog-demographics'), $chartFemaleCount))); ?>&chf=bg,s,f9f9f9" alt="<?php _e('Gender demographics', 'blog-demographics'); ?>" /></p>
		<p><?php printf(_n('The age-diagram is based on %1$d visitor (%2$d male, %3$d female).', 'The age-diagram is based on %1$d visitors (%2$d male, %3$d female).', $ageCount, 'blog-demographics'), $ageCount, $maleAgeCount, $femaleAgeCount); ?></p>
		<p><form><?php _e('Age groups for:', 'blog-demographics'); ?>
		<input class="age-gender-select" type="radio" id="age-gender-all" name="age-gender" checked="checked" value="all" /> <label for="age-gender-all"><?php _e('All', 'blog-demographics'); ?></label>
		<input class="age-gender-select" type="radio" id="age-gender-male" name="age-gender" value="male" /> <label for="age-gender-male"><?php _e('Male', 'blog-demographics'); ?></label>
		<input class="age-gender-select" type="radio" id="age-gender-female" name="age-gender" value="female" /> <label for="age-gender-female"><?php _e('Female', 'blog-demographics'); ?></label>
		</form></p>
		<p><img id="age-chart-all" src="http://chart.apis.google.com/chart?cht=bhs&chco=FFCC33&chd=t:<?php echo(implode(',', $relativeAgeGroups)); ?>&chxt=x,y&chxl=0:|0%25|10%25|20%25|30%25|40%25|50%25|60%25|70%25|80%25|90%25|100%25|1:|<?php echo(implode('|', array_reverse(array_keys($ageGroupDescriptions)))); ?>&chbh=18,9&chg=-1,0,4,4&chs=370x247&chf=bg,s,f9f9f9" alt="<?php _e('Age groups', 'blog-demographics'); ?>" />
		<img id="age-chart-male" style="display:none" src="http://chart.apis.google.com/chart?cht=bhs&chco=FFCC33,FFE9A5&chd=t:<?php echo(implode(',', $maleRelativeAgeGroups)); ?>|<?php echo(implode(',', $relativeAgeGroupsUnstackedForMales)); ?>&chxt=x,y&chxl=0:|0%25|10%25|20%25|30%25|40%25|50%25|60%25|70%25|80%25|90%25|100%25|1:|<?php echo(implode('|', array_reverse(array_keys($ageGroupDescriptions)))); ?>&chbh=18,9&chg=-1,0,4,4&chs=370x247&chf=bg,s,f9f9f9" alt="<?php _e('Male age group', 'blog-demographics'); ?>" />
		<img id="age-chart-female" style="display:none" src="http://chart.apis.google.com/chart?cht=bhs&chco=FFCC33,FFE9A5&chd=t:<?php echo(implode(',', $femaleRelativeAgeGroups)); ?>|<?php echo(implode(',', $relativeAgeGroupsUnstackedForFemales)); ?>&chxt=x,y&chxl=0:|0%25|10%25|20%25|30%25|40%25|50%25|60%25|70%25|80%25|90%25|100%25|1:|<?php echo(implode('|', array_reverse(array_keys($ageGroupDescriptions)))); ?>&chbh=18,9&chg=-1,0,4,4&chs=370x247&chf=bg,s,f9f9f9" alt="<?php _e('Female age group', 'blog-demographics'); ?>" /></p>
		<p><?php printf(_n('The services-diagram is based on %1$d visitor (%2$d male, %3$d female) who use at least one service.', 'The services-diagram is based on %1$d visitors (%2$d male, %3$d female) who use at least one service.', $servicesCount, 'blog-demographics'), $servicesCount, $servicesMaleCount, $servicesFemaleCount); ?></p>
		<p><form><?php _e('Service groups for:', 'blog-demographics'); ?>
		<input class="services-gender-select" type="radio" id="services-gender-all" name="services-gender" checked="checked" value="all" /> <label for="services-gender-all"><?php _e('All', 'blog-demographics'); ?></label>
		<input class="services-gender-select" type="radio" id="services-gender-male" name="services-gender" value="male" /> <label for="services-gender-male"><?php _e('Male', 'blog-demographics'); ?></label>
		<input class="services-gender-select" type="radio" id="services-gender-female" name="services-gender" value="female" /> <label for="services-gender-female"><?php _e('Female', 'blog-demographics'); ?></label>
		</form></p>
		<script type="text/javascript">
			jQuery(".age-gender-select").change(changeAgeGenderChart);
			function changeAgeGenderChart () {
				var chartTypes = new Array("all", "male", "female");
				var to = jQuery(".age-gender-select:checked").val();
				jQuery.each(chartTypes, function(index, value) {
					if (value == to) {
						jQuery('#age-chart-' + value).css('display', 'block');
					} else {
						jQuery('#age-chart-' + value).css('display', 'none');
					}
				});
			}

			jQuery(".services-gender-select").change(changeServicesGenderChart);
			function changeServicesGenderChart() {
				var chartTypes = new Array("all", "male", "female");
				var to = jQuery(".services-gender-select:checked").val();
				jQuery.each(chartTypes, function(index, value) {
					if (value == to) {
						jQuery('#services-chart-' + value).css('display', 'block');
					} else {
						jQuery('#services-chart-' + value).css('display', 'none');
					}
				});
			}

			function checkGenderMale() {
				jQuery('#age-gender-male').attr('checked', 'checked');
				changeAgeGenderChart();

				jQuery('#services-gender-male').attr('checked', 'checked');
				changeServicesGenderChart();
			}

			function checkGenderFemale() {
				jQuery('#age-gender-female').attr('checked', 'checked');
				changeAgeGenderChart();

				jQuery('#services-gender-female').attr('checked', 'checked');
				changeServicesGenderChart();
			}

			jQuery.getJSON("http://chart.apis.google.com/chart?cht=p&chco=FFCC33,FFE9A5&chd=t:<?php echo($chartMaleCount); ?>,<?php echo($chartFemaleCount); ?>&chs=250x100&chl=<?php echo(urlencode(sprintf(__('Male %d%%', 'blog-demographics'), round($chartMaleCount)))); ?>|<?php echo(urlencode(sprintf(__('Female %d%%', 'blog-demographics'), $chartFemaleCount))); ?>&chf=bg,s,f9f9f9&chof=json&callback=?", function(data) {
				var map = '<map name="gender-chart-map">';
				jQuery.each(data.chartshape, function(index, value) {
					var name = value['name'];
					if (name.substr(name.length - 1, 1) == 0) {
						map += '<area name="'+ name +'" shape="'+ value['type'] +'" coords="'+ value['coords'].join(",") +'" alt="<?php _e('Male', 'blog-demographics'); ?>" href="javascript:checkGenderMale()" />';
					} else {
						map += '<area name="'+ name +'" shape="'+ value['type'] +'" coords="'+ value['coords'].join(",") +'" alt="<?php _e('Female', 'blog-demographics'); ?>" href="javascript:checkGenderFemale()" />';
					}
				});
				jQuery(map + '</map>').insertAfter("#gender-chart");
			});
		</script>
		<p><img id="services-chart-all" src="http://chart.apis.google.com/chart?cht=bhs&chco=FFCC33&chd=t:<?php echo(implode(',', $relativeServicesGroups)); ?>&chxt=x,y&chxl=0:|<?php echo(implode('%25|', $indicators)); ?>%25|1:|<?php echo(implode('|', array_reverse($servicesGroupDescriptions))); ?>&chbh=18,9&chg=-1,0,4,4&chs=370x<?php echo($serviceChartHeight); ?>&chf=bg,s,f9f9f9" alt="<?php _e('Social media usage', 'blog-demographics'); ?>" />
		<img id="services-chart-male" style="display:none" src="http://chart.apis.google.com/chart?cht=bhs&chco=FFCC33,FFE9A5&chd=t:<?php echo(implode(',', $maleRelativeServicesGroups)); ?>|<?php echo(implode(',', $relativeServicesGroupsUnstackedForMales)); ?>&chxt=x,y&chxl=0:|<?php echo(implode('%25|', $indicators)); ?>%25|1:|<?php echo(implode('|', array_reverse($servicesGroupDescriptions))); ?>&chbh=18,9&chg=-1,0,4,4&chs=370x<?php echo($serviceChartHeight); ?>&chf=bg,s,f9f9f9" alt="<?php _e('Male social media usage', 'blog-demographics'); ?>" />
		<img id="services-chart-female" style="display:none" src="http://chart.apis.google.com/chart?cht=bhs&chco=FFCC33,FFE9A5&chd=t:<?php echo(implode(',', $femaleRelativeServicesGroups)); ?>|<?php echo(implode(',', $relativeServicesGroupsUnstackedForFemales)); ?>&chxt=x,y&chxl=0:|<?php echo(implode('%25|', $indicators)); ?>%25|1:|<?php echo(implode('|', array_reverse($servicesGroupDescriptions))); ?>&chbh=18,9&chg=-1,0,4,4&chs=370x<?php echo($serviceChartHeight); ?>&chf=bg,s,f9f9f9" alt="<?php _e('Female social media usage', 'blog-demographics'); ?>" /></p>
		<p><form action="https://www.paypal.com/cgi-bin/webscr" method="post">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="K3LYXUE973NSY">
			<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypal.com/de_DE/i/scr/pixel.gif" width="1" height="1">
		</form></p>
<?php
		flush();
		die();
	}

	/**
	 * Echoes the main page for the plugin
	 */
	function pluginView() {
?>
<div id="blog-demographics-content" class="wrap"></div>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		var demographicsData = {
			action: 'get_demographics'
		};

		var progressTimer = setTimeout(progressCheck, 500);
		var stopTimer = false;

		var dontLoadAgain = false;

		function displayResponse(response) {
			if (!dontLoadAgain) {
				dontLoadAgain = true;

				stopTimer = true;
				clearTimeout(progressTimer);
				jQuery('#blog-demographics-loading').remove();
				jQuery('#blog-demographics-content').css('display', 'none').append(response).fadeIn();
			}
		}

		jQuery.post(ajaxurl, demographicsData, displayResponse);

		var progressData = {
			action: 'loading_progress'
		}

		jQuery('#blog-demographics-content').append('<div id="blog-demographics-loading"><h2><?php _e('Blog Demographics', 'blog-demographics'); ?></h2><p><?php _e('Please wait while your visitors are being analyzed. (This may take a long time if you are calling this site for the first time.)', 'blog-demographics'); ?></p><div id="blog-demographics-loading-progress"></div></div>');
		jQuery('#blog-demographics-loading-progress').progressbar({
			value: 0
		});


		function progressCheck() {
			jQuery.post(ajaxurl, progressData, function(response) {
				jQuery('#blog-demographics-loading-progress').progressbar('value', Math.round(response));

				// sometimes the page is finished loading but it doesn't get displayed. Fix this here by calling it again
				if (Math.round(response) == 100) {
					jQuery.post(ajaxurl, demographicsData, displayResponse);
				}

				if (!stopTimer) {
					progressTimer = setTimeout(progressCheck, 500);
				}
			});
		}
	});
</script>
<?php
	}
}

load_plugin_textdomain('blog-demographics', null, basename(dirname(__FILE__)) .'/languages/');

register_activation_hook(__FILE__, array('BlogDemographics', 'activatePlugin'));
register_deactivation_hook(__FILE__, array('BlogDemographics', 'deactivatePlugin'));

add_action('admin_init', array('BlogDemographics', 'settingsInit'));
add_action('admin_menu', array('BlogDemographics', 'pluginMenu'));
add_action('admin_header', array('BlogDemographics', 'addAdminHeaderCode'));

add_action('wp_ajax_get_demographics', array('BlogDemographics', 'getDemographics'));
add_action('wp_ajax_loading_progress', array('BlogDemographics', 'loadingProgress'));

add_action('wp_footer', array('BlogDemographics', 'addBlogJS'));
add_action('wp_print_styles', array('BlogDemographics', 'addBlogCSS'));

add_filter('upgrader_post_install', array('BlogDemographics', 'upgradePlugin'));

if (!function_exists('http_build_query')) {
	/**
	 * @author flyingmeteor at gmail dot com
	 * @link http://at2.php.net/manual/en/function.http-build-query.php#90438
	 * @param array $data
	 * @param string $prefix
	 * @param string $sep
	 * @param string $key
	 * @return string
	 */
	function http_build_query($data, $prefix='', $sep='', $key='') {
	    $ret = array();
	    foreach ((array)$data as $k => $v) {
	        if (is_int($k) && $prefix != null) $k = urlencode($prefix . $k);
	        if (!empty($key)) $k = $key.'['.urlencode($k).']';

	        if (is_array($v) || is_object($v))
	            array_push($ret, http_build_query($v, '', $sep, $k));
	        else    array_push($ret, $k.'='.urlencode($v));
	    }

	    if (empty($sep)) $sep = ini_get('arg_separator.output');

	    return implode($sep, $ret);
	}
}

/**
 * Recursive array_unique()
 *
 * @author regeda at inbox dot ru
 * @link http://at.php.net/manual/en/function.array-unique.php#97285
 * @param array $array Array process array_unique() recursively on
 * @return array Returns the same array but every entry is unique
 */
function super_unique($array) {
	$result = array_map("unserialize", array_unique(array_map("serialize", $array)));

	foreach ($result as $key => $value) {
		if (is_array($value)) {
			$result[$key] = super_unique($value);
		}
	}

	return $result;
}
