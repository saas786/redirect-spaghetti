<?php
/*
Plugin Name: Redirect Spaghetti
Description: Enter a URL and view all redirects that are occuring on visit
Version: 1.0.0
Author: Mike Jordan
Author URI: http://knowmike.com/
*/

add_action( 'init', 'MJ_Redirect_Spaghetti::get_instance' );

class MJ_Redirect_Spaghetti {

		/**
	 * @var MJ_Redirect_Spaghetti Instance of the class.
	 */
	private static $instance = false;
	
	/**
	 * Don't use this. Use ::get_instance() instead.
	 */
	public function __construct() {
		if ( !self::$instance ) {
			$message = '<code>' . __CLASS__ . '</code> is a singleton.<br/> Please get an instantiate it with <code>' . __CLASS__ . '::get_instance();</code>';
			wp_die( $message );
		}	
	}
	
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = true;
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}
	
	/**
	 * Initial setup. Called by get_instance.
	 */
	protected function init() {

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		$plugin_basename = plugin_basename( __FILE__ ); 
		add_filter( "plugin_action_links_$plugin_basename", array( $this, 'plugin_action_links' ) );
		
		add_action( 'admin_init', array( $this, 'admin_init' ) );
				
	}
	
	/**
	 * Add tools menu item
	 */
	function admin_menu(){

		add_submenu_page( 'tools.php', 'Redirect Spaghetti', 'Redirect Spaghetti', 'administrator', 'redirect-spaghetti', array( $this, 'display_settings' ) );	
	}
	
	/**
	 * Display admin page 
	 */
	function display_settings(){
		echo '<div class="wrap">';
		echo '<h2>Redirect Spaghetti</h2>';
		echo '<form name="rs_form" method="post" action="options.php">';
		settings_fields( 'rs-settings-group' ); 
		do_settings_sections( 'redirect-spaghetti' ); 
		submit_button( 'View Redirects' );
		echo '</form>';

		$source_url = get_option( 'rs-source-url' );
		if ( $source_url ){
			echo "<p>Redirects for '$source_url':</p>";
			echo '<ol>';
			$this->map_redirects( $source_url );
			echo '</ol>';
		}

		echo '</div>';
	}
	
	/**
	 * Initialize components of admin page
	 */
	function admin_init(){

		$source_url = get_option( 'rs-source-url' );
		
		add_settings_section(  
		    'rs_general_options_section',           
		    '',                    
		    array( $this, 'general_options_section_callback' ),   
			'redirect-spaghetti'
		);
		
		add_settings_field(   
		    'rs_source',                       
		    'Source URL',                
		    array( $this, 'checkbox_callback' ),  
		    'redirect-spaghetti',                          
		    'rs_general_options_section',           
		    array(                               
		        'id' => 'rs-source-url'
		    )  
		);
		
		register_setting( 'rs-settings-group', 'rs-source-url' );
	}
	
	function general_options_section_callback(){
		echo '<p>Please enter a URL that you would like redirection info on, exactly as you would type it into a browser.</p>';		
	}
	
	
	function checkbox_callback( ){
		$source_url = get_option( 'rs-source-url' );
		echo "http://" . $_SERVER['HTTP_HOST'] . "<input type='text' id='rs-source-url' name='rs-source-url' value='$source_url'>";
	}

	function map_redirects( $url ){

		// Dev note: currently using CURL because the WP HTTP api does not return redirect location data :(

        $ch = curl_init();
        $timeout = 15;
        curl_setopt ($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'].$url);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);

        // Collect data
        $header = curl_exec($ch);
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach( $fields as $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
                if( isset($retVal[$match[1]]) ) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        
			// A redirect does exist, so print
		if (isset($retVal['Location'])){
			 echo '<li>';
		     echo $url .' ----> <strong>'. $retVal['Location'] .'</strong>';
		     echo '<br />( '.$fields[0] .' )';
		     echo '</li>';

		     $this->map_redirects( $retVal['Location'] );

		} else {
		     echo '<li>';
		     echo "<strong> Final destination:  $url </strong> ";
		     if ( $fields[0] ){
		     	echo '<br />( '.$fields[0] .' )';
		     }
		     echo '</li>';
		}
		curl_close($ch);
		
	}
	
	/**
	 * Add settings page link for this plugin
	 *
	 * @return array 
	 */
	function plugin_action_links( $links ){
	
		$settings_link = '<a href="tools.php?page=redirect-spaghetti">Enter URL</a>'; 
		array_unshift( $links, $settings_link ); 
		
		return $links;
	}
	
}

