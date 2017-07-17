<?php

$esg_obj = esg_cpt::get_instance();

add_action( 'init', array( $esg_obj, 'init') );
add_action( 'admin_init', array( $esg_obj, 'admin_init' ) );

class esg_cpt extends esg_base {
    public $post_type = 'esg_gallery';
    public $tax_cat = 'esg_cat';
    public $tax_tag = 'esg_tag';

    public $add_product_url = '';
    public $manage_products_url = '';

    /**
     * This must be called after wp's init
     */
    public function init() {
        $this->add_product_url = admin_url( 'post-new.php?post_type=' . $this->get_key( 'post_type' ) );
        $this->manage_products_url = admin_url( 'edit.php?post_type=' . $this->get_key( 'post_type' ) );
        
        $this->register_cpt();
        $this->register_custom_cols();

        add_action( 'esg_action_after_gallery_item', [ $this, 'render_delete_media' ] );

        // add img to gallery
        add_action( 'wp_ajax_esg_admin_ajax_add_image_to_gallery', [ $this, 'process_add_image_to_gallery'] );
        add_action( 'wp_ajax_nopriv_esg_admin_ajax_add_image_to_gallery', [ $this, 'process_add_image_to_gallery'] );

        // remove img from gallery
        add_action( 'wp_ajax_esg_admin_ajax_remove_image_from_gallery', [ $this, 'process_remove_image_from_gallery'] );
        add_action( 'wp_ajax_nopriv_esg_admin_ajax_remove_image_from_gallery', [ $this, 'process_remove_image_from_gallery'] );
    }
    
    public function admin_init() {
        // https://codex.wordpress.org/Javascript_Reference/wp.media
        if ( 1 ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'add_media_test_scripts' ] );
        }
    }

    /**
     * @todo check admin access
     */
    public function process_add_image_to_gallery() {
        $rec = [
            'msg' => '',
            'data' => [],
            'html' => '',
            'status' => 0,
        ];

        try {
            $ids_arr = $this->get( 'attachment_ids', self::INT | self::FORCE_ARRAY);
            $gallery_id = $this->get( 'gallery_id', self::INT );
            
            $user_api = esg_user::get_instance();
            
            if ( ! $user_api->can_access( [ 'gallery_id' => $gallery_id ] ) ) {
                throw new esg_exception( __( "Access denied.", 'esg' ) );
            }

            // This will point the attachment to the gallery.
            // That means that only the current gallery will use it.
            $gallery_api = esg_gallery::get_instance();
            $gallery_api->set_media_parent( [ 'media_id' => $ids_arr, 'gallery_id' => $gallery_id ] );

            $buff = sprintf( '[esg_gallery ctx=admin id="%d" media_id="%s" featured_image=0 admin_upload=0 gallery_title=0 gallery_description=0 skip_gallery_wrapper=1 skip_gallery_images_wrapper=1]', 
                    $gallery_id, join(',', $ids_arr ) );

            $rec['html'] = do_shortcode( $buff );
            $rec['status'] = 1;
        } catch (esg_exception $e) {
            $rec['msg'] = "Error: " . $e->getMessage();
            $rec['html'] = $this->msg( $rec['msg'] );
        }
        
        wp_send_json( $rec );
    }
    
    /**
     * @todo check admin access
     */
    public function process_remove_image_from_gallery() {
        $rec = [
            'msg' => '',
            'data' => [],
            'html' => '',
            'status' => 0,
        ];

        try {
            $media_id = $this->get( 'media_id', self::INT );
            
            $user_api = esg_user::get_instance();
            
            if ( ! $user_api->can_access( [ 'media_id' => $media_id ] ) ) {
                throw new esg_exception( __( "Access denied.", 'esg' ) );
            }
            
            $gallery_api = esg_gallery::get_instance();
            $gallery_api->set_media_parent( [ 'media_id' => $media_id, 'gallery_id' => 0 ] );

            $rec['msg'] = '';
            $rec['status'] = 1;
        } catch (esg_exception $e) {
            $rec['msg'] = "Error: " . $e->getMessage();
            $rec['html'] = $this->msg( $rec['msg'] );
        }
        
        wp_send_json( $rec );
    }
    
    function add_media_test_scripts() {
	wp_enqueue_media();
	/*wp_enqueue_script( 'aubreypwd-wp-forums-metabox-add-media-test-script', 
                plugins_url( 'media.js', ESG_CORE_BASE_PLUGIN ), array( 'jquery' ), '1.0.0' );*/
    }

    public function register_cpt() {
        static $initialized = null;

        if ( ! is_null( $initialized ) ) {
            return true;
        }
        
        $initialized = 1;
        
        ///////////////////////////////////////////////////////////////////////////////////
        // Gallery
        $labels = array(
            'name'               => _x( 'Galleries', 'post type general name', 'esg' ),
            'singular_name'      => _x( 'Gallery', 'post type singular name', 'esg' ),
            'menu_name'          => _x( 'Galleries', 'admin menu', 'esg' ),
            'name_admin_bar'     => _x( 'Gallery', 'add new on admin bar', 'esg' ),
            'add_new'            => _x( 'Add New', $this->post_type, 'esg' ),
            'add_new_item'       => __( 'Add New Gallery', 'esg' ),
            'new_item'           => __( 'New Gallery', 'esg' ),
            'edit_item'          => __( 'Edit Gallery', 'esg' ),
            'view_item'          => __( 'View Gallery', 'esg' ),
            'all_items'          => __( 'All Galleries', 'esg' ),
            'search_items'       => __( 'Search Galleries', 'esg' ),
            'parent_item_colon'  => __( 'Parent Galleries:', 'esg' ),
            'not_found'          => __( 'No galleries found.', 'esg' ),
            'not_found_in_trash' => __( 'No galleries found in Trash.', 'esg' ),
	);
        
        $cpt_args = array(
            'labels' => $labels,
            'description' => __( 'Cool gallery.', 'esg' ),
            'label' => __( 'Galleries', 'esg' ),
            'singular_label' => __('Gallery', 'esg'),
            'public' => true,
            'show_ui' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite'      => array( 'slug' => 'esg-gallery' ),
            'supports' => array( 'title', 'thumbnail' ), // 'custom-fields', 'editor', 
            'show_in_menu' => false,
//            'show_in_menu' => 'admin.php?page=esg_module_admin',
        );
        
        register_post_type( $this->post_type, $cpt_args );
        ///////////////////////////////////////////////////////////////////////////////////
        
        ///////////////////////////////////////////////////////////////////////////////////
        $labels = array(
            'name' => _x('Categories', 'esg'),
            'singular_name' => _x('Category', 'esg'),
            'search_items' => __('Search Categories', 'esg'),
            'all_items' => __('All Categories', 'esg'),
            'parent_item' => __('Parent Category', 'esg'),
            'parent_item_colon' => __('Parent Category:', 'esg'),
            'edit_item' => __('Edit Category', 'esg'),
            'update_item' => __('Update Category', 'esg'),
            'add_new_item' => __('Add New Category', 'esg'),
            'new_item_name' => __('New Category Name', 'esg'),
        );

        register_taxonomy(
            $this->tax_cat,
            $this->post_type,
            array(
                'hierarchical' => true,
                'labels' => $labels,
                'rewrite'               => array( 'slug' => 'esg-category' ),
		'show_ui'               => true,
		'show_admin_column'     => true,
                'show_in_nav_menus' => true,
//                'show_in_menu' => false,
//                'show_in_menu' => 'edit-tags.php?taxonomy=esg_tag&post_type=esg_gallery',
            )
        );
        ///////////////////////////////////////////////////////////////////////////////////
        
        ///////////////////////////////////////////////////////////////////////////////////
        // Add new taxonomy, NOT hierarchical (like tags)
	$labels = array(
            'name'                       => _x( 'Tags', 'taxonomy general name', 'esg' ),
            'singular_name'              => _x( 'Tag', 'taxonomy singular name', 'esg' ),
            'search_items'               => __( 'Search Tags', 'esg' ),
            'popular_items'              => __( 'Popular Tags', 'esg' ),
            'all_items'                  => __( 'All Tags', 'esg' ),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Tag', 'esg' ),
            'update_item'                => __( 'Update Tag', 'esg' ),
            'add_new_item'               => __( 'Add New Tag', 'esg' ),
            'new_item_name'              => __( 'New Tag Name', 'esg' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'esg' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'esg' ),
            'choose_from_most_used'      => __( 'Choose from the most used tags', 'esg' ),
            'not_found'                  => __( 'No tags found.', 'esg' ),
            'menu_name'                  => __( 'Tags', 'esg' ),
	);

	$args = array(
            'hierarchical'          => false,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
//		'update_count_callback' => '_update_post_term_count',
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'esg-tag' ),
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            //'show_in_menu' => 'esg_module_admin',
	);

	register_taxonomy( $this->tax_tag, $this->post_type, $args );
        ///////////////////////////////////////////////////////////////////////////////////
        
        $this->add_meta_boxes();
        
        add_filter( 'post_updated_messages', [ $this, 'gallery_updated_messages' ] );
    }

    /**
     * Gallery update messages.
     * See /wp-admin/edit-form-advanced.php
     *
     * @param array $messages Existing post update messages.
     * @return array Amended post update messages with new CPT update messages.
     */
    function gallery_updated_messages( $messages ) {
        $post             = get_post();
        $post_type        = get_post_type( $post );
        $post_type_object = get_post_type_object( $post_type );

        $messages[ $this->post_type ] = array(
                0  => '', // Unused. Messages start at index 1.
                1  => __( 'Gallery updated.', 'esg' ),
                2  => __( 'Custom field updated.', 'esg' ),
                3  => __( 'Custom field deleted.', 'esg' ),
                4  => __( 'Gallery updated.', 'esg' ),
                /* translators: %s: date and time of the revision */
                5  => isset( $_GET['revision'] ) ? sprintf( __( 'Gallery restored to revision from %s', 'esg' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
                6  => __( 'Gallery published.', 'esg' ),
                7  => __( 'Gallery saved.', 'esg' ),
                8  => __( 'Gallery submitted.', 'esg' ),
                9  => sprintf(
                        __( 'Gallery scheduled for: <strong>%1$s</strong>.', 'esg' ),
                        // translators: Publish box date format, see http://php.net/date
                        date_i18n( __( 'M j, Y @ G:i', 'esg' ), strtotime( $post->post_date ) )
                ),
                10 => __( 'Gallery draft updated.', 'esg' )
        );

        // We won't show preview/view links to gallery because the galleries
        // will be included on a page of user's choices.
        if ( $post_type_object->publicly_queryable && $this->post_type === $post_type ) {
            $permalink = '';
            //$permalink = get_permalink( $post->ID );

            //$view_link_html = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View gallery', 'esg' ) );
            $view_link_html = '';
            $messages[ $post_type ][1] .= $view_link_html;
            $messages[ $post_type ][6] .= $view_link_html;
            $messages[ $post_type ][9] .= $view_link_html;

            $preview_permalink = '';
            $preview_link_html = '';
            //$preview_permalink = add_query_arg( 'preview', 'true', $permalink );
            //$preview_link_html = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview gallery', 'esg' ) );
            $messages[ $post_type ][8]  .= $preview_link_html;
            $messages[ $post_type ][10] .= $preview_link_html;
        }

        return $messages;
    }
    
    public function on_plugin_activate() {
        flush_rewrite_rules();
    }

    /**
     *
     * @param array $params
     */
    public function add( $params ) {
        $ctx = [];
		$post_data = apply_filters( 'orb_alert_filter_pre_add_params', $params, $ctx );

        $post_data['post_title'] = $params['title'];
		$post_data['post_status'] = self::PENDING;
		$post_data['post_type'] = $this->post_type;
        $post_data['post_excerpt'] = $params['email'];
        $post_data['post_date'] = $params['date'];

        $post_id = wp_insert_post( $post_data );
        $res = new esg_result();
        
        $res->id = (int) $post_id;
        $res->status = $res->id > 0;

        if ( $res->id && ! empty( $params['id' ] ) ) {
            update_post_meta( $res->id, '_orb_alert_id', $params['id' ] );
            //update_post_meta( $res->id, '_orb_alert_method', $params['alert' ] );
        }
        
        return $res;
    }

    public function load( $params ) {
        $args = array(
            'posts_per_page'   => 10,
            'offset'           => 0,
            //'orderby'          => 'date',
            //'orderby'          => 'post_date',
//            'order'            => 'DESC',
            'post_type'        => $this->post_type,
            'post_status'      => 'publish',
            //'suppress_filters' => true,
        );
        
        // search by upc
        if ( empty( $params['search_by'] ) ) { // default is search by upc
            if ( ! empty( $params['upc'] ) ) { // if first search did't yield any results use upc
                $args['meta_key'] = '_esg_gallery_upc';
                $args['meta_value'] = $params['upc'];
            } elseif ( ! empty( $params['q'] ) ) { 
                if ( strlen( $params['q'] ) == 40 || strlen( $params['q'] ) == 32 ) { // hash sha1 or md5
                    $args['meta_key'] = '_esg_gallery_hash';
                    $args['meta_value'] = $params['q'];
                } else {
                    $args['s'] = $params['q']; // search by title
                }
            }
        } else if ( $params['search_by'] == 'cat' ) {
            $args[ 'tax_query' ] = empty( $args[ 'tax_query' ] ) ? [] : $args[ 'tax_query' ];

            // search by cat
            $args[ 'tax_query' ][] = array(
                'taxonomy' => $this->tax_cat,
                'field' => 'slug',
                'terms' => $params['q'],
            );
        } else if ( $params['search_by'] == 'tag' ) {
            $args[ 'tax_query' ] = empty( $args[ 'tax_query' ] ) ? [] : $args[ 'tax_query' ];

            $args[ 'tax_query' ][] = array(
                'taxonomy' => $this->tax_tag,
                'field' => 'slug',
                'terms' => $params['q'],
            );
        }

        $posts_array = get_posts( $args );

        $results = [];
        
        foreach ( $posts_array as $obj ) {
            $obj = new esg_gallery( $obj );
            $results[] = $obj;
//            $results[] = $obj->get_data();
        }

        return $results;
    }
    
    /**
     * 
     * @param type $post
     * @param type $key
     * @param type $val
     * @return type
     */
    public function tax( $post, $tax, $val = null ) {
        // Some keys could be prefixed by meta:some_key 
        $tax = preg_replace( '#^\s*tax[\s\:/\|\_]*#si', '', $tax );
        $tax = trim( $tax );
        
        $post_id = $this->get_id( $post );
        $terms = wp_get_post_terms( $post_id, $tax, array( "fields" => "all" ) );
        return $terms;
    }
    
    public function get_id( $post ) {
        $id = 0;
        
        if ( is_numeric( $post ) ) {
            $id = $post;
        } elseif ( is_object( $post ) ) {
            $id = $post->ID;
        } elseif ( is_array( $post ) ) {
            $id = $post['ID'];
        } else {
            throw new Exception( 'Missing post id.' );
        }
        
        return $id;
    }
    
    /**
     * 
     * @param type $post
     * @param type $key
     * @param type $val
     * @return type
     */
    public function meta( $post, $key, $val = null ) {
        $id = $this->get_id( $post );
        $status = true;
        
        // Some keys could be prefixed by meta:some_key 
        $key = preg_replace( '#^\s*meta[\s\:/\|]*#si', '', $key );
        $key = trim( $key );
        
        if ( is_null( $val ) ) { // read meta
            $status = get_post_meta( $id, $key, true );
        } else {
            $val = is_scalar( $val ) ? trim( $val ) : $val;

            $ctx = [
                'post_id' => $id,
                'meta_key' => $key,
            ];

            if ( empty( $val ) ) {
                $val = apply_filters( 'esg_gallery_filter_delete_gallery_meta_field', $key, $ctx );
                $status = delete_post_meta( $id, $key );
            } else {
                $val = apply_filters( 'esg_gallery_filter_save_gallery_meta_field', $val, $ctx );
                $status = update_post_meta( $id, $key, $val );
            }
        }
        
        return $status;
    }
    
    function add_custom_meta_boxes_cb( $post ) {
        add_meta_box( 
            'esg_gallery_settings',
            __( 'Gallery Settings' ),
            [ $this, 'render_meta_box' ],
            $this->post_type,
            'normal',
            'default'
        );
        
        global $post;
        $esg_obj = esg_cpt::get_instance();
        
        if ( empty( $post->ID )
                || empty( $_REQUEST['post'] )
                || get_post_type() != $esg_obj->post_type ) {
            return;
        }
        
        add_meta_box( 
            'esg_gallery_images',
            __( 'Gallery Images' ),
            [ $this, 'render_gallery_images' ],
            $this->post_type,
            'normal',
            'default'
        );
    }

    public function add_meta_boxes() {
        add_action( 'add_meta_boxes_' . $this->post_type, [ $this, 'add_custom_meta_boxes_cb' ] );
        add_action( 'save_post', [ $this, 'save_meta_boxes' ], 20, 3 );
    }

    /**
     * Saves the boxes values or deletes the meta if empty.
     * 
     * @param int $post_id
     * @param obj $post
     * @param type $update
     * @return void
     */
    function save_meta_boxes( $post_id, $post, $update ) {
        // check autosave
        if ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return $post_id;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Check permissions
        if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] 
                && ! current_user_can( 'edit_page', $post_id ) ) {
            return $post_id;
        } elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }
        
        $field_and_data = [];
        
        $gal_api = esg_gallery::get_instance();
        
        foreach ( $gal_api->get_fields() as $field_rec ) {
            $key = $field_rec[ 'meta_key' ];
            
            if ( isset( $_REQUEST[ $key ] ) ) {
                $val = esg_string_util::sanitize( $_REQUEST[ $key ] );
                $this->meta( $post_id, $key, $val );
                $field_and_data[ $key ] = $val;
            }
        }
        
        // Let's have a unique fingerprint for this product with the current data
        $post_data = (array) $post;
        $post_data = array_merge( $post_data, $field_and_data );
        ksort( $post_data );
        $hash_str = http_build_query( $post_data );
        $this->meta( $post_id, '_esg_gallery_hash', sha1( $hash_str ) ); 
    }
    
    /**
     * 
     * @param obj $post
     */
    public function render_meta_box( $post ) {
        $default_text_area_settings = [
            'teeny' => true,
            'textarea_rows' => 5,
            'media_buttons' => false,
        ];
        ob_start();
        
        $gal_api = esg_gallery::get_instance();
        $fields = $gal_api->get_fields();
        ?>
        <?php foreach ( $fields as $field_rec ) : ?>
            <?php 
                $key = $field_rec[ 'meta_key' ];
                $label = $field_rec[ 'label' ];
                $val = $this->meta( $post, $key );
                $notice = '';
                
                if ( empty( $val ) ) {
                    $maybe_req_var = $this->get( $key );
                
                    if ( ! empty( $maybe_req_var ) ) {
                        $val = $maybe_req_var;
                    } elseif ( !empty ( $field_rec[ 'default' ] ) ) {
                        $val = $field_rec[ 'default' ];
                    }
                    
                    if ( $key == '_esg_gallery_upload_pass' && empty( $val ) ) {
                        $notice = __( '<em><i class="uk-icon-warning"></i> Note: We highly recommended creating a password to protect your gallery.</em>', 'esg' );
                    }
                }
            ?>
            <label for="<?php echo $key; ?>" class="esg_field_label">
                <?php echo esc_html__( $label, 'esg' ); ?></label>
                    <?php if ( $field_rec['input_type'] == 'textarea' ) : ?>
                        <?php
                           $editor_id = $key;
                           $current_area_settings = array_merge( $default_text_area_settings, [
                               'textarea_name' => $key,
                           ] );
                           
                           wp_editor( $val, $editor_id, $current_area_settings );
                        ?>
                    <?php elseif ( $field_rec['input_type'] == 'text' ) : ?>
                        <input type="text" 
                           id="<?php echo $key; ?>"
                           name="<?php echo $key; ?>" 
                           class="esg_text_field" 
                           value="<?php esc_attr_e( $val ); ?>" />
                    <?php elseif ( $field_rec['input_type'] == 'dropdown' ) : ?>
                        <?php
                        echo esg_html_util::html_select( $key, $val, $field_rec['options'] );
                        ?>
                    <?php elseif ( $field_rec['input_type'] == 'big_text' ) : ?>
                        <input type="text" 
                           id="<?php echo $key; ?>"
                           name="<?php echo $key; ?>" 
                           class="esg_text_field widefat" 
                           value="<?php esc_attr_e( $val ); ?>" />
                    <?php else : ?>
                        Don't know how to render field: <?php echo $key; ?>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $notice ) ) : ?>
                        <div class="esg_warning"><?php echo $notice; ?></div>
                    <?php endif; ?>
            <hr/>
            <?php endforeach; ?>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Renders a button that 
     * @param obj $post
     */
    public function get_pick_images_from_lib( $post = null ) {
        ob_start();
        ?>
        <div id="esg_pick_images_from_media_library_wrapper" class="esg_pick_images_from_media_library_wrapper uk-margin-top">
            <button id="esg_pick_images_from_media_library" 
                class="esg_pick_images_from_media_library button-primary uk-margin-small-bottom"
                data-type="image"><?php _e( '<i class="uk-icon-plus"></i> Add Image(s) From Media Library', 'esg' ); ?></button>
            <div class="uk-margin-large-bottom">
				<em>Hold CTRL (Windows) or CMD (Mac) key to select multiple images.</em>
            </div>
        </div>
        <?php
        $buff = ob_get_clean();
        return $buff;
    }
    
    public function render_delete_media($ctx) {
        $user_api = esg_user::get_instance();
        
        if ( ! $user_api->can_access( [ 'gallery_id' => $ctx['gallery_item']['id'] ] ) ) {
            return;
        }
        ?>
            <div class="esg_gallery_remove">
                <a href='javascript:void(0);' data-uk-tooltip title="Remove item from gallery"
                   data-media_id='<?php echo $ctx['gallery_item']['id'];?>' 
                   class="esg_gallery_item_admin_delete_media_btn uk-close uk-close-alt"></a>
            </div>
        <?php
    }
    
    /**
     * 
     * @param obj $post
     */
    public function render_gallery_images( $post, $ctx = [] ) {
        $cpt_obj = esg_cpt::get_instance();
        $post_id = $cpt_obj->get_id($post);

        $gallery_shortcode = sprintf( '[esg_gallery ctx=admin id="%d" admin_upload=0 featured_image=0 gallery_title=0 gallery_description=0]', $post_id );

        ob_start();
        echo $this->get_pick_images_from_lib($post);
        $gallery_buff = do_shortcode( $gallery_shortcode );
        echo $gallery_buff;
        $buff = ob_get_clean();
        echo $buff;
    }
    
    function get_key($something) {
        return isset( $this->$something ) ? $this->$something : null;
    }
    
    public function register_custom_cols() {
        add_filter( 'manage_' . $this->post_type . '_posts_columns' , [ $this, 'add_columns' ] );
        add_action( 'manage_' . $this->post_type . '_posts_custom_column' , [ $this, 'display_custom_cols' ], 10, 2 );
    }
    
    /* Add custom column to post list */
    public function add_columns( $columns ) {
        // Let's remove any taxonomies
        foreach ($columns as $id => $label ) {
            if ( preg_match( '#tax|date#si', $id ) ) {
                unset( $columns[$id] );
            }
        }
        
        $columns = array_merge( $columns,
            array( 'esg_shortcode' => __( 'Shortcode', 'esg' ) ),
            array( 'esg_upload' => __( 'Upload', 'esg' ) ),
            array( 'esg_gallery_id' => __( 'Gallery ID', 'esg' ) )
        );
        
        return $columns;
    }

    /**
     * Generates the shortcode for a given gallery id.
     * @param int $post_id
     */
    public function generate_shortcode( $post_id, $attribs = [] ) {
        $attribs_str = $this->attr_to_str($attribs);
        $post_id = absint( $post_id );
        $buff = $post_id ? sprintf( '[esg_gallery id="%d" ' . $attribs_str . ']', $post_id ) : '';
        return $buff;
    }

    /**
     * 
     * @param array $attribs
     * @return str
     * @see http://stackoverflow.com/questions/6132721/http-build-query-without-url-encoding
     */
    public function attr_to_str( $attribs = [] ) {
        $attribs_str = empty($attribs) ? '' : http_build_query($attribs, '', ' ' );
        $attribs_str = urldecode( $attribs_str );
        return $attribs_str;
    }
    
    /**
     * Generates the shortcode for a given gallery id.
     * @param int $post_id
     */
    public function generate_upload_shortcode( $post_id, $attribs = [] ) {
        $attribs_str = $this->attr_to_str($attribs);
        $post_id = absint( $post_id );
        $buff = $post_id ? sprintf( '[esg_upload id="%d" ' . $attribs_str . ']', $post_id ) : '';
        return $buff;
    }
    
    /**
     * Generates the shortcode for a given gallery id.
     * @param int $post_id
     */
    public function generate_upload_url( $post_id ) {
        $post_id = absint( $post_id );
        $buff = sprintf( site_url( '/?esg_up=%d'), $post_id );
        return $buff;
    }
    
    /**
     * Generates the shortcode for a given gallery id.
     * @param int $post_id
     */
    public function generate_preview_url( $post_id ) {
        $post_id = absint( $post_id );
        $buff = sprintf( site_url( '/?esg_preview=%d'), $post_id );
        return $buff;
    }
    
    public function display_custom_cols( $column, $post_id ) {
	switch ( $column ) {
            case 'esg_gallery_id':
                echo $post_id;
                break;

            case 'esg_shortcode':
                echo $this->generate_shortcode( $post_id );
                break;

            case 'esg_upload':
                echo sprintf( '[esg_upload id="%d"]', $post_id );
                echo '<br/> or <br/>' . $this->generate_upload_url( $post_id );

                break;
	}
    }
}
