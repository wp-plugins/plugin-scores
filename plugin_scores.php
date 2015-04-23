<?php
/**
 * @package plugin_scores
 * @version 1.5
 */
/*
Plugin Name: plugin scores
Plugin URI: http://www.funsite.eu/plugin_scores
Description: Adds an admin dashboard widget with info on your plugins
Author: Gerhard Hoogterp
Version: 1.5
Author URI: http://www.funsite.eu/
Text Domain: pluginscores
Domain Path: /languages
*/

if (!class_exists('basic_plugin_class')) {
	require(plugin_dir_path(__FILE__).'basics/basic_plugin.class');
}

class plugin_scores_class  extends basic_plugin_class {

	function getPluginBaseName() { return plugin_basename(__FILE__); }
	function getChildClassName() { return get_class($this); }

    public function __construct() {
		parent::__construct();
		add_shortcode( 'plugin_scores', array($this,'showPluginScores') );
		
		add_action('admin_init', array($this,'plugin_scores_headercode'),false,false,true);
		
		add_action('wp_dashboard_setup', array($this,'plugin_scores_dashboard_widgets'));
		add_action( 'admin_menu', array($this,'plugin_scores_menu') );
	}

	function pluginInfoRight($info) {  }
	
	
	const FS_TEXTDOMAIN		= 'pluginscores';
	const FS_PLUGINNAME		= 'plugin-scores';

	
	
	
	const CACHE_TIMEOUT 	= 1800;
	const OPTION_NAME 		= 'myPluginList';
	const HIDDEN_NAME		= 'mpl_submit_hidden';
	const API_URL			= 'http://api.wordpress.org/plugins/info/1.0/';
	const REVIEW_URL		= 'https://wordpress.org/support/view/plugin-reviews/';
	const PLUGIN_URL		= 'https://wordpress.org/plugins/';
	
	
	function showpluginScores( $atts) {
		$res = $this->plugin_scores_create_widget_function();
		return $res;	
	}	

	/**
	* Add a widget to the dashboard.
	*
	* This function is hooked into the 'wp_dashboard_setup' action below.
	*/
	function plugin_scores_dashboard_widgets() {
		wp_add_dashboard_widget(
					'plugin_scores',         // Widget slug.
					__('How do my plugins score?',self::FS_TEXTDOMAIN),      // Title.
					array($this,'plugin_scores_widget_function') // Display function.
			);	
	}

	function plugin_scores_headercode () {
		wp_enqueue_style('plugin_scores_css_handler', plugins_url('/css/plugin_scores.css', __FILE__ ));
	}


	/**
	* Create the function to output the contents of our Dashboard Widget.
	*/
	function showPluginInfo($pluginSlug) {
		$url = self::API_URL.$pluginSlug.'.php';
		$info = unserialize(file_get_contents($url));
		
		$res = "<tr>";
		$res .= '<th><a href="'.self::PLUGIN_URL.$pluginSlug.'/" title="'. __("version",self::FS_TEXTDOMAIN).' '.$info->version.', '. __("last update:",self::FS_TEXTDOMAIN).' '.$info->last_updated.'">'.$pluginSlug.'</a></th> ';
		$res .= '<td class="center'.($info->downloaded?'':' faded').'">'.(int)$info->downloaded.'</td>';
		if ($info->rating) {
			$res .= '<td class="center"><a href="'.self::REVIEW_URL.$pluginSlug.'">'.number_format((int)$info->rating/20,1).'/5</a></td>';
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
		$pluginList = explode("\n",trim(get_option( self::OPTION_NAME )," \n"));
		sort($pluginList);

		//Cleanup and to lowercase
		for($cnt=0;$cnt<count($pluginList);$cnt++) { $pluginList[$cnt]=strtolower(trim($pluginList[$cnt])); }
		$res .=  '<table id="plugin_scores">';
		$res .=  "<thead><tr>";
		$res .=  '	<th>'.__('Plugin',self::FS_TEXTDOMAIN).'</th>';
		$res .=  '	<th class="center">'.__('Downloads',self::FS_TEXTDOMAIN).'</th>';
		$res .=  '	<th class="center">'.__('Rating',self::FS_TEXTDOMAIN).'</th>';
		$res .=  '	<th class="center">'.__('No.ratings',self::FS_TEXTDOMAIN).'</th>';
		$res .=  "</tr></thead>";
		foreach($pluginList as $pluginSlug) {
			$res .= $this->showPluginInfo($pluginSlug);
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
			$result = $this->plugin_scores_create_widget_function();
			set_transient( $transient, $result,self::CACHE_TIMEOUT);
		} 
		print $result;
	}


	function plugin_scores_menu() {
		add_plugins_page( 'My plugins', 'My Plugins', 'manage_options', 'plugins_scores_settings', array($this,'plugin_scores_options') );
	}

	function plugin_scores_options() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.',self::FS_TEXTDOMAIN) );
		}

		// variables for the field and option names 
		$opt_name = self::OPTION_NAME;
		$data_field_name = self::OPTION_NAME;
		$hidden_field_name = self::HIDDEN_NAME;

		// Read in existing option value from database
		$opt_val = get_option( self::OPTION_NAME );

		// See if the user has posted us some information
		// If they did, this hidden field will be set to 'Y'
		if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
			// Read their posted value
			$opt_val = $_POST[ $data_field_name ];

			// Save the posted value in the database
			update_option( $opt_name, $opt_val );
			delete_transient( 'plugin_scores');
			
			// Put an settings updated message on the screen

			?><div class="updated"><p><strong><?php _e('settings saved.', self::FS_TEXTDOMAIN ); ?></strong></p></div><?php
		}
		
		echo '<div class="wrap">';

		// header
		echo "<h2>" . __( 'My plugins list', self::FS_TEXTDOMAIN ) . "</h2>";

		// settings form
		?>

		<form name="form1" method="post" action="">
		<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

		<p><?php _e("Pluginnames, one per line", self::FS_TEXTDOMAIN ); ?><br>
		<textarea name="<?php echo $data_field_name; ?>" style="max-width: 400px;min-height: 300px;"><?php echo $opt_val; ?></textarea>
		</p>
		<hr />

		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes',self::FS_TEXTDOMAIN) ?>" />
		</p>

		</form>

	<?php
		echo '</div>';
	}
	

}


$plugin_scores = new plugin_scores_class();

?>