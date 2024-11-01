<?php
/*
Plugin Name: WP Signaturer
Plugin URI: http://www.dimio.org/tag/wp-signaturer
Description: Вывод случайной подписи из списка заданных под сообщениями Wordpress. Удобно использовать для указания ссылки на RSS-канал блога вида: "Даже если ты балбес - подпишись на RSS!".
Output of a random signature from the list given under the posts Wordpress. It is convenient to use to indicate links to the RSS-feed kind of blog: "Even if you dunce - Subscribe to RSS!".
Version: 1.2
Author: dimio
Author URI: http://www.dimio.org
*/
/*
    Copyright 2009  dimio  (website: www.dimio.org, email: dimio@dimio.org, jabber: dimio@jabber.org)
    Благодарю Arser (www.arserblog.com), на основе его плагина "Уникализатор" (уникализатор контента для Wordpress) создан этот плагин.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See the GNU General Public License for more details:
	http://www.gnu.org/licenses/gpl.txt
*/

register_activation_hook(__FILE__, 'wpsignaturer_set_options');
register_deactivation_hook(__FILE__, 'wpsignaturer_unset_options');

add_action('admin_menu', 'wpsignaturer_admin_page');
add_filter('the_content', 'addSignature');

$rss_url = get_option('siteurl') . "/feed";
$wpsig_sig_table = wpsig_get_table_handle();

function wpsig_get_table_handle() {
	global $wpdb;
	return $wpdb->prefix . "wpsig_signatures";
}

function wpsignaturer_set_options () {
	global $wpdb;
	add_option('wpsig_version', "1.2");
	add_option('wpsig_print_randphrase', 0);
	add_option('wpsig_print_rss', 1);
	add_option('wpsig_sig_colour', "#339966");

	$wpsig_sig_table = wpsig_get_table_handle();
	$charset_collate = '';
	if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') )
			$charset_collate = "DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci";
    if($wpdb->get_var("SHOW TABLES LIKE '$wpsig_sig_table'") != $wpsig_sig_table) {
		$sql = "CREATE TABLE `" . $wpsig_sig_table . "` (
		 	`id` INT NOT NULL AUTO_INCREMENT,
		  	`sig_type` INT(11) NOT NULL,
		  	`sig_body` VARCHAR(255) NOT NULL default '',
		  	UNIQUE KEY id (id)
		)$charset_collate";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
    }
}

function wpsignaturer_unset_options () {
	global $wpdb, $wpsig_sig_table;
	delete_option('wpsig_version');
	delete_option('wpsig_print_randphrase');
	delete_option('wpsig_print_rss');
	delete_option('wpsig_sig_colour');
	$sql = "DROP TABLE $wpsig_sig_table";
	$wpdb->query($sql);
}

function wpsignaturer_admin_page() {
    add_options_page('WordpressSignaturer', 'WordpressSignaturer', 8, __FILE__, 'wpsignaturer_options_page');
}

function wpsignaturer_options_page() {
	global $wpdb, $wpsig_sig_table;
	$wpsig_options = array(
		'wpsig_print_randphrase',
		'wpsig_print_rss',
		'wpsig_sig_colour',
	);
	$cmd = $_POST['cmd'];
	foreach ($wpsig_options as $wpsig_opt) {
		$$wpsig_opt = get_option($wpsig_opt);
	}
	if ($cmd == "del_sig") {
		$sql = "TRUNCATE TABLE $wpsig_sig_table";
		$wpdb->query( $sql );
		echo '<div class="updated"><p><strong>All signatures are removed from the database</strong></p></div>';
	}
	if ($cmd == "add_sig" && $_POST['signatures_base']) {
		$lines = explode("\n", $_POST['signatures_base']);
		foreach($lines as $line){
			$line = trim($line);
			$anchor = '#';
			if (!$line) continue;
			if (substr_count($line, $anchor) > 0) $sig_type = 0;
			if (substr_count($line, $anchor) == 0) $sig_type = 1;
			$line = mysql_real_escape_string($line);
			$sql = "INSERT INTO $wpsig_sig_table (sig_type, sig_body) VALUES('$sig_type','$line')";
			$wpdb->query( $sql );
		}
		echo '<div class="updated"><p><strong>Signatures added to the database</strong></p></div>';
	}
	if ($cmd == "wpsig_save_opt") {
		foreach ($wpsig_options as $wpsig_opt) {
			$$wpsig_opt = $_POST[$wpsig_opt];
		}
		// Save the posted value in the database
		foreach ($wpsig_options as $wpsig_opt) {
			update_option($wpsig_opt, $$wpsig_opt);
		}
		echo '<div class="updated"><p><strong>Settings saved</strong></p></div>';
	}
	$wpsig_total_signatures = $wpdb->get_var("SELECT COUNT(*) FROM $wpsig_sig_table");
?>
	<div class="wrap">
	<h2>Wordpress Signaturer</h2>



	<h3>Settings</h3>
	<form method="post" action="<? echo $_SERVER['REQUEST_URI'];?>">
	<table class="form-table">
	<tr>
	<th colspan=2 scope="row">
		<input name="wpsig_print_rss" type="checkbox" <?if($wpsig_print_rss)echo "checked";?>> Prompts you to subscribe to the RSS-feed
	</th>
	</tr>
	<tr>
	<th colspan=2 scope="row">
		<input name="wpsig_print_randphrase" type="checkbox" <?if($wpsig_print_randphrase)echo "checked";?>> The Show a random phrase (phrase without reference)
	</th>
	</tr>
	<tr>
	<td>
	<br />Specify the color of the text for signature (fo example - red, green, blue etc).
	<br /><a href="http://www.dimio.org/wp-content/uploads/2009/10/HTML_Colors.png" target="_blank" title="HTML Colours table"><img src="http://www.dimio.org/wp-content/uploads/2009/10/HTML_Colors-115x300.png" width="200" height="150" alt="HTML colours table"></a>
	<br />NOTE: hex representation has to be entered with a # before the number (eg #45ff00).
	<textarea cols=7 rows=1 name="wpsig_sig_colour"></textarea>
	<br />Current colour: <big><span style="color: <?echo get_option('wpsig_sig_colour');?>;"><?echo get_option('wpsig_sig_colour');?></span></big>
	</td>
	</tr>
	</table>
	<input type="hidden" name="cmd" value="wpsig_save_opt">
	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
	</p>
	</form>

	<h3>Plugin Wordpress Signaturer developed:</h3>
	<table class="form-table">
	<tr><th>
	<ul>
	<li>By: <a href="http://www.dimio.org/" target="_blank">dimio</a> - linux, perl, seo</li>
	<li>Thank for the idea <a href="http://wasting-money.blogspot.com/" target="_blank"> Andrey K.</a></li>
	</ul>
	</th></tr></table>

	<h3>Adding signatures</h3>
	<table class="form-table" width="300px">
	<tr>
	<td>
		Add phrase (anchor link to the RSS-feed must be enclosed in # characters).<br />
		If a line missing parts, framed by a #, the string is not considered to be RSS-signed and displayed without links.<br />
		<b>Syntax RSS-signature:</b> <em>Soft rolls seller #Subscribe to RSS#!</em><br />
		<b>Syntax random phrases:</b> <em>Come and see us again!</em><br />
		All signatures in the database: <b><?php echo $wpsig_total_signatures; ?></b><br />
	<form method="post" action="<? echo $_SERVER['REQUEST_URI'];?>">
	<textarea cols=80 rows=12 name="signatures_base"></textarea>
	</td>
	</tr>
	</table>
	<input type="hidden" name="cmd" value="add_sig">
	<p class="submit">
	<input type="submit" name="Submit" value="Add signature" />
	</p>
	</form>
	<form method="post" action="<? echo $_SERVER['REQUEST_URI'];?>">
	<input type="hidden" name="cmd" value="del_sig">
	<input type="submit" name="Submit" value="Remove all the signatures from the database" />
	</form>
	</div>

<?php

}

function addSignature($content){
/* $content."<br />"; */
	$sig_colour = get_option('wpsig_sig_colour');
	if (get_option('wpsig_print_randphrase')) {
		$content = wpsig_add_randphrase($content,$sig_colour);
	}
	if (get_option('wpsig_print_rss')) {
		$content = wpsig_add_rss($content,$sig_colour);
	}
	return $content;
}

function wpsig_get_signature ($sig_type){
	global $wpsig_sig_table, $wpdb;
	$sql = "SELECT sig_body FROM $wpsig_sig_table WHERE sig_type = $sig_type ORDER BY RAND() LIMIT 1";
	$sig = $wpdb->get_var($sql);
	return $sig;
}

function wpsig_add_rss ($content,$sig_colour){
	global $rss_url;
	$pattern = "/#(.+)#/i";
	$replacement = "<a href=\"$rss_url\">$1</a>";
	$sig = preg_replace($pattern, $replacement, wpsig_get_signature(0));
	return $content . '<span style="color: '."$sig_colour".';"><small><br>'."$sig". '</small></span>';
}

function wpsig_add_randphrase ($content,$sig_colour){
	$sig = wpsig_get_signature(1);
	return $content . '<span style="color: '."$sig_colour".';"><small><br>'."$sig". '</small></span>';
}

?>
