<?php
/**
 * borrowed from qsandbox string_util
 */
class esg_string_util {
    /**
     * 
     * @param str $val
     */
    public static function exists( $key, $arr ) {
        $t = !empty( $val ) && (
                    $val == 1
                    || ( strcasecmp( $val, 'true' ) == 0 ) );

        return $t;
    }
    
    /**
     * This method is used to check if a given field has a true value.
     * e.g. show_title='yes'
     * @param str $val
     * @return bool
     */
    public static function is_true( $val ) {
        $val = trim( $val );
        $status = ! empty( $val ) && (
               $val == 1
            || $val === true
            || strcasecmp( $val, 'yes' ) == 0
            || strcasecmp( $val, 'true' ) == 0
        );

        return $status;
    }
    
    /**
     * This method is used to check if a given field has a true value.
     * e.g. show_title='yes'
     * @param str $val
     * @return bool
     */
    public static function is_false( $val ) {
        $val = trim( $val );
        $status = ! self::is_true( $val ) && (
                empty( $val )
            || strcasecmp( $val, 'no' ) == 0
            || strcasecmp( $val, 'false' ) == 0
        );

        return $status;
    }
    
    /**
     * Loops through the array and if the link matches the current page puts the $cur_css_class (appends).
     * requirement: class attrib must exist
     * NTF: if class attribute doesn't exits created it.
     * esg_string_util::set_current_page_class();
     * @param array $search_array
     * @param str $buff_or_file
     * @return array
     */
    public static function set_current_page_class( array $links, $cur_css_class ) {
        $req_uri = $_SERVER['REQUEST_URI'];
        $req_uri = preg_replace( '#\?.*#si', '', $req_uri );
        $req_uri_regex = '#href=[\'\"]' . preg_quote( $req_uri, '#' ) . '[\'\"]#si';
        $req_uri_css_regex = '#(class\s*=\s*[\'\"])(.*?[\'\"])#si';

        foreach ( $links as & $html_menu_el ) {
            if ( preg_match( $req_uri_regex, $html_menu_el ) ) {
                $html_menu_el = preg_replace( $req_uri_css_regex, '$1 ' . $cur_css_class . ' $2', $html_menu_el );
            }
        }
           
        return $links;
    }

    /**
     * esg_string_util::replace();
     * @param array $search_array
     * @param str $buff_or_file
     * @return str
     */
    public static function replace( array $search_array, $buff_or_file ) {
        $buff = $buff_or_file;

        if ( ( stripos( $buff, '.' ) !== false ) && file_exists( $buff_or_file ) ) {
            $buff = file_get_contents( $buff_or_file, LOCK_SH );
        }

        foreach ( $search_array as $key => $value ) {
            $regex = '#[\{\%]{1,5}' . preg_quote( $key, '#' ) . '[\}\%]{1,5}#si';
            $buff = preg_replace( $regex, $value, $buff );
        }

        return $buff;
    }

    /**
     * esg_string_util::readPhpConst( 'WP_DEBUG', 'C:\var\www\vhosts\qsandbox.com\users\2\8\qsu28\sites\test3.devqsandbox.com\htdocs\wp-config.php' );
     *
     * @param str $const_name
     * @param type $buff_or_file
     */
    public static function readPhpConst( $const_name, $buff_or_file ) {
        $val = esg_string_util::phpConst( $const_name, null, $buff_or_file );
        return $val;
    }

     /**
     * This method reads or updates a php constant from a string or a file.
     * If the constant doesn't exist it will be created just right after the first php opening php tag.
     *
     * Set value
     * $new_file_buff = esg_string_util::phpConst( 'DB_NAME', 'new_db_name_2x_modified', $file_or_buff );
     *
     * Read value
     * $enabled = esg_string_util::phpConst( 'WP_DEBUG', null, 'C:\var\www\vhosts\qsandbox.com\users\2\8\qsu28\sites\test3.devqsandbox.com\htdocs\wp-config.php' );
     *
     * @param str $const_name
     * @param mixed $const_new_val
     * @param str $buff_or_file
     * @param str $target_file if it exists the buffer is saved in that file otherwise it's returned.
     */
    public static function phpConst( $const_name, $const_new_val, $buff_or_file, $target_file = '' ) {
        $const_name_esc = preg_quote( $const_name, '#' ); // It should not contain any special chars but just in case.
        $const_name_esc = strtoupper( $const_name ); // that's how a good looking const name looks like.
        $wp_config_buff = preg_match( '#\.php$#si', $buff_or_file ) ? esg_file_util::read( $buff_or_file ) : $buff_or_file;

        $search_const_regex = '#(define\s*\(\s*[\'"]' . $const_name_esc . '[\'"]\s*,\s*)[\'"]+(.*?)\s*[\'"\s]+([\\\ \t;\)]+)#sim';

        if ( strcasecmp( $const_new_val, 'true' ) == 0 || $const_new_val === true || $const_new_val === 1 ) {
            $const_new_val_esc = 'true';
        } elseif ( strcasecmp( $const_new_val, 'false' ) == 0 || $const_new_val === false || $const_new_val === 0 ) {
            // This is a special case because during the concatenation it evaluates to an empty string so
            // I have to put it in quotes
            $const_new_val_esc = 'false';
        } else {
            $const_new_val_esc = addslashes( $const_new_val ); // in case it has a single quote
            $const_new_val_esc = "'$const_new_val_esc'";
        }

        // The const is defined in the wp-config.php
        if ( preg_match( $search_const_regex, $wp_config_buff, $matches ) ) {
            if ( is_null( $const_new_val ) ) {
                $val = $matches[2];

                if ( strcasecmp( $val, 'true' ) == 0 ) {
                    $val = true;
                } elseif ( strcasecmp( $val, 'false' ) == 0 ) {
                    $val = false;
                }

                return $val;
            }

            $wp_config_buff = preg_replace( $search_const_regex, '${1}' . $const_new_val_esc . '${3}' . '${4}', $wp_config_buff );
        } else { // doesn't exist we'll define it.
            if ( is_null( $const_new_val ) ) {
                return false;
            }

            $str = "define( '$const_name_esc', $const_new_val_esc ); // qs";

            // add it the first thing after starting php as in wp-config loads a php file so const mu be defined higher to take effect
            if ( stripos( $wp_config_buff, '<?php' ) === false ) {
                $wp_config_buff .= $str;
            } else {
                $wp_config_buff = preg_replace( '#\<\?(?:php)?#si', '<?php' . "\n" . $str, $wp_config_buff, 1 ); // 1 replacement needed
            }
        }

        return ! empty( $target_file ) ? esg_file_util::write( $target_file, $wp_config_buff ) : $wp_config_buff;
    }

    /**
     * removes everything after the @ sign
     *
     * esg_string_util::extractUserFromEmail();
     * @param array $setup_params
     */
    public static function extractUserFromEmail( $email ) {
        return trim( preg_replace( '#\s*\@.*#si', '', $email ) );
    }

    /**
     * Generates an alphanumeric or int id based on the APP_SANDBOX_RND_USER_ID_POLICY constant.
     *
     * esg_string_util::generateUniqueId( 'microtime' ); -> nice str based on microtime
     * esg_string_util::generateUniqueId();
     * @param array $setup_params
     */
    public static function generateUniqueId( $setup_params = array(), $prefix = '' ) {
        $uniq_id = 0;

        if ( is_scalar( $setup_params ) ) {
            switch ($setup_params) {
                case 'crc':
                case 'crc32':
                case 'checksum':
                    // according to php docs a more portable solution is to use the hash
                    // @see http://php.net/manual/en/function.crc32.php
                    $uniq_id = hash("crc32b", microtime( true ) );
                break;

                case 'microtime':
                case 'user':
                case 'username':
                    $uniq_id = microtime( true ); // timestamp first
                    $uniq_id = preg_replace( '#[^\w]#si', '_', $uniq_id );
                    $uniq_id_parts = explode( '_', $uniq_id ); // make it consistent nums.float part should be 5 numbs
                    $uniq_id_parts[1] = sprintf( '%05d', empty( $uniq_id_parts[1] ) ? 0 : $uniq_id_parts[1] );
                    $uniq_id = join( '_', $uniq_id_parts );
                    $uniq_id = esg_string_util::singlefyChars( $uniq_id );

                    // random username will be prefix + some random s***
                    if ( $setup_params == 'user' || $setup_params == 'username' ) {
                        $uniq_id = ( empty( $prefix ) ? 'user' : $prefix ) . str_replace( '_', '', $uniq_id );
                    }

                break;

                default:
                    $uniq_id = uniqid( 'qs' );
                break;
            }

            return $uniq_id;
        }

        // All is good. Proceed then.
        if ( defined('APP_SANDBOX_RND_USER_ID_POLICY') ){
           if ( APP_SANDBOX_RND_USER_ID_POLICY == 'timestamp' ) {
               $uniq_id = time(); // + append more stuff or randomize + microtime.
            } elseif ( APP_SANDBOX_RND_USER_ID_POLICY == 'ip' ) {
                $uniq_id = sprintf( '%u', ip2long( $_SERVER['REMOTE_ADDR'] ) );
            } elseif ( APP_SANDBOX_RND_USER_ID_POLICY == 'microtime' ) {
                $uniq_id = microtime();
                $uniq_id = str_replace( '0.', '', $uniq_id ); // rm leading 0.
            } else {
                trigger_error( "Unknown APP_SANDBOX_RND_USER_ID_POLICY policy", E_USER_WARNING );
            }
        }

        if ( isset( $setup_params['rand'] ) ) {
            $uniq_id .= mt_rand( 999, 9999999 ); // uniqness by ip!
        }

        $uniq_id = empty( $uniq_id ) ? uniqid() : $uniq_id; // php's function; alpha num

        return $uniq_id;
    }

    const UNIQUES = 2;
    const SKIP_COMMENTS = 4;
    const REMOVE_EMPTY_ITEMS = 8;
    const SORT = 16;

