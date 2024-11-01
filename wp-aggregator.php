<?php
/*
Plugin Name: WordPress Aggregator
Author: Loosky
Plugin URI: http://www.loosky.net/
Description: WordPress Aggregator can gather information from other sites and display them.
Version: 1.0
Author URI: http://www.loosky.net/
*/

//avoid direct calls to this file, because now WP core and framework has been used
if ( !function_exists('add_action') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

add_action('init', 'init_aggregator');
function init_aggregator(){
  load_plugin_textdomain('wp-aggregator',PLUGINDIR . '/' . dirname(plugin_basename (__FILE__)) . '/languages');
}

if ( !function_exists('esc_attr') ) {
	function esc_attr( $text ) {
		return attribute_escape( $text );
	}
}

/*config*/
/*Aggregator List*/
$aggregator_feeds = get_option('aggregator_feeds');	
if(!$aggregator_feeds) $aggregator_feeds = 'http://feed.loosky.net/';
$aggregator_feeds = explode(',',$aggregator_feeds);

//update items every time
$aggregator_update_items = get_option('aggregator_update_items');	
if(!$aggregator_update_items) $aggregator_update_items = 2;
/*config end*/

add_action('update_aggregator_hook', 'get_update_feeds');

register_activation_hook( __FILE__,'aggregator_install');
function aggregator_install () {	
	global $wpdb;
	
	if(!defined('DB_CHARSET') || !($db_charset = DB_CHARSET))  $db_charset = 'utf8';
	$db_charset = "CHARACTER SET ".$db_charset;

	$table_name = $wpdb->prefix . "aggregator";
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		id bigint(20) unsigned NOT NULL auto_increment,
		title text NOT NULL,
		url varchar(255) NOT NULL default '',
		content longtext NOT NULL,
		author varchar(255) NOT NULL default '',
		date datetime NOT NULL default '0000-00-00 00:00:00',
		blog_title text NOT NULL,
		blog_url varchar(255) NOT NULL default '',
		guid varchar(255) NOT NULL default '',
		PRIMARY KEY  (id)
		) ENGINE=MyISAM  {$db_charset};";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta($sql);
		
		wp_schedule_event( time(), 'hourly', 'update_aggregator_hook' ); //    * hourly    * twicedaily   * daily
	}
	add_option('aggregator_feeds', 'http://feed.loosky.net/');
	add_option('aggregator_update_items', '2');
}

/*Update hourly by WP_Cron*/
function get_update_feeds(){
	global $aggregator_feeds,$aggregator_update_items;
	$i=0;
	
	$last_update = get_option('last_update');	
	if(!$last_update) $last_update = 0;
	
	$total = count($aggregator_feeds);	
	if($last_update + 1 > $total)	$last_update = 0;
	
	while($i<$aggregator_update_items&&$last_update+$i<$total){
		feed_to_aggregator($aggregator_feeds[$last_update+$i]);
		$i++;
	}
	
	update_option("last_update",$last_update + $i);
	unset($last_update,$total,$i);
}
	
function feed_to_aggregator($rss){
	global $wpdb;
	
	if (!class_exists('SimplePie') ) {
		if ( file_exists(ABSPATH . WPINC . '/class-simplepie.php') ) {
			@require_once (ABSPATH . WPINC . '/class-simplepie.php');
		} else {
			die (__('Error in file: ' . __FILE__ . ' on line: ' . __LINE__ . '.<br />The WordPress file "class-simplepie.php" with class SimplePie could not be included.'));
		}
	}
	
	$feed = new SimplePie();
	$feed->set_feed_url($rss);
	$feed->enable_cache(false);		
	$feed->enable_order_by_date(true); 
	$feed->init();
	$feed->handle_content_type();
		
	$blog_title = esc_attr( strip_tags( $feed->get_title())); 
	$blog_url   = wp_filter_kses($feed->get_permalink());
		
	foreach ($feed->get_items() as $item){
		$guid = $item->get_id();
		if(!$guid)	continue;
		$title = esc_attr( strip_tags($item->get_title()));
		$url = wp_filter_kses($item->get_permalink());
		$date = date_i18n( 'Y-m-d H:i:s', strtotime( $item->get_date() ) );
		
		$content = @html_entity_decode( $item->get_content(), ENT_QUOTES, get_option('blog_charset') ); // For import with HTML
		
		$author = $item->get_author(); 
		if ($author_array = $item->get_author()){
			$author = esc_attr( strip_tags($author_array->get_name())); 
			if(!$author){
				$author = esc_attr( strip_tags($author_array->get_email())); 
			}
		}
		
		update_aggregator($title,$url,$content,$author,$date,$blog_title,$blog_url,$guid);
	}
	unset($feed,$title,$url,$content,$author,$date,$blog_title,$blog_url,$guid);
}

function update_aggregator($title,$url,$content,$author,$date,$blog_title,$blog_url,$guid){
	global $wpdb;
	
	$sql = "SELECT id FROM ". $wpdb->prefix . "aggregator where guid = '$guid'";	
	$query = $wpdb -> query($sql);
	
	if($query){
		$sql = "UPDATE ". $wpdb->prefix . "aggregator SET title = '$title', url='$url', content='$content', author='$author', blog_title='$blog_title',blog_url='$blog_url', date='$date' WHERE guid = '$guid'";
	}else{
		$sql = "INSERT INTO ". $wpdb->prefix . "aggregator (title,url,content,author,date,blog_title,blog_url,guid) values ('$title','$url','$content','$author','$date','$blog_title','$blog_url','$guid')";
	}	
	
	$query = $wpdb -> query($sql);	
	unset($sql);
	return $query;
}

function wp_aggregator($perpage=6,$maxto=6,$istruncate=true,$truncatedescchar = 600, $truncatedescstring = ' ... ',$date_format = '',$target = '_blank')
{	
	//get_update_feeds();
	global $wpdb,$paged;	
	$perpage = intval($perpage);
	$maxto = intval($maxto);
	$truncatedescchar = intval($truncatedescchar);
	if ( $date_format == '' ) $date_format = get_option('date_format');
	
	$output='';
	if(!$paged)	$paged = 1;
	
	$sql = "SELECT id FROM ". $wpdb->prefix . "aggregator";
	$total_numer = $wpdb->query($sql);               //Total number
	$total_page = ceil($total_numer/$perpage);       //Total pages
	
	$paged = ($paged>$total_page|$paged<1)? 1:$paged;  
	$offset=($paged-1)*$perpage;

	$sql = "SELECT * FROM ". $wpdb->prefix . "aggregator order by date desc LIMIT $offset,$perpage";
	$aggregators = $wpdb->get_results($sql);	
	
	if($aggregators){
		foreach($aggregators as $aggregator){
			////Output content
			$output .=	'<div class="aggregator_post">
							<div class="title">';
			////title
			$output .=	'<h2><a href="'.$aggregator->url.'" title="'.$aggregator->title.'" target="'.$target.'">'.$aggregator->title.'</a></h2>';
			////date and author
			$output .=	'<div class="meta"><span>'.date($date_format,strtotime($aggregator->date)).'</span>  By <span>'.$aggregator->author.'</span></div>
			 </div>';
			////content 
			if ( $istruncate && $truncatedescchar) {
					$content = TextToHtm(wp_html_excerpt($aggregator->content, $truncatedescchar) . $truncatedescstring);
			}
			else $content=$aggregator->content;
			
			$output .=	'<div class="entry">'.$content.' <p><a href="'.$aggregator->url.'" target="'.$target.'">'.__('Read Original...', 'wp-aggregator').'</a></p>';
			$output .='<p>'.__('Source:', 'wp-aggregator').'<a href="'.$aggregator->blog_url.'" target="'.$target.'">'.$aggregator->blog_title.'</a</p></div>';
			$output .=	'</div>';
		}
	}
	echo $output;

	echo showpage($paged, $perpage, $total_page, $total_numer,$maxto);
}

//Text To HTML
function TextToHtm($txt)
{
	$txt = str_replace("  ","　",$txt);
	$txt = str_replace("<","&lt;",$txt);
	$txt = str_replace(">","&gt;",$txt);
	$txt = preg_replace("/[\r\n]{1,}/isU","<br/>\r\n",$txt);
	return $txt;
}

function showpage($paged, $num, $pagenum, $totalnum,$maxto,$permalink='',$prev_paging_link = '&laquo;', $next_paging_link = '&raquo;') {
	global $id;
	
	if(empty($permalink)) $permalink = get_permalink($id);               //get permalink
	else $permalink =$permalink;
	
	$permalink_structure = get_option('permalink_structure');
	if($permalink_structure){
		$base_url = $permalink . '/paged/';
	} else {
		$base_url = $permalink . '&paged=';
	}
		
	$lastpage = ($paged - 1<1)?1:$paged - 1;  //Last page
	$nextpage = ($paged + 1>$pagenum)?$pagenum:$paged + 1;  //next page
   
	if($paged>(int)($maxto/2)&&$pagenum>$maxto)
		$for_begin =$paged - (int)($maxto/2);
	else 
		$for_begin =1;
	
	$for_end = ($pagenum > ($for_begin + $maxto)) ? ($for_begin +$maxto) : $pagenum;
	
	if($for_end>$maxto&&($for_end-$for_begin)<$maxto) $for_begin=$for_end-$maxto;
	
	$pagenav.= "<div class='pages'><em>&nbsp;".__('Total:', 'wp-aggregator')." $totalnum&nbsp;</em><a href=".$base_url."1 title=".__('The First', 'wp-aggregator').">".__('The First', 'wp-aggregator')."</a><a href=".$base_url."$lastpage class=\"next\" title=".__('The Last', 'wp-aggregator').">$prev_paging_link</a>";   
	for ($i = $for_begin; $i <= $for_end; $i++) {
		if ($i != $page){
			$pagenav.= "<a href=".$base_url."$i>$i</a> ";
		} else {
			$pagenav.= "<strong>$i</strong>";
		}
	}
	$pagenav.= "<a href=".$base_url."$nextpage class=\"next\" title=".__('The Next', 'wp-aggregator').">$next_paging_link</a><a href=".$base_url."$pagenum class=\"last\" title=".__('The End', 'wp-aggregator').">$pagenum ".__('pages', 'wp-aggregator')."</a>";
	$pagenav.= "<kbd><input type=\"text\" name=\"custompage\" size=3 onkeydown=\"if(event.keyCode==13) {window.location='$base_url'+this.value; return false;}\" title=".__('Quick To', 'wp-aggregator')."></kbd></div>";
	return $pagenav;
}

function WPAggregator_Shortcode($atts) {
	extract( shortcode_atts( array(
			'perpage'=>6,
			'maxto'=>6,
			'istruncate'=>true,
			'truncatedescchar' => 600, 
			'truncatedescstring' =>'...',
		    'date_format' => '',
		    'target' => '_blank',
			), $atts) );
	//var_dump($atts);
	
	$perpage = intval($perpage);
	$maxto = intval($maxto);
	
	if(empty($istruncate)) $istruncate=true;
	else $istruncate=$istruncate;
	
	$truncatedescchar = intval($truncatedescchar);
	if ( $date_format == '' ) $date_format = get_option('date_format');
	
	$return = wp_aggregator($perpage,$maxto,$istruncate,$truncatedescchar, $truncatedescstring,$date_format,$target);					
	return $return;
}
//Use:[WPAggregator perpage=6 maxto=6 istruncate='true/false' truncatedescchar = 600 truncatedescstring = ' ... ' date_format = '' target = '_blank']
if ( function_exists('add_shortcode') ) add_shortcode('WPAggregator', 'WPAggregator_Shortcode');
		
function delete_aggregator($id)
{
	if($id) {
		global $wpdb;
		$sql = "DELETE from " . $wpdb->prefix ."aggregator" .
			" WHERE id = " . $id;
		if(FALSE === $wpdb->query($sql))
			return __('There was an error in the MySQL query', 'wp-aggregator');		
		else
			return __('Aggregator deleted', 'wp-aggregator');
	}
	else return __('The aggregator cannot be deleted', 'wp-aggregator');
}

function bulkdelete_aggregator($ids)
{
	if(!$ids)
		return __('Nothing done!', 'wp-aggregator');
	global $wpdb;
	$sql = "DELETE FROM ".$wpdb->prefix."aggregator 
		WHERE id IN (".implode(', ', $ids).")";
	$wpdb->query($sql);
	return __('Aggregator(s) deleted', 'wp-aggregator');
}

function aggregator_admin_menu() 
{
	global $aggregator_admin_userlevel;
	if ( function_exists('add_management_page') ) {
	add_management_page('WP-Aggregator', 'WP-Aggregator',8, basename(__FILE__), 'aggregator_management');
	}
}

function edit_aggregator()
{
	  $aggregator_update_items = stripslashes($_POST['aggregator_update_items']);
	  $aggregator_feeds = stripslashes($_POST['aggregator_feeds']);	
	
	  update_option("aggregator_feeds",$aggregator_feeds);
	  update_option("aggregator_update_items",$aggregator_update_items);
	
	  return __('Changes saved', 'wp-aggregator');
}

function aggregator_editform()
{
	$form_name = "editconfig";
	$action_url = $_SERVER['PHP_SELF']."?page=wp-aggregator.php#editconfig";
	$list_label = __('Aggregator List', 'wp-aggregator').'<br />'.__('(Separate rss address with commas.)', 'wp-aggregator');
	$update_items = __('Update items every time', 'wp-aggregator');
	$submit_value = __('Save changes', 'wp-aggregator');
	
	$aggregator_feeds = get_option('aggregator_feeds');	
	if(!$aggregator_feeds) update_option("aggregator_feeds",'http://feed.loosky.net/');
	$aggregator_update_items = get_option('aggregator_update_items');	
	if(!$aggregator_update_items) update_option("aggregator_update_items",'2');
	
	$display .=<<< EDITFORM
<form name="{$form_name}" method="post" action="{$action_url}">
	<table class="form-table" cellpadding="5" cellspacing="2" width="100%">
		<tbody><tr class="form-field form-required">
			<th style="text-align:left;" scope="row" valign="top"><label for="quotescollection_quote">{$list_label}</label></th>
			<td><textarea id="feeds" name="aggregator_feeds" rows="5" cols="50" style="width: 97%;">{$aggregator_feeds}</textarea></td>
		</tr>
		<tr class="form-field">
			<th style="text-align:left;" scope="row" valign="top"><label for="quotescollection_author">{$update_items}</label></th>
			<td><input type="text" id="update_items" name="aggregator_update_items" size="40" value="{$aggregator_update_items}" /></td>
		</tr>
	</tbody>
	</table>
	<p class="submit"><input name="submit" value="{$submit_value}" type="submit" class="button button-primary" /></p>
</form>
EDITFORM;
	return $display;
}

function aggregator_management()
{	
	global $wpdb;
	$perpage=10;
	
	$paged=$_GET['paged'];	
	if(!$paged)	$paged = 1;
	
	$sql = "SELECT id FROM ". $wpdb->prefix . "aggregator";
	$total_numer = $wpdb->query($sql);               
	$total_page = ceil($total_numer/$perpage);       
	
	$paged = ($paged>$total_page|$paged<1)? 1:$paged;  
	$offset=($paged-1)*$perpage;

	if($_REQUEST['submit'] == __('Save changes', 'wp-aggregator')) {
		extract($_REQUEST);
		$msg = edit_aggregator();
	}else if($_REQUEST['action'] == 'delaggregator') {
		$msg = delete_aggregator($_REQUEST['id']);
	}
	else if(isset($_REQUEST['bulkaction']))  {
		if($_REQUEST['bulkaction'] == __('Delete', 'wp-aggregator')) 
		$msg = bulkdelete_aggregator($_REQUEST['bulkcheck']);
	}

	$display .= "<div class=\"wrap\">";
	
	if($msg)
		$display .= "<div id=\"message\" class=\"updated fade\"><p>{$msg}</p></div>";

	$display .= "<h2>".__('Aggregator Management', 'wp-aggregator')."</h2>";

	$sql = "SELECT * FROM " . $wpdb->prefix . "aggregator ";
	$total_numer = $wpdb->query($sql);
	
	if(isset($_REQUEST['orderby'])) {
		$sql .= " ORDER BY " . $_REQUEST['criteria'] . " " . $_REQUEST['order'];
		$option_selected[$_REQUEST['criteria']] = " selected=\"selected\"";
		$option_selected[$_REQUEST['order']] = " selected=\"selected\"";
	}
	else {
		$sql .= " ORDER BY id DESC";
		$option_selected['id'] = " selected=\"selected\"";
		$option_selected['ASC'] = " selected=\"selected\"";
	}
	$sql .= " LIMIT $offset,$perpage";

	$aggregator = $wpdb->get_results($sql);
	
	foreach($aggregator as $aggregator_data) {
		if($alternate) $alternate = "";
		else $alternate = " class=\"alternate\"";
		$aggregator_list .= "<tr{$alternate}>";
		$aggregator_list .= "<th scope=\"row\" class=\"check-column\"><input type=\"checkbox\" name=\"bulkcheck[]\" value=\"".$aggregator_data->id."\" /></th>";
		$aggregator_list .= "<td>" . $aggregator_data->id . "</td>";
		$aggregator_list .= "<td>" . wptexturize(nl2br($aggregator_data->title)) ."</td>";
		$aggregator_list .= "<td>" . $aggregator_data->author;
		if($aggregator_data->author && $aggregator_data->blog_title)
			$aggregator_list .= " / ";
		$aggregator_list .= "<a href=".$aggregator_data->blog_url." target=\"_blank\">".$aggregator_data->blog_title."</a></td>";
		$aggregator_list .= "<td>" . $aggregator_data->date . "</td>";
		$aggregator_list .= "<td><a href=\"" . $_SERVER['PHP_SELF'] . "?page=wp-aggregator.php&action=delaggregator&amp;id=".$aggregator_data->id."\" onclick=\"return confirm( '".__('Are you sure you want to delete this aggregator?', 'wp-aggregator')."');\" class=\"delete\">".__('Delete', 'wp-aggregator')."</a> </td>";
		$aggregator_list .= "</tr>";
	}
	
	if($aggregator_list) {
	$display .= "<p>";
	$display .= sprintf(__ngettext('Currently, you have %d aggregator.', 'Currently, you have %d aggregators.', $total_numer, 'wp-aggregator'), $total_numer);
	$display .= "</p>";
	
	$page_links_text =sprintf(showpage($paged, $perpage, $total_page, $total_numer,5,'?page=wp-aggregator.php'));
	
		$display .= "<form id=\"aggregator\" method=\"post\" action=\"{$_SERVER['PHP_SELF']}?page=wp-aggregator.php\">";
		$display .= "<div class=\"tablenav\">";
		$display .= "<div class='tablenav-pages'>".$page_links_text."</div>";
		$display .= "<div class=\"alignleft actions\">";
		$display .= "<input type=\"submit\" name=\"bulkaction\" value=\"".__('Delete', 'wp-aggregator')."\" class=\"button-secondary\" />";
		$display .= "&nbsp;&nbsp;&nbsp;";
		$display .= __('Sort by: ', 'wp-aggregator');
		$display .= "<select name=\"criteria\">";
		$display .= "<option value=\"id\"{$option_selected['id']}>".__('ID', 'wp-aggregator')." </option>";
		$display .= "<option value=\"date\"{$option_selected['date']}>".__('Date', 'wp-aggregator')."</option>";
		$display .= "</select>";
		$display .= "<select name=\"order\"><option{$option_selected['ASC']}>ASC</option><option{$option_selected['DESC']}>DESC</option></select>";
		$display .= "<input type=\"submit\" name=\"orderby\" value=\"".__('Go', 'wp-aggregator')."\" class=\"button-secondary\" />";
		$display .= "</div>";
		$display .= "<div class=\"clear\"></div>";	
		$display .= "</div>";
		

		
		$display .= "<table class=\"widefat\">";
		$display .= "<thead><tr>
			<th class=\"check-column\"><input type=\"checkbox\" onclick=\"aggregator_checkAll(document.getElementById('aggregator'));\" /></th>
			<th>ID</th><th>".__('Title', 'wp-aggregator')."</th>
			<th>
				".__('Author', 'wp-aggregator')." / ".__('Source', 'wp-aggregator')."
			</th>
			<th>".__('Date', 'wp-aggregator')."</th>
			<th colspan=\"2\" style=\"text-align:center\">".__('Action', 'wp-aggregator')."</th>
		</tr></thead>";
		$display .= "<tbody id=\"the-list\">{$aggregator_list}</tbody>";
		$display .= "</table>";


		$display .= "<div class=\"tablenav\">";
		$display .= "<div class='tablenav-pages'>".$page_links_text."</div>";
		
		$display .= "<div class=\"alignleft actions\">";
		$display .= "<input type=\"submit\" name=\"bulkaction\" value=\"".__('Delete', 'wp-aggregator')."\" class=\"button-secondary action\" />";
		$display .= "</div>";
		$display .= "</div>";
		$display .= "</form>";
		$display .= "<br style=\"clear:both;\" />";

	}
	else
		$display .= "<p>".__('No aggregator in the database', 'wp-aggregator')."</p>";

	$display .= "</div>";
	
	$display .= "<div id=\"addnew\" class=\"wrap\">\n<h2>".__('Edit Config', 'wp-aggregator')."</h2>";
	$display .= aggregator_editform();
	$display .= "</div>";
	
	echo $display;
}

function aggregator_admin_head()
{
	?>
<script type="text/javascript">
function aggregator_checkAll(form) {
	for (i = 0, n = form.elements.length; i < n; i++) {
		if(form.elements[i].type == "checkbox" && !(form.elements[i].hasAttribute('onclick'))) {
				if(form.elements[i].checked == true)
					form.elements[i].checked = false;
				else
					form.elements[i].checked = true;
		}
	}
}
</script>
	<?php
}

add_action('admin_head', 'aggregator_admin_head');


function aggregator_css_head() 
{

	if ( !defined('WP_PLUGIN_URL') )
		$wp_plugin_url = get_bloginfo( 'url' )."/wp-content/plugins";
	else
		$wp_plugin_url = WP_PLUGIN_URL;
	?>
	<link rel="stylesheet" type="text/css" href="<?php echo $wp_plugin_url; ?>/wp-aggregator/wp-aggregator.css"/>
	<?php
}
add_action('wp_head', 'aggregator_css_head' );
add_action('admin_menu', 'aggregator_admin_menu');
?>