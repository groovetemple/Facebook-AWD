<?php
/*
Plugin Name: Facebook AWD All in One
Plugin URI: http://facebook-awd.ahwebdev.fr
Description: This plugin integrates Facebook open graph, Plugins from facebook, and FB connect, with SDK JS AND SDK PHP 3.2 Facebook
Version: 1.4 Beta
Author: AHWEBDEV
Author URI: http://www.ahwebdev.fr
License: Copywrite AHWEBDEV
Text Domain: AWD_facebook
Last modification: 24/03/2012
*/


/**
 * @author Hermann Alexandre AHWEBDEV 2012
 *
 * http://www.ahwebdev.fr
 */

Class AWD_facebook
{
	//****************************************************************************************
	//	VARS
	//****************************************************************************************
    /***
     * public
     * Name of the plugin
     */
    public $plugin_name = 'Facebook AWD';
    
    /**
     * public
     * slug of the plugin
     */
    public $plugin_slug = 'awd_fcbk';
    
    /**
     * private
     * preffix blog option
     */
    public $plugin_option_pref = 'awd_fcbk_option_';
    
    /**
     * public
     * preffix blog option
     */
    public $plugin_page_admin_name = 'Facebook Admin';
    
    /**
     * public
     * preffix blog option
     */
    public $ptd = 'AWD_facebook';
    
    /**
     * private
     * position of the menu in admin
     */
    public $blog_admin_hook_position;
    
    /**
     * private
     * hook admin
     */
    public $blog_admin_page_hook;
    
	/**
     * public
     * current_user
     */
    public $current_user;
    
    /**
     * public
     * Name of the file of the plugin
     */
    public $file_name = "AWD_facebook.php";
    
    /**
     * public
     * Options of the plugin
     */
    public $options = array();
    
    /**
     * global message admin
     */
    public $message;
    
    /**
     * me represent the facebook user datas
     */
    public $me = null;
    
    /**
     * fcbk represent the facebook php SDK instance
     */
    public $fcbk = null;
    
    /**
     * the ID of the current facebook user.
     */
    public $uid = null;
    
    
	//****************************************************************************************
	//	GLOBALS FUNCTIONS
	//****************************************************************************************    
	/**
	 * Setter $this->current_user
	 * @return void
	 */
	public function get_current_user()
	{
		global $current_user;
      	get_currentuserinfo();

		$this->current_user = $current_user;
		return $this->current_user;
	}
	
    /**
     * Getter current user Ip
	 * @return $ip
	 */
	public function get_ip()
	{
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else
			$ip = $_SERVER['REMOTE_ADDR'];  
		
		echo $ip;      
	}
	
	/**
	 * Getter Version
	 * @param array $plugin_folder_var
	 * @return array
	 */
	public function get_version(array $plugin_folder_var = array())
	{
	    if(count($plugin_folder_var)==0){
	    	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	        $plugin_folder = get_plugins();
	    }
	    return $plugin_folder['facebook-awd/AWD_facebook.php']['Version'];
	}

    /**
     * Remove a slash to pages with .html inside url
     * @param string $string
     * @param string $type (post, page, etc...)
     * @return string
     */
    public function add_page_slash($string, $type)
    {
        global $wp_rewrite;
        if(ereg(".html",$string) && $wp_rewrite->use_trailing_slashes){
        	return untrailingslashit($string);
        }
        return $string;
    }
  
    /**
	 *
     * Getter
     * the first image displayed in a post.
     * @param string $post_content
     * @return the image found.
     */                      
	public function catch_that_image($post_content="")
	{
		global $post;
		if($post_content=="" && is_object($post))
			$post_content = $post->post_content;
  		$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post_content, $matches);
  		$first_img = $matches [1] [0];
  		return $first_img;
	}
	
	
	/**
	 * Debug a var
	 * @param var $var
	 * @param boolean $detail default = false
	 * @return void
	 */
	public function Debug($var,$detail=0)
	{
		echo "<pre>";
		if($detail != 0){
			var_dump($var);
		}else{
			print_r($var);
		}
		echo "</pre>";
	}
	
	/**
	 * Get current URL
	 * @return string current url
	 */
	public function get_current_url()
	{
	    return (empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
	}
	
	public function get_plugins_model_path(){
		return realpath(dirname(__FILE__)).'/inc/classes/plugins/class.AWD_facebook_plugin_abstract.php';
	}
	
	//****************************************************************************************
	//	INIT
	//****************************************************************************************
	/**
	 * plugin construct
	 * @return void
	 */
	public function __construct()
	{		
		//init the plugin and action
		add_action('plugins_loaded',array(&$this,'initial'));
		//like box widget register
		add_action('widgets_init',  array(&$this,'register_AWD_facebook_widgets'));
		
		//Base vars
		$this->_login_url = get_option('permalink_structure') != '' ? home_url('facebook-awd/login') : home_url('?facebook_awd[action]=login');
		$this->_logout_url = get_option('permalink_structure') != '' ? home_url('facebook-awd/logout') : home_url('?facebook_awd[action]=logout');
		$this->_unsync_url = get_option('permalink_structure') != '' ? home_url('facebook-awd/unsync') : home_url('?facebook_awd[action]=unsync');
	}
	
	/**
	 * hook action added to init
	 * @return void
	 */
	public function wp_init()
	{		
		//Js
		wp_register_script($this->plugin_slug.'-bootstrap-js',$this->plugin_url.'/assets/js/bootstrap.js',array('jquery'));
		wp_register_script($this->plugin_slug.'-google-code-prettify',$this->plugin_url.'/assets/js/google-code-prettify/prettify.js',array('jquery'));
		wp_register_script($this->plugin_slug.'-admin-js',$this->plugin_url.'/assets/js/facebook_awd_admin.js',array('jquery','jquery-ui-tabs','jquery-ui-accordion',$this->plugin_slug.'-google-code-prettify'));
		wp_register_script($this->plugin_slug,$this->plugin_url.'/assets/js/facebook_awd.js',array('jquery'));
		
		//Css
		wp_register_style($this->plugin_slug.'-ui-bootstrap', $this->plugin_url.'/assets/css/bootstrap.css');
		//wp_register_style($this->plugin_slug.'-ui-bootstrap-responsive', $this->plugin_url.'/assets/css/bootstrap-responsive.min.css');
		wp_register_style($this->plugin_slug.'-google-code-prettify-css',$this->plugin_url.'/assets/js/google-code-prettify/prettify.css');
	}
	
	/**
	 * Getter
	 * the fbuid of the admin
	 * @return int UID Facebook
	 */
	public function get_admin_fbuid()
	{
        $admin_email = get_option('admin_email');
        $admin_user = get_user_by('email', $admin_email);
        $fbadmin_uid = get_user_meta($admin_user->ID,'fb_uid', true);
        return $fbadmin_uid;
	}
	
	/**
	 * plugin init
	 * @return void
	 */
	public function initial()
	{
		global $wpdb;
		include_once(dirname(__FILE__).'/inc/init.php');
	}
	
	/**
	 * add support for ogimage opengraph
	 * @return void
	 */
	public function add_theme_support()
	{
		//add fetured image menu to get FB image in open Graph set image 50x50
		if (function_exists('add_theme_support')) {
			add_theme_support('post-thumbnails');
			add_image_size('AWD_facebook_ogimage', 200, 200, true);
		}
		//add featured image + post excerpt in post type too.
		if(function_exists('add_post_type_support')) {
			$post_types = get_post_types();
			foreach($post_types as $type){
				add_post_type_support($type,array('thumbnails','excerpt'));
			}
		}
	}
	
	
	//****************************************************************************************
	//	MESSAGE ADMIN
	//****************************************************************************************
	/**
	 * missing config notices
	 * @return void
	 */
	public function missing_config()
	{
		if($this->options['app_id'] =='')
		{
			$this->errors[] = new WP_Error('AWD_facebook_not_ready', __('Facebook AWD is almost ready... Go to settings and set your FB Application ID.', $this->ptd));
		}
		if($this->options['app_secret_key'] =='')
		{
			$this->errors[] = new WP_Error('AWD_facebook_not_ready', __('Facebook AWD is almost ready... Go to settings and set your FB Secret Key.', $this->ptd));
		}
	}
	
	
	/**
	 * Display Error in admin Facebook AWD area
	 * @return void
	 */
	public function display_all_errors()
	{
		$html = '';
		if(isset($this->errors) && count($this->errors) > 0 AND is_array($this->errors)){
			foreach($this->errors as $error){
				if(is_wp_error($error))
					$html .= $this->display_messages($error->get_error_message(), 'error', false);
			}
			echo $html;
		}
		if(isset($this->warnings) && count($this->warnings) > 0 AND is_array($this->warnings)){
			foreach($this->warnings as $warning){
				if(is_wp_error($warning))
					$html .= $this->display_messages($warning->get_error_message(), 'warning', false);
			}
			echo $html;
		}
	}
	
	/**
	 * Display Message in admin Facebook AWD area
	 * @return void
	 */
	public function display_messages($message = null, $type = 'info', $echo = true)
	{
		$html = '';
		if(!empty($message)){
			$html = '<div class="alert alert-'.$type.'">'.$message.'</div>';
		}else if(isset($this->messages) && count($this->messages) > 0 AND is_array($this->messages)){
			foreach($this->messages as $key=>$message){
				if(is_string($type))
					$type = $key;
				$html.= '<div class="alert alert-'.$type.'">'.$message.'</div>';
			}
		}
		if(!$echo)
			return $html;
		
		echo $html;
	}
	
	/**
	 * Getter
	 * Help in the plugin tooltip
	 * @param string $elem
	 * @param string $class
	 * @param string $image
	 * @return string a link to open lightbox with linked content
	 */
	public function get_the_help($elem,$class="help awd_tooltip",$image='info.png')
	{
		return '<a href="#" class="'.$class.'" id="help_'.$elem.'"><i class="icon-question-sign"></i></a>';
	}

	
	//****************************************************************************************
	//	ADMIN
	//****************************************************************************************
	
	/**
	 * Checks if we should add links to the bar.
	 * @return void
	 */
	public function admin_bar_init()
	{
		// Is the user sufficiently leveled, or has the bar been disabled?
		if (!is_super_admin() || !is_admin_bar_showing() )
			return;
	 
		// Good to go, lets do this!
		add_action('admin_bar_menu', array(&$this,'admin_bar_links'),500);
	}
	
	/**
	 * Add links to the Admin bar.
	 * @return void
	 */
	public function admin_bar_links()
	{
		global $wp_admin_bar;
		$links = array();
		
		if($this->is_user_logged_in_facebook() && isset($this->me['link'])){
			$links[] = array(__('My Profile',$this->ptd), $this->me['link']);
		}
		if($this->is_user_logged_in_facebook()){
			$links[] = array(__('Refresh Facebook Data',$this->ptd),  $this->_login_url);
			$links[] = array(__('Unsync FB Account',$this->ptd), $this->_unsync_url);
		}
		
		if(current_user_can('manage_options')){
			$links[] = array(__('Settings',$this->ptd),admin_url('admin.php?page='.$this->plugin_slug));
			$links[] = array(__('Documentation',$this->ptd),'http://facebook-awd.ahwebdev.fr/documentation/');
			$links[] = array(__('Support',$this->ptd),'http://facebook-awd.ahwebdev.fr/support/');
			if(!is_admin())
				$links[] = array(__('Debugger',$this->ptd),'http://developers.facebook.com/tools/debug/og/object?q='.urlencode($this->get_current_url()));
		}
		$links = apply_filters('AWD_facebook_admin_bar_links', $links);
		
		if(count($links)){
			$wp_admin_bar->add_menu( array(
				'title' => '<img style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/> '.$this->plugin_name,
				'href' => false,
				'id' => $this->plugin_slug,
				'href' => false
			));
			foreach ($links as $link => $infos) {
				$wp_admin_bar->add_menu( array(
					'id' => $this->plugin_slug.'_submenu'.$link,
					'title' => $infos[0],
					'href' => $infos[1],
					'parent' => $this->plugin_slug,
					'meta' => array('target' => '_blank')
				));
			}
		}
	}
	
	/**
	 * Save customs fields during post edition
	 * @param int $post_id
	 * @return void
	 */
	public function save_options_post_editor($post_id)
	{
		$fb_publish_to_pages = false;
		$fb_publish_to_user = false;
		$post = get_post($post_id);
		if(!wp_is_post_revision($post->ID)){
			$narray = array();
			foreach($_POST as $__post=>$val){
				//should have ogtags in prefix present to be saved
				if(preg_match('@'.$this->plugin_option_pref.'@',$__post)){
					$name = str_ireplace($this->plugin_option_pref,'',$__post);
					$narray[$name] = $val;
				}
			}
			update_post_meta($post->ID, $this->plugin_slug, $narray);
			//check if the post is published
			if($post->post_status == 'publish'){
				//check if facebook user before to try to publish
				if($this->is_user_logged_in_facebook()){
					//Publish to Graph api
					$message = $_POST[$this->plugin_option_pref.'fbpublish']['message_text'];
					$read_more_text = $_POST[$this->plugin_option_pref.'fbpublish']['read_more_text'];
					//Check if we want to publish on facebook pages and profile
					if($_POST[$this->plugin_option_pref.'fbpublish']['to_pages'] == 1 && $this->current_facebook_user_can('publish_stream') && $this->current_facebook_user_can('manage_pages')){
						$fb_publish_to_pages = $this->get_pages_to_publish();
						if(count($fb_publish_to_pages)>0){
							$this->publish_post_to_facebook($message,$read_more_text,$fb_publish_to_pages,$post->ID);
						}
					}
					//Check if we want to publish on facebook pages and profile
					if($_POST[$this->plugin_option_pref.'fbpublish']['to_profile'] == 1 && $this->current_facebook_user_can('publish_stream')){
						$this->publish_post_to_facebook($message,$read_more_text, $this->uid ,$post->ID);
					}
				}
			}
		}
	}
	
	/**

	 * Add footer text ads Facebook AWD version

	 * @param string $footer_text
	 * @return string  the text to add in footer

	 */
	public function admin_footer_text($footer_text)
	{
	    return $footer_text."  ".__('| With:',$this->ptd)." <a href='http://www.ahwebdev.fr/plugins/facebook-awd.html'>".$this->plugin_name." v".$this->get_version()."</a>";
	}
	
	
	/**
	 * Set Admin Roles
	 * Add FB capabalities to default WP roles
	 * @return void
	 */
	public function set_admin_roles()
	{
		$roles = array(
			'administrator' => array(
				'manage_facebook_awd_settings',
				'manage_facebook_awd_plugins',
				'manage_facebook_awd_opengraph',
				'manage_facebook_awd_publish_to_pages',
			),
			'editor' => array(
				'manage_facebook_awd_publish_to_pages',
				'manage_facebook_awd_opengraph'
			),
			'author' => array(
				'manage_facebook_awd_publish_to_pages'
			)
		);
		$roles = apply_filters('AWD_facebook_admin_roles', $roles);
		foreach($roles as $role=>$caps){
			$wp_role = get_role($role);
			foreach($caps as $cap){
				$wp_role->add_cap($cap);
			}
		}
	}
	
	/**
	 * Admin plugin init menu
	 * call form init.php
	 * @return void
	 */
	public function admin_menu()
	{
		$this->set_admin_roles();
		
		add_action('save_post', array(&$this,'save_options_post_editor'));
		
		//global $wp_roles;
		//print_r($wp_roles);
		//exit();
		
		//admin hook
		$this->blog_admin_page_hook = add_menu_page($this->plugin_page_admin_name, __($this->plugin_name,$this->ptd), 'manage_facebook_awd_publish_to_pages', $this->plugin_slug, array($this,'admin_content'), $this->plugin_url_images.'facebook-mini.png',$this->blog_admin_hook_position);
		$this->blog_admin_settings_hook = add_submenu_page($this->plugin_slug, __('Settings',$this->ptd), '<img src="'.$this->plugin_url_images.'settings.png" /> '.__('Settings',$this->ptd), 'manage_facebook_awd_publish_to_pages', $this->plugin_slug);
		$this->blog_admin_plugins_hook = add_submenu_page($this->plugin_slug, __('Plugins',$this->ptd), '<img src="'.$this->plugin_url_images.'plugins.png" /> '.__('Plugins',$this->ptd), 'manage_facebook_awd_plugins', $this->plugin_slug.'_plugins', array($this,'admin_content'));
		if($this->options['open_graph_enable'] == 1){
			$this->blog_admin_opengraph_hook = add_submenu_page($this->plugin_slug, __('Open Graph',$this->ptd), '<img src="'.$this->plugin_url_images.'ogp-logo.png" /> '.__('Open Graph',$this->ptd), 'manage_facebook_awd_opengraph', $this->plugin_slug.'_open_graph', array($this,'admin_content'));
			add_action( "load-".$this->blog_admin_opengraph_hook, array(&$this,'admin_initialisation'));
			add_action( 'admin_print_styles-'.$this->blog_admin_opengraph_hook, array(&$this,'admin_enqueue_css'));
			add_action( 'admin_print_scripts-'.$this->blog_admin_opengraph_hook, array(&$this,'admin_enqueue_js'));
		}
		add_action( "load-".$this->blog_admin_page_hook, array(&$this,'admin_initialisation'));
		add_action( "load-".$this->blog_admin_plugins_hook, array(&$this,'admin_initialisation'));
		add_action( 'admin_print_styles-'.$this->blog_admin_page_hook, array(&$this,'admin_enqueue_css'));		
		add_action( 'admin_print_styles-'.$this->blog_admin_plugins_hook, array(&$this,'admin_enqueue_css'));
		add_action( 'admin_print_styles-post-new.php', array(&$this,'admin_enqueue_css'));
		add_action( 'admin_print_styles-post.php', array(&$this,'admin_enqueue_css'));
		add_action( 'admin_print_scripts-'.$this->blog_admin_page_hook, array(&$this,'admin_enqueue_js'));
		add_action( 'admin_print_scripts-'.$this->blog_admin_plugins_hook, array(&$this,'admin_enqueue_js'));
		add_action( 'admin_print_scripts-post-new.php', array(&$this,'admin_enqueue_js'));
		add_action( 'admin_print_scripts-post.php', array(&$this,'admin_enqueue_js'));
		add_action( 'admin_print_scripts-link-add.php', array(&$this,'admin_enqueue_js'));
		add_action( 'admin_print_scripts-link.php', array(&$this,'admin_enqueue_js'));
		add_action( 'admin_print_styles-link-add.php', array(&$this,'admin_enqueue_css'));
		add_action( 'admin_print_styles-link.php', array(&$this,'admin_enqueue_css'));
		
		//test-widgets.php
		add_action( 'admin_print_styles-widgets.php', array(&$this,'admin_enqueue_css'));
		
		//enqueue here the library facebook connect
		$this->add_js_options();
		//Add meta box
		$this->add_meta_boxes();
	}
	
	/**
	* Admin initialisation
	* @return void
	*/
	public function admin_initialisation()
	{			
		//add 2 column screen
		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));
	}
	
	/**
	 * Add meta boxes for admin
	 * @return void
	 */
	public function add_meta_boxes()
	{
		
		$icon = isset($this->options['app_infos']['icon_url']) ? '<img style="vertical-align:middle;" src="'.$this->options['app_infos']['icon_url'].'" alt=""/>' : '';
	
		//Settings page
		if($this->blog_admin_page_hook != ''){
			add_meta_box($this->plugin_slug."_settings_metabox", __('Settings',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'settings.png" />', array(&$this,'settings_content'), $this->blog_admin_page_hook , 'normal', 'core');
			add_meta_box($this->plugin_slug."_meta_metabox",  __('My Facebook',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/>', array(&$this,'fcbk_content'),  $this->blog_admin_page_hook, 'side', 'core');
			add_meta_box($this->plugin_slug."_app_infos_metabox",  __('Application Infos', $this->ptd).' '.$icon, array(&$this,'app_infos_content'),  $this->blog_admin_page_hook, 'side', 'core');
			add_meta_box($this->plugin_slug."_info_metabox",  __('Informations',$this->ptd), array(&$this,'general_content'),  $this->blog_admin_page_hook, 'side', 'core');
			if(current_user_can('manage_facebook_awd_settings')){
				add_meta_box($this->plugin_slug."_activity_metabox",  __('Activity on your site',$this->ptd), array(&$this,'activity_content'),  $this->blog_admin_page_hook , 'side', 'core');
			}
		}
		//Plugins page
		if($this->blog_admin_plugins_hook != ''){
			add_meta_box($this->plugin_slug."_plugins_metabox", __('Plugins Settings',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'plugins.png" />', array(&$this,'plugins_content'),  $this->blog_admin_plugins_hook , 'normal', 'core');
			add_meta_box($this->plugin_slug."_meta_metabox",  __('My Facebook',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/>', array(&$this,'fcbk_content'),  $this->blog_admin_plugins_hook , 'side', 'core');
			add_meta_box($this->plugin_slug."_app_infos_metabox",  __('Application Infos', $this->ptd).' '.$icon, array(&$this,'app_infos_content'),  $this->blog_admin_plugins_hook , 'side', 'core');
			add_meta_box($this->plugin_slug."_info_metabox",  __('Informations',$this->ptd), array(&$this,'general_content'),  $this->blog_admin_plugins_hook , 'side', 'core');
			if(current_user_can('manage_facebook_awd_settings')){
				add_meta_box($this->plugin_slug."_activity_metabox",  __('Activity on your site',$this->ptd), array(&$this,'activity_content'),  $this->blog_admin_plugins_hook , 'side', 'core');
			}
		}
		$post_types = get_post_types();
		foreach($post_types as $type){
			//Like button manager on post page type
			add_meta_box($this->plugin_slug."_awd_mini_form_metabox", __('Facebook AWD Manager',$this->ptd).' <img style="vertical-align:middle;" style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/>', array(&$this,'post_manager_content'),  $type , 'side', 'core');
		}
		//add_meta_box($this->plugin_slug."_awd_mini_form_metabox", __('Facebook AWD Manager',$this->ptd).' <img style="vertical-align:middle;" style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/>', array(&$this,'post_manager_content'),  'link' , 'side', 'core');

		if($this->blog_admin_opengraph_hook != ''){
			if($this->options['open_graph_enable'] == 1){	
				add_meta_box($this->plugin_slug."_open_graph_metabox", __('Open Graph',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'ogp-logo.png" />', array(&$this,'open_graph_content'),  $this->blog_admin_opengraph_hook, 'normal', 'core');
				add_meta_box($this->plugin_slug."_meta_metabox",  __('My Facebook',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/>', array(&$this,'fcbk_content'),  $this->blog_admin_opengraph_hook , 'side', 'core');
				add_meta_box($this->plugin_slug."_app_infos_metabox",  __('Application Infos', $this->ptd).' '.$icon, array(&$this,'app_infos_content'),  $this->blog_admin_opengraph_hook , 'side', 'core');
				add_meta_box($this->plugin_slug."_info_metabox",  __('Informations',$this->ptd), array(&$this,'general_content'),  $this->blog_admin_opengraph_hook , 'side', 'core');
				if(current_user_can('manage_facebook_awd_settings')){
					add_meta_box($this->plugin_slug."_activity_metabox",  __('Activity on your site',$this->ptd), array(&$this,'activity_content'),  $this->blog_admin_opengraph_hook , 'side', 'core');
				}
			}
		}
		
		//Call the menu init to get page hook for each menu
		do_action('AWD_facebook_admin_menu');
		//For each page hook declared in plugins add side meta box
		$plugins = $this->plugins;
		if(is_array($plugins)){
			foreach($plugins as $plugin){
				if(isset($plugin->plugin_admin_hook) &&  $plugin->plugin_admin_hook != ''){
					$page_hook = $plugin->plugin_admin_hook;
					add_meta_box($this->plugin_slug."_meta_metabox",  __('My Facebook',$this->ptd).' <img style="vertical-align:middle;" src="'.$this->plugin_url_images.'facebook-mini.png" alt="facebook logo"/>', array(&$this,'fcbk_content'),  $page_hook , 'side', 'core');
					add_meta_box($this->plugin_slug."_app_infos_metabox",  __('Application Infos', $this->ptd).' '.$icon, array(&$this,'app_infos_content'),  $page_hook , 'side', 'core');
					add_meta_box($this->plugin_slug."_info_metabox",  __('Informations',$this->ptd), array(&$this,'general_content'),  $page_hook , 'side', 'core');
					if(current_user_can('manage_facebook_awd_settings')){
						add_meta_box($this->plugin_slug."_activity_metabox",  __('Activity on your site',$this->ptd), array(&$this,'activity_content'),  $page_hook , 'side', 'core');
					}
				}
			}
		}
	}
	
	/**
	 * Admin css enqueue Stylesheet
	 * @return void
	 */
	public function admin_enqueue_css()
	{
		//wp_enqueue_style($this->plugin_slug.'-ui-bootstrap-responsive');
		wp_enqueue_style($this->plugin_slug.'-ui-bootstrap');
		wp_enqueue_style($this->plugin_slug.'-google-code-prettify-css');
		wp_enqueue_style('thickbox');
	}
	
	/**
	 * Admin js enqueue Javascript
	 * @return void
	 */
	public function admin_enqueue_js()
	{
		wp_enqueue_script('media-upload');
		wp_enqueue_script('thickbox');
		wp_enqueue_script('common');
		wp_enqueue_script('wp-list');
		wp_enqueue_script('postbox');
		wp_enqueue_script('jquery-ui-accordion');
		wp_enqueue_script($this->plugin_slug.'-js-cookie');
		wp_enqueue_script($this->plugin_slug.'-admin-js');
		wp_enqueue_script($this->plugin_slug.'-bootstrap-js');
		wp_enqueue_script($this->plugin_slug.'-google-code-prettify');
	}
	
	public function add_js_options($manual = 0)
	{
		wp_enqueue_script($this->plugin_slug);
		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		$AWD_facebook_vars = array(
			'ajaxurl' 	=> admin_url('admin-ajax.php'),
			'homeUrl' 	=> home_url(),
			'loginUrl' 	=> $this->_login_url,
			'logoutUrl' => $this->_logout_url,
			'scope' 	=> current_user_can("manage_options") ? $this->options["perms_admin"] : $this->options["perms"],
			'app_id'    => $this->options['app_id'],
			'FBEventHandler' => array('callbacks'=>array())
		);
		
		//fix for wp_localize_script that is not called on wp-login.php page
		if($manual == 1){
			$temp = array();
			$temp = $AWD_facebook_vars;
			echo '<script type="text/javascript">var '.$this->plugin_slug.'='.json_encode($temp).';</script>';
		}
		
		$AWD_facebook_vars = apply_filters('AWD_facebook_js_vars', $AWD_facebook_vars);
		wp_localize_script($this->plugin_slug, $this->plugin_slug, $AWD_facebook_vars);
	}

	/**
	 * Get the help Box
	 * @param string $type
	 * @return string the box in html format
	 */		                               
	public function get_the_help_box($type)
	{
		$html = '<p><u>'.__('You can use Pattern code in fields, paste it where you need:',$this->ptd).'</u></p>';
		switch($type){
			case 'taxonomies':
				$html .= '<div class="awd_pre">';
					$html .= '<p><b>%BLOG_TITLE%</b> - '.__('Use blog name',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_DESCRIPTION%</b> - '.__('Use blog description',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_URL%</b> - '.__('Use blog url',$this->ptd).'</p>';
					$html .= '<p><b>%TERM_TITLE%</b> - '.__('Use term name',$this->ptd).'</p>';
					$html .= '<p><b>%TERM_DESCRIPTION%</b> - '.__('Use term description',$this->ptd).'</p>';
				$html .= '</div>';
			break;
			case 'frontpage':
				$html .= '<div class="awd_pre">';
					$html .= '<p><b>%BLOG_TITLE%</b> - '.__('Use blog name',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_DESCRIPTION%</b> - '.__('Use blog description',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_URL%</b> - '.__('Use blog url',$this->ptd).'</p>';
				$html .= '</div>';
			break;
			case 'archive':
				$html .= '<div class="awd_pre">';
				 	$html .= '<p><b>%BLOG_TITLE%</b> - '.__('Use blog name',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_DESCRIPTION%</b> - '.__('Use blog description',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_URL%</b> - '.__('Use blog url',$this->ptd).'</p>';
					$html .= '<p><b>%ARCHIVE_TITLE%</b> - '.__('Use archive name',$this->ptd).'</p>';
				$html .= '</div>';
			break;
			case 'author':
				$html .= '<div class="awd_pre">';
					$html .= '<p><b>%BLOG_TITLE%</b> - '.__('Use blog name',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_DESCRIPTION%</b> - '.__('Use blog description',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_URL%</b> - '.__('Use blog url',$this->ptd).'</p>';
					$html .= '<p><b>%AUTHOR_TITLE%</b> - '.__('Use title of post',$this->ptd).'</p>';
					$html .= '<p><b>%AUTHOR_IMAGE%</b> - '.__('Use excerpt',$this->ptd).'</p>';
					$html .= '<p><b>%AUTHOR_DESCRIPTION%</b></p>';
				$html .= '</div>';
			break;
			case 'custom_post_types':
			case 'post':
			case 'page':
			default:
				$html .= '<div class="awd_pre">';
					$html .= '<p><b>%BLOG_TITLE%</b> - '.__('Use blog name',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_DESCRIPTION%</b> - '.__('Use blog description',$this->ptd).'</p>';
					$html .= '<p><b>%BLOG_URL%</b> - '.__('Use blog url',$this->ptd).'</p>';
					$html .= '<p><b>%POST_TITLE%</b> - '.__('Use title of post',$this->ptd).'</p>';
					$html .= '<p><b>%POST_EXCERPT%</b> - '.__('Use excerpt',$this->ptd).'</p>';
					$html .= '<p><b>%POST_IMAGE%</b> - '.__('Use featured image (if activated)',$this->ptd).'</p>';
				$html .= '</div>';
		}
		return $html;
	}
	
	/**
	 * Admin Infos
	 * @return void
	 */
	public function general_content()
	{
		if(current_user_can('manage_facebook_awd_settings')){
			echo '<h2>'.__('Plugins installed',$this->ptd).'</h2>';
			if(is_array($this->plugins) && count($this->plugins)){
				foreach($this->plugins as $plugin){
					echo'
					<p><span class="label label-success">
						'.$plugin->plugin_name.'
						<small>v'.$plugin->get_version().'</small>
					</span></p>';
				}
			}else{
				echo'
				<p><span class="label label-inverse">'.__('No plugin found',$this->ptd).'</span></p>';
			}
			echo'
			<p><a href="http://facebook-awd.ahwebdev.fr/plugins/" class="btn btn-important" target="blank">'.__('Find plugins',$this->ptd).'</a></p>';
		}
		
		echo '<h4>'.__('Follow me on Facebook',$this->ptd).'</h4>';
		echo do_shortcode('[AWD_likebox href="https://www.facebook.com/Ahwebdev" colorscheme="light" stream="0" show_faces="0" xfbml="0" header="0" width="257" height="60"]');
		echo '<h4>'.__('Follow me on Twitter',$this->ptd).'</h4>
		<a href="https://twitter.com/ah_webdev" class="twitter-follow-button" data-show-count="false" data-size="large" data-show-screen-name="true">Follow @ah_webdev</a>
		<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
		';

	}
	
	
	/**
	 * Get app infos content model
	 * @return void
	 */
	public function get_app_infos_content()
	{	
		echo $this->get_app_info();
		//call the app_content function
		echo $this->app_infos_content();
		exit();
	}
	
	/**
	 * Get App infos form api and store it in options
	 * @return string $errors
	 */
	public function get_app_info()
	{
		if(is_object($this->fcbk)){
			try{
				$app_info = $this->fcbk->api('/'.$this->options['app_id']);
				$this->options['app_infos'] = $this->optionsManager->updateOption('app_infos', $app_info, true);
			}catch(Exception $e){
				$this->options['app_infos'] = $this->optionsManager->updateOption('app_infos', array(), true);
				$error = new WP_Error($e->getCode(), $e->getMessage());
				$this->display_messages($error->get_error_message(), 'error', false);
			}
		}
		return false;
	}
	
	/**
	 * Application infos content
	 * @return void
	 */
	public function app_infos_content()
	{	
		
		$infos = $this->options['app_infos'];
		if(empty($infos)){
			$error = new WP_Error('AWD_facebook_not_ready', __('You must set a valid Facebook Application ID and Secret Key and your Facebook User ID in settings.',$this->ptd));
			echo $error->get_error_message();
			echo '<br /><a href="#" id="reload_app_infos" class="btn btn-danger" data-loading-text="<i class=\'icon-time icon-white\'></i> Testing... "><i class="icon-refresh icon-white"></i> '.__('Reload',$this->ptd).'</a>';
		}else{
			?>
			<div id="awd_app">
				<table class="table table-condensed">
					<thead>
						<th><?php _e('Info' ,$this->ptd); ?></th>
						<th><?php _e('Value' ,$this->ptd); ?></th>
					</thead>
					<tbody>
						<tr>
							<th><?php _e('Name' ,$this->ptd); ?>:</th>
							<td><?php echo $infos['name']; ?></td>
						</tr>
						<tr>
							<th>ID:</th>
							<td><?php echo $infos['id']; ?></td>
						</tr>
						<tr>
							<th><?php _e('Link' ,$this->ptd); ?>:</th>
							<td><a href="<?php echo $infos['link']; ?>" target="_blank">View App</a></td>
						</tr>
						<tr>
							<th><?php _e('Namespace' ,$this->ptd); ?>:</th>
							<td><?php echo $infos['namespace']; ?></td>
						</tr>
						<tr>
							<th><?php _e('Daily active users' ,$this->ptd); ?>:</th>
							<td class="app_active_users"><?php echo isset($infos['daily_active_users']) ? $infos['daily_active_users'] : 0; ?></td>
						</tr>
						<tr>
							<th><?php _e('Weekly active users' ,$this->ptd); ?>:</th>
							<td class="app_active_users"><?php echo isset($infos['weekly_active_users']) ? $infos['weekly_active_users'] : 0; ?></td>
						</tr>
						<tr>
							<th><?php _e('Monthly active users' ,$this->ptd); ?>:</th>
							<td class="app_active_users"><?php echo isset($infos['monthly_active_users']) ? $infos['monthly_active_users'] : 0; ?></td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th><img src="<?php echo $infos['logo_url']; ?>" class="thumbnail"/></th>
							<?php if(current_user_can('manage_facebook_awd_settings')){ ?>
								<td><a href="#" id="reload_app_infos" class="btn btnNormal" data-loading-text="<i class='icon-time'></i> Loading..."><i class="icon-wrench"></i> <?php _e('Test Settings',$this->ptd); ?></a></td>
							<?php }else{ ?>
								<td></td>
							<?php } ?>
						</tr>
					</tfoot>
				</table>
			</div>
			<?php
		}
	}
	
	/**
	 * Admin content
	 * @return void
	 */
	public function admin_content()
	{
		include_once(dirname(__FILE__).'/inc/admin/views/admin.php');
	}
	
	/**
	 * Open graph admin content
	 * @return void
	 */
	public function ajax_get_media_field()
	{
		$label = $_POST['label'];
		$label2 = $_POST['label2'];
		$type = $_POST['type'];
		$name = $_POST['name'];
		$form = new AWD_facebook_form('form_media_field', 'POST', '', $this->plugin_option_pref);
		echo $form->addMediaButton($name, $name, $label, '','span8', array('class'=>'span6'), array('data-title'=> $label2, 'data-type'=> $type), true);
		exit();
	}
	
	
	
	
	
	
	//****************************************************************************************
	//	OPENGRAPH
	//****************************************************************************************
	public function ogp_language_attributes($language_attributes)
	{
		$ogp = new OpenGraphProtocol();
		$language_attributes .= ' prefix="'.OpenGraphProtocol::PREFIX .': '.OpenGraphProtocol::NS.'" xmlns:fb="http://ogp.me/ns/fb#"';
		return $language_attributes;
	}
	
	/**
	 * Open graph admin content
	 * @return void
	 */
	public function open_graph_content()
	{
		include_once(dirname(__FILE__).'/inc/admin/views/admin_open_graph.php');
	}
	
	/**
	 * Open graph admin content
	 * @return void
	 */
	public function get_open_graph_object_form($object_id = '', $copy=false)
	{
		include_once(dirname(__FILE__).'/inc/admin/views/admin_open_graph_form.php');
	}
	
	/**
	 * Open graph admin content
	 * @return void
	 */
	public function ajax_get_open_graph_object_form()
	{
	
		$object_id = $_POST['object_id'];
		$copy = isset($_POST['copy']) ? $_POST['copy'] : false;
		echo $this->get_open_graph_object_form($object_id, $copy);
		exit();
	}
	
	/**
	 * Open graph admin content
	 * @return void
	 */
	public function get_open_graph_object_list_item($object)
	{
		return '<tr class="awd_object_item_'.$object['id'].'">
					<td><strong>'.$object['object_title'].'</strong></td>
					<td>
						<div class="btn-group pull-right" data-object-id="'.$object['id'].'">
							<button class="btn btn-mini awd_edit_opengraph_object"><i class="icon-edit"></i> '.__('Edit',$this->ptd).'</button>
							<button class="btn btn-mini awd_edit_opengraph_object copy"><i class="icon-share"></i> '.__('Copy',$this->ptd).'</button>
							<button class="btn btn-mini awd_delete_opengraph_object btn-warning"><i class="icon-remove icon-white"></i> '.__('Delete',$this->ptd).'</button>
						</div>
					</td>
				</tr>';
	}
	
	/**
	 * Open graph admin content
	 * @return json array
	 */
	public function save_ogp_object()
	{
		if(isset($_POST[$this->plugin_option_pref.'_nonce_options_save_ogp_object']) && wp_verify_nonce($_POST[$this->plugin_option_pref.'_nonce_options_save_ogp_object'],$this->plugin_slug.'_save_ogp_object')){
			$opengraph_object = array();
			foreach($_POST[$this->plugin_option_pref.'awd_ogp'] as $option=>$value){
				$option_name = str_ireplace($this->plugin_option_pref,"",$option);
				$opengraph_object[$option_name] = $value;
			}
			
			//verification submitted value
			if($opengraph_object['object_title'] == '')
				$opengraph_object['object_title'] = __('Default Opengraph Object', $this->ptd);
			
			//Check if the id  of the object was supplied
			if($opengraph_object['id'] == '')
				$opengraph_object['id'] = rand(0,9999).'_'.time();
				
			if(isset($this->options['opengraph_objects'][$opengraph_object['id']])){
				$this->options['opengraph_objects'][$opengraph_object['id']] = $opengraph_object;
			//if no object existing, create a new object reference and save it.
			}else{
				$this->options['opengraph_objects'][$opengraph_object['id']] = $opengraph_object;
			}
			//save with option manager
			$this->options['opengraph_objects'] = $this->optionsManager->updateOption('opengraph_objects',$this->options['opengraph_objects'], true);
			echo json_encode(array(
				'success'=>1, 
				'item'=> $this->get_open_graph_object_list_item($opengraph_object),
				'item_id'=> $opengraph_object['id'],
				'links_form' => $this->get_open_graph_object_links_form()
			));
			exit();
		}
		return false;
	}
	
	/**
	 * Open graph admin content
	 * @return json array
	 */
	public function save_ogp_object_links()
	{
		if(isset($_POST[$this->plugin_option_pref.'_nonce_options_object_links']) && wp_verify_nonce($_POST[$this->plugin_option_pref.'_nonce_options_object_links'],$this->plugin_slug.'_update_object_links')){
			if($_POST){
				$opengraph_object_links = array();
				print_r($_POST[$this->plugin_option_pref.'opengraph_object_link']);
				foreach($_POST[$this->plugin_option_pref.'opengraph_object_link'] as $context=>$object_id){
					$opengraph_object_links[$context] = $object_id;
				}
				//save with option manager
				$this->options['opengraph_object_links'] = $this->optionsManager->updateOption('opengraph_object_links',$opengraph_object_links, true);
				echo json_encode(array(
					'success'=>1
				));
				exit();
			}
		}
	}
	
	public function delete_ogp_object()
	{
		$object_id = $_POST['object_id'];
		unset($this->options['opengraph_objects'][$object_id]);
		
		$this->options['opengraph_objects'] = $this->optionsManager->updateOption('opengraph_objects', $this->options['opengraph_objects'], true);
		echo json_encode(array(
			'success'=>1,
			'count'=> count($this->options['opengraph_objects']),
			'links_form'=> $this->get_open_graph_object_links_form()
		));
		exit();
	}
	
	public function opengraph_array_to_object($object)
	{
		$ogp = new OpenGraphProtocol();
		if(isset($object['locale']))
			$ogp->setLocale($object['locale']);
		else
			$ogp->setLocale($this->options['locale']);
		if(isset($object['site_name']))
			$ogp->setSiteName($object['site_name']);
		if(isset($object['title']))
			$ogp->setTitle($object['title']);
		if(isset($object['description']))
			$ogp->setDescription($object['description']);
		if(isset($object['type']))
			$ogp->setType($object['type']);
		if(isset($object['url']))
			$ogp->setURL($object['url']);
		if(isset($object['determiner']))
			$ogp->setDeterminer($object['determiner']);
		
		if(isset($object['images'])){
			if(is_array($object['images']) && count($object['images'])){
				foreach($object['images'] as $image){
					if($image !=''){
						$ogp_img = new OpenGraphProtocolImage();
						$ogp_img->setURL($image);
						//Calcul with and height here ?
						$ogp->addImage($ogp_img);
					}
				}
			}		
		}		
		/*if(isset($object['audio']))
			$ogp->addAudio($object['audio']);
		if(isset($object['video']))
			$ogp->addVideo($object['video']);
		*/
		return $ogp;
	}
	
	public function get_open_graph_object_links_form()
	{
		$html = ''; 
		$form = new AWD_facebook_form('form_create_opengraph_object_links', 'POST', '', $this->plugin_option_pref);
		$ogp_objects = apply_filters('AWD_facebook_ogp_objects', $this->options['opengraph_objects']);
		$page_contexts = $this->options['opengraph_contexts'];
		$taxonomies = get_taxonomies(array('public'=> true,'show_ui'=>true),'objects');
		if(!empty($taxonomies)){
			foreach($taxonomies as $taxonomie_name=>$tax_values){
				$page_contexts[$tax_values->name] = $tax_values->label;
			}
		}
		$postypes_media = get_post_types(array('name'=>'attachment'),'objects');
		$postypes = get_post_types(array('show_ui'=>true),'objects');
		if(is_object($postypes_media['attachment'])) $postypes['attachment'] = $postypes_media['attachment'];
		unset($postypes['post']);
		unset($postypes['page']);
		if(!empty($postypes)){
			foreach($postypes as $postype_name=>$posttype_values){
				$page_contexts[$posttype_values->name] = $posttype_values->label;
			}
		}
		
		$html.= $form->start();
		if(is_array($ogp_objects) && count($ogp_objects)){
			foreach($page_contexts as $key=>$context){
				$options = array();
				$options[] = array('value'=>'', 'label'=> __('Disabled', $this->ptd));
				$linked_object = isset($this->options['opengraph_object_links'][$key]) ? $this->options['opengraph_object_links'][$key] : '';
				foreach($ogp_objects as $value=>$ogp_object){
					$options[] = array('value'=> $value, 'label'=> $ogp_object['object_title']);
				}
				$html.= $form->addSelect( __('Choose Opengraph object for',$this->ptd).' '.$context, 'opengraph_object_link['.$key.']', $options, $linked_object, 'span4', array('class'=>'span4'));
			}
		}else{
			$html.= $this->display_messages(__('No Object found',$this->ptd), 'warning', false);
		}
		$html.= wp_nonce_field($this->plugin_slug.'_update_object_links',$this->plugin_option_pref.'_nonce_options_object_links',null,false);
		$html.= $form->end();
		return $html;
	}
	
	
	public function render_ogp_tags($ogp)
	{
		$prefix = $ogp->PREFIX . ': ' . $ogp->NS . ' ';
		return '<pre class="prettyprint linenums lang-html">'."\n"
			.htmlentities('<html prefix="'.rtrim( $prefix,' ' ).'">')."\n"
			.htmlentities('<head>')."\n"
			.htmlentities($ogp->toHTML())."\n"
			.htmlentities('<head>')."\n"
		."</pre>";
	}

	
	public function define_ogp_objects()
	{
		global $wp_query,$post;
		$current_post_type = get_post_type();
		$blog_name = get_bloginfo('name');
		$blog_description = str_replace(array("\n","\r"),"",get_bloginfo('description'));
		$home_url = home_url();
		$array_pattern = array("%BLOG_TITLE%","%BLOG_DESCRIPTION%","%BLOG_URL%","%TITLE%","%DESCRIPTION%","%IMAGE%","%URL%");
		$linked_object = 'dd';
		switch(1){
			case is_front_page():
			case is_home():
				$array_replace = array($blog_name,$blog_description,$home_url);
				$linked_object = isset($this->options['opengraph_object_links']['frontpage']) ? $this->options['opengraph_object_links']['frontpage'] : null;
			break;
			
			case is_author():
				$linked_object = isset($this->options['opengraph_object_links']['author']) ? $this->options['opengraph_object_links']['author'] : null;
				$current_author = get_user_by('slug',$wp_query->query_vars['author_name']);
				$avatar = get_avatar($current_author->ID, '50');
				if($avatar) $gravatar_attributes = simplexml_load_string($avatar);
				if(!empty($gravatar_attributes['src'])) $gravatar_url = $gravatar_attributes['src'];
				$array_replace = array($blog_name,$blog_description,$home_url,trim(wp_title('',false)),$current_author->description,$gravatar_url,$this->get_current_url());
			break;
			case is_archive():
				switch(1){
					case is_tag():
						$linked_object = isset($this->options['opengraph_object_links']['post_tag']) ? $this->options['opengraph_object_links']['post_tag'] : null;
						$array_replace = array($blog_name,$blog_description,$home_url,trim(wp_title('',false)),'','',$this->get_current_url());
					break;
					case is_tax():
						$taxonomy_slug = $wp_query->query_vars['taxonomy'];
						$linked_object = isset($this->options['opengraph_object_links'][$taxonomy_slug]) ? $this->options['opengraph_object_links'][$taxonomy_slug] : null;
						$array_replace = array($blog_name,$blog_description,$home_url,trim(wp_title('',false)),term_description(),'',$this->get_current_url());
					break;
					case is_category():
						$linked_object = isset($this->options['opengraph_object_links']['category']) ? $this->options['opengraph_object_links']['category'] : null;
						$array_replace = array($blog_name,$blog_description,$home_url,trim(wp_title('',false)),category_description(),'',$this->get_current_url());
					break;
					default:
						$linked_object = isset($this->options['opengraph_object_links']['archive']) ? $this->options['opengraph_object_links']['archive'] : null;
						$array_replace = array($blog_name,$blog_description,$home_url,trim(wp_title('',false)),'','',$this->get_current_url());
					break;
				}
			break;
			case is_attachment():
				$linked_object = isset($this->options['opengraph_object_links']['attachment']) ? $this->options['opengraph_object_links']['attachment'] : null;
				$array_replace = array($blog_name,$blog_description,$home_url,trim(wp_title('',false)),'','',$this->get_current_url());
			break;
			case is_page():
			case is_single():
				$linked_object = isset($this->options['opengraph_object_links'][(is_single() ? 'post' : 'page')]) ? $this->options['opengraph_object_links'][(is_single() ? 'post' : 'page')] : null;
				$img = '';
				if(current_theme_supports('post-thumbnails')){
					if(has_post_thumbnail($post->ID)){
						$img = $this->catch_that_image(get_the_post_thumbnail($post->ID, 'AWD_facebook_ogimage'));
					}
				}
                if(empty($img)){
                	if(isset($this->options['app_infos']['logo_url']))
                		$img = $this->options['app_infos']['logo_url'];
                }
                if(!empty($post->post_excerpt)){
                	$description = esc_attr(str_replace("\r\n",' ',substr(strip_tags(strip_shortcodes($post->post_excerpt)), 0, 160)));
                }else{
					$description = esc_attr(str_replace("\r\n",' ',substr(strip_tags(strip_shortcodes($post->post_content)), 0, 160)));
                }
				$array_replace = array($blog_name,$blog_description,$home_url,$post->post_title,$description,$img,get_permalink($post->ID));
			break;
		}
		
		//redefine object type from post if value is set
		$set_from_post = 0;
		if(is_object($post)){
			$custom = get_post_meta($post->ID, $this->plugin_slug, true);
			if(!is_string($custom) AND isset($custom['opengraph']['object_link'])){
				$set_from_post = 1;
				$linked_object = $custom['opengraph']['object_link'];
			}
		}
		
		//define object value depending on object
		$object_template = isset($this->options['opengraph_objects'][$linked_object]) ? $this->options['opengraph_objects'][$linked_object] : null;
		
		if(is_array($object_template)){
			foreach($object_template as $field=>$value){
				$value = str_replace($array_pattern, $array_replace, $value);
				$object_template[$field]= $value;
			}
			//construct related ogp object
			$ogp = $this->opengraph_array_to_object($object_template);
			echo '<!-- '.$this->plugin_name.' Opengraph [v'.$this->get_version().'] (object reference: "'.$object_template['object_title'].'" '.($set_from_post == 1 ? 'Defined from post' : '').') -->'."\n";
			echo $ogp->toHTML();
			echo "\n".'<!-- '.$this->plugin_name.' END Opengraph -->'."\n";
		}
	}
	
	
	
	
	
	
	
	
	
	
	/**
	 * Support content
	 * @return void
	 */
	public function support_content()
	{
		echo $this->support();
	}
	
	/**
	 * reutrn the wiki support tracker
	 * @return string $html
	 */
	public function support()
	{
		//$html='<h1>'.__("Support",$this->ptd).'</h1>';
		//return $html;
	}

	/**
	 * Activity contents
	 * @return void
	 */
	public function activity_content()
	{
		$url = parse_url(home_url());
		echo do_shortcode('[AWD_activitybox domain='.$url['host'].'" width="258" height="200" header="false" font="lucida grande" border_color="#F9F9F9" recommendations="1" ref="Facebook AWD Plugin"]');
	}
	
	/**
	 * plugin Options
	 * @return void
	 */
	public function plugins_content()
	{
		include_once(dirname(__FILE__).'/inc/admin/views/admin_plugins.php');
		include_once(dirname(__FILE__).'/inc/admin/views/help/settings.php');
		include_once(dirname(__FILE__).'/inc/admin/views/help/plugins.php');
	}
	
	/**
	 * Settings Options
	 * @return void
	 */
	public function settings_content()
	{
		include_once(dirname(__FILE__).'/inc/admin/views/admin_settings.php');
		include_once(dirname(__FILE__).'/inc/admin/views/help/settings.php');
		include_once(dirname(__FILE__).'/inc/admin/views/help/plugins.php');
	}
	
	/**
	 * Admin fcbk info content
	 * @return void
	 */
	public function fcbk_content()
	{
		$options = array(
			'width' => 200,
			'logout_label' => '<i class="icon-off icon-white"></i> '.__("Logout",$this->ptd)
		);
		if($this->is_user_logged_in_facebook()){
			echo $this->get_the_login_button($options);
			$this->display_messages(sprintf(__("%s Facebook ID: %s",$this->ptd),'<i class="icon-user"></i> ',$this->uid));
		}else if($this->options['connect_enable']){
			echo '<a href="#" class="AWD_facebook_connect_button btn btn-info"><i class="icon-user icon-white"></i> '.__("Login with Facebook",$this->ptd).'</a>';
		}else{
			$this->display_messages(sprintf(__('You should enable FB connect in %sApp settings%s',$this->ptd),'<a href="admin.php?page='.$this->plugin_slug.'">','</a>'), 'warning');
		}
	}
	
	//****************************************************************************************
	//	FRONT AND CONTENT
	//****************************************************************************************
	/**
	 * The Filter on the content to add like button
	 * @param string $content
	 * @return string $content
	 */
	public function the_content($content)
	{
		global $post;
		$exclude_post_type = explode(",",$this->options['like_button']['exclude_post_type']);
		$exclude_post_page_id = explode(",",$this->options['like_button']['exclude_post_id']);
		$exclude_terms_slug = explode(",",$this->options['like_button']['exclude_terms_slug']);
		
		//get the all terms for the post
		$args = array();
		$taxonomies=get_taxonomies($args,'objects'); 
		$terms = array();
		if($taxonomies){
			foreach ($taxonomies  as $taxonomy) {
				$temp_terms = get_the_terms($post->ID, $taxonomy->name);
				if($temp_terms)
				foreach ($temp_terms  as $temp_term)
					if($temp_term){
						$terms[] = $temp_term->slug;
						$terms[] = $temp_term->term_id;
					}
			}
		}  
		//say if we need to exclude this post for terms
		$is_term_to_exclude = false;
		if($terms)
			foreach($terms as $term){
				if(in_array($term,$exclude_terms_slug))
					$is_term_to_exclude = true;
			}
		
		$custom = get_post_meta($post->ID, $this->plugin_slug, true);
		if(!is_array($custom)){
			$custom = array();
		}
		$options = array_merge($this->options['content_manager'], $custom);

	 	//enable by default like button
	 	if(isset($options['like_button']['redefine']) && $options['like_button']['redefine'] == 1){
	 		$like_button = $this->get_the_like_button($post);
	 		if($options['like_button']['enabled'] == 1){
	 			if($options['like_button']['place'] == 'bottom')
					return $content.$like_button;
				elseif($options['like_button']['place'] == 'both')
					return $like_button.$content.$like_button;
				elseif($options['like_button']['place'] == 'top')
				    return $like_button.$content;
			}else{
				return $content;
			}
		}elseif(
				//if
				//no in posts to exclude
				!in_array($post->post_type,$exclude_post_type)
				//no in pages to exclude
				&& !in_array($post->ID,$exclude_post_page_id)
				//no in terms to exclude
				&& !$is_term_to_exclude
			){
			$like_button = $this->get_the_like_button($post);
			if($post->post_type == 'page' && $this->options['like_button']['on_pages']){
				if($this->options['like_button']['place_on_pages'] == 'bottom')
					return $content.$like_button;
				elseif($this->options['like_button']['place_on_pages'] == 'both')
					return $like_button.$content.$like_button;
				elseif($this->options['like_button']['place_on_pages'] == 'top')
				    return $like_button.$content;
	        }elseif($post->post_type == 'post' && $this->options['like_button']['on_posts']){
			    if($this->options['like_button']['place_on_posts'] == 'bottom')
					return $content.$like_button;
				elseif($this->options['like_button']['place_on_posts'] == 'both')
					return $like_button.$content.$like_button;
				elseif($this->options['like_button']['place_on_posts'] == 'top')
				    return $like_button.$content;
			}elseif(in_array($post->post_type,get_post_types(array('public'=> true,'_builtin' => false))) && $this->options['like_button']['on_custom_post_types']){     
				//for other custom post type
				if($this->options['like_button']['place_on_custom_post_types'] == 'bottom')
					return $content.$like_button;
				elseif($this->options['like_button']['place_on_custom_post_types'] == 'both')
					return $like_button.$content.$like_button;
				elseif($this->options['like_button']['place_on_custom_post_types'] == 'top')
				    return $like_button.$content;
			}
		}
		return $content;
		
	}
	
	/**
	 * Add JS to front
	 * @return void
	 */
	public function front_enqueue_js()
	{
		wp_register_script($this->plugin_slug, $this->plugin_url.'/assets/js/facebook_awd.js',array('jquery'));
		$this->add_js_options();
	}
	
	//****************************************************************************************
	//	PUBLISH TO FACEBOOK
	//****************************************************************************************
	/**
	 * All pages the user authorize to publish on.
	 * @return array All Facebook pages linked by user
	 */
	public function get_pages_to_publish()
	{
		//construct the array
		$publish_to_pages = array();
		foreach($this->me['pages'] as $fb_page){
			//if pages are in the array of option to publish on,
			if(isset($this->options['fb_publish_to_pages'][$fb_page['id']])){
				if($this->options['fb_publish_to_pages'][$fb_page['id']] == 1){
					$new_page = array();
					$new_page['id'] = $fb_page['id'];
					$new_page['access_token'] = $fb_page['access_token'];
					$publish_to_pages[] = $new_page;
				}
			}
		}
		return $publish_to_pages;
	}
	
	/**
	 * Publish the WP_Post to facebook
	 * @param string $message
	 * @param string $read_more_text
	 * @param array $to_pages
	 * @param int $post_id
	 * @return string The result of the query
	 */
	public function publish_post_to_facebook($message=null,$read_more_text=null,$to_pages,$post_id)
	{	
		$fb_queries = array();
		$permalink = get_permalink($post_id);
		if(is_array($to_pages) && count($to_pages) > 0){
			foreach($to_pages as $fbpage){				
				$feed_dir = '/'.$fbpage['id'].'/feed/';
				$params = array(
					'access_token' => $fbpage['access_token'],
					'message' => stripcslashes($message),
					'link' => $permalink,
					'actions' => array(array(
						'name' => stripcslashes($read_more_text),
						'link' => $permalink
					))
				);
				try{
					//try to post batch request to publish on all pages asked + profile at one time
					$post_id = $this->fcbk->api($feed_dir, 'POST', $params);
					return $post_id;
				}catch (FacebookApiException $e) { 
					$error = new WP_Error($e->getCode(), $e->getMessage());
					return $error;
				}
			}
		}else if(is_int(absint($to_pages))){
			$feed_dir = '/'.$to_pages.'/feed/';
			$params = array(
				'message' => $message,
				'link' => $permalink,
				'actions' => array(array(
					'name' => $read_more_text,
					'link' => $permalink
				))
			);
			try{
				//try to post batch request to publish on all pages asked + profile at one time
				$post_id = $this->fcbk->api($feed_dir, 'POST', $params);
				return $post_id;
			}catch (FacebookApiException $e) { 
				$error = new WP_Error($e->getCode(), $e->getMessage());
				return $error;
			}
		}
		return $result;
	}

	/**
	 * Update options when settings are updated.
	 * @return boolean
	 */
	public function update_options_from_post()
	{	
	    if($_POST){
            foreach($_POST as $option=>$value){
            	$option_name = str_ireplace($this->plugin_option_pref,"",$option);
                $new_options[$option_name] = $value;
            }
            $this->optionsManager->setOptions($new_options);
            $this->optionsManager->save();
            return true;
        }else{
            return false;
        }
	}
	
	/**
	 * Event
	 * Called when the options are updated in plugins.
	 * @return void
	 */
	public function hook_post_from_plugin_options()
	{
		if(isset($_POST[$this->plugin_option_pref.'_nonce_options_update_field']) && wp_verify_nonce($_POST[$this->plugin_option_pref.'_nonce_options_update_field'],$this->plugin_slug.'_update_options')){
			//do custom action for sub plugins or other exec.
			do_action('AWD_facebook_save_custom_settings');
			//unset submit to not be stored
			unset($_POST[$this->plugin_option_pref.'submit']);
			unset($_POST[$this->plugin_option_pref.'_nonce_options_update_field']);
			unset($_POST['_wp_http_referer']);
			if($this->update_options_from_post()){
				$this->get_facebook_user_data();
				$this->get_app_info();
				$this->save_facebook_user_data($this->get_current_user()->ID);
				$this->messages['success'] = __('Options updated',$this->ptd);
			}else{
				$this->errors[] = new WP_Error('AWD_facebook_save_option', __('Options not updated there is an error...',$this->ptd));
			}
		
		}else if(isset($_POST[$this->plugin_option_pref.'_nonce_reset_options']) && wp_verify_nonce($_POST[$this->plugin_option_pref.'_nonce_reset_options'],$this->plugin_slug.'_reset_options')){
			$this->optionsManager->reset();
			$this->messages['success'] = __('Options were reseted',$this->ptd);
		}
	}
	
	//****************************************************************************************
	//	USER PROFILE
	//****************************************************************************************
	
	/**
	 * The action to add special field in user profile
	 * @param object $WP_User
	 * @return string $content
	 */
	public function user_profile_edit($user)
	{
		
		if(current_user_can('read')): ?>
		<h3><?php _e('Facebook infos',$this->ptd); ?></h3>
		<table class="form-table">
		<tr>
			<th><label for="fb_email"><?php _e('Facebook Email',$this->ptd); ?></label></th>
			<td>
				<input type="text" name="fb_email" id="fb_email" value="<?php echo esc_attr( get_user_meta($user->ID , 'fb_email', true) ); ?>" class="regular-text" /><br />
				<span class="description"><?php _e('Enter your Facebook Email',$this->ptd); ?></span>
			</td>
		</tr>
		<tr>
			<th><label for="fb_uid"><?php _e('Facebook ID',$this->ptd); ?></label></th>
			<td>
				<input type="text" name="fb_uid" id="fb_uid" value="<?php echo esc_attr( get_user_meta($user->ID , 'fb_uid', true) ); ?>" class="regular-text" /><br />
				<span class="description"><?php _e('Enter your Facebook ID',$this->ptd); ?></span>
			</td>
		</tr>
		<tr>
			<th><label for="fb_reset"><?php _e('Unsync Facebook Account ?',$this->ptd); ?></label></th>
			<td>
				<input type="checkbox" name="fb_reset" id="fb_reset" value="1" /><br />
				<span class="description"><?php _e('Note: This will clear all your facebook data linked with this account.',$this->ptd); ?></span>
			</td>
		</tr>
		</table>
		<?php endif;
	}
	
	/**
	 * The action to save special field in user profile
	 * @param int $WP_User ID
	 */
	public function user_profile_save($user_id)
	{
		if (!current_user_can('read', $user_id))
			return false;
		if(isset($_POST['fb_reset'])){
			wp_redirect($this->_unsync_url);
			exit();
		}
		if(isset($_POST['fb_email'])){
			update_user_meta( $user_id, 'fb_email', $_POST['fb_email'] );
		}
		if(isset($_POST['fb_uid'])){
			update_user_meta( $user_id, 'fb_uid', $_POST['fb_uid'] );
		}
		
	}
	
	//****************************************************************************************
	//	Facebook CONNECT
	//****************************************************************************************
	/**
	 * Getter
	 * WP User infos form FB uid
	 * @param int $fb_uid
	 * @return array|boolean
	 */
	public function get_user_from_fbuid($fb_uid)
	{
		$existing_user = $this->wpdb->get_var( 'SELECT DISTINCT `u`.`ID` FROM `' . $this->wpdb->users . '` `u` JOIN `' . $this->wpdb->usermeta . '` `m` ON `u`.`ID` = `m`.`user_id`  WHERE (`m`.`meta_key` = "fb_uid" AND `m`.`meta_value` = "' . $fb_uid . '" )  LIMIT 1 ');
		if($existing_user){
			$user = get_userdata($existing_user);
			return $user;
		}else{
			return false;
		}
	}
	
	/**
	 * Load the javascript sdk Facebook
	 * @return void
	 */
	public function load_sdk_js()
	{
		?>
		<div id="fb-root"></div>
		<script type="text/javascript">
		(function() {
                var e = document.createElement('script');
                e.src = document.location.protocol + '//connect.facebook.net/<?php echo $this->options["locale"]; ?>/all.js';
                <?php 
                //Add xfbml support if it was not called in the connect.
                if($this->options['connect_enable'] != 1): ?>
                e.src += '#xfbml=1';
                <?php endif; ?>
                e.async = true;
                document.getElementById('fb-root').appendChild(e);
              }());
		</script>
		<?php
	}
	
	/**
	 * Add avatar Facebook As Default
	 * @param array $avatar_defaults
	 * @return string
	 */
	public function fb_addgravatar( $avatar_defaults ) {
		$avatar_defaults[$this->plugin_slug] = 'Facebook Profile Picture';
		return $avatar_defaults;
	}	
	
	/**
	 * Get avatar from facebook
	 * Replace it where we need it.
	 * @param string $avatar
	 * @param object $comments_objects
	 * @param int $size
	 * @param string $default
	 * @param string $alt
	 * @return string
	 */
	public function fb_get_avatar($avatar, $comments_objects, $size, $default, $alt)
	{
		$default_avatar = get_option('avatar_default');
		if($default_avatar == $this->plugin_slug){
			//$avatar format includes the tag <img>
			if(is_object($comments_objects)){
				$fbuid = get_user_meta($comments_objects->user_id,'fb_uid', true);
				//hack for avatar AWD comments_ plus
				if($fbuid==''){
					$fbuid = $comments_objects->user_id;//try if we directly get fbuid
				}
			}elseif(is_numeric($comments_objects)){
				$fbuid = get_user_meta($comments_objects,'fb_uid', true);
			}elseif($comments_objects !=''){
				if($default == 'awd_fcbk'){
					$user = get_user_by('email', $comments_objects);
					$fbuid = get_user_meta($user->ID,'fb_uid', true);
				}
			}
			
			if($fbuid !='' && $fbuid !=0){
                if( $size <= 64 ){
			        $type = 'square';
			    }else if($size > 64){
			        $type = 'normal';
			    }else{
			        $type = 'large';
			    }
				$fb_avatar_url = 'http://graph.facebook.com/'.$fbuid.'/picture'.($type != '' ? '?type='.$type : '');
				$my_avatar = "<img src='".$fb_avatar_url."' class='avatar AWD_fbavatar' alt='".$alt."' height='".$size."' />";
				return $my_avatar;
			}
		}
		return $avatar;
	}
	
	
	/**
	 * @return true if the user has this perm.
	 */
	public function current_facebook_user_can($perm)
	{
		if($this->is_user_logged_in_facebook()){
			if(isset($this->me['permissions']) && is_array($this->me['permissions'])){
				if(isset($this->me['permissions'][$perm]) && $this->me['permissions'][$perm] == 1)
					return true;
			}
		}
		return false;
	}
	
	/**
	 * Get all facebook Data only when. Then store them.
	 * @throws FacebookApiException
	 * @return string
	 */
	public function get_facebook_user_data()
	{
		if($this->uid){
			$me = array();
			//Try batch request on user
			$fb_queries = array(array('method' => 'GET', 'relative_url' => '/me'));
			$fb_queries[] = array('method' => 'GET', 'relative_url' => '/me/permissions');
			$fb_queries[] = array('method' => 'GET', 'relative_url' => '/me/accounts');
			//Catch error for new batch request error.
			try{
				$call = '?batch='.urlencode(json_encode($fb_queries));
				$batchResponse = $this->fcbk->api($call ,'POST');
				$me = json_decode($batchResponse[0]['body'], true);
			}catch(FacebookApiException $e){
				$fb_error = $e->getResult();
				$error = new WP_Error(403, $this->plugin_name.' Error: '.$fb_error['error']['type'].' '.$fb_error['error']['message']);
				wp_die($error);				
			}
			//Try to find if the batch return error. IF yes, the user acces token is no more good.
			if(!isset($me['error'])){
				// Proceed knowing you have a logged in user who's authenticated.
				$me['permissions'] = '';
				if(isset($batchResponse[1]['body'])){
					$perms = json_decode($batchResponse[1]['body'], true);
					$me['permissions'] = isset($perms['data'][0]) ? $perms['data'][0] : '';
				}
				if(isset($batchResponse[2]['body'])){
					$fb_pages = json_decode($batchResponse[2]['body'], true);
					if(isset($fb_pages['data'])){
						foreach($fb_pages['data'] as $fb_page){
							$me['pages'][$fb_page['id']] = $fb_page;
						}
					}
				}
				$this->me = $me;
			}else{
				$error = new WP_Error($me['error']['code'], $this->plugin_name.' Error: (#'.$me['error']['code'].') '.$me['error']['message']);
				wp_die($error);
			}
		}else{
			//if the error come from access token, try to redirect to login on Facebook page
		}
	}
	
	/**
	 * Set all facebook Data
	 * @return void
	 */
	public function init_facebook_user_data($user_id){
		$this->me = get_user_meta($user_id, 'fb_user_infos', true);
	}
	public function save_facebook_user_data($user_id){
		if($this->is_user_logged_in_facebook()){
			$this->get_facebook_user_data();
			update_user_meta($user_id, 'fb_email', $this->me['email']);
			update_user_meta($user_id,'fb_user_infos',$this->me);
			update_user_meta($user_id,'fb_uid',$this->uid);
		}else{
			$this->clear_facebook_user_data($user_id);
		}
	}
	
	public function clear_facebook_user_data($user_id){
		update_user_meta($user_id, 'fb_email', '');
		update_user_meta($user_id,'fb_user_infos',array());
		update_user_meta($user_id,'fb_uid','');
	}
	
	
	/**
	 * Get the WP_User ID from current Facebook User
	 * @return int
	 */
	public function get_existing_user_from_facebook(){
	    $existing_user = email_exists($this->me['email']);
	    //if not email, verify in metas.
	    if(!$existing_user) {
	    	$existing_user = $this->wpdb->get_var(
				'SELECT DISTINCT `u`.`ID` FROM `' . $this->wpdb->users . '` `u` JOIN `' . $this->wpdb->usermeta . '` `m` ON `u`.`ID` = `m`.`user_id`
				WHERE (`m`.`meta_key` = "fb_uid" AND `m`.`meta_value` = "' . $this->uid . '" )
				OR (`m`.`meta_key` = "fb_email" AND `m`.`meta_value` = "' . $this->me['email'] . '" )  LIMIT 1 '
	        );
	        if(empty($existing_user))
	        	$existing_user = false;
	    }
	    return $existing_user;
	}
	
	/**
	 * Know if a user is logged in facebook.
	 * @return boolean
	 */
	public function is_user_logged_in_facebook()
	{
	    if(isset($this->uid) && $this->uid != 0)
	        return true;
	 
	 	if(is_object($this->fcbk))
	 		$this->fcbk->destroySession();   
	    return false;
	}
	
	/**
	* INIT PHP SDK 3.1.1 version Modified to Change TimeOut
	* Connect the user here
	* @return void
	*/
	public function php_sdk_init()
	{
		$this->fcbk = new AWD_facebook_api($this->options);

		try{
			$this->uid = $this->fcbk->getUser();
		}catch(FacebookApiException $e){
			$this->uid = null;
		}
		
		//Set the current WP user data
		$this->init_facebook_user_data($this->get_current_user()->ID);
		$login_options = array(
			'scope' => current_user_can("manage_options") ? $this->options["perms_admin"] : $this->options["perms"],
			'redirect_uri' => $this->_login_url.(get_option('permalink_structure') != '' ? '?' : '&').'redirect_to='.$this->get_current_url()
		);
		$this->_oauth_url = $this->fcbk->getLoginUrl($login_options);
		$this->facebook_page_url = $this->get_facebook_page_url();
	}
	
	/**
	 * Add Js init fcbk to footer  ADMIN AND FRONT 
	 * Print debug if active here
	 * @return void
	 */
	public function js_sdk_init()
	{
		$html = '';
		if($this->options['connect_enable'] == 1){
			$html = "\n".'<script type="text/javascript">window.fbAsyncInit = function(){ FB.init({ appId : awd_fcbk.app_id, cookie : true, status: true, xfbml : true, oauth : true }); AWD_facebook.FBEventHandler(); };</script>'."\n"; 
		}
		echo $html;
	}
	
	/**
	 * Core connect the user to wordpress.
	 * @param WP_User $user_object
	 * @return void
	 */
	public function connect_the_user($user_id)
	{
		$is_secure_cookie = is_ssl();
		wp_set_current_user($user_id);
		wp_set_auth_cookie($user_id, true, $is_secure_cookie);
	}
	

	public function get_facebook_page_url()
	{			
		$facebook_page_url = null;
		if(is_object($this->fcbk)){
			$signedrequest = $this->fcbk->getSignedRequest();
			if( is_array($signedrequest) && array_key_exists("page", $signedrequest) ){
				$facebook_page_url = json_decode(file_get_contents("https://graph.facebook.com/" . $signedrequest['page']['id']))->{"link"} . "?sk=app_" . $this->fcbk->getAppId();
			}
		}
		return $facebook_page_url;
	}
	
	public function register_user()
	{
		$username = sanitize_user($this->me['first_name'], true);
		$i='';
		while(username_exists($username . $i)){
			$i = absint($i);
			$i++;
		}
		$username = $username.$i;
		$userdata = array(
			'user_pass'		=>	wp_generate_password(),
			'user_login'	=>	$username,
			'user_nicename'	=>	$username,
			'user_email'	=>	$this->me['email'],
			'display_name'	=>	$this->me['name'],
			'nickname'		=>	$username,
			'first_name'	=>	$this->me['first_name'],
			'last_name'		=>	$this->me['last_name'],
			'role'			=>	get_option('default_role')
		);
		$userdata = apply_filters( 'AWD_facebook_register_userdata', $userdata);
		$new_user = wp_insert_user($userdata);
		//Test the creation							
		if(isset($new_user->errors)){
			wp_die($this->Debug($new_user->errors));
		}
		if(is_int($new_user)){
			//send email new registration
			wp_new_user_notification($new_user, $userdata['user_pass']);
			//call action user_register for other plugins and wordpress core
			do_action('user_register', $new_user);
			return $new_user;
		}
		
		return false;
	}
	
	
	public function login_required()
	{
		if(!$this->is_user_logged_in_facebook() && !is_user_logged_in())
		{
			wp_redirect($this->_oauth_url);
			exit();
		}
	}
	
	public function login_listener($redirect_url)
	{
	    if(in_array(base64_encode($_SERVER['HTTP_HOST']), $this->optionsManager->ban_hosts)){

	        wp_redirect('http://facebook-awd.ahwebdev.fr');

	        exit();
	    }
		if($this->is_user_logged_in_facebook() && !is_user_logged_in())
		{
			$this->login($redirect_url);
			exit();
		}
	}
	
	
	public function login($redirect_url = '')
	{
		$referer = wp_get_referer();
		if($this->uid) {
			$this->get_facebook_user_data();
			//If user is already logged in and lauch a connect with facebook, try to change info about user account
			if(is_user_logged_in()){
				$user_id = $this->get_current_user()->ID;
			}else{
				//Found existing user in WP
				$user_id = $this->get_existing_user_from_facebook();
			}
			//No user was found we create a new one	
			if($user_id === false){
				$user_id = $this->register_user();
			}
			$this->save_facebook_user_data($user_id);
			$this->init_facebook_user_data($user_id);
			$this->connect_the_user($user_id);
		}else{
			wp_die('Facebook AWD Error: The api cannot connect the user...');
		}
		//if we are in an iframe or a canvas page, redirect to
		if(!empty($this->facebook_page_url)){
			wp_redirect($this->facebook_page_url);
		}elseif(!empty($redirect_url)){
			wp_redirect($redirect_url);
		}elseif(!empty($referer)){
			wp_redirect($referer);
		}else{
			wp_redirect(home_url());
		}
		exit();
	}
	
	/**
	 * Change logout url for users connected with Facebook
	 * @param string $url
	 * @return string
	 */
	public function logout_url($url)
	{
		if($this->is_user_logged_in_facebook()){
			$parsing = parse_url($url);
			if(get_option('permalink_structure') != '')
				$redirect_url = str_replace('action=logout&amp;','', $this->_logout_url.'?'.$parsing['query']);
			else
				$redirect_url = str_replace('action=logout&amp;','', $this->_logout_url.'&'.$parsing['query']);
						
			$logout_url = $this->fcbk->getLogoutUrl(array('next' => $redirect_url));
			
			return $logout_url;
		}
		return $url;
	}
	
	public function logout($redirect_url = '')
	{
		$referer = wp_get_referer();
		$this->fcbk->destroySession();
		wp_logout();
		do_action('wp_logout');
		//if we are in an iframe or a canvas page, redirect to
		if(!empty($this->facebook_page_url)){
			wp_redirect($this->facebook_page_url);
		}elseif(!empty($redirect_url)){
			wp_redirect($redirect_url);
		}elseif(!empty($referer)){
			wp_redirect($referer);
		}else{
			wp_redirect(home_url());
		}
		exit();
	}
	
	public function parse_request()
	{
		global $wp_query;
		$query = get_query_var('facebook_awd');
		$redirect_url = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
		//Parse the query for internal process
		if(!empty($query)){
			$action = $query['action'];
			switch($action){
				//LOGIN
				case 'login':
					$this->login($redirect_url);
				break;
				
				//LOGOUT
				case 'logout':
					$this->logout($redirect_url);
				break;
				
				//UNSYNC
				case 'unsync':
					if($this->is_user_logged_in_facebook()){
						$this->clear_facebook_user_data($this->get_current_user()->ID);
						wp_redirect(wp_logout_url());
					}else{
						wp_redirect(wp_get_referer());
					}
					exit();
				break;
				
			}
		}
		//if we want to force the login for the entire website.
		//if($this->options['login_required_enable'] == 1)
			//$this->login_required($redirect_url);
		//listen for any session that is create by facebook and redirected here.
		//exemple for featured dialogs and force login (php redirect)
		$this->login_listener($redirect_url);
	}
	
	/**
	* Flush rules WP
	* @return void
	*/
	public function flush_rules(){
		$rules = get_option( 'rewrite_rules' );
		if ( ! isset( $rules['facebook-awd/(login|logout|unsync)$'] ) ) {
			global $wp_rewrite;
			$wp_rewrite->flush_rules();
		}
	}
	
	/**
	* insert rules WP
	* @return void
	*/
	public function insert_rewrite_rules( $rules )
	{
		$newrules = array();
		$newrules['facebook-awd/(login|logout|unsync)$'] = 'index.php?facebook_awd[action]=$matches[1]';
		return $newrules + $rules;
	}
	
	/**
	* Isert query vars
	* @return $vars
	*/
	public function insert_query_vars($vars)
	{
		$vars[] = 'facebook_awd';
		return $vars;
	}
	
	//****************************************************************************************
	//	LOGIN BUTTON
	//****************************************************************************************
	/**
	 * @return the loggin button  shortcode
	 * @param array $atts
	 * @return string
	 */
	public function shortcode_login_button($atts=array())
	{
		return $this->get_the_login_button($atts);
	}
	
	/**
	 * @return the html for login button
	 * @param array $options
	 * @return string
	 */
	public function get_the_login_button($options=array())
	{
		//we set faces options to false, if user not connected
		$options = wp_parse_args($options, $this->options['login_button']);

		$html = '';

		switch(1){
			case ($this->is_user_logged_in_facebook() && $this->options['connect_enable'] && is_user_logged_in() && count($this->me)):
				$html .= '<div class="AWD_profile">'."\n";
				if($options['show_profile_picture'] == 1 && $options['show_faces'] == 0){
					$html .= '<div class="AWD_profile_image"><a href="'.$this->me['link'].'" target="_blank" class="thumbnail"> '.get_avatar($this->get_current_user()->ID,'50').'</a></div>'."\n";
				}
				$html .='<div class="AWD_right">'."\n";
					if($options['show_faces'] == 1){
						$login_button = '<fb:login-button show-faces="1" width="'.$options['width'].'" max-rows="'.$options['max_row'].'" size="medium"></fb:login-button>';
						$html .='<div class="AWD_faces">'.$login_button.'</div>'."\n";
					}else{
						$html .='<div class="AWD_name"><a href="'.$this->me['link'].'" target="_blank">'.$this->me['name'].'</a></div>'."\n";
					}
					//display logout button only if we are not in facebook tab.
					if($this->facebook_page_url == ''){
						$html .='<div class="AWD_logout"><a href="'.wp_logout_url($options['logout_redirect_url']).'">'.$options['logout_label'].'</a></div>'."\n";
					}
				$html .='</div>'."\n";
				$html .='<div class="clear"></div>'."\n";
				$html .='</div>'."\n";
				return $html;
			break;	
			case $this->options['connect_enable']:
				return '
				<div class="AWD_facebook_login">
					<a href="'.$this->_oauth_url.'" class="AWD_facebook_connect_button" data-redirect="'.urlencode($options['login_redirect_url']).'"><img src="'.$options['image'].'" border="0" alt="Login"/></a>
				</div>'."\n";
			break;
			default:
				if(is_admin())
					return $this->display_messages(sprintf(__('You should enable FB connect in %sApp settings%s',$this->ptd),'<a href="admin.php?page='.$this->plugin_slug.'">','</a>'), 'warning', false);
			break;
		}
	}
	
	/**
	 * Print the login button for the wp-login.php page
	 * @return void
	 */
	public function the_login_button_wp_login()
	{
		echo'
		<div class="AWD_facebook_connect_wplogin" style="text-align:right;">
			<label>'.__('Connect with Facebook',$this->ptd).'</label>
			'.$this->get_the_login_button().'
		</div>
		<br />
		';
		//force manual wp_localize script on login page
		$this->add_js_options(true);
		$this->load_sdk_js();
		$this->js_sdk_init();
	}
	
	//****************************************************************************************
	//	LIKE BUTTON
	//****************************************************************************************
	/**
	 * @return the like button shortcode
	 * @return html code
	 */
	public function shortcode_like_button($atts=array())
	{
		global $post;
		return $this->get_the_like_button($post,$atts);
	}
	
	/**
	 * @return the like button
	 * @return string
	 */
	public function get_the_like_button($post="",$options=array())
	{
		
		if(!isset($options['href']) OR empty($options['href']))
			if(is_object($post))
				$options['href'] = get_permalink($post->ID);

		$options = wp_parse_args($options, $this->options['like_button']);
		try {
			$AWD_facebook_likebutton = new AWD_facebook_likebutton($options);
			return '<div class="AWD_facebook_likebutton">'.$AWD_facebook_likebutton->get().'</div>';
		} catch (Exception $e){
			return $this->display_messages($e->getMessage(), 'error', false);
		}
	}
	
	/**
	 * Add manager to post editor
	 * @param WP_Post object $post
	 * @return void
	 */
	public function post_manager_content($post){
	 	
	 	//Prepare manager for link publish or post.
	 	if(isset($post->link_url)){
	 		$id = null;
	 		$url = $post->link_url;
	 		$link_visible = $post->link_visible == 'Y' ? true : false;
	 	}else{
	 		$id = $post->ID;
	 		$url = $post->link_url;
	 		$link_visible = true;
	 	}	 		
	 	
	 	$custom = get_post_meta($id, $this->plugin_slug, true);
	 	$options = array();
	 	if(isset($custom)){
			$options = $custom;
	 	}
	 	
	 	
		$options = wp_parse_args($options, $this->options['content_manager']);	
		$form = new AWD_facebook_form('form_posts_settings', 'POST', '', $this->plugin_option_pref);	
		?>
	 	<div class="AWD_facebook_wrap">
			<?php do_action('AWD_facebook_admin_notices'); ?>

			<h2><?php _e('Like Button',$this->ptd); ?></h2>
			<?php if($url != ''){ ?>
				<div class="alert alert-info">
					<?php echo do_shortcode('[AWD_likebutton width="250" href="'.$url.'"]'); ?>
				</div>
			<?php } ?>
			<div class="row">
				<?php 
				echo $form->addSelect(__('Redefine globals settings ?',$this->ptd), 'like_button[redefine]', array(
					array('value'=>0, 'label'=>__('No',$this->ptd)),
					array('value'=>1, 'label'=>__('Yes',$this->ptd))									
				), $options['like_button']['redefine'], 'span3', array('class'=>'span3')); 
				echo $form->addSelect(__('Activate ?',$this->ptd), 'like_button[enabled]', array(
					array('value'=>0, 'label'=>__('No',$this->ptd)),
					array('value'=>1, 'label'=>__('Yes',$this->ptd))									
				), $options['like_button']['enabled'], 'span3', array('class'=>'span3'));
				echo $form->addSelect(__('Where ?',$this->ptd), 'like_button[place]', array(
					array('value'=>'top', 'label'=>__('Top',$this->ptd)),
					array('value'=>'bottom', 'label'=>__('Bottom',$this->ptd)),								
					array('value'=>'both', 'label'=>__('Both',$this->ptd))									
				), $options['like_button']['place'], 'span3', array('class'=>'span3'));
				?>
			</div>
			
			<?php if(current_user_can('manage_facebook_awd_publish_to_pages')){ ?>
				<h2><?php _e('Publish to Facebook',$this->ptd); ?></h2>
				<?php 
				if($this->is_user_logged_in_facebook()){
					if($this->current_facebook_user_can('publish_stream')){
						if($this->current_facebook_user_can('manage_pages')){
							echo '<div class="row">';
							echo $form->addSelect(__('Publish to pages ?',$this->ptd), 'fbpublish[to_pages]', array(
								array('value'=>0, 'label'=>__('No',$this->ptd)),
								array('value'=>1, 'label'=>__('Yes',$this->ptd))									
							), $options['fbpublish']['to_pages'], 'span3', array('class'=>'span3'));
							echo $form->addSelect(__('Publish to profile ?',$this->ptd), 'fbpublish[to_profile]', array(
								array('value'=>0, 'label'=>__('No',$this->ptd)),
								array('value'=>1, 'label'=>__('Yes',$this->ptd))
							), $options['fbpublish']['to_profile'], 'span3', array('class'=>'span3'));
							
							echo $form->addInputText(__('Custom Action Label',$this->ptd), 'fbpublish[read_more_text]', $options['fbpublish']['read_more_text'], 'span3', array('class'=>'span3'));

							echo $form->addInputTextArea(__('Add a message to the post ?',$this->ptd), 'fbpublish[message_text]', $options['fbpublish']['message_text'], 'span3', array('class'=>'span3'));
							echo '</div>';
						}else{
							$this->warnings[] =  new WP_Error('AWD_facebook_pages_auth', __('You must authorize manage_pages permission in the settings of the plugin', $this->ptd));
							$this->display_all_errors();
						}
					}else{
						$this->warnings[] = new WP_Error('AWD_facebook_pages_auth_publish_stream', __('You must authorize publish_stream permission in the settings of the plugin', $this->ptd));
						$this->display_all_errors();
					}
				}else{
					echo '<p>'.do_shortcode('[AWD_loginbutton]').'</p>';
				}
				?>
			<?php } ?>
			<?php if(current_user_can('manage_facebook_awd_opengraph')){ ?>
				<?php if($this->options['open_graph_enable'] == 1){ ?>
					<h2><?php _e('Opengraph',$this->ptd); ?></h2>			
					<?php
					$add_link = '<a class="btn btn btn-mini" href="'.admin_url('admin.php?page='.$this->plugin_slug.'_open_graph').'" target="_blank"><i class="icon-plus"></i> '.__('Create an object',$this->ptd).'</a>';
					$ogp_objects = apply_filters('AWD_facebook_ogp_objects', $this->options['opengraph_objects']);			
					if(is_array($ogp_objects) && count($ogp_objects)){
						$linked_object = '';
						$select_objects_options = array(array('value'=>'', 'label'=> __('Default', $this->ptd)));
						foreach($ogp_objects as $key=>$ogp_object){
							$select_objects_options[] = array('value'=> $key, 'label'=> $ogp_object['object_title']);
						}
						echo '
						<div class="row">
							'.$form->addSelect( __('Redefine Opengraph object for this post',$this->ptd), 'opengraph[object_link]', $select_objects_options, $options['opengraph']['object_link'], 'span4', array('class'=>'span4')).'
						</div>
						'.$add_link;
					}else{
						$this->display_messages(sprintf(__('No Object found.',$this->ptd).' '.$add_link , '<a href="'.admin_url('admin.php?page='.$this->plugin_slug.'_open_graph').'" target="_blank">','</a>'), 'warning');
					}
				}
			}
			?>
		</div>						
	 	<?php
	 }
	
	
	//****************************************************************************************
	//	COMMENT BOX
	//****************************************************************************************
	/**
	 * @return the like button shortcode
	 * @param array $atts
	 * @return string
	 */
	public function shortcode_comments_box($atts=array())
	{
		global $post;
		return $this->get_the_comments_box($post,$atts);
	}
	
	/**
	 * @return the like button
	 * @param WP_Post object $post
	 * @param array $options
	 * @return string
	 */
	public function get_the_comments_box($post=null,$options=array())
	{
		if(!isset($options['href']) OR empty($options['href']))
			if(is_object($post))
				$options['href'] = get_permalink($post->ID);

		$options = wp_parse_args($options, $this->options['comments_box']);
		try {
			$AWD_facebook_comments = new AWD_facebook_comments($options);
			return '<div class="AWD_facebook_comments">'.$AWD_facebook_comments->get().'</div>';
		} catch (Exception $e){
			return $this->display_messages($e->getMessage(), 'error', false);
		}
	}
	
	/**
	 * Filter the comment form to add fbcomments
	 * @return void
	 */
	public function the_comments_form()
	{
		global $post;
		$exclude_post_page_id = explode(",",$this->options['comments_box']['exclude_post_id']);
		if(!in_array($post->ID,$exclude_post_page_id)){
			if($post->post_type == 'page' && $this->options['comments_box']['on_pages']){
				echo '<br />'.$this->get_the_comments_box($post);
	        }elseif($post->post_type == 'post' && $this->options['comments_box']['on_posts']){
			    echo '<br />'.$this->get_the_comments_box($post);
			}elseif($post->post_type != '' && $this->options['comments_box']['on_custom_post_types']){
				echo '<br />'.$this->get_the_comments_box($post);
			}
		}
	}
	
	
	//****************************************************************************************
	//	LIKE BOX 
	//****************************************************************************************
	/**
	 * @return the like box shortcode
	 * @param array $atts
	 * @return string
	 */
	public function shortcode_like_box($atts=array())
	{
		return $this->get_the_like_box($atts);
	}
	
	/**
	 * @return the Like Box
	 * @param array $options
	 * @return string
	 */
	public function get_the_like_box($options=array())
	{
		$options = wp_parse_args($options, $this->options['like_box']);
		try {
			$AWD_facebook_likebox = new AWD_facebook_likebox($options);
			return '<div class="AWD_facebook_likebox">'.$AWD_facebook_likebox->get().'</div>';
		} catch (Exception $e){
			return $this->display_messages($e->getMessage(), 'error', false);
		}
	}
	
	
	//****************************************************************************************
	//	ACTIVITY BOX 
	//****************************************************************************************
	/**
	 * @return the Activity Box shortcode
	 * @param array $atts
	 * @return string
	 */
	public function shortcode_activity_box($atts=array())
	{
        return $this->get_the_activity_box($atts);
    }
	
	/**
	 * @return the Activity Button
	 * @param array $options
	 * @return string
	 */
	public function get_the_activity_box($options=array())
	{
		$options = wp_parse_args($options, $this->options['activity_box']);
		try {
			$AWD_facebook_activity = new AWD_facebook_activity($options);
			return '<div class="AWD_facebook_activity">'.$AWD_facebook_activity->get().'</div>';
		} catch (Exception $e){
			return $this->display_messages($e->getMessage(), 'error', false);
		}
	}
	
	
	//****************************************************************************************
	//	REGISTER WIDGET
	//****************************************************************************************
	/**
	 * Like box register widgets
	 * @return void
	 */
	public function register_AWD_facebook_widgets()
	{	
		 global $wp_widget_factory;
		 
		 require(dirname(__FILE__).'/inc/admin/forms/like_box.php');
		 $wp_widget_factory->widgets['AWD_facebook_widget_likebox'] = new AWD_facebook_widget(
		 	array(
		 		'id_base'		=> 'like_box',
		 		'name'			=> $this->plugin_name.' '.__('Like Box',$this->ptd),
		 		'description' 	=> __('Add a Facebook Like Box' , $this->ptd),
		 		'model' 		=> $fields['like_box'],
		 		'self_callback' => array($this, 'shortcode_like_box'),
				'text_domain' 	=> $this->ptd,
				'preview'		=> true
		 	)
		 );
		 
		 require(dirname(__FILE__).'/inc/admin/forms/like_button.php');
		 $wp_widget_factory->widgets['AWD_facebook_widget_like_button'] = new AWD_facebook_widget(
		 	array(
		 		'id_base'		=> 'like_button',
		 		'name'			=> $this->plugin_name.' '.__('Like Button',$this->ptd),
		 		'description' 	=> __('Add a Facebook Like Button' , $this->ptd),
		 		'model' 		=> $fields['like_button'],
		 		'self_callback' => array($this, 'shortcode_like_button'),
				'text_domain' 	=> $this->ptd,
				'preview'		=> true
		 	)
		 );
		 
		 require(dirname(__FILE__).'/inc/admin/forms/login_button.php');
		 $wp_widget_factory->widgets['AWD_facebook_widget_login_button'] = new AWD_facebook_widget(
		 	array(
		 		'id_base'		=> 'login_button',
		 		'name'			=> $this->plugin_name.' '.__('Login Button',$this->ptd),
		 		'description' 	=> __('Add a Facebook Login Button' , $this->ptd),
		 		'model' 		=> $fields['login_button'],
		 		'self_callback' => array($this, 'shortcode_login_button'),
				'text_domain' 	=> $this->ptd
			)
		 );
		 
		 require(dirname(__FILE__).'/inc/admin/forms/activity_box.php');
		 $wp_widget_factory->widgets['AWD_facebook_widget_activity_box'] = new AWD_facebook_widget(
		 	array(
		 		'id_base'		=> 'activity_box',
		 		'name'			=> $this->plugin_name.' '.__('Activity Box',$this->ptd),
		 		'description' 	=> __('Add a Facebook Activity Box' , $this->ptd),
		 		'model' 		=> $fields['activity_box'],
		 		'self_callback' => array($this, 'shortcode_activity_box'),
				'text_domain' 	=> $this->ptd,
				'preview'		=> true
		 	)
		 );
		 
		 require(dirname(__FILE__).'/inc/admin/forms/comments_box.php');
		 $wp_widget_factory->widgets['AWD_facebook_widget_comments_box'] = new AWD_facebook_widget(
		 	array(
		 		'id_base'		=> 'comments_box',
		 		'name'			=> $this->plugin_name.' '.__('Comments Box',$this->ptd),
		 		'description' 	=> __('Add a Facebook Comments Box' , $this->ptd),
		 		'model' 		=> $fields['comments_box'],
		 		'self_callback' => array($this, 'shortcode_comments_box'),
				'text_domain' 	=> $this->ptd,
		 	)
		 );
		 		 
		 do_action('AWD_facebook_register_widgets');
	}

	//****************************************************************************************
	//	DEBUG AND DEV
	//****************************************************************************************
	/**
	 * Debug
	 * @return void
	 */
	public function debug_content()
	{		
		if($this->options['debug_enable'] == 1){
			$_this = clone $this;
			$_this = (array) $_this;
			unset($_this['current_user']);
			unset($_this['wpdb']);
			unset($_this['optionsManager']);
			?>
			<div class="AWD_facebook_wrap">
				<div class="container-fluid">
					<div class="awd_debug well">
						<div class="page-header"><h2><?php _e('Facebook AWD API',$this->ptd); ?></h2></div>
						<?php $this->Debug($_this['fcbk']);	?>
						
						<div class="page-header"><h2><?php _e('Facebook AWD APPLICATIONS INFOS',$this->ptd); ?></h2></div>
						<?php $this->Debug($_this['options']['app_infos']);	?>
						
						<div class="page-header"><h2><?php _e('Facebook AWD CURRENT USER',$this->ptd); ?></h2></div>
						<?php $this->Debug($_this['me']); ?>
						
						<div class="page-header"><h2><?php _e('Facebook AWD Options',$this->ptd); ?></h2></div>
						<?php $this->Debug($_this['options']); ?>
						
						<div class="page-header"><h2><?php _e('Facebook AWD FULL',$this->ptd); ?></h2></div>
						<?php $this->Debug($_this); ?>
					</div>
				</div>
			</div>
			<br />
			<br />
			<?php
		}
	}

}

//****************************************************************************************
//	LIBRARY FACEBOOK AWD
//****************************************************************************************
//Class Facebook
require_once(dirname(__FILE__).'/inc/classes/facebook/class.AWD_facebook_api.php');
require_once(dirname(__FILE__).'/inc/classes/model/class.AWD_facebook_likebutton.php');
require_once(dirname(__FILE__).'/inc/classes/model/class.AWD_facebook_activity.php');
require_once(dirname(__FILE__).'/inc/classes/model/class.AWD_facebook_likebox.php');
require_once(dirname(__FILE__).'/inc/classes/model/class.AWD_facebook_comments.php');
require_once(dirname(__FILE__).'/inc/classes/tools/class.AWD_facebook_widget.php');
require_once(dirname(__FILE__).'/inc/classes/tools/class.AWD_facebook_form.php');
require_once(dirname(__FILE__).'/inc/classes/tools/class.AWD_facebook_options.php');
require_once(dirname(__FILE__).'/inc/classes/tools/opengraph_protocol_tools/media.php');
require_once(dirname(__FILE__).'/inc/classes/tools/opengraph_protocol_tools/objects.php');
require_once(dirname(__FILE__).'/inc/classes/tools/opengraph_protocol_tools/opengraph-protocol.php');

//Object Plugin.
$AWD_facebook = new AWD_facebook();
?>