=== Plugin Name ===
Contributors: Loosky
Donate link: http://www.loosky.net/
Tags: rss, aggregator
Requires at least: 2.3
Tested up to: 2.8.5
Stable tag: 1.0

WordPress Aggregator Plugin can gather information from other sites and display them.

== Description ==
WordPress Aggregator Plugin can gather information from other sites and display them,use PHP or the Shortcode.The plugin use the standards of WordPress, non extra library; use [SimplePie](http://simplepie.org/) for parse feeds.

You can contact me here:http://www.loosky.net/?page_id=489, or send e-mail:zhuquan168@gmail.com.

Please visit [the official website](http://www.loosky.net/?p=890) for the latest information on this plugin.

Use following code with a PHP-Plugin or in a template, example `single.php`, for WordPress:

Example for function:
<?php wp_aggregator($perpage,$maxto,$istruncate,$truncatedescchar, $truncatedescstring,$date_format,$target); ?>

Example for Shortcode:
[WPAggregator perpage=6 maxto=6 istruncate='true/false' truncatedescchar=600 truncatedescstring='...' date_format='' target = '_blank']
_
For boolean parameter($istruncate) it is possible to use the string 'true' or 'false'.

The plugin have many parameters for custom import and display content form feeds. See the list of parameters. You can also use all parameters with shorcode in posts and pages.

$perpage:               Each page display how many items,default is '6'
$maxto:                 Most show how many pages,default is '6'
$istruncate:            (bool)truncate content or not,default is 'true'
$truncatedescchar:	truncate content, number of chars, Default is '600'
$truncatedescstring:	string after truncate content, Default is ' ... '
$date_format:		your format for the date, leave empty for use format of your WordPress installation, alternativ give 				the php date string, Example: 'F j, Y'; see also:http://codex.wordpress.org/Formatting_Date_and_Time
$target:		string with the target-attribut, Default is '_blank'; use '_blank', '_self', '_parent', '_top'

== Installation ==
1. Upload the folder wp-aggregator to the '/wp-content/plugins/' directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place '<?php wp_aggregator($perpage,$maxto,$istruncate,$truncatedescchar, $truncatedescstring,$date_format,$target); ?>' in your templates or use '[WPAggregator perpage=6 maxto=6 istruncate='true/false' truncatedescchar=600 truncatedescstring='...' date_format='' target ='_blank']' in you posts.
1. Navigate to Manage > Tool > WP-Aggregator to configure plugin and manage contents.

== Screenshots ==
1. WordPress Aggregator setting menu.
2. Manage contents
3. Config the plugin.

== Changelog ==
= 1.0 =
* first version