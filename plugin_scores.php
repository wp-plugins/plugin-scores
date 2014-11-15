<?php
/**
 * @package plugin_scores
 * @version 1.2
 */
/*
Plugin Name: plugin scores
Plugin URI: http://www.funsite.eu/plugin_scores
Description: Adds an admin dashboard widget with info on your plugins
Author: Gerhard Hoogterp
Version: 1.2
Author URI: http://www.funsite.eu/
*/

define('CACHE_TIMEOUT',30*60);

define('OPTION_NAME','myPluginList');
define('HIDDEN_NAME','mpl_submit_hidden');
define('API_URL',	 'http://api.wordpress.org/plugins/info/1.0/');
define('REVIEW_URL', 'https://wordpress.org/support/view/plugin-reviews/');
define('PLUGIN_URL', 'https://wordpress.org/plugins/');

/* -------------------------------------------------------------------------------------- */

function RegisterPluginLinks($links, $file) {
		$base = plugin_basename(__FILE__);
		if ($file == $base) {
			$links[] = '<a href="plugins.php?page=plugins_scores_settings">' . __('Settings','myPlugins') . '</a>';
			$links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/plugin-scores" title="'.__('a review would be appriciated!','myPlugins').'">' . __('reviews','myPlugins') . '</a>';
			$links[] = '<a href="http://www.funsite.eu/plugins/">' . __('Other plugins written by me','myPlugins') . '</a>';
		}
		return $links;
	}

add_filter('plugin_row_meta', 'RegisterPluginLinks',10,2);

/* -------------------------------------------------------------------------------------- */

function showpluginScores( $atts) {
	$res = plugin_scores_create_widget_function();
	return $res;	
}	


add_shortcode( 'plugin_scores', 'showPluginScores' );

/**
 * Add a widget to the dashboard.
 *
 * This function is hooked into the 'wp_dashboard_setup' action below.
 */
function plugin_scores_dashboard_widgets() {

	wp_add_dashboard_widget(
                 'plugin_scores',         // Widget slug.
                 'How do my plugins score?',      // Title.
                 'plugin_scores_widget_function' // Display function.
        );	
}

function plugin_scores_headercode () {
  wp_enqueue_style('plugin_scores_css_handler', plugins_url('/css/plugin_scores.css', __FILE__ ));
}
add_action('admin_init', 'plugin_scores_headercode',false,false,true);
add_action('wp_dashboard_setup', 'plugin_scores_dashboard_widgets');



/**
 * Create the function to output the contents of our Dashboard Widget.
 */
function showPluginInfo($pluginSlug) {
	$url = API_URL.$pluginSlug.'.php';
	$info = unserialize(file_get_contents($url));
	
	$res = "<tr>";
	$res .= '<th><a href="'.PLUGIN_URL.$pluginSlug.'/">'.$pluginSlug.'</a></th> ';
	$res .= '<td class="center'.($info->downloaded?'':' faded').'">'.(int)$info->downloaded.'</td>';
	if ($info->rating) {
		$res .= '<td class="center"><a href="'.REVIEW_URL.$pluginSlug.'">'.number_format((int)$info->rating/20,1).'/5</a></td>';
	} else {
		$res .= '<td class="center faded">0</td>';
	}
	$res .= '<td class="center'.($info->num_ratings?'':' faded').'">'.(int)$info->num_ratings.'</td>';
	$res .= "</tr>";
	return $res;
}
 
function plugin_scores_create_widget_function() {
	date_default_timezone_set(get_option('timezone_string'));

	$res =  '<div style="min-height: 120px">';
	$pluginList = explode("\n",trim(get_option( OPTION_NAME )," \n"));
	sort($pluginList);

	//Cleanup and to lowercase
	for($cnt=0;$cnt<count($pluginList);$cnt++) { $pluginList[$cnt]=strtolower(trim($pluginList[$cnt])); }
	$res .=  '<table id="plugin_scores">';
	$res .=  "<thead><tr>";
	$res .=  '	<th>Plugin</th>';
	$res .=  '	<th class="center">Downloads</th>';
	$res .=  '	<th class="center">Rating</th>';
	$res .=  '	<th class="center">No.ratings</th>';
	$res .=  "</tr></thead>";
	foreach($pluginList as $pluginSlug) {
		$res .= showPluginInfo($pluginSlug);
	}
	
	$res .=  "<tr><td colspan='4' class='small'>".strftime('%H:%M:%S',time()).'</td></tr>';
	$res .=  "</table>";
	$res .=  '</div>';
	return $res;
}

function plugin_scores_widget_function() {
	$transient = 'plugin_scores';
	$result = get_transient( $transient);
	if ( $result === false) {
		$result = plugin_scores_create_widget_function();
		set_transient( $transient, $result,CACHE_TIMEOUT);
	} 
	print $result;
}

/* -------------------------------------------------------------------------------------- */



add_action( 'admin_menu', 'plugin_scores_menu' );

function plugin_scores_menu() {
	add_plugins_page( 'My plugins', 'My Plugins', 'manage_options', 'plugins_scores_settings', 'plugin_scores_options' );
}

function plugin_scores_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

    // variables for the field and option names 
    $opt_name = OPTION_NAME;
    $data_field_name = OPTION_NAME;
    $hidden_field_name = HIDDEN_NAME;

    // Read in existing option value from database
    $opt_val = get_option( OPTION_NAME );

    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        $opt_val = $_POST[ $data_field_name ];

        // Save the posted value in the database
        update_option( $opt_name, $opt_val );
		delete_transient( 'plugin_scores');
        
        // Put an settings updated message on the screen

		?><div class="updated"><p><strong><?php _e('settings saved.', 'myPlugins' ); ?></strong></p></div><?php
	}
	
	echo '<div class="wrap">';

    // header
    echo "<h2>" . __( 'My plugins list', 'myPlugins' ) . "</h2>";

    // settings form
    ?>

	<form name="form1" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

	<p><?php _e("Pluginnames, one per line", 'myPlugins' ); ?><br>
	<textarea name="<?php echo $data_field_name; ?>" style="max-width: 400px;min-height: 300px;"><?php echo $opt_val; ?></textarea>
	</p>
	<hr />

	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>

	</form>

<?php
	echo '</div>';
}
?>