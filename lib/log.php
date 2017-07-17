<?php

class esg_log extends esg_singleton {
    private $severity = 1;
    public static $log_file = '';
    private static $logging_enabled = 1;

    public function __call($method, $arges = array()) {

    }

    /**
     * Returns the log dir with a trailing slash based on the defined APP_SANDBOX_CURRENT_LOG_FILE constant.
     * Some vhosts may override error_log ini variable.
     *
     * esg_log::getCurrentLogDir();
     * @return str
     */
    public static function getCurrentLogDir() {
        $dir = ESG_CORE_DATA_DIR;
        $full_dir = $dir . '/log/' . date( 'Y/m' );

        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            file_put_contents( $dir . '/.htaccess', "deny from all" );
        }

        return $full_dir;
    }

    /**
     * Returns the log dir with a trailing slash based on the defined APP_SANDBOX_CURRENT_LOG_FILE constant.
     * Some vhosts may override error_log ini variable.
     *
     * esg_log::getLogPrefixEnv();
     * @return str
     */
    public static function getLogPrefixEnv() {
        $prefix = getenv( 'QS_LOG_PREFIX' );
        $prefix = empty( $prefix ) ? '' : $prefix;
        $prefix = trim( $prefix );
        return $prefix;
    }

    /**
     * esg_log::setLogPrefixEnv();
     * @param str $prefix
     */
    public static function setLogPrefixEnv( $prefix ) {
        putenv( "QS_LOG_PREFIX=$prefix" );
        return self::getLogPrefixEnv();
    }

    /**
     * Returns path to the log dir with an already prefilled prefix which includes the current date and s***.
     *
     * esg_log::getLogPrefix();
     * @return str
     */
    public static function getLogPrefix( $what = '', $append_ext = 1 ) {
        $file = '';
        $prefix = esg_log::getLogPrefixEnv();

        if ( ! empty( $prefix ) ) {
            $file = $prefix;
        } else {
            $file = esg_log::getCurrentLogDir() . '/' . date( 'Y-m-d_' );
        }

        if ( ! empty( $what ) ) {
            $what = sanitize_title( $what );
            $file .= $what;

            if ( $append_ext ) {
                $file .= '.log';
            }
        }

        return $file;
    }

    public static function file( $file = '' ) {
        if ( ! empty( $file ) ) {
            self::$log_file = $file;
            return self::$log_file;
        }

        $default_log_file = ini_get('error_log');

        if ( defined( 'APP_SANDBOX_CURRENT_LOG_FILE' ) ) {
            self::$log_file = APP_SANDBOX_CURRENT_LOG_FILE;
        } elseif ( 0 && ! empty( $default_log_file ) ) {
            self::$log_file = $default_log_file;
        } else {
            /*
                [path] => C:\path\to\wordpress\wp-content\uploads\2010\05
                [url] => http://example.com/wp-content/uploads/2010/05
                [subdir] => /2010/05
                [basedir] => C:\path\to\wordpress\wp-content\uploads
                [baseurl] => http://example.com/wp-content/uploads
             */
            $up_dir = wp_upload_dir();
            $base_359_dir = dirname( $up_dir['basedir'] ) . '/go359/'; // 1 level above upload
            $base_log_dir = $base_359_dir . 'logs/';
            $log_dir = $base_log_dir . date('Y/m/');
            
            if ( ! is_dir( $log_dir ) ) {
                mkdir( $log_dir, 0770, 1 ); // avoid loop if using my ::mkdir
            }

            $log_file = $log_dir . 'app_' . date('Y-m-d') . '.log';

            // Always use protection! ;)
            if ( ! file_exists( $base_359_dir . '.htaccess' ) ) {
                esg_file_util::write( $base_359_dir . '.htaccess', "deny from all" );
            }

            self::$log_file = $log_file;
        }

        return self::$log_file;
    }

    /**
     * Logs a message with a label in a file unless disabled (when showing some sys info).
     * esg_log::msg();
     * @param string $msg
     * @param string $label
     * @param string $log_file
     */
    public static function msg( $msg, $label = '', $log_file = '' ) {
        if ( ! self::$logging_enabled ) {
            return;
        }

        // If admin user wants some dbg info to be appended.
        // he'll include __USER_INFO__ text to the dbg
        $user_info_regex = '#_+USER_+INFO_+#si';

        if ( preg_match( $user_info_regex, $msg ) ) {
            $user = esg_user::get_instance();
            $dbg_info = [
                'ip' => $user->get_ip(),
                'browser' => empty($_SERVER['HTTP_USER_AGENT']) ? '' : $_SERVER['HTTP_USER_AGENT'],
                'req_uri' => $_SERVER['REQUEST_URI'],
            ];
            $dbg_info = 'User Info: ' . var_export( $dbg_info, 1 );
            $msg = preg_replace( $user_info_regex, $dbg_info, $msg );
        }

        $file = empty( $log_file ) ? esg_log::file() : $log_file;

        if (!empty($file)) {
            esg_file_util::mkdir( dirname( $file ) );

            $msg = '[' . date( 'r' ) . '] ' . ( empty( $label ) ? '' : "[$label] " ) . $msg;
            error_log( $msg . "\n", 3, $file );

            if ( defined( 'APP_SANDBOX_ETC_OS_MAIN_USER' ) ) {
                esg_file_util::setOwner( $file, APP_SANDBOX_ETC_OS_MAIN_USER, APP_SANDBOX_ETC_OS_MAIN_USER );
            }
        } else {
            error_log( $msg . "\n", 3 );
        }
        
        $msg = "<pre>$msg</pre>";
        
        return $msg;
    }

    /**
     * esg_log::info( $msg, $title );
     * @param string $msg
     * @param string $label
     */
    public static function info($msg, $label = '') {
        if (!is_scalar($msg)) {
            $msg = var_export($msg, 1);
        }

        $msg = '[INFO] ' . $msg;

        return self::msg($msg, $label);
    }

    /**
     * esg_log::error( $msg, $title );
     * @param string $msg
     * @param string $label
     */
    public static function error($msg, $label = '') {
        if ( ! is_scalar( $msg ) ) {
            $msg = var_export( $msg, 1 );
        }

        $msg = '[ERROR] ' . $msg;

        return self::msg( $msg, $label );
    }

    public static function enableLogging() {
        self::$logging_enabled = 1;
    }

    public static function disableLogging() {
        self::$logging_enabled = 0;
    }
}