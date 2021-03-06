<?php

$user_api = esg_user::get_instance();
$user_api->install_hooks();

add_action( 'init', array( $user_api, 'init' ) );

/**
* $user_api = esg_user::get_instance();
*
* Singleton pattern i.e. we have only one instance of this obj
* @todo use some sort of factory and dynamically instantiate membership based on the 
 * memebership plugin.
* @staticvar type $instance
* @return cls
*/
class esg_user extends esg_singleton {
    /**
     * This is prefixed to all meta keys. _ means system and won't be shown for editing
     * @var str
     */
    private $meta_key_prefix = '_esg_';

    public function init() {
        
    }
    
    function install_hooks() {
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }

    /**
     *
     * @return void
     */
    public function admin_init() {
        if ( $this->is_admin() ) {
            return;
        }
    }

    /**
     * Returns user obj for a given user id or currently logged in one.
     * The search can be done by email, id, username etc.
     * @param int/obj $user_id
     * @return obj
     */
    public function get_user( $user_id = 0 ) {
        $user_obj = null;
        
        if ( empty( $user_id ) ) {
            $user_obj = wp_get_current_user();
        } elseif ( is_object( $user_id ) ) {
            $user_obj = $user_id;
        } elseif ( ! is_scalar( $user_id ) ) {
            throw new Exception( "Wrong ID." );
        } elseif ( is_numeric( $user_id ) ) {
            $user_obj = get_user_by( 'id', (int) $user_id );
        } elseif ( strpos( $user_id, '@' ) !== false ) {
            $user_obj = get_user_by( 'email', $user_id );
        } else {
            $user_obj = get_user_by( 'login', $user_id );
        }

        return $user_obj;
    }

    /**
     * orb->obj->is_admin();
     *
     * @return bool
     */
    public function is_admin( $user_id = 0 ) {
        $permission = 'manage_options';
        $is_admin = $user_id
                ? user_can( $user_id, $permission )
                : current_user_can( $permission );
        
        return $is_admin;
    }

    /**
     * orb->obj->is_editor();
     *
     * @return bool
     * @see https://codex.wordpress.org/Roles_and_Capabilities#Editor
     */
    public function is_editor( $user_id = 0 ) {
        $permission = 'edit_others_posts';
        $is_admin = $user_id
                ? user_can( $user_id, $permission )
                : current_user_can( $permission );

        return $is_admin;
    }

    /**
     * Gets the QS api key for the currently logged in user id or for the supplied one.
     * @param void
     * @return str
     */
    public function get_api_key( $user_id = 0) {
        $api_key = $this->get_meta( 'api_key', $user_id );

        return $api_key;
    }

    /**
     * 
     * @return str
     */
    public function get_ip($param = null) {
        // This could have been defined in core apps
        if ( method_exists( 'Orbisius_Sandboxer_Util', 'getUserIP' ) ) {
            return Orbisius_Sandboxer_Util::getUserIP($param);
        }

        $ips = array();

        $vars = array(
            'HTTP_X_FORWARDED',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'REMOTE_ADDR',
        );

        foreach ($vars as $key) {
            if (!empty($_SERVER[$key])) {
                $ips[] = $_SERVER[$key];
            }
        }

        $ips = array_map('strip_tags', $ips);
        $ips = array_map('trim', $ips);
        $ips = array_unique($ips);

        // My vm has multiple ips we'll skip some.
        $skip_ips = [
            '127.0.0.1',
            '192.168.168.1',
        ];

        if ( count( $ips ) > 1 ) {
            $new_ips = array_diff( $ips, $skip_ips );

            if ( ! empty( $new_ips ) ) {
                $ips = $new_ips;
            }
        }

        return array_shift($ips);
    }

    /**
     * Sets the api key for the selected user id or for the current user id
     * @param str $api_key
     * @return str
     */
    public function set_api_key( $api_key, $user_id = 0 ) {
        $api_key = $this->set_meta( 'api_key', $api_key, $user_id );
        return $api_key;
    }
  
    /**
     * Returns the ID of the currently logged in user or the ID of the supplied user obj.
     * @return int
     */
    public function get_user_id( $user_id_or_obj = null ) {
        $user_obj = $this->get_user($user_id_or_obj);
        return !empty($user_obj->ID) ? $user_obj->ID : 0;
    }

    /**
     *
     * @return str
     */
    public function get_email( $user_id = 0 ) {
        $user_obj = $this->get_user( $user_id );
        return !empty($user_obj->user_email) ? $user_obj->user_email : '';
    }

    public function sanitize_id( $id ) {
        return abs( $id );
    }

    public function is_logged_in() {
        return $this->get_user_id() > 0;
    }

    /**
     * Gets meta of the currently logged in user or the those of the supplied one
     * @param str $key
     * @param int $user_id
     * @return mixed
     */
    public function get_meta( $key, $user_id = 0 ) {
        if ( empty( $key ) ) {
            throw new Exception( "Key cannot be empty." );
        }

        $user_obj = $this->get_user( $user_id );
        $user_id = $this->get_user_id( $user_obj );
        $user_id = $this->sanitize_id( $user_id );

        if ( empty( $user_id ) ) {
            return false;
        }
        
        $val = get_user_meta( $user_id, $this->meta_key_prefix . $key, true );
        
        return $val;
    }

    /**
     * Sets meta of the currently logged in user or the those of the supplied one
     * @param str $key
     * @param mixed $val
     * @param int $user_id
     * @return mixed
     */
    public function set_meta( $key, $val = null, $user_id = 0 ) {
        if ( empty( $key ) ) {
            throw new Exception( "Key cannot be empty." );
        }
        
        $user_id = $user_id ? $user_id : $this->get_user_id();
        $user_id = $this->sanitize_id( $user_id );

        if ( empty( $user_id ) ) {
            return false;
        }

        if ( empty( $val ) ) {
            delete_user_meta( $user_id, $this->meta_key_prefix . $key );
        } else {
            update_user_meta( $user_id, $this->meta_key_prefix . $key, $val );
        }

        return $val;
    }

    /**
     * using internal meta key with the key
     * 
     * @param str $key
     * @return str
     */
    public function get_meta_key_with_prefix( $key ) {
         return $this->meta_key_prefix . $key;
    }

    /**
     *
     * @param array $params
     * @return array
     */
    public function search( $params = [] ) {
        $args = array(
            /*'blog_id'      => $GLOBALS['blog_id'],
            'role'         => '',
            'role__in'     => array(),
            'role__not_in' => array(),
            'meta_key'     => '',
            'meta_value'   => '',
            'meta_compare' => '',
            'meta_query'   => array(),
            'date_query'   => array(),
            'include'      => array(),
            'exclude'      => array(),
            'orderby'      => 'login',
            'order'        => 'ASC',
            'offset'       => '',
            'search'       => '',
            'number'       => '',
            'count_total'  => false,
            'fields'       => 'all',
            'who'          => ''*/
        );

        $users = [];
        $raw_users_obj_arr = get_users( $args );

        if ( isset( $params['load_meta'] ) ) {
            foreach ( $raw_users_obj_arr as $user_obj ) {
                $u = (array) $user_obj;
                $u['meta']['sys_api_key'] = $this->get_api_key( $user_obj );
                $u['user'] = $user_obj->data->user_login;
                $u['email'] = $user_obj->data->user_email;
                $users[ $user_obj->ID ] = $u;
            }
        }

        return $users;
    }
    
    /**
     *
     * @param array $params
     * @return array
     */
    public function has_access( $plan = '', $user_id = 0 ) {
        $user_obj = $this->get_user( $user_id );
        return ! empty( $user_obj->ID );
    }
    
    /**
     * This is called by a shortcode: [esg_user_notices]
     */
    public function render_user_notices() {
       $ctx = [];
       do_action( 'esg_action_user_notices', $ctx );
   }
    
    /**
     * This is called by a shortcode: [esg_user_info]
     */
    public function process_shortcode_user_info( $attribs = [] ) {
       $buff = '';
       $user_id = 0;
       $user_id = $this->get_user_id($user_id);
       
       if ( $user_id ) {
           $qs_user_id_esc = esc_attr( $this->get_meta( 'sys_user_id' ) );
           $email = $this->get_email($user_id);
           $buff .= "<div class='app_user_info' title='QS User ID: $qs_user_id_esc'>Email: $email (ID: $user_id)</div>\n";
       }
       
       $buff = do_shortcode( $buff );
       
       return $buff;
   }
   
   /**
    * We can check for roles in the future.
    * @param array $ctx
    * @return bool
    */
   public function can_access( $ctx = [] ) {
       return $this->is_admin();
   }
}
