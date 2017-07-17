<?php

class esg_gallery extends esg_singleton {
    private $item = [];
    
    /**
     *
     * @var array 
     */
    public $fields = [
        '_esg_gallery_items_per_row' => [
            'label' => 'Items per Row',
            'meta_key' => '_esg_gallery_items_per_row',
            'input_type' => 'dropdown',
            'options' => [
                '' => '',
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
            ],
            'default' => 3,
            'val_type' => 'int',
        ],
        
        '_esg_gallery_type' => [
            'label' => 'Gallery Type',
            'meta_key' => '_esg_gallery_type',
            'input_type' => 'dropdown',
            'options' => [
                '' => '',
                'grid' => 'Grid',
            ],
            'default' => 'grid',
            'val_type' => 'text',
        ],
        
        '_esg_gallery_upload_pass' => [
            'label' => 'Upload Password',
            'meta_key' => '_esg_gallery_upload_pass',
            'input_type' => 'text',
            'default' => '',
            'val_type' => 'string',
        ],
    ];
    
    private $field_map = [
        'ID' => 'id',
        'post_title' => 'title',
        'post_content' => 'description',
        'post_date' => 'date_added',
        
        // Meta is dynamically populated based on the $fields
        
        // Taxonomy data
        'tax:esg_cat' => 'category',
        'tax:esg_tag' => 'tag',
    ];

    // @todo refactor this because it's a singleton.
    public function __construct( $post_obj = null ) {
        add_filter( 'esg_gallery_filter_save_gallery_meta_field', [ $this, 'filter_meta_fields' ], 10, 2 );
        
        if ( is_numeric( $post_obj ) ) {
            $p = get_post( $post_obj );
            
            if ( ! empty( $p->ID ) ) {
                $post_obj = $p;
            }
        }
        
        if ( ! empty( $post_obj ) ) {
            $arr = (array) $post_obj;
            $cpt_obj = esg_cpt::get_instance();

            foreach ( $this->get_fields() as $rec ) {
                $this->field_map[ 'meta:' . $rec['meta_key'] ] = $rec['meta_key'];
            }

            foreach ( $this->field_map as $key => $mapped_key ) {
                $val = empty( $arr[ $key ] ) ? '' : $arr[ $key ];
                $val = trim( $val );

                // If it's meta we'll pull that info.
                if ( stripos( $key, 'meta:' ) !== false ) {
                    $val = $cpt_obj->meta( $arr[ 'ID' ], $key );
                } elseif ( stripos( $key, 'tax:' ) !== false ) {
                    $val = $cpt_obj->tax( $arr[ 'ID' ], $key );
                }

                $this->item[ $mapped_key ] = $val;
            }
        }
    }

    /**
     * This method will get called by a filter and we'll make sure some params are ints before save.
     * 
     * @param mixed $val
     * @param array $ctx
     * @return mixed
     */
    public function filter_meta_fields( $val, $ctx ) {
        if ( ! empty( $ctx['meta_key'] ) && $ctx['meta_key'] == '_esg_gallery_items_per_row' ) {
            $val = absint( $val );
        }
        
        return $val;
    }
    
    public function from_data( $post_obj = null ) {
        if ( is_numeric( $post_obj ) ) {
            $p = get_post( $post_obj );
            
            if ( ! empty( $p->ID ) ) {
                $post_obj = $p;
            }
        }
        
        if ( ! empty( $post_obj ) ) {
            $arr = (array) $post_obj;
            $cpt_obj = esg_cpt::get_instance();

            foreach ( $this->get_fields() as $rec ) {
                $this->field_map[ 'meta:' . $rec['meta_key'] ] = $rec['meta_key'];
            }

            foreach ( $this->field_map as $key => $mapped_key ) {
                $val = empty( $arr[ $key ] ) ? '' : $arr[ $key ];
                $val = trim( $val );

                // If it's meta we'll pull that info.
                if ( stripos( $key, 'meta:' ) !== false ) {
                    $val = $cpt_obj->meta( $arr[ 'ID' ], $key );
                } elseif ( stripos( $key, 'tax:' ) !== false ) {
                    $val = $cpt_obj->tax( $arr[ 'ID' ], $key );
                }

                $this->item[ $mapped_key ] = $val;
            }
        }
    }

