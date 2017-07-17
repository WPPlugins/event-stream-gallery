<?php

/**
 * @stodo:: singleton here?
 * determine the asset suffix
 * load from internally defined assets
 */
class esg_base extends esg_singleton {
    protected $data = null;
    
    /**
     *
     * @param array $data
     */
    public function __construct( $data = array() ) {
        $this->req_init($data);
    }

    public function get_asset_suffix() {
        // @todo: minimize all asssets and re-enable this
        $suffix = 0&&empty($_SERVER['DEV_ENV']) ? '.min' : '';
        return $suffix;
    }

    /**
     * This calls the appropriate action or filter. Our convension is to have a 'filter' or 'action' as part of the $action name.
     * If it's an action the content is captured. If it's a filter it is returned.
     * When calling it in an action context only the 2 params should be passed.
     * When calling it in a filter context 3 params should be passed. 1 for filter, 2 default value, 3 context.
     *
     * @param str $action
     * @param mixed $default_or_ctx
     * @param mixed/null $none_or_ctx
     * @return str
     */
    public function do_hook( $action, $default_or_ctx, $none_or_ctx = null ) {
        if ( ( stripos( $action, 'action') !== false ) || is_null( $none_or_ctx ) ) {
            ob_start();
            do_action( $action, $default_or_ctx );
            $buff = ob_get_contents();
            ob_end_clean();
        } else {
            $buff = apply_filters( $action, $default_or_ctx, $ctx );
        }
        
        return $buff;
    }

    /**
     * Sometimes we don't care about the result so we'll let it finish
     */
    public function become_unstoppable() {
        ignore_user_abort(true);
        set_time_limit( 15 * 50 );
    }

    /**
     * Redirect and exit. It checks if headers are sent and sents a different redirect code.
     * @param str $url
     */
    public function redirect( $url ) {
        if ( headers_sent() ) {
            $location_esc = esc_url ( $url );
            echo '<meta http-equiv="refresh" content="0;URL=\'' . $location_esc . '\'" />  '; // jic
            echo '<script language="javascript">window.parent.location="' . $location_esc . '";</script>';
        } else {
            wp_safe_redirect( $url );
        }
        
        exit;
    }

    /**
     * WP puts slashes in the values so we need to remove them.
     * @param array $data
     */
    public function req_init( $data = null ) {
        // see https://codex.wordpress.org/Function_Reference/stripslashes_deep
        if ( is_null( $this->data ) ) {
            $data = empty( $data ) ? $_REQUEST : $data;
            $this->raw_data = $data;
            $data = stripslashes_deep( $data );
            $data = $this->sanitize_data( $data );
            $this->data = $data;
        }
    }

    /**
     *
     * @param str/array $data
     * @return str/array
     * @throws Exception
     */
    public function sanitize_data( $data = null ) {
        if ( is_scalar( $data ) ) {
            $data = wp_kses_data( $data );
            $data = trim( $data );
        } elseif ( is_array( $data ) ) {
            $data = array_map( array( $this, 'sanitize_data' ), $data );
        } else {
            throw new Exception( "Invalid data type passed for sanitization" );
        }

        return $data;
    }

    /**
     *
     * @param array/void $params
     * @return bool
     */
    public function validate($params = array()) {
        return !empty($_POST);
    }

    const INT = 2;
    const FLOAT = 4;
    const ESC_ATTR = 8;
    const JS_ESC_ATTR = 16;
    const EMPTY_STR = 32; // when int/float nubmers are 0 make it an empty str
    const STRIP_SOME_TAGS = 64;
    const STRIP_ALL_TAGS = 128;
    const SKIP_STRIP_ALL_TAGS = 256;
    const FORCE_ARRAY = 512;

    /**
     * @var array
     * @see https://codex.wordpress.org/Function_Reference/wp_kses
     */
    private $allowed_permissive_html_tags = array(
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array(),
            'class' => array(),
        ),
        'br' => array(),
        'em' => array(),
        'p' => array(),
        'div' => array(),
        'hr' => array(),
        'i' => array(),
        'strong' => array(),
    );

    /**
     *
     * @param str $key
     * @return mixed
     */
    public function get( $key, $force_type = 1 ) {
        $this->req_init();
        
        $key = trim( $key );
        $val = isset($this->data[$key]) ? $this->data[$key] : '';
        
        if ( $force_type & self::FORCE_ARRAY ) {
            $val = (array) $val;
        }
        
        if ( $force_type & self::INT ) {
            if ( is_array( $val ) ) {
                $val = array_map( 'intval', $val );
                $val = array_unique( $val );
                $val = array_filter( $val );
            } else {
                $val = intval($val);

                if ( $val == 0 && $force_type & self::EMPTY_STR ) {
                    $val = "";
                }
            }
        }

        if ( $force_type & self::FLOAT ) {
            $val = floatval($val);

            if ( $val == 0 && $force_type & self::EMPTY_STR ) {
                $val = "";
            }
        }

        if ( $force_type & self::ESC_ATTR ) {
            $val = esc_attr($val);
        }

        if ( $force_type & self::JS_ESC_ATTR ) {
            $val = esc_js($val);
        }

        if ( $force_type & self::STRIP_SOME_TAGS ) {
            $val = wp_kses($val, $this->allowed_permissive_html_tags);
        }

        // Sanitizing a var
        if ( $force_type & self::STRIP_ALL_TAGS ) {
            $val = wp_kses($val, array());
        }

        $val = is_scalar($val) ? trim($val) : $val;

        return $val;
    }

    /**
     * get and esc
     * @param str $key
     * @param int $force_type
     * @return str
     */
    public function gete( $key, $force_type = 1 ) {
        $v = $this->get( $key, $force_type );
        $v = esc_attr( $v );
        return $v;
    }

    /**
     * ->msg();
     * a simple status message, no formatting except color
     */
    public function msg($msg, $status = 0, $use_inline_css = 0) {
        $id = 'app';
        $cls = $extra = $inline_css = $extra_attribs = '';

        $msg = is_scalar($msg) ? $msg : join("\n<br/>", $msg);
        $icon = 'exclamation-sign';

        if ( $status === 2 ) { // notice
            $cls = 'app_info alert alert-info';
        } elseif ( $status === 6 ) { // dismissable notice
            $cls = 'esg_warning alert alert-danger alert-dismissable';
            $extra = ' <button type="button" class="close" data-dismiss="alert" aria-hidden="false"><span aria-hidden="true">&times;</span><span class="__sr-only">Close</span></button>';
            //$extra = ' <button type="button" class="close" data-dismiss="alert" aria-hidden="false">X</button>';
        } elseif ( $status === 4 ) { // dismissable notice
            $cls = 'app_info alert alert-info alert-dismissable';
            $extra = ' <button type="button" class="close" data-dismiss="alert" aria-hidden="false"><span aria-hidden="true">&times;</span><span class="__sr-only">Close</span></button>';
            //$extra = ' <button type="button" class="close" data-dismiss="alert" aria-hidden="false">X</button>';
        } elseif ( $status == 0 || $status === false ) {
            $cls = 'esg_error alert alert-danger';
            $icon = 'remove';
        } elseif ( $status == 1 || $status === true ) {
            $cls = 'esg_success alert alert-success';
            $icon = 'ok';
        }

        if (is_array($use_inline_css)) {
            $extra_attribs = self::array2data_attr($use_inline_css);
        } elseif (!empty($use_inline_css)) {
            $inline_css = empty($status) ? 'background-color:red;' : 'background-color:green;';
            $inline_css .= 'text-align:center;margin-left: auto; margin-right:auto; padding-bottom:10px;color:white;';
        }

        $msg_icon = "<span class='glyphicon glyphicon-$icon' aria-hidden='true'></span>";
        $msg = $msg_icon . ' ' . $msg;

        $str = <<<MSG_EOF
<div id='$id-notice' class='$cls' style="$inline_css" $extra_attribs>$msg $extra</div>
MSG_EOF;
        return $str;
    }

    /**
     *
     * @param array $attributes
     * @return string
     */
    public static function array2data_attr($attributes = array()) {
        $pairs = array();

        foreach ($attributes as $name => $value) {
            $name = 'data-' . $name; // prefix the keys with data- prefix so it's accessible later.

            $name  = htmlentities($name, ENT_QUOTES, 'UTF-8');
            $value = htmlentities($value, ENT_QUOTES, 'UTF-8');

            if (is_bool($value)) {
                if ($value) {
                    $pairs[] = $name;
                }
            } else {
                $pairs[] = sprintf('%s="%s"', $name, $value);
            }
        }

        return join(' ', $pairs);
    }

    /**
     *
     * @return bool
     */
    public function check_access() {
        $user = esg_user::get_instance();
        return $user->get_user_id() > 0;
    }

    /**
     * Shown when the user can't access a resource.
     * @return str
     */
    public function no_access_msg() {
        return __( 'You need to be logged in or need higher permissions.', 'esg_cp' );
    }

    /**
     * Shown when the user can't access a resource.
     * @return str
     */
    public function has_access_msg() {
        return sprintf( __( "You are already logged. Next, go to <a href='%s'>%s</a>." ), ESG_MANAGE_PAGE, __( 'Manage', 'go_359' ) );
    }
    
    public function wp_init() {
        
    }
}
