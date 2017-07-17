<?php

$esg_obj = esg_module_shortcodes::get_instance();
add_action('init', array($esg_obj, 'init'));
        
// Process ajax for logged in or not users.
add_action( 'wp_ajax_esg_ajax_upload', [ $esg_obj, 'process_ajax_upload'] );
add_action( 'wp_ajax_nopriv_esg_ajax_upload', [ $esg_obj, 'process_ajax_upload'] );

class esg_module_shortcodes extends esg_base {
    /**
     */
    public function init() {
        add_shortcode( 'esg_upload', array( $this, 'render_upload' ) );
        add_shortcode( 'esg_gallery', array( $this, 'render_gallery' ) );
        
        $esg_up = $this->get( 'esg_up', self::INT );
        
        $this->check_gallery_pwd();
        
        if ( $esg_up ) {
            get_header();
            echo do_shortcode( sprintf( '[esg_upload id="%d"]', $esg_up ) );
            get_footer();
            exit;
        }
        
        $esg_preview = $this->get( 'esg_preview', self::INT );
        
        if ( $esg_preview ) {
            get_header();
            echo do_shortcode( sprintf( '[esg_gallery id="%d" admin_upload=0]', $esg_preview ) );
            get_footer();
            exit;
        }
    }
    
    /**
     * 
     */
    public function process_ajax_upload() {
        $rec = [
            'msg' => '',
            'data' => [],
            'html' => '',
            'media_html' => '',
            'status' => 0,
        ];

        try {
            $gallery_api = esg_gallery::get_instance();
            $upload_key = $gallery_api->get_upload_key();
            
            if ( ! $gallery_api->is_supported_upload( $_FILES[$upload_key] ) ) {
                throw new esg_exception( "File not supported." );
            }
            
            // These files need to be included as dependencies when on the front end.
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );

            $user_api = esg_user::get_instance();
            
            global $esg_gallery_id;
            $esg_gallery_id = $this->get( 'esg_gallery_id', self::INT );

            if ( ! empty( $esg_gallery_id ) 
                &&  ( $user_api->has_access( [ 'gallery_id' => $esg_gallery_id ] ) 
                        || $this->check_gallery_pwd( $esg_gallery_id ) ) ) {
                
                add_filter( 'upload_dir', array( $this, 'change_to_gallery_dir' ) );
                $attachment_id = media_handle_upload( 'esg_upload', $esg_gallery_id );
                remove_filter( 'upload_dir', array( $this, 'change_to_gallery_dir' ) );

                if ( is_wp_error( $attachment_id ) ) {
                    throw new esg_exception( "Upload failed. Error: " . $attachment_id->get_error_message() );
                }
                
                $rec['data']['media_id'] = $attachment_id;
                
                $buff = sprintf( '[esg_gallery ctx=admin id="%d" media_id="%s" featured_image=0 admin_upload=0 gallery_title=0 gallery_description=0 skip_gallery_wrapper=1 skip_gallery_images_wrapper=1]', 
                    $esg_gallery_id, $attachment_id );

                $rec['media_html'] = do_shortcode( $buff );
            } else {
                throw new esg_exception( "Not authenticated or the gallery password has changed." );
            }
            
            // The image was uploaded successfully!
            $rec['msg'] = 'Your upload is complete.';
            $rec['html'] = $this->msg( $rec['msg'], 1 );
            $rec['status'] = 1;
        } catch (esg_exception $e) {
            $rec['msg'] = "Error: " . $e->getMessage();
            $rec['html'] = $this->msg( $rec['msg'] );
        }
        