    public function get_icon_src() {
        $url_source = plugins_url( '/assets/icons/icon.svg', ESG_CORE_BASE_PLUGIN );
        return $url_source;
    }
    
    public function get_fields() {
        $fields = apply_filters( 'esg_gallery_filter_gallery_fields', $this->fields );
        return $fields;
    }
    
    /**
     * Gets one or more data.
     * @return array
     */
    public function get_data( $key = null, $default = null ) {
        if ( empty( $key ) ) {
            return $this->item;
        }
        
        if ( $key == 'link' ) {
            $link = get_permalink( $this->get_data( 'id' ) );
            return $link;
        }
        
        if ( $key == 'thumbnail_src' ) {
            $esg_admin = esg_module_admin::get_instance();
            $opts = $esg_admin->get_options();
            $size = $opts['gallery_thumbnail_src'];
            $th_src_arr = wp_get_attachment_image_src( $this->get_data( 'id' ), $size );
            $src = empty( $th_src_arr[0] ) ? '' : $th_src_arr[0];
            return $src;
        }
        
        if ( $key == 'featured_image_html' ) {
            $img_html = get_the_post_thumbnail( $this->get_data( 'id' ), 'thumbnail' );
            $img_html = preg_replace( '#(width|height)\s*=\"\d*\"\s#si', '', $img_html );
            return $img_html;
        }

        if ( ! empty( $this->item[ $key ] ) ) { // match
            $val = $this->item[ $key ];
        } else {
            // We will try to find a close match. Something that ends with the key we're searching for.
            // I want to skip the prefix (if any).
            // We'll use the first key that ends in the whatever we're searching for.
            // e.g. gallery_type -> _esg_gallery_type
            $keys = preg_grep( '#' . preg_quote( $key, '#' ) . '$#si', array_keys( $this->item ) );
            $first_key = array_shift( $keys );
            
            if ( ! empty( $first_key ) && ! empty( $this->item[ $first_key ] ) ) {
                $val = $this->item[ $first_key ];
            } else {
                if ( is_null( $default ) ) { 
                    $fields = $this->get_fields();

                    if ( ! empty( $fields[ $first_key ]['default'] ) ) {
                        $default = $fields[ $first_key ]['default'];
                    }
                }
                
                $val = $default;
            }
        }
        
        return $val;
    }
    
    /**
     * 
     * @param array $params
     * @return esg_cpt_result
     */
    public function update( $params = [] ) {
        $params = array_merge( $this->item, $params );
        
        if ( ! empty( $params['id'] ) ) {
            $params['ID'] = $params['id'];
        }

        if ( ! empty( $params['status'] ) ) {
            $params['post_status'] = $params['status'];
        }
        
        $post_id = wp_update_post( $params, true );

        $res = new esg_cpt_result();
        $res->status = ! is_wp_error( $post_id );
        return $res;
    }
    
    public function get_cpt() {
        return $this->post_type;
    }
    
    public function render( $item = [], $options = [] ) {
        ob_start();
        $item = empty( $item ) ? $this->item : $item;
        
        ?>
        <div class="esg_detail_wrapper">
            <div class="esg_gallery_detail">
                <?php if ( 1 ) : /* Inactive code */ ?>
                    <!-- meta fields -->
                    <?php foreach ( $this->get_fields() as $idx => $field_rec ) : ?>
                        <?php
                            $val = empty( $item[ $field_rec['meta_key'] ] ) ? '' : $item[ $field_rec['meta_key'] ];

                            // Some fields may require additional post processing.
                            // e.g. something that looks like a link it has to be made clickable.
                            if ( ! empty( $field_rec['post_process'] ) ) {
                                $post_process = (array) $field_rec['post_process'];

                                foreach ( $post_process as $cb ) {
                                    if ( is_callable( $cb ) ) {
                                        $val = call_user_func( $cb, $val );
                                    }
                                }
                            }
                        ?>
                        <?php if ( ! empty( $val ) ) : ?>
                            <div class="<?php echo $field_rec['meta_key']; ?>_wrapper">
                                <label><?php echo $field_rec['label']; ?></label>
                                <div class="<?php echo $field_rec['meta_key']; ?>_content">
                                    <?php echo $val; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <!-- /meta fields -->
                <?php endif; ?>
            </div>
        </div>

        <?php
        $buff = ob_get_clean();
        return $buff;
    }
    