    /**
     * Splits a string on commas, tabs and optionally surrounded by spaces.
     * esg_string_util::splitOnSeparators();
     *
     * @param string $buff
     * @param int $flags
     * @return array
     */
    static public function splitOnSeparators( $buff, $flags = 1 )  {
        $lines = preg_split( '#\s*[,;\|\t]+\s*#si', $buff);
        $lines = (array) $lines;
        $lines = array_filter( $lines );
        $lines = array_unique( $lines );
        $lines = esg_string_util::trim( $lines );
        return $lines;
    }

    /**
     * Splits a buffer nicely on one or more newlines and based on the flags does some more.
     * esg_string_util::splitOnNewLines();
     * esg_string_util::splitOnNewLines( $str, esg_string_util::UNIQUES | esg_string_util::REMOVE_EMPTY_ITEMS | esg_string_util::SORT );
     *
     * @param string $buff
     * @param int $flags
     * @return array
     */
    static public function splitOnNewLines( $buff, $flags = 1 )  {
        $lines = preg_split( '#[\r\n]+#si', $buff);
        $lines = (array) $lines;

        if ( $flags & self::UNIQUES ) {
            $lines = array_unique( $lines );
        }

        if ( $flags & self::REMOVE_EMPTY_ITEMS ) {
            $lines = array_filter( $lines );
        }

        if ( $flags & self::SORT ) {
            sort( $lines );
        }

        return $lines;
    }

    /**
     * esg_string_util::isValidUsername();
     * @param string $str
     * @return string
     */
    static public function isValidUsername( $user )  {
        $valid = ! empty( $user ) && preg_match( '#^[\w-]{2,50}$#si', $user );
        return $valid;
    }

    /**
     * esg_string_util::addTrailingSlash();
     * @param string $str
     * @return string
     */
    static public function addTrailingSlash( $str )  {
        $str = self::removeTrailingSlash( $str );
        $str .= '/';
        return $str;
    }

    /**
     * esg_string_util::removeTrailingSlash();
     * @param str $str
     * @return str
     */
    static public function removeTrailingSlash( $str )  {
        $str = rtrim($str, '/\\');
        return $str;
    }

    /**
     * Make sure that a given character is shown only ones e.g. ----- -> -
     * This doesn't work well for \t, \s
     * esg_string_util::singlefyChars();
     * @param str $str
     * @return str
     */
    static public function singlefyChars( $str, $chars = array() )  {
        $default_chars = array( '.', '-', ',', '=', ';', '_', '!', ' ', "\n", "\r" );
        $chars = empty( $chars ) ? $default_chars : $chars;

        foreach ( $chars as $char ) {
            $char_esc = in_array( $char, array( '\s', '\t', '\r', '\n' ) ) ? $char : preg_quote( $char, '#' );
            $str = preg_replace( '#' . $char_esc . '+#si', $char, $str );
        }

        return $str;
    }

    /**
     * esg_string_util::removeSpaces();
     * @param str $str
     * @return str
     */
    static public function removeSpaces($str, $replace_with = '') {
        $str = preg_replace('#\s#si', $replace_with, $str);
        return $str;
    }

    /**
     * esg_string_util::generateHash();
     *
     * @param str $str
     * @return str
     */
    static public function generateHash($str) {
        $str = sha1(sha1($str) . '1981-SandASFP@##@$OKASOASPFK___ASF)ASF_+_Q@!#ASFASFIJIJboX.#%#5kljafkjasf');
        return $str;
    }

    /**
     * This should make the hash consistent when calculated for the correct person.
     * It's probably too much but it's ok.
     *
     * @todo figure out how to make this time bound e.g. in the next hour.
     * calc a future time or something?
     *
     * @todo improve the security even more by checking the currently logged in user (qs)
     * and passing domain record which this method will extract the other.
     * the system plugin will check the site's env to get user id getenv( 'SANDBOX_USER_ID' );
     *
     * esg_string_util::generateAuthoLoginHash();
     * @param str $str this should be the domain name
     * @return str
     */
    static public function generateAuthoLoginHash( $domain ) {
        $hash_str = $domain;
        $hash_str = preg_replace( '#^.*?://+#si', '', $hash_str );
        $hash_str = preg_replace( '#/.*#si', '', $hash_str );
        $hash_str = strtolower( $hash_str );
        $hash_str = trim( $hash_str );

        $hash_str .= 'lasfasfsd992ASFASfiojoasf3asfaf_';
        $hash_str .= empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
        $hash_str .= empty($_SERVER['SERVER_ADDR']) ? '' : $_SERVER['SERVER_ADDR'];
        $hash_str .= empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'];
        $hash_str .= empty($_SERVER['SERVER_SOFTWARE']) ? '' : $_SERVER['SERVER_SOFTWARE'];
        $hash_str .= PHP_OS . phpversion();

        $hash = sha1( md5( sha1( $hash_str ) ) . sha1( 'aasfaosfj182ijsoifjasfas' ) );
        $hash = strtolower( $hash );

        return $hash;
    }