        wp_send_json( $rec );
    }
    
    /**
     * Changes where the gallery images are being uploaded.
     * 
     * @global type $esg_gallery_id
     * @param array $param
     * @return array
     */
    public function change_to_gallery_dir( $param ) {
        static $deep_hash = '';
        global $esg_gallery_id;
        $orig = $param;

        // It seems this method is called multiple times
        // we need to ensure that we're pointing to the right subfolder per request.
        if ( empty( $deep_hash ) ) {
            $deep_hash = sha1( microtime() );
        }
        
        $ch1 = substr( $deep_hash, 0, 1 );
        $ch2 = substr( $deep_hash, 1, 1 );
        $ch3 = substr( $deep_hash, 2, 1 );
        
        $custom_dir = "/esg/gallery/$esg_gallery_id/$ch1/$ch2";
        $param['url'] = str_replace( $param['subdir'], '', $param['url'] ); // rm mm/dd from dir
        $param['path'] = str_replace( $param['subdir'], '', $param['path'] ); // rm mm/dd from dir
        $param['subdir'] = $custom_dir;
        
        // new dir path that now includes the gallery id
        $param['url'] .= $custom_dir;
        $param['path'] .= $custom_dir;
        
        if ( ! is_dir( $param['path'] ) ) {
            //wp_mkdir_p( $param['path'] );
        }

        return $param;
    }

    const DEFALT_TRUE = 2;
    const DEFALT_FALSE = 4;
    const EXPECTS_TRUE = 8;
    const EXPECTS_FALSE = 16;
  
    public function has_atttib( $attribs = [], $key, $flags = 1 ) {
        $stat = true;
        
        if ( isset( $attribs[ $key ] ) ) {
            $val = $attribs[ $key ];
        } elseif ( $flags & self::DEFALT_TRUE ) {
            $val = 1;
        } elseif ( $flags & self::DEFALT_FALSE ) {
            $val = 0;
        }

        if ( $flags & self::EXPECTS_TRUE ) {
            $stat = esg_string_util::is_true( $val );
        }

        if ( $flags & self::EXPECTS_FALSE ) {
            $stat = esg_string_util::is_false( $val );
        }

        return $stat;
    }
    
    /**
     * 
     * @param array $attribs
     * @return str
     */
    public function render_gallery( $attribs = [] ) {
        $check_res = $this->check( $attribs );
        
        if ( ! $check_res->is_success() ) {
            return $this->msg( $check_res->msg() );
        }
        
        $esg_admin_api = esg_module_admin::get_instance();
        $opts = $esg_admin_api->get_options();
        
        $ctx = [];
        ob_start();

        $suffix = '';
        $gallery_id = empty( $attribs['id'] ) ? 0 : absint( $attribs['id'] );
        $gallery_obj = esg_gallery::get_instance();
        $gallery_obj->from_data($gallery_id);
        $gallery_type = $gallery_obj->get_data( 'gallery_type' );
        $admin_ctx = !empty($attribs['ctx']) && $attribs['ctx'] == 'admin';
        $gallery_items_per_row = $admin_ctx
                ? 10
                : $gallery_obj->get_data( 'gallery_items_per_row' );
        
        $media_id = empty( $attribs['media_id'] ) ? 0 : $attribs['media_id'];
        
        // If the type specific css exists we'll include it.
        // gallery_type_grid.css
        if ( file_exists( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/assets/css/gallery_type_{$gallery_type}{$suffix}.css" ) ) {
            wp_enqueue_style( 'esg_assets_gallery_type_css', plugins_url( "/assets/css/gallery_type_{$gallery_type}{$suffix}.css", ESG_CORE_BASE_PLUGIN ), false,
                filemtime( plugin_dir_path( ESG_CORE_BASE_PLUGIN ) . "/assets/css/gallery_type_{$gallery_type}{$suffix}.css" ) );
        }
        ?>

        <?php if ( empty( $attribs['skip_gallery_wrapper'] ) ) : ?>
        <div class="esg_gallery_wrapper uk-margin-large-bottom">
            <div class="esg_gallery esg_gallery_<?php echo $gallery_id; ?> esg_gallery_type_<?php echo $gallery_type; ?>"> 
        <?php endif; ?>
                <?php if ( $this->has_atttib( $attribs, 'featured_image', self::EXPECTS_TRUE | self::DEFALT_TRUE )
                        && has_post_thumbnail( $gallery_id ) ) : ?>
                <div class="esg_gallery_featured_image">
                    <?php echo $gallery_obj->get_data( 'featured_image_html' ); ?>
                </div>
                <?php endif; ?>

                <?php if ( $this->has_atttib( $attribs, 'gallery_title', self::EXPECTS_TRUE | self::DEFALT_TRUE ) ) : ?>
                    <div class="esg_gallery_title">
                        <?php echo $gallery_obj->get_data( 'title' ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $this->has_atttib( $attribs, 'gallery_description', self::EXPECTS_TRUE | self::DEFALT_FALSE ) ) : ?>
                    <div class="esg_gallery_description">
                        <?php echo $gallery_obj->get_data( 'description' ); ?>
                    </div>
                <?php endif; ?>
                <?php
                $images = $gallery_obj->get_images( [ 'media_id' => $media_id, ] );
                ?>
                    <?php if ( empty( $attribs['skip_gallery_images_wrapper'] ) ) : ?>
                        <div class="esg_gallery_images_wrapper esg_gallery_images_wrapper_<?php echo $gallery_id; ?> uk-margin-large">
                            <ul class="esg_gallery_images_list esg_gallery_images_list_<?php echo $gallery_id; ?> uk-grid" data-uk-grid-margin>
                    <?php endif; ?>

                    <?php if ( empty( $images ) ) : ?>
                        <li>
                            <div id="esg_gallery_images_nothing_found" class="esg_gallery_images_nothing_found">
                                You haven't added any images yet.
                            </div>
                        </li>
                    <?php else : ?>
                        <?php foreach ( $images as $image_rec ) : ?>
                            <?php
                            $th_src = apply_filters( 'esg_gallery_filter_gallery_image_thumbnail_src', $image_rec[ 'thumbnail_src' ], $image_rec );
                            $img_alt = apply_filters( 'esg_gallery_filter_gallery_image_alt', '', $image_rec );
                            $img_title = apply_filters( 'esg_gallery_filter_gallery_image_title', '', $image_rec );
                            $img_target = apply_filters( 'esg_gallery_filter_gallery_image_target', '_blank', $image_rec );
                            ?>
                            <li class="esg_gallery_item esg_gallery_item_<?php echo $image_rec['id']; ?> uk-width-medium-1-%%gallery_items_per_row_col%% uk-animation-fade uk-text-center">
                                <?php do_action( 'esg_action_before_gallery_item', [ 'gallery_item' => $image_rec ] ); ?>
                                <a id="esg_gallery_item_link_<?php echo $image_rec['id']; ?>"
                                   class="esg_gallery_item_link esg_gallery_item_link_<?php echo $image_rec['id']; ?>"
                                   href="<?php echo $image_rec['src']; ?>"
                                   data-lightbox-type="image" 
                                   data-uk-lightbox="{group:'esg_gallery-<?php echo $gallery_id; ?>'}" 
                                   title="<?php echo $img_title; ?>"
                                   target="<?php echo $img_target; ?>"
                                   >
                                    <img
                                        src="<?php echo $th_src; ?>" 
                                        alt="<?php echo $img_alt; ?>"
                                        title="<?php echo $img_title; ?>"
                                        class="uk-width-1-1"
                                        />
                                </a>
                                <?php do_action( 'esg_action_after_gallery_item', [ 'gallery_item' => $image_rec ] ); ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                                
                    <?php if ( empty( $attribs['skip_gallery_images_wrapper'] ) ) : ?>
                            </ul> <!-- /esg_gallery_images_list -->
                        </div> <!-- /esg_gallery_images_wrapper -->
                <?php endif; ?>
                
            <?php if ( empty( $attribs['skip_gallery_wrapper'] ) ) : ?>
                </div>
            </div>
            <?php endif; ?>
        <?php
        
        $user_api = esg_user::get_instance();
        
        if ( $user_api->can_access()
                && $this->has_atttib( $attribs, 'admin_upload', self::EXPECTS_TRUE | self::DEFALT_TRUE ) ) {

            // no need for the logo and info if this is rended in the admin
            if ( ! $admin_ctx ) {
                $icon_src = $gallery_obj->get_icon_src();

                echo "<div class='esg_dotted_separator uk-margin-large-bottom'></div><p class='uk-text-muted uk-text-center uk-margin-large-bottom'>
                        <strong>Upload Images Below:</strong><br /> 
                        <em>(only an Administrator will see this)</em></p>";
            }
            
            echo do_shortcode( sprintf( '[esg_upload id="%d"]', $gallery_id ) );
        }
        
        $buff = ob_get_clean();
        $buff = str_replace( '%%gallery_items_per_row_col%%', $gallery_items_per_row, $buff );
        
        return $buff;
    }
    
    /**
     * This method checks if we have everything in the attribs.
     * Also it checks if the gallery is valid.
     * @param type $attribs
     * @return \esg_result
     * @throws esg_exception
     */
    public function check( $attribs = [] ) {
        $res = new esg_result(0);
        $gallery_id = empty( $attribs['id'] ) ? 0 : absint( $attribs['id'] );
        
        try {
            if ( empty( $gallery_id ) ) {
                throw new esg_exception( 'Gallery ID not supplied' );
            }
            
            $gallery_obj = new esg_gallery( $gallery_id );
            
            if ( ! $gallery_obj->exists() ) {
                throw new esg_exception( 'Gallery does not exist.' );
            }
            
            $res->status(1);
        } catch (esg_exception $e ) {
            $res->msg( $e->getMessage()  );
        }
        
        return $res;
    }
    
    /**
     * This method needs to run on init because we save
     * gallery pwd in session. We can't do it when the shortcode is rendered
     * because WP has output some content and cookies won't be set.
     */
    public function check_gallery_pwd($gallery_id = 0) {
        $auth = false;
        $session_api = esg_session::get_instance();
          
        $gallery_id = $gallery_id ? $gallery_id : $this->get( 'esg_gallery_id', self::INT );
        $gallery_id = (int) $gallery_id;
        
        if ( ! empty( $gallery_id ) ) {
            $auth_key = 'esg_upload_auth' . $gallery_id;

            $gallery_obj = new esg_gallery( $gallery_id );
            $gallery_pwd = $gallery_obj->get_data( 'gallery_upload_pass' );
            
            if ( empty( $gallery_pwd ) ) { // gallery dosn't have a pwd set.
                $auth = true;
            } elseif ( $gallery_pwd == $this->get( 'esg_gallery_upload_pass' ) ) {
                $session_api->set( $auth_key, $gallery_pwd );
                $auth = true;
            } else {
                $saved_pwd = $session_api->get( $auth_key );
                $auth = $saved_pwd == $gallery_pwd;
                
                if ( empty( $auth ) ) {
                    $session_api->remove( $auth_key );
                }
            }
        }
        
        return $auth;
    }
    
    /**
     * 
     * @param array $attribs
     * @return string
     */
    public function render_upload( $attribs = [] ) {
        $check_res = $this->check( $attribs );
        
        if ( ! $check_res->is_success() ) {
            return $this->msg( $check_res->msg() );
        }

        $ctx = [];
        $msg = '';

        $ask_for_pwd = 1; // by default do this.
        $session_api = esg_session::get_instance();
        
        $gallery_id = empty( $attribs['id'] ) ? 0 : absint( $attribs['id'] );
        $gallery_obj = esg_gallery::get_instance();
        $gallery_obj->from_data($gallery_id);
        $icon_src = $gallery_obj->get_icon_src();

        if ( $this->check_gallery_pwd($gallery_id)) {  // @todo check for expiration. 4h?
            $ask_for_pwd = 0;
        } elseif ( ! empty( $_POST ) && isset( $_REQUEST['esg_gallery_upload_pass'] ) ) {
            $msg = $this->msg( __( 'Incorrect password', 'esg' ) );
        }

        ob_start();
	?>
        <div id='esg_gallery_upload_form_wrapper' class='esg_gallery_upload_form_wrapper uk-margin-large-bottom uk-margin-top'>
           <div class="uk-width-9-10 uk-width-medium-2-3 uk-width-large-1-2 uk-container-center uk-panel">
            <div class='esg_icon_uploader uk-text-center'>
                    <img src='<?php echo $icon_src; ?>' width='40' height='40' />
            </div>
            <form id='esg_gallery_upload_form_<?php echo $gallery_id; ?>' 
                  class='esg_gallery_upload_form esg_gallery_upload_form_<?php echo $gallery_id; ?> uk-form' 
                  method="POST" enctype="multipart/form-data">
                <input id="esg_gallery_id" name="esg_gallery_id" 
                               value="<?php echo esc_attr( $gallery_id );?>" type="hidden" />

                <div class="esg_gallery_title uk-text-center uk-margin-large-top uk-margin-bottom">
                    Uploading to: <strong class="uk-text-primary"><?php echo $gallery_obj->get_data( 'title' ); ?></strong>
                </div>
                
                <?php if ( $ask_for_pwd && ( empty( $attribs['ctx'] ) || $attribs['ctx'] != 'admin' ) ) : ?>
                    <?php echo $msg; ?>
                    <input id="esg_gallery_upload_pass" name="esg_gallery_upload_pass" class="uk-form-large" 
                           value="<?php echo ''; ?>" type="password" placeholder="Enter Gallery Password"/>
                    <br/>
                    <button type="submit" class="uk-margin-small-top uk-button uk-button-large uk-button-danger esg_field_full"><i class="uk-icon-lock"></i> Submit</button> 
                <?php else : ?>
                    <div class="fileinput-button esg_wrapper uk-button uk-button-large uk-button-primary uk-width-1-1">
                        <div class="esg_fake_upload_button">
                            <i class="glyphicon glyphicon-cloud-upload"></i> Select Image(s)...</div>

                        <!-- The file input field used as target for the file upload widget -->
                        <input id="esg_upload" name="esg_upload" multiple 
                               type="file" class="esg_hide_imp" />
                    </div>
                    <br />
                    <br />
                    <!-- The global progress bar -->
                    <div id="esg_progress" class="uk-progress uk-progress-striped uk-progress-success uk-active">
                            <div class="uk-progress-bar" style="width: 0%;">0%</div>
                    </div>
                    <div id="esg_result" class="esg_result"></div>
                <?php endif; ?>
            </form>
			</div>
        </div>
            
        <?php
        
        $buff = ob_get_clean();
        return $buff;
    }
}