    /**
     * Gets product thumbnail and has a default if necessary.
     * @return str
     * @see http://stackoverflow.com/questions/11261883/how-to-get-wordpress-post-featured-image-url
     */
    public function get_thumbnail_src() {
        $id = $this->get_data('id');
        
        if ( ! empty( $id ) && has_post_thumbnail( $id ) ) {
            $th_url = wp_get_attachment_url( get_post_thumbnail_id( $id ), [ 250, 250 ] );
        } else {
            $th_url = plugins_url( "/assets/images/missing_image.jpg", ESG_CORE_BASE_PLUGIN );
        }
        
        return $th_url;
    }
    
    /**
     * Gets product thumbnail and has a default if necessary.
     * @return str
     * @see http://stackoverflow.com/questions/11261883/how-to-get-wordpress-post-featured-image-url
     */
    public function get_thumbnail_html() {
        $th_url = $this->get_thumbnail_src();
        ob_start();
        ?>
            <div class="thumbnail">
                <div class="thumbnail_title">Product Picture</div>
                <img src="<?php echo $th_url; ?>" alt="Product Picture" />
            </div><!-- /thumbnail -->
        <?php
        $buff = ob_get_clean();
        return $buff;
    }
    
    /**
     * Checks if a product exists already in the db.
     * @param array $data
     * @see http://wordpress.stackexchange.com/questions/108912/using-wpdb-to-query-posts-with-meta-value-containing-current-post-id
     */
    public function exists( $data = [] ) {
        $res = new esg_result();
        $exists = 0;
        
        // Search by ID
        if ( empty( $data ) && ! empty( $this->item['id'] ) ) {
            $exists = $this->item['id'];
        } else if ( ! empty( $data['id'] ) ) {
            $post_obj = get_post( $data['id'] );
            $exists = ! empty( $post_obj->ID ) ? $post_obj->ID : 0;
        } elseif ( ! empty( $data['upc'] ) ) { // The search is done via UPC
            $exists = $this->get_id_by_meta_key( $data['upc'] );
        } elseif ( ! empty( $data['upc_code'] ) ) { // The search is done via UPC
            $exists = $this->get_id_by_meta_key( $data['upc_code'] );
        } elseif ( ! empty( $data['barcode'] ) ) { // The search is done via UPC
            $exists = $this->get_id_by_meta_key( $data['barcode'], '_esg_gallery_barcode' );
        } elseif ( 0&&! empty( $data['product_name'] ) ) {
            // This is not reliable.
            $post_obj = get_page_by_title( $data['product_name'] );
        }
        
        $res->success(1);
        $res->data( 'id', is_numeric( $exists ) ? $exists : 0 );
        $res->data( 'exists', $exists );
        
        return $res;
    }
    
    /**
     * Performs one sql query to get an id by meta key.
     * 
     * @global type $wpdb
     * @param type $meta_val
     * @param type $meta_key
     * @return int/0
     */
    public function get_id_by_meta_key( $meta_val, $meta_key = '_esg_gallery_upc' ) {
        global $wpdb;
        
        $cpt = esg_cpt::get_instance();
        $type = $cpt->get_key( 'post_type' );
        $prep = $wpdb->prepare(
            "SELECT DISTINCT $wpdb->posts.ID FROM $wpdb->posts, $wpdb->postmeta
            WHERE 
                $wpdb->posts.ID = $wpdb->postmeta.post_id AND
                $wpdb->posts.post_status = 'publish' AND
                $wpdb->posts.post_type = '$type' AND
                $wpdb->postmeta.meta_key = %s AND
                meta_value = %s 
                LIMIT 1",
                $meta_key,
                $meta_val
        );

        $id = ! empty( $prep ) ? $wpdb->get_var( $prep ) : false;
        $exists = ! empty( $id ) ? $id : 0;

        return $exists;
    }
    
    /**
     * 
     * @param array $data
     */
    public function add( $data ) {
        $cpt = esg_cpt::get_instance();
        $type = $cpt->get_key( 'post_type' );
        
        $res = new esg_result();
        
        // Create post object
        $my_post = array(
            'post_title' => $data['product_name'],
            'post_type' => $type,
            'post_status' => 'publish',
        );

        if ( ! empty( $data['content'] ) ) {
            $my_post['post_content'] = $data['post_content'];
        }

        if ( ! empty( $data['user_id'] ) ) {
            $my_post['post_author'] = (int) $data['user_id'];
        }

        // Set taxanomies on insert how nice.
        // https://developer.wordpress.org/reference/functions/wp_insert_post/
        if ( 0 && ! empty( $data['cat'] ) ) {
            /*'tax_input'    => array(
                'hierarchical_tax'     => $hierarchical_tax,
                'non_hierarchical_tax' => $non_hierarchical_tax,
            ),*/
            //$my_post['tax_input'] = '';
        }
        
        // Set meta on insert how nice.
        if ( 0 && ! empty( $data['meta'] ) ) {
            /*'meta_input'   => array(
                'test_meta_key' => 'value of test_meta_key',
            ),
            ),*/
            //$my_post['meta_input'] = '';
        }
        
        $wp_error = null;

        // Insert the post into the database
        $ins_product_id = wp_insert_post( $my_post, $wp_error );

        $res->data( 'id', $ins_product_id );
        $res->success( is_numeric( $ins_product_id ) );
        
        return $res;
    }
    
    public function delete( $id ) {
        $res = new esg_result();
        $del_res = wp_delete_post( $id, true ); // skip trash
        
        $res->success(!empty($del_res));
        
        return $res;
    }
    
    /**
     * Sets internal cat for a given product
     * @param type $prod_data
     * @return esg_result
     */
    public function set_cat( $prod_data ) {
        $res = new esg_result();
        
        if ( ! empty( $prod_data['id'] ) 
                && ! empty( $prod_data['parsed_cat'] )  ) {
            
            $cpt_obj = esg_cpt::get_instance();
            $taxonomy = $cpt_obj->get_key( 'tax_cat' );
            
            /*
             'parsed_cat' => 
                array (
                  0 => 'Inside the Home',
                  1 => 'Spot and tain remover',
                  2 => 'Fabric',
                ),
            */
            foreach ( $prod_data['parsed_cat'] as $cat ) {
                 /*
                    object(WP_Term)[321]
                    public 'term_id' => int 5
                    public 'name' => string 'Bin 1' (length=5)
                    public 'slug' => string 'bin1' (length=4)
                    public 'term_group' => int 0
                    public 'term_taxonomy_id' => int 5
                    public 'taxonomy' => string 'hm_bin' (length=6)
                    public 'description' => string 'Auto Products  Brake Fluid Category' (length=35)
                    public 'parent' => int 0
                    public 'count' => int 3
                    public 'filter' => string 'raw' (length=3)
                */
                $cat_obj = get_term_by( 'name', $cat, $taxonomy );
                
                if ( empty( $cat_obj->term_id ) ) {
                    $cat_obj = wp_insert_term( $cat, $taxonomy, array() );
                }
                
                // should exist by now.
                if ( ! empty( $cat_obj->term_id ) ) {
                    $append = 1;
                    wp_set_object_terms( $prod_data['id'], $cat_obj->term_id, $taxonomy, $append );
                    $res->success(true);
                }
            }
        }
        
        return $res;
    }
    
    public function set_thumbnail( $prod_data ) {
        $res = new esg_result();
        
        return $res;        
    }
    
    public function set_meta( $prod_data ) {
        $res = new esg_result();
        
        return $res;        
    }
    
    /**
     * 
     * @param array $filters
     * @return array
     */
    public function get_images( $filters = [] ) {
        $res = new esg_result();
        
        $args = array( 
            'post_type' => 'attachment', 
            'post_status' => 'inherit',
            'posts_per_page' => empty($filters['limit']) ? 250 : (int) $filters['limit'],
            'post_parent' => $this->get_data( 'id' ), 
        );
        
        if ( ! empty( $filters['media_id'] ) ) {
            if ( is_scalar( $filters['media_id'] ) ) {
                $args['post__in'] = preg_split( '#\s*\,+\s*#si', $filters['media_id'] );
            } elseif ( is_array( $filters['media_id'] ) ) {
                $args['post__in'] = join( ',', $filters['media_id'] );
            }
        }

        $attachments = get_posts( $args );
        $images = [];
        
        if ( ! empty( $attachments ) ) {
            $esg_admin = esg_module_admin::get_instance();
            $opts = $esg_admin->get_options();
            
            // @todo we can have custom_size_100x100 and we can parse this to an array
            // [100,100] and pass it as a size.
            $size = $opts['gallery_thumbnail_src'];
            
            foreach ( $attachments as $attachment ) {
                $rec = [];
                $rec['id'] = $attachment->ID;
                $rec['title'] = apply_filters( 'the_title' , $attachment->post_title );
                $rec['post_date'] = $attachment->post_date;
                $rec['author_id'] = $attachment->post_author;

                // Get thumb
                $th_src_arr = wp_get_attachment_image_src( $attachment->ID, $size );
                $rec['thumbnail_src'] = empty( $th_src_arr[0] ) ? '' : $th_src_arr[0];

                // Full source
                $src_arr = wp_get_attachment_image_src( $attachment->ID, 'full' );
                $rec['src'] = empty( $src_arr[0] ) ? '' : $src_arr[0];
                    
                if ( ! empty( $filters['link'] ) ) {
                    $rec['link'] = get_attachment_link( $attachment->ID , false );
                }
                
                /*
                $rec['html'] = wp_get_attachment_image( $attachment->ID, 'full' );
                $rec['thumbnail_html'] = wp_get_attachment_image( $attachment->ID, 'thumbnail' );
                
                $th_src_arr = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' );
                $rec['thumbnail_src'] = empty( $th_src_arr[0] ) ? '' : $th_src_arr[0];*/
                
                $images[] = $rec;
            }
        }
        
        return $images;        
    }

    private $supported_mime_types = [
        'image/jpg',
        'image/jpeg',
        'image/png',
    ];

    private $upload_key = 'esg_upload';

    public function get_upload_key() {
        return $this->upload_key;
    }
    
    public function get_supported_mime_types() {
        $types = apply_filters( 'esg_gallery_filter_supported_mime_types', $this->supported_mime_types );
        return $types;
    }
    
    public function is_supported_upload( $rec_from_files ) {
        if ( empty( $rec_from_files['size'] ) || $rec_from_files['size'] <= 0 ) {
            return false;
        }
        
        if ( empty( $rec_from_files['type'] ) ) {
            return false;
        }
        
        $mime = $rec_from_files['type'];

        if ( in_array( $mime, $this->get_supported_mime_types() ) ) {
            return true;
        }
        
        return false;
    }

    /**
     * Sets the parent id of a gallery.
     * This means an image can be in only 1 gallery.
     * @param array $filters
     */
    public function set_media_parent( $filters = [] ) {
        $res = new esg_result();

        $media_id = empty( $filters['media_id'] ) ? 0 : $filters['media_id'];
        $gallery_id = empty( $filters['gallery_id'] ) ? 0 : (int) $filters['gallery_id'];

        $media_ids_arr = (array) $media_id;
        $media_ids_arr = array_filter($media_ids_arr);
        $media_ids_arr = array_unique($media_ids_arr);
        
        foreach ( $media_ids_arr as $cur_media_id ) {
            $attachment = array(
                'ID' => $cur_media_id,
                'post_parent' => $gallery_id,
            );
            
            $up_res = wp_update_post( $attachment );
        }

        $res->status( $up_res !== false );
        return $res;
    }
    
    /**
     * 
     * @return array
     */
    public function get_thumbnail_options() {
        $th_options = [
            'thumbnail' => 'Thumbnail (150x150px max)',
            'medium' => 'Medium (300x300px max)',
            'medium_large' => 'Medium Large (768 x ? max)',
            'large' => 'Large (640x640 max)',
            'full' => 'Full (Original Image)',
        ];
        
        return $th_options;
    }
}