    /**
     * Allowed chars a-z_-\d
     * esg_string_util::removeNonAlpha();
     * @param str $str
     * @return str
     */
    static public function removeNonAlpha( $str, $replace_with = '' ) {
        $str = preg_replace( '#[^\w-]#si', $replace_with, $str );
        $str = self::trim($str);
        return $str;
    }

    /**
     * Does what it says.
     * esg_string_util::removeNewLies();
     * @param str $str
     * @return str
     */
    static public function removeNewLies( $str, $replace_with = '' ) {
        $str = preg_replace( '#[\r\n]+#si', $replace_with, $str );
        return $str;
    }

    /**
     * Searches in an array and returns some results.
     * The search (insensitive) can be done in the key or value.
     *
     * esg_string_util::searchArray();
     *
     * @param str $search_kwd
     * @param array $haystack
     * @param type $field
     * @param int $limit
     * @return array
     */
    static public function searchArray($search_kwd, $haystack, $field = 'key', $limit = 5) {
        $cnt = 1;
        $results = array();

        // Searches exact match
        foreach ($haystack as $key => $value) {
            $q = preg_quote($search_kwd);

            if ($field == 'key' && $search_kwd == $key) {
                $results[$key] = $value;
                $cnt++;
            } elseif ($field == 'value' && $search_kwd == $value) {
                $results[$key] = $value;
                $cnt++;
            }

            if ($cnt > $limit) {
                break;
            }
        }

        if ($cnt < $limit) {
            // Searches using regex to filter results that start with the searched key.
            foreach ($haystack as $key => $value) {
                $q = preg_quote($search_kwd);

                if ($field == 'key' && preg_match('#^' . $q . '#si', $key)) {
                    $results[$key] = $value;
                    $cnt++;
                } elseif ($field == 'value' && preg_match('#^' . $q . '#si', $value)) {
                    $results[$key] = $value;
                    $cnt++;
                }

                if ($cnt > $limit) {
                    break;
                }
            }
        }

        // if we're still looking ... then do a partial search
        // but skip already found items.
        if ($cnt < $limit) {
            foreach ($haystack as $key => $value) {
                if (!empty($results[$key])) {
                    continue;
                }

                if ($field == 'key' && (stripos($key, $search_kwd) !== false)) {
                    $results[$key] = $value;
                    $cnt++;
                } elseif ($field == 'value' && (stripos($value, $search_kwd) !== false)) {
                    $results[$key] = $value;
                    $cnt++;
                }

                if ($cnt > $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Generates a random string that can be used for a password.
     * esg_string_util::generateRandomString();
     * @param type $length
     * @return string
     * @see http://stackoverflow.com/questions/4356289/php-random-string-generator
     */
    static public function generateRandomString($length = 10, $use_extra_chars = 0 ) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_';
        $extra_chars = '~!@#$%^&*()+?,.[]';

        if ( $use_extra_chars ) {
            $characters.= $extra_chars;
        }

        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * esg_string_util::encodeEntities();
     * @param type $str
     * @return type
     */
    static public function encodeEntities($str) {
        $str = htmlentities($str, ENT_QUOTES, 'UTF-8');
        return $str;
    }

    /**
     *
     * esg_string_util::decodeEntities();
     * @param type $str
     * @return type
     */
    static public function decodeEntities($str) {
        $str = html_entity_decode( $str, ENT_COMPAT, 'UTF-8' );
        return $str;
    }

    /**
     * Sanitizes some
     * esg_string_util::sanitize()
     * @param string
     * @return int $id
     */
    static public function sanitize($str) {
        if ( is_array( $str ) ) {
            $params = $str;

            foreach ( $params as $key => $val) {
                $params[$key] = self::stripSomeTags( $val );
            }

            $str = $params;
        } else {
            $str = self::stripSomeTags($str);
            $str = self::trim($str);
        }

        return $str;
    }

    const STRIP_SOME_TAGS = 2;
    const STRIP_ALL_TAGS = 4;

    /**
     * Uses WP's wp_kses to clear some of the html tags but allow some attribs
     * usage: esg_string_util::stripSomeTags($str);
	 * uses WordPress' wp_kses()
     * @param str $buffer string buffer
     * @return str cleaned up text
     */
    public static function stripSomeTags($buffer, $flags = self::STRIP_SOME_TAGS ) {
        // these work only in WP ctx
        static $default_attribs = array(
            'id' => array(),
            'rel' => array(),
            'class' => array(),
            'title' => array(),
            'style' => array(),
            'data' => array(),
            'target' => array(),
            'data-mce-id' => array(),
            'data-mce-style' => array(),
            'data-mce-bogus' => array(),
        );

        $allowed_tags = array(
            'div'           => $default_attribs,
            'span'          => $default_attribs,
            'p'             => $default_attribs,
            'a'             => array_merge( $default_attribs, array(
                'href' => array(),
                'target' => array('_blank', '_top', '_self'),
            ) ),
            'u'             => $default_attribs,
            'i'             => $default_attribs,
            'q'             => $default_attribs,
            'b'             => $default_attribs,
            'ul'            => $default_attribs,
            'ol'            => $default_attribs,
            'li'            => $default_attribs,
            'br'            => $default_attribs,
            'hr'            => $default_attribs,
            'strong'        => $default_attribs,
            'strike'        => $default_attribs,
            'blockquote'    => $default_attribs,
            'del'           => $default_attribs,
            'em'            => $default_attribs,
            'pre'           => $default_attribs,
            'code'          => $default_attribs,
            'style'         => $default_attribs,
        );

        if (function_exists('wp_kses')) { // WP is here
            $buffer = wp_kses($buffer, $allowed_tags);
        } elseif ( $flags & self::STRIP_ALL_TAGS ) {
            $buffer = strip_tags($buffer);
        } else {
            $tags = array();

            foreach (array_keys($allowed_tags) as $tag) {
                $tags[] = "<$tag>";
            }

            $buffer = strip_tags($buffer, join('', $tags));
        }

        $buffer = self::trim($buffer);

        return $buffer;
    }

    /**
     * esg_string_util::sanitizeSubDomain()
     * @param string
     * @return int $id
     */
    static public function sanitizeSubDomain($str) {
        $str = self::sanitizeUsername($str, '-');
        $str = str_replace( '_', '-', $str );
        return $str;
    }

    /**
     * esg_string_util::sanitizeSeo()
     * @param string
     * @return int $id
     */
    static public function sanitizeSeo($str) {
        $str = self::sanitizeUsername($str);
        return $str;
    }

    /**
     * Basic domain validation
     * esg_string_util::validateDomainName();
     * @param str $str
     * @return bool
     */
    static public function validateDomainName( $str ) {
        $ok = preg_match('#^[\w-.]+\.\w{2,10}$#si', $str );

        if ($ok && ( strlen($str) < 3 || strlen($str) > 75 )) {
            $ok = 0;
        }

        return $ok;
    }

    /**
     * Checks if a username is valid and long enough.
     * esg_string_util::validateUsername();
     * @param type $str
     * @return bool
     */
    static public function validateUsername($str) {
        $ok = preg_match('#^[\w-.\@]+$#si', $str);

        if ($ok && ( strlen($str) < 3 || strlen($str) > 60 )) {
            $ok = 0;
        }

        return $ok;
    }

    /**
     * requires the phone to look like valid e.g.
     * +1 111 222 333
     * +1-111-222-333
     * +1 (111) 222 333
     * * +1 (111) 222 333, +1 (111) 222 333,
     * +1 (111) 222 333 ext 2
     * @param str $str
     * @return bool
     */
    static public function validatePhone($str) {
        $ok = preg_match('/^\+?[\(\)\s\d-_.ext#,]+$/si', $str);
        return $ok;
    }

    /**
     * esg_string_util::sanitizeUsername();
     * @param str $str
     * @return str
     */
    static public function sanitizeUsername($str, $sep = '_') {
        $str = self::sanitize($str);
        $str = preg_replace('#\.+\w{2,6}$#si', $sep, $str); // .uk or .travel, .club
        $str = preg_replace('#\.+\w{2,6}$#si', $sep, $str); // .co
        $str = preg_replace('#[^\w-]#si', $sep, $str);
        $str = esg_string_util::singlefyChars($str);
        $str = strtolower($str);
        $str = trim($str, '_-');

        return $str;
    }

    /**
     * Returns a link to the dashboard file or to another redirect URL.
     * e.g. if we want to direct the user to plan.php instead.
     * esg_string_util::getNextLink()
     * @param string
     * @return int $id
     */
    static public function getNextLink() {
        $r = q('r');
        $default = 'dashboard.php';

        if (!empty($r) && preg_match('#^(?:/app/)?([\w-]+\.php)#si', $r, $matches) && file_exists(APP_SANDBOX_BASE_DIR . '/' . $matches[1])) { // is this our file?
            // all is good. keep the $r as is.
        } else {
            $r = $default;
        }

        return $r;
    }

    /**
     * esg_string_util::getCurrentDateSuffix()
     * @return str
     */
    static public function getCurrentDateSuffix() {
        $date_suffix = date('Y-m-d'); // _H-i_s

        return $date_suffix;
    }

    /**
     * Formatting the seconds into human readable form.
     * Useful for expiration times. e.g. 2h
     *
     * esg_string_util::secondsToFmt();
     *
     * @param int $seconds
     * @return string
     * @see http://stackoverflow.com/questions/3172332/convert-seconds-to-hourminutesecond
     */
    static public function secondsToFmt( $inp_seconds ) {
        $str = '';

        $str = '';
        $hours = floor($inp_seconds / 3600);
        $minutes = floor(($inp_seconds / 60) % 60);
        $seconds = $inp_seconds % 60;
        $days = floor( $inp_seconds / ( 24 * 3600 ) );

        $parts = array();

        if ( $days ) {
            $parts[] = "$days Day" . ( $days == 1 ? '' : 's' );
            $hours -= $days * 24 * 3600;
        }

        if ( $hours > 0 ) {
            $parts[] = "$hours Hour" . ( $hours == 1 ? '' : 's' );
        }

        if ( $minutes > 0 ) {
            $parts[] = "$minutes Minute" . ( $minutes == 1 ? '' : 's' );
        }

        $str = join( ' ', $parts );

        //$str = "$days:$hours:$minutes:$seconds";

        return $str;
    }

    /**
     * esg_string_util::getDateDiff();
     * @param int $your_date timestamp
     * @param bool $hr human readable. e.g. 1 year(s) 2 day(s)
     * @see http://stackoverflow.com/questions/2040560/finding-the-number-of-days-between-two-dates
     */
    static public function getDateDiff($your_date, $hr = 0) {
        $now = time(); // or your date as well
        $datediff = $now - $your_date;
        $days = floor($datediff / ( 3600 * 24 ));

        $label = '';

        if ($hr) {
            if ($days >= 365) { // over a year
                $years = floor($days / 365);
                $label .= $years . ' Year(s)';
                $days -= 365 * $years;
            }

            if ($days) {
                $months = floor($days / 30);
                $label .= ' ' . $months . ' Month(s)';
                $days -= 30 * $months;
            }

            if ($days) {
                $label .= ' ' . $days . ' day(s)';
            } elseif (empty($label)) {
                $label = 'Today';
            }
        } else {
            $label = $days;
        }

        return $label;
    }

    /**
     * Package for easy transportation
     * esg_string_util::encodeBinary();
     * @param str $str
     * @return str
     */
    public static function encodeBinary($str) {
        $str = bin2hex($str);
        return $str;
    }

    /**
     * Unpackage for easy processing
     * esg_string_util::decodeBinary();
     * @param str $str
     * @return str
     */
    public static function decodeBinary($str) {
        $str = hex2bin($str);
        return $str;
    }

    /**
     * esg_string_util::trim();
     * @param type $str
     * @return string/array
     * Ideas gotten from: http://www.jonasjohn.de/snippets/php/trim-array.htm
     */
    public static function trim($data) {
        if ( is_scalar( $data ) ) {
            return trim( $data );
        }

        return array_map( 'esg_string_util::trim', $data );
    }

    public static function makeLink( $location, $anchor_text, $attribs = [] ) {
        if ( ! empty( $attribs['target'] ) ) {
            $target = ' target="' . $target . '"';
        }
    }

    /**
     * esg_string_util::autoLink();
     * @param str $str
     * @return string/array
     * @see http://stackoverflow.com/questions/1038284/php-parse-links-emails
     */
    public static function autoLink( $str, $target = 'target' ) {
        if ( ! empty( $target ) ) {
            $target = ' target="' . $target . '"';
        }

        $img_map = array();

        // Get all images and replaced them with hashes because the autolink function breaks the image tags ?!?
        // find and replace images e.g. http://slavi.ca/me.jpg
        if ( preg_match_all( '@(?:https?://)?[-\w\./:]+(?:jpe?g|png)@si', $str, $img_matches ) ) {
            $img_links = $img_matches[0];

            foreach ( $img_links as $img_link ) {
                $img_map[ $img_link ] = 'tmp_img_hash_' . sha1( $img_link );
                $str = str_replace( $img_link, $img_map[ $img_link ], $str );
            }
        }

        // Find and replace something that starts with http or https -> href
        $str = preg_replace( '@(https?:/+([-\w\.]+\.[-\w\.]+)+\w(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)*)@si', '<a href="$1" ' . $target . '>$1</a>', $str );

        // add "http://" if not set
        //$str = preg_replace( '/<a\s[^>]*href\s*=\s*"((?!https?:\/\/)[^"]*)"[^>]*>/si', '<a href="http://$1" ' . $target . '>', $str );

        // Now safely replace images
        foreach ( $img_map as $img_link => $hash ) {
            $str = str_replace( $hash, "<img src='$img_link' alt='' />", $str );
        }

        return $str;
    }

    /**
     * esg_string_util::parseAndRunCodeTemplate();
     *
     * There can be inline linux commands that can be executed as part of the content.
     * There can be multiple template blocks.
     * Only whitelisted commands are allowed for security reasons.
     *
     * [[[exec=(((date)))]]]
     *
     * @param type $str
     * @return string/array
     * Ideas gotten from: http://www.jonasjohn.de/snippets/php/trim-array.htm
     */
    public static function parseAndRunCodeTemplate( $buff ) {
        // [[[exec=(((df -h)))]]]
        while ( strpos( $buff, '[[[' ) !== false ) {
            if ( preg_match( '#\[{3,}\s*(exec)\s*\=\s*\({3,}\s*(.+?)\s*\){3,}\s*\]{3,}#si', $buff, $matches ) ) {
                $op = $matches[1];
                $cmd = $matches[2];
                $cmd_output = '';

                // Replace a constant e.g. in a path prefix
                if ( preg_match( '#%%(APP_\w+)%%#si', $cmd, $matches_const_search ) ) {
                    if ( constant( $matches_const_search[1] ) ) {
                        $cmd = str_replace( $matches_const_search[0], constant( $matches_const_search[1] ), $cmd );
                    }
                }

                if ( preg_match( '#^\s*(date|df|du|ps|free|stat)[\w\s-\'"\=\%\/\\\\.\:+]*$#si', $cmd ) ) {
                    $cmd_output = shell_exec( "$cmd 2>&1" ); // Run
                    $cmd_output = trim( $cmd_output );
                } else {
                    trigger_error( "parseAndRunCodeTemplate Error: cannot/won't parse CodeTemplate", E_USER_NOTICE );
                    $cmd_output = "\n<!-- parseAndRunCodeTemplate Error: cannot/won't parse CodeTemplate -->\n";
                }

                $buff = str_replace( $matches[0], $cmd_output, $buff );
            }
        }

        return $buff;
    }

    /**
     * Proxy method to isValidEmail
     * @param str $str
     * @return bool
     */
    static public function validateEmail($str) {
        return esg_string_util::isValidEmail( $str );
    }

    /**
     *
     * esg_string_util::isDisposableEmail();
     *
     * @param str $email
     * @return bool
     */
    static public function isDisposableEmail( $email ) {
        if ( ! esg_string_util::isValidEmail( $email ) ) {
            return true;
        }

        static $regex = '';

        if ( empty( $regex) ) {
            $source_file = APP_SANDBOX_DATA_DIR . '/system/disposable_email_providers.txt';
            $email_providers_buff = esg_file_util::read( $source_file );
            $email_providers = esg_string_util::splitOnNewLines( $email_providers_buff );
            $email_providers = esg_string_util::formatArray( $email_providers, esg_string_util::SKIP_COMMENTS );

            $email_providers = array_map( 'preg_quote', $email_providers );
            $regex = '#^(' . join( '|', $email_providers ) . ')$#si';
        }

        // We'll search current email's provider against the list.
        $email_provider = preg_replace( '#.+\@#si', '', $email );
        $email_provider = trim( $email_provider );

        if ( preg_match( $regex, $email_provider ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * esg_string_util::isValidEmail( $email );
     * @param str $email
     * @return bool
     */
    public static function isValidEmail( $email ) {
        // !preg_match('#^[\w-.+]+\@[\w-.]+$#si', $email)
        return strlen( $email ) > 3 && filter_var( $email, FILTER_VALIDATE_EMAIL ) && preg_match( '#\.[a-z]{2,10}$#si',  $email );
    }

    /**
     *
     * @param array $params
     * @return str
     */
    public static function encodeQueryParams( $params = array() ) {
        $str = serialize( $params );
        $str = esg_string_util::pack( $str );
        return $str;
    }

    /**
     *
     * @param str $str
     * @return array
     */
    public static function decodeQueryStr( $str ) {
        $str = esg_string_util::unpack( $str );
        $data_arr = unserialize( $str );
        //$str = base64_decode( $str );
        //
        //parse_str( $str, $data_arr );
//        $data_arr = json_decode( $str );
        return $data_arr;
    }

    /**
     * Package for easy transportation
     * esg_string_util::pack();
     * @param str $str
     * @return str
     */
    public static function pack( $str ) {
        $str = bin2hex( $str );
        return $str;
    }

    /**
     * Unpackage for easy processing.
     * esg_string_util::unpack();
     * @param str $str
     * @return str
     */
    public static function unpack( $str ) {
        $str = hex2bin( $str );
        return $str;
    }

    /**
     * Removes duplicates and empty elements. Trims elements.
     * Won't work for multidimensional arrays.
     *
     * esg_string_util::formatArray();
     *
     * @param array $str
     * @return array
     */
    public static function formatArray( $data_items, $flags = 1 ) {
        $data_items = empty( $data_items ) ? array() : (array) $data_items;
        $data_items = array_unique( $data_items );
        $data_items = array_filter( $data_items );
        $data_items = array_map( 'strip_tags', $data_items );
        $data_items = array_map( 'trim', $data_items );

        if ( $flags & self::SKIP_COMMENTS ) {
            foreach ( $data_items as $idx => &$line ) {
                $line = preg_replace( '#^\s*\#.*#si', '', $line ); // rm comments
                $line = preg_replace( '#^\s*//.*#si', '', $line );

                if ( empty( $line ) ) {
                    unset( $data_items[ $idx ] );
                }
            }
        }

        return $data_items;
    }

    /**
     * esg_string_util::serialize();
     * @param mixed $thing
     * @return str
     */
    public static function serialize( $thing ) {
        $str = json_encode( $thing );
        $str = base64_encode( $str );
        $str = str_replace( '=', '__QS_EQ_SIGN__', $str ); // in case I pass this param in cmd line

        return $str;
    }

    /**
     * esg_string_util::unserialize();
     * @param string $str
     * @return mixed
     */
    public static function unserialize( $str ) {
        $thing = str_replace( '__QS_EQ_SIGN__', '=', $str ); // in case I pass this param in cmd line
        $thing = base64_decode( $thing );
        $thing = json_decode( $thing, true ); // always an array

        return $thing;
    }

    const OVERRIDE_AFF_ID = 2;

    /**
     * Processes some defined tags.
     *
     * [qs:social_sharing]
     * esg_string_util::processShortCodes();
     * @param type $str
     */
    public static function processShortCodes( $buff, $flags = self::OVERRIDE_AFF_ID ) {
        $tags = array(
            '[qs:social_sharing]',
        );

        foreach ( $tags as $tag ) {
            if ( stripos( $buff, $tag ) === false ) {
                continue;
            }

            $tag_file = $tag;
            $tag_file = trim( $tag_file, '[]' );
            $tag_file = preg_replace( '#^\w+\:#si', '', $tag_file );
            $tag_file_full = APP_SANDBOX_BASE_DIR . "apps/demo/data/short_codes/$tag_file.txt";

            $replace = "\n<!-- qs:short_code:error $tag not found -->\n";

            if ( file_exists( $tag_file_full ) ) {
                $replace = esg_file_util::read( $tag_file_full );
            }

            $buff = str_ireplace( $tag, $replace, $buff );
        }

        $aff = App_Sandbox_Util::getAffId();

        // If 'a' exists append it to the QS link
        if ( ! empty( $aff ) && ( stripos( $buff, APP_SANDBOX_HOST ) !== false ) ) {
            if ( preg_match_all( '#href=["\']*.*?' . preg_quote( APP_SANDBOX_HOST, '#' ) . '[^\'"]*#si', $buff, $matches, PREG_SET_ORDER ) ) {
                $links = $matches;

                $replace = array();

                foreach ( $links as $match ) {
                    $link = $match[ 0 ]; // link with href stuff in front of it.

                    if ( $flags == self::OVERRIDE_AFF_ID || ! preg_match( '#(?:[?&]|&amp;)a=\w+#', $link ) ) {
                        $modified_link = App_Sandbox_Util::add_url_params( $link, array( App_Sandbox_Util::$aff_key => $aff ) );
                        $replace[ $link ] = $modified_link;
                    }
                }

                if ( ! empty( $replace ) ) {
                    $buff = str_replace( array_keys( $replace ), array_values( $replace ), $buff );
                }
            }
        }

        return $buff;
    }

    const FLAG_UPPERCASE_WORDS = 2;

    /**
     * esg_string_util::toHumanRadable();
     * @param sstr $file
     * @return str
     */
    static function toHumanRadable($file, $flags = 1) {
        $file = basename($file);
        $file = preg_replace('#\.\w+$#si', '', $file); // rm ext
        $file = preg_replace('#[^\s\w]+#si', ' ', $file);
        $file = preg_replace('#_+#si', ' ', $file);
        $file = preg_replace('#\s+$#si', ' ', $file);

        if ( $flags & self::FLAG_UPPERCASE_WORDS ) {
            $file = ucwords($file);
        }

        $file = trim($file);

        return $file;
    }

    /**
     * esg_string_util::do_shortcode();
     * @param str $buff
     * @return str
     */
    public static function do_shortcode($buff, $output = 0 ) {
        ob_start();
        $buff = do_shortcode( $buff );

        if ( $output ) {
            echo $buff;
        }

        return $buff;
        //return ob_get_clean();
    }
    
    /**
     * esg_string_util::json_encode();
     * 
     * @param type $thing
     * @param type $opts
     * @return str
     */
    public static function json_encode( $thing, $opts = 1 ) {
        $res = json_encode( $thing, defined( 'JSON_PRETTY_PRINT' ) ? JSON_PRETTY_PRINT : 0 );
        
        // With php7+ encoding can/will fail if contents are not utf8 encoded.
        if ( empty( $res ) ) {
            $thing = esg_string_util::encodeUTF8( $thing );
            $res = json_encode( $thing, defined( 'JSON_PRETTY_PRINT' ) ? JSON_PRETTY_PRINT : 0 );
        }
        
        return $res;
    }

    /**
     * esg_string_util::json_decode();
     * @param mixed $thing
     * @param int $opts
     * @return array
     */
    public static function json_decode( $thing, $opts = 1 ) {
        if ( is_scalar( $thing ) ) {
            $res = json_decode( $thing, $opts );
        } else {
            $res = $thing;
        }
        
        return $res;
    }
    
    /**
     * This is needed when doing JSON encode as decoding may not work with php 7+
     * esg_string_util::encodeUTF8();
     * borrowed from:
     * App_Updater_String_Util::utf8_encode();
     * @param mixed $d
     * @return mixed
     * @see http://stackoverflow.com/questions/19361282/why-would-json-encode-returns-an-empty-string
     */
    public static function encodeUTF8($d) {
        if (is_array($d)) {
            foreach ($d as $k => $v) {
                $d[$k] = self::encodeUTF8($v);
            }
        } elseif (is_object($d)) {
            foreach ($d as $k => $v) {
                $d->$k = self::encodeUTF8($v);
            }
        } elseif (is_scalar($d)) {
            $d = utf8_encode($d);
        }

        return $d;
    }
}
