<?php

$esg_asset_loader_obj = esg_module_asset_loader::get_instance();
add_action('init', array($esg_asset_loader_obj, 'init'));

class esg_module_asset_loader extends esg_base {
    /**
     *
     */
    public function init() {
        $session_api = esg_session::get_instance();
        $session_api->start();
        
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
    * Call wp_enqueue_media() to load up all the scripts we need for media uploader
    * Credit: https://github.com/dbspringer/wp-frontend-media/blob/master/frontend-media.php
    * credit: http://jeroensormani.com/how-to-include-the-wordpress-media-selector-in-your-plugin/
     * @todo load these assets in the admin only when necessary (plugin's settings or edit/add new gallery.
    */
    public function enqueue_assets() {
        $suffix = $this->get_asset_suffix();
        
        wp_enqueue_script( 'jquery' );
        wp_enqueue_style( 'esg_asset_css', plugins_url( "/assets/css/main{$suffix}.css", ESG_CORE_BASE_PLUGIN ), false,
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/assets/css/main{$suffix}.css" ) );

        wp_enqueue_script(
            'esg_shared_upload_ui_widget',
            plugins_url( '/share/jQuery-File-Upload-master/js/vendor/jquery.ui.widget.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery' ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/jQuery-File-Upload-master/js/vendor/jquery.ui.widget.js' ),
            true
        );
        
        wp_enqueue_script(
            'esg_shared_upload_iframe_transport',
            plugins_url( '/share/jQuery-File-Upload-master/js/jquery.iframe-transport.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery' ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/jQuery-File-Upload-master/js/jquery.iframe-transport.min.js' ),
            true
        );

        wp_enqueue_script(
            'esg_shared_upload_main',
            plugins_url( '/share/jQuery-File-Upload-master/js/jquery.fileupload.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery' ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/jQuery-File-Upload-master/js/jquery.fileupload.min.js' ),
            true
        );
        
        // enqueues our external font awesome stylesheet
        // @todo check settings in case user doesn't want FA to loaded because they 
        // have a different copy of it.
	wp_enqueue_style( 'esg_shared_font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', true ); 

        // UI kit
        wp_enqueue_script(
            'esg_shared_uikit',
            plugins_url( '/share/uikit/uikit.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery' ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/uikit/uikit.min.js' ),
            true
        );
        
        wp_enqueue_script(
            'esg_shared_uikit_modal',
            plugins_url( '/share/uikit/modal.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery', 'esg_shared_uikit', ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/uikit/modal.min.js' ),
            true
        );
        
        wp_enqueue_script(
            'esg_shared_uikit_lightbox',
            plugins_url( '/share/uikit/lightbox.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery', 'esg_shared_uikit', ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/uikit/lightbox.min.js' ),
            true
        );
		
        wp_enqueue_script(
            'esg_shared_uikit_tooltip',
            plugins_url( '/share/uikit/tooltip.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery', 'esg_shared_uikit', ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/uikit/tooltip.min.js' ),
            true
        );
        
        wp_enqueue_script(
            'esg_assets_upload_bootstrap',
            '//netdna.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js',
            array( 'jquery' ),
            true
        );

        wp_enqueue_script(
            'esg_shared_upload_main',
            plugins_url( '/share/jQuery-File-Upload-master/js/jquery.fileupload.min.js', ESG_CORE_BASE_PLUGIN ),
            array( 'jquery' ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . '/share/jQuery-File-Upload-master/js/jquery.fileupload.min.js' ),
            true
        );
        
        wp_enqueue_style( 'esg_shared_uikit_style', plugins_url( "/share/uikit/styles.css", ESG_CORE_BASE_PLUGIN ), false,
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/share/uikit/styles.css" ) );
        
        wp_enqueue_style( 'esg_assets_upload_css', plugins_url( "/share/jQuery-File-Upload-master/css/jquery.fileupload{$suffix}.css", ESG_CORE_BASE_PLUGIN ), false,
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/share/jQuery-File-Upload-master/css/jquery.fileupload{$suffix}.css" ) );
            
        wp_enqueue_style( 'esg_assets_upload_css_ui', plugins_url( "/share/jQuery-File-Upload-master/css/jquery.fileupload-ui{$suffix}.css", ESG_CORE_BASE_PLUGIN ), false,
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/share/jQuery-File-Upload-master/css/jquery.fileupload-ui{$suffix}.css" ) );
            
        wp_enqueue_style( 'esg_assets_upload_bootstrap', '//netdna.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css' );            
            
        wp_enqueue_script( 'esg_asset_js', plugins_url( "/assets/js/main{$suffix}.js", ESG_CORE_BASE_PLUGIN ), array( 'jquery', ),
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/assets/js/main{$suffix}.js" ), true );
 
        wp_enqueue_style( 'esg_shared_uikit_style', plugins_url( "/share/uikit/styles.css", ESG_CORE_BASE_PLUGIN ), false,
            filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/share/uikit/styles.css" ) );

        $ctx = [
            'asset_prefix' => 'esg_asset_',
            'asset_suffix' => $suffix,
            'base_plugin_name' => ESG_CORE_BASE_PLUGIN,
        ];
        
        do_action( 'esg_gallery_action_enqueue_assets', $ctx ); 
        add_action( 'wp_print_scripts', array( $this, 'output_js_cfg' ) );
    }
    
    /**
     * Defines some config variables that assets need.
     * If you want to add more stuff to the cfg hook into this filter: esg_filter_asset_loader_cfg
     */
    function output_js_cfg() {
        $opts = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'loading' => '<div class="app_loading">Loading ...</div>',
        );
        
        $opts = apply_filters('esg_filter_asset_loader_cfg', $opts );
        $json = esg_string_util::json_encode($opts);

        echo "<!-- go359:inline -->\n";
        echo "<script type='text/javascript'>\n";
        echo "var esg_cfg = $json;\n";
        echo "</script>\n";

        $inline_css = [];
        $inline_css = apply_filters('esg_filter_asset_output_public_inline_css', $inline_css, $opts );

        if ( ! empty( $inline_css ) ) {
            if ( is_array( $inline_css ) ) {
                $inline_css = join( "\n", $inline_css );
            }

            if ( is_scalar( $inline_css ) ) {
                echo "<style>\n";
                echo $inline_css . "\n";
                echo "</style>\n";
            }
        }
        
        echo "<!-- /go359:inline -->\n";
    }
}

