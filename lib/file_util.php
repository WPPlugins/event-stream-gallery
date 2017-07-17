<?php
/**
 * borrowed from qsandbox file_util
 */
class esg_file_util {
    // options for read/write methods.
    const SERIALIZE = 2;
    const UNSERIALIZE = 4;
    const AUTO_DETECT = 8;
    const AUTO_DETECT_ARRAY = 16;
    const AUTO_DETECT_OBJECT = 32;
    const FILE_APPEND = 64;
    const CLEAR_STAT_CACHE = 128;

    /**
     * This array collects all of the commands that have been executed through the current request.
     * @var array
     */
    public static $profiling_res = array();

    /**
     * esg_file_util::load_file();
     * @param str $file
     */
    public static function load_file( $file ) {
        if ( file_exists( $file ) ) { // full path
            return self::read( $file );
        }

        $dirs = [];

        $locale = '';

        if ( defined( 'ESG_LOCALE' ) && ( ! defined( 'ESG_SKIP_TRANSLATION') || ESG_SKIP_TRANSLATION == 0 ) ) {
            $locale = ESG_LOCALE;
        }

        $dirs[] = ESG_CORE_DATA_DIR . "/{locale}/email_templates/";
        $dirs[] = ESG_CORE_DATA_DIR . "/email_templates/";
        $dirs = apply_filters( 'go359_filter_load_file_dirs', $dirs, [] );
        $dirs = array_unique( $dirs );
        $dirs = array_filter( $dirs );

        foreach ( $dirs as $dir ) {
            $full_file = trailingslashit( $dir ) . $file;
            $full_file = esg_string_util::replace( [ 'locale' => $locale ], $full_file );
            
            if ( file_exists( $full_file ) ) {
                return self::read( $full_file );
            }
        }

        return false;
    }

    /**
     * proto str esg_file_util::formatFileSize( int $size )
     * @todo move to File_Util
     * @param string
     * @return string 1 KB/ MB
     */
    public static function formatFileSize($size) {
        if ( $size <= 0 ) {
            return 'N/A';
        }

    	$size_suff = 'Bytes';

        if ($size > 1024 ) {
            $size /= 1024;
            $size_suff = 'KB';
        }

        if ( $size > 1024 ) {
            $size /= 1024;
            $size_suff = 'MB';
        }

        if ( $size > 1024 ) {
            $size /= 1024;
            $size_suff = 'GB';
        }

        if ( $size > 1024 ) {
            $size /= 1024;
            $size_suff = 'TB';
        }

        $size = number_format( $size, 2);
		$size = preg_replace('#\.00$#', '', $size);

        return $size . " $size_suff";
    }

    /**
     * esg_file_util::turnOffOutputBuffering();
     * @return void
     * @see http://www.binarytides.com/php-output-content-browser-realtime-buffering/
     */
    public static function turnOffOutputBuffering() {
        @header("Content-type: text/plain");
        @header('Cache-Control: no-cache'); // recommended to prevent caching of event data.

        // Turn off output buffering
        ini_set('output_buffering', 'off');

        // Turn off PHP output compression
        ini_set('zlib.output_compression', false);

        //Flush (send) the output buffer and turn off output buffering
        //ob_end_flush();
        while (@ob_end_flush());
        echo str_repeat( " ", 1024 );
        //echo "<!-- Jump over the web server's buffer: [" . str_repeat( "\t", 1024 ) . ']-->';
        //echo "<!-- Jump over the web server's buffer: [" . str_repeat( '.', 1024 ) . ']-->';

        return '';
    }

    /**
     * esg_file_util::flushOutput();
     */
    public static function flushOutput() {
        @ob_flush();
        flush();
    }

    /**
     *
     * @param str $file
     * @param int $secs
     * @return bool
     */
    public static function isFileOlderThan( $file, $secs ) {
        $st = file_exists( $file ) && ( time() - filemtime( $file ) >= $secs );
        return $st;
    }

    /**
     *
     * @param str $file
     * @param int $secs
     * @return bool
     */
    public static function isFileYoungerThan( $file, $secs ) {
        $st = file_exists( $file ) && ( time() - filemtime( $file ) <= $secs );
        return $st;
    }

    /**
     *
     * esg_file_util::isBadPath();
     * @param str $path
     */
    public static function isBadPath( $path ) {
        $bad = empty( $path )
                //|| ! is_dir( $path )
                || preg_match( '#\.\.+#si', $path )
                || preg_match( '#^/+$#si', $path )
                || preg_match( '#/root/#si', $path )
                || preg_match( '#/etc#si', $path )
                || preg_match( '#/home/#si', $path )
                || preg_match( '#' . preg_quote( APP_SANDBOX_ETC_APPS_DIR, '#' ) .'#si', $path )
            ;
        return $bad;
    }

    /**
     *
     * esg_file_util::change_stuff();
     *
     * @param str $path
     * @param str $group this can be a user/group OR an octal number in case of chmod 0755
     * @param str $function : chown, chgrp, chmod
     * @return boolean
     * @see http://serversideguy.com/2009/11/08/php-recursively-chmod-chown-and-chgrp/
     */
    public static function change_stuff($path, $group, $function = 'chgrp' ) {
        if ( ! function_exists( $function ) ) {
            trigger_error( __METHOD__ . " invalid function. [" . htmlentities( $function ) . "]"  );
        }

        if ( ! is_dir( $path ) ) {
            return $function($path, $group);
        }

        $dh = opendir($path);

        while ( ($file = readdir($dh)) !== false ) {
            if ( $file != '.' && $file != '..' ) {
                $fullpath = $path . '/' . $file;

                if ( is_link($fullpath ) ) {
                    return FALSE;
                } elseif ( ! is_dir( $fullpath ) && ! $function( $fullpath, $group ) ) {
                    return FALSE;
                } elseif (! self::change_stuff( $fullpath, $group, $function ) ) {
                    return FALSE;
                }
            }
        }

        closedir($dh);

        if ($function($path, $group)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Replaces or removes the file extension.
     *
     * esg_file_util::replaceExt();
     *
     * @param str $file
     * @param str $ext
     */
    public static function replaceExt( $file, $new_ext = '' ) {
        if ( ! empty( $new_ext ) && stripos( $new_ext, '.' ) === false ) {
            $new_ext = '.' . $new_ext;
        }

        $ext_regex = '#[_\.]+\w{2,5}$#si'; // avoid _.txt situations

        if ( preg_match( $ext_regex, $file ) ) {
            $file = preg_replace( $ext_regex, $new_ext, $file );
        } else { // for files with no ext just append
            $file .= $new_ext;
        }

        return $file;
    }

    /**
     * Sets ownership and permissions to a file or folder (linux only).
     * esg_file_util::setOwner();
     * @param str $file_or_folder
     * @param str $user
     * @param str $group
     */
    public static function setOwner( $file_or_folder, $user, $group = '' ) {
        $status = 0;

        if ( App_Sandbox_Env::isLinux()
                && App_Sandbox_Env::getUID() == 0 // root
                && ( file_exists( $file_or_folder ) || is_dir( $file_or_folder ) ) // must exist
                ) {

            $items = array();
            $items[] = $file_or_folder;

            // We need to set ownership of the parent folders.
            // We will only go up 2 levels to change permissions to folders that are within QS base dir.
            // There has been issues with admin scripts that create folders and other code can't write to these folders.
            if ( preg_match( '#^' . preg_quote( APP_SANDBOX_BASE_DIR, '#' ) . '#si', $file_or_folder ) ) {
                $items[] = dirname( $file_or_folder );
                $items[] = dirname( dirname( $file_or_folder ) );
            }

            foreach ( $items as $thing ) {
                $os_user = App_Sandbox_User::getOSOwner( $thing );

                if ( $os_user && $os_user != $user ) { // do we need to change the owner at all?
                    if ( chown( $thing, $user ) ) {
                        $status = 1;
                    }
                } else {
                    $status = 1;
                }

                // Setting OS Group
                $file_group_id = filegroup( $thing );
                $os_group_rec = posix_getgrgid( $file_group_id );
                $os_group = empty($os_group_rec['name']) ? '' : $os_group_rec['name'];
                $group = empty( $group ) ? $user : $group;

                if ( $os_group != $group && ! empty( $group ) ) { // do we need to change the owner at all?
                    if ( chgrp( $thing, $group ) ) {
                        $status = 1;
                    }
                } else {
                    $status = 1;
                }
            }
        } else {
            $status = 1;
        }

        return $status;
    }

    /**
     * Recursively create folders if they don't exist and logs when the creation fails.
     * esg_file_util::mkdir();
     *
     * @param str $folder
     * @param int $perm
     */
    public static function mkdir( $folder, $perm = 0770 ) {
        $status = true;

        if ( empty( $folder ) ) {
            throw new Exception( __METHOD__ . " empty directory passed. " );
        }

        // trying to see if the folder looks like a file which may not exist (of course).
        if ( ( is_file( $folder ) || preg_match( '#\.(?:php|bak|txt|log|zip|rar|tar|gz)$#si', $folder ) )
                && ! is_dir( $folder ) ) { // if the dev has passed a file we'll get its folder.
            $folder = dirname( $folder );
        }

        if ( ! is_dir( $folder ) ) {
            if ( ! mkdir( $folder, $perm, 1 ) ) {
                esg_log::error( "Couldn't create folder: [$folder]", __METHOD__ );
                $status = false;
            }
        }

        if ( $status ) {
            @chmod($file_or_folder, is_dir($file_or_folder) ? $perm : 0664);

            if ( defined( 'APP_SANDBOX_ETC_OS_MAIN_USER' ) ) {
                esg_file_util::setOwner( $folder, APP_SANDBOX_ETC_OS_MAIN_USER, APP_SANDBOX_ETC_OS_MAIN_USER );
            }
        }

        return $status;
    }

    /**
     * @desc read function using flock
     * esg_file_util::read();
     * @param string $vars
     * @param string $buffer
     * @param int $option whether to unserialize the data
     * @return mixed : string/data struct
     */
    public static function read($file, $option = self::AUTO_DETECT) {
        $buff = false;
        $read_mod = "rb";
        $handle = false;

        if (($handle = @fopen($file, $read_mod))
                && (flock($handle, LOCK_SH))) { //  | LOCK_NB - let's block; we want everything saved
            $buff = @fread($handle, filesize($file));
            flock($handle, LOCK_UN);
            @fclose($handle);
        }

        if ($option & self::UNSERIALIZE) {
            $buff = json_decode($buff, true); // true -> array and not obj
        } elseif ($option & self::AUTO_DETECT) {
            $first_char = substr($buff, 0, 1);
            $last_char = substr($buff, -1, 1);

            if ( ($first_char == '{' && $last_char == '}') // first and last chars are {} => serialized JSON
                    || ($first_char == '[' && $last_char == ']') ) {  // first and last chars are [] => serialized JSON
                $buff = json_decode( $buff, ($option & self::AUTO_DETECT_OBJECT) ? false : true ); // true -> array and not obj
            }
        }

        return $buff;
    }

    /**
     * @desc write function using flock
     * esg_file_util::write();
     *
     * @param string $vars
     * @param string $buffer
     * @param int $append
     * @return bool
     */
    public static function write($file, $buffer = '', $option = 1) {
        $buff = false;
        $tries = 0;
        $handle = '';

        $write_mod = 'wb';

        // We can't save something that's not a scalar so we'll serialize it.
        if ( !is_scalar($buffer) || ($option & self::SERIALIZE)) {
            $opts = 0;

            if (defined(JSON_PRETTY_PRINT) || version_compare(phpversion(), '5.4.0', '>=')) { // make JSON look nice
                $opts = JSON_PRETTY_PRINT;
            }

            $buffer = json_encode($buffer, $opts);
        }

        if ($option & self::FILE_APPEND) {
            $write_mod = 'ab';
        }

        $dir = dirname($file);
        esg_file_util::mkdir( $dir );

        if ( ($handle = @fopen($file, $write_mod) )
                && flock($handle, LOCK_EX)) {
            // lock obtained
            if (fwrite($handle, $buffer) !== false) {
                @flock($handle, LOCK_UN);
                @fclose($handle);

                if ($option & self::CLEAR_STAT_CACHE) {
                    clearstatcache();
                }

                return true;
            }
        } else {
            $rec = error_get_last();
            esg_log::error( "Couldn't save [$file]",
                    "error_get_last: " . var_export( $rec, 1 ), __METHOD__ );
        }

        return false;
    }

    /**
     * esg_file_util::addSlash()
     * @param type $path
     * @return string
     */
    static public function addSlash($path) {
        $path = self::removeSlash($path);
        $path .= '/';
        return $path;
    }

    /**
     * esg_file_util::removeSlash()
     * @param type $path
     * @return type
     */
    static public function removeSlash($path) {
        $path = rtrim($path, '/\\');
        return $path;
    }

    /**
     * moves a file or folder.
     * esg_file_util::move($old, $new);
     * @param type $old
     * @param type $new
     * @return bool
     * @see http://php.net/manual/en/function.rename.php
     * @see ddoyle [at] canadalawbook [dot] ca
     */
    static public function move($old, $new) {
        $time_ms = App_Sandbox_Util::time( __METHOD__ );
        $output_arr = array();
        $return_var = false;

        if ( is_dir( $old ) ) {
            $old = self::addSlash( $old );
        }

        if ( App_Sandbox_Env::isWindows() ) {
            if ( is_dir( $old ) ) {
                $old .= '*.*';
            }

           //$bin = 'move /Y'; // override if the folder exists. // but doesn't work well
           // move doesn't move folders ?!?

           // We'll use xcopy and then delete the source
           /*
           /S Copies directories and subdirectories except empty ones.
           /E Copies directories and subdirectories, including empty ones.
           /I If destination does not exist and copying more than one fileassumes that destination must be a directory.
           /H Copies hidden and system files also.
           /Q Does not display file names while copying.
           /Y Suppresses prompting to confirm you want to overwrite an existing destination file.
           */
           $bin = "xcopy /s /e /i /h /q /y";

           $old = esg_file_util::toWinPath( $old );
           $new = esg_file_util::toWinPath( $new );
        } elseif (0&&rename($old, $new)) { // ???cannot be used to move a directory!
           return true;
        } else {
           // mv can reliably move files and folders (e.g. hidden and non hidden).
           // for the explanation of the options see import.php
           $bin = 'rsync -arvupt --remove-source-files --whole-file';
        }

        $old_esc = esg_file_util::escapeShellArg($old);
        $new_esc = esg_file_util::escapeShellArg($new);

        $args = array();
        $args[] = $old_esc;
        $args[] = $new_esc;

        $cmd = $bin . ' ' . join(' ', $args) . ' 2>&1';

        exec($cmd, $output_arr, $return_var);

        esg_log::info("Post move info cmd [$cmd]. Return val: [$return_var] [$old -> $new], Output: "
                . (is_scalar($output_arr) ? $output_arr : join("\n", $output_arr)),
                __METHOD__);

        // So if success and Windows
        if ( empty( $return_var ) && App_Sandbox_Env::isWindows() ) {
            /*
             RMDIR [/S] [/Q] [drive:]path
                RD [/S] [/Q] [drive:]path

                /S      Removes all directories and files in the specified directory
                        in addition to the directory itself.  Used to remove a directory
                        tree.

                /Q      Quiet mode, do not ask if ok to remove a directory tree with /S
             */
            exec( 'rmdir /S /Q ' . esg_file_util::escapeShellArg( trim( $old, '*.' ) ) ); // rm any trailing *.*
        }

        // if the old file/dir still exists there must have been an error.
        $item_exist = is_file( $old ) || is_dir( $old ) ? false : true;

        $time_ms = App_Sandbox_Util::time( __METHOD__ );

        $return_rec['cmd'] = $cmd;
        $return_rec['status'] = empty( $return_var );
        $return_rec['exec_time'] = $time_ms;
        $return_rec['item_exist'] = $item_exist;
        $return_rec['output'] = $output_arr;

        esg_log::info("Post move info cmd [$cmd]. Return val: [$return_var] "
                . "\nmove exec_time: " . $return_rec['exec_time']
                . "\n[$old -> $new], Output: "
                . ( is_scalar($output_arr) ? $output_arr : join("\n", $output_arr) ),
                __METHOD__);

        return $return_rec;
    }

    /**
     * Removes files or directories (recursively).
     * esg_file_util::remove($old_dir);
     *
     * @param str $dir
     * @return bool
     * @see http://php.net/manual/en/function.rmdir.php
     */
    public static function remove($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            is_dir("$dir/$file")
                ? self::remove("$dir/$file")
                : unlink("$dir/$file");
        }

        return rmdir($dir);
    }

    /**
     * Very dangerous method!!!
     *
     * esg_file_util::removeDir();
     *
     * @param str $dir
     * @return Result obj
     */
    static public function removeDir( $dir ) {
        $out = array();
        $return_val = true;

        if ( empty( $dir ) ) {
            throw new Exception( "Directory cannot be empty" );
        }

        if ( App_Sandbox_Env::isWindows() ) {
            /*
             RMDIR [/S] [/Q] [drive:]path
                RD [/S] [/Q] [drive:]path

                /S      Removes all directories and files in the specified directory
                        in addition to the directory itself.  Used to remove a directory
                        tree.

                /Q      Quiet mode, do not ask if ok to remove a directory tree with /S
             */
            // rm any trailing *.* if any
            $dir_cleaned = trim( $dir, '*.' );

            $cmd = 'rmdir';
            $cmd_params = array( '/S', '/Q', $dir_cleaned );
        } else {
            $cmd = 'rm';
            $cmd_params = array( '-rf', $dir );
        }

        $exec_res = esg_file_util::exec( $cmd, $cmd_params );

        return $exec_res;
    }

    /**
     * Converts formatted money into a float val.
     * e.g. 1,000,000 into 1000000
     * @param int/str/ $fmt_money
     * @return float
     */
    public static function getFolderFromZipOutput($buff) {
        $data = preg_split('/[\r\n]+/si', $buff);
        $data = (array) $data;

		// 0  Stored        0   0%  13-07-10 19:16  00000000  admin-ui-simplificator/
        $data = preg_grep('#^\s*0\s+#si', $data); // directories are empty i.e. 0 space
		$data = array_merge($data); // reorder indexes as preg_grep would've removed some lines

		$zip_dir = '';

		if (!empty($data)) {
			$zip_dir = $data[0];

			$zip_dir = preg_replace('#.+?0+\s*#si', '', $zip_dir); // rm all but dirname
			$zip_dir = trim($zip_dir, '/');
			$zip_dir = trim($zip_dir);
		}

        return $zip_dir;
    }

    const UNZIP_METHOD_OS_ZIP = 2;
    const UNZIP_METHOD_OS_7ZIP = 4;
    const UNZIP_METHOD_WP_ZIP = 8;
    const UNZIP_VERIFY_MODULE = 16;

    // This will save us 1 operation as it won't run zip -lv .zip to list contents
    const DONT_CHECK_ZIP_CONTENTS = 32;

    /**
     * Usage: esg_file_util::toLinuxPath()
     * @param str $file
     * @return str
     */
    static public function toLinuxPath( $file ) {
        $file = str_replace( '\\', '/', $file ); // from \\ -> /
        $file = str_replace( '//', '/', $file ); // rm dups
        return $file;
    }

    /**
     * Usage: esg_file_util::toWinPath()
     * @param str $file
     * @return str
     */
    static public function toWinPath( $file ) {
        $file = preg_replace('#[\\/]+#si', '\\', $file );
        return $file;
    }

	/**
	 * Extracts a file which was saved in a tmp folder. We're expecting the zip file to contain a folder first
     * and then some contents
     *
     * Usage: esg_file_util::extractArchiveFile()
     * @param string $archive_file a file in the tmp folder
     * @param string $target_directory usually wp-content/plugins/
     * @see http://www.phpconcept.net/pclzip/user-guide/54
     * @see http://core.trac.wordpress.org/browser/tags/3.6/wp-admin/includes/file.php#L0
	*/
	static public function extractArchiveFile($archive_file, $target_directory, $method = self::UNZIP_METHOD_WP_ZIP, $extract_specific_files = array() ) {
        $time_ms = App_Sandbox_Util::time(__METHOD__);

		$status = 0;
		$error = $plugin_folder = $main_module_file = '';
        $target_directory = self::removeSlash( $target_directory );
        esg_file_util::mkdir( $target_directory ); // Create if necessary

        esg_log::info( "Will extract: [$archive_file] into [$target_directory]", __METHOD__);

        // WP is not available so we'll use unzip
        if ( ! defined( 'ABSPATH' ) || $method == self::UNZIP_METHOD_OS_ZIP ) {
            if ( App_Sandbox_Env::isWindows() ) {
                $archive_file = esg_file_util::toLinuxPath( $archive_file );
                $target_directory = esg_file_util::toLinuxPath( $target_directory);
            }

            // !!! for some weird reason -v option (Windows?) makes the unzip program
            // not to unzip in the target folder!?! so I will list the files from another step
            $archive_file_esc = esg_file_util::escapeShellArg($archive_file);
            $target_directory_esc = esg_file_util::escapeShellArg($target_directory);
            $extract_specific_files_str = '';

            if ( ! empty( $extract_specific_files ) ) {
                $extract_specific_files = (array) $extract_specific_files;
                $extract_specific_files_esc = array_map( 'esg_file_util::escapeShellArg', $extract_specific_files );
                $extract_specific_files_str = ' ' . join( ' ', $extract_specific_files_esc );
            }

            // @see http://unix.stackexchange.com/questions/14120/extract-only-a-specific-file-from-a-zipped-archive-to-a-given-directory
            if ( $method & self::UNZIP_METHOD_OS_7ZIP ) {
                $cmd = "7za x -y $archive_file_esc $extract_specific_files_str -o$target_directory_esc"; // cmd: e extract no paths, x extract w/ paths -oTARGET_DIR -y yes to all
                //$cmd = "7za x -y $archive_file_esc -o$target_directory_esc"; // cmd: e extract no paths, x extract w/ paths -oTARGET_DIR -y yes to all
            } else { // On windows the unzip fails when the folder contains *files that are too long* ?!?
                $cmd = "unzip -q -o $archive_file_esc $extract_specific_files_str -d $target_directory_esc"; // -q is quiet (let's not fill up logs) -v verbose, -o override, -d extract in target dir // -v
            }

            $result = `$cmd 2>&1`;
            $result = trim( $result );
            $failed_to_unzip_files = array();

            $lines = App_Sandbox_String_Util::splitOnNewLines( $result );
            $failed_to_unzip_files = preg_grep( '#\s*(error|warning)\s*:#si', $lines );

            $module_type = '';
            $main_module_file = '';
            $zip_file_list = 'zip file inspection skipped';

            // We need to list the contents so we can get the folder so we can later verify that the extraction was successful.
            // ... that's unless the admin has requested not to check the archive's contents.
            // let's save half of a second there.
            if ( $method & self::DONT_CHECK_ZIP_CONTENTS == 0 ) {
                $zip_file_list_cmd = "unzip -lv $archive_file";
                $zip_file_list = `$zip_file_list_cmd`;

                // get the first folder from the zip
                $plugin_folder = self::getFolderFromZipOutput($zip_file_list);

                if (($main_module_file = self::findThemeFile(self::addSlash($target_directory) . $plugin_folder))
                        &&  !empty($main_module_file)) {
                    $module_type  = 'theme';
                } elseif (($main_module_file = self::findMainPluginFile(self::addSlash($target_directory) . $plugin_folder))
                        &&  !empty($main_module_file)) {
                    $module_type  = 'plugin';
                } else {

                }

                esg_log::info( "Main file: [$main_module_file] Extraction results: [$result]", __METHOD__);
                $good_strings_in_output = strpos( $zip_file_list, 'CRC-32' ) !== true;
            } else {
                $good_strings_in_output = 1;
            }

            $result_rec = array(
                'status' => empty( $module_type ) ? $good_strings_in_output : ! empty( $main_module_file ), // we've found the main plugin file. cool; if not rely on some output.
                'exec_time' => App_Sandbox_Util::time(__METHOD__),
                'cmd' => $cmd,
                'failed_to_unzip_files' => $failed_to_unzip_files,
                'archive_file' => $archive_file,
                'target_directory' => $target_directory,
                'folder' => $plugin_folder,
                'main_file' => $main_module_file, // could be a plugin or a theme
                'module_type' => $module_type, // could be a plugin or a theme
                'debug' => array(
                    'archive_info' => $zip_file_list,
                    'unzip_info' => $result,
                ),
            );

            esg_log::info( "Completed. exec_time: " . $result_rec['exec_time'], __METHOD__);

            return $result_rec;
        }

		// Requires WP to be loaded.
		include_once(ABSPATH . 'wp-admin/includes/file.php');
        include_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');

		if (function_exists('WP_Filesystem')) {
			WP_Filesystem();

            $archive = new PclZip($archive_file);
            $list = $archive->listContent(); // this contains all of the files and directories

            /*
            array(2) {
              [0]=>
              array(10) {
                ["filename"]=>
                string(7) "addons/"
                ["stored_filename"]=>
                string(7) "addons/"
                ["size"]=>
                int(0)
                ["compressed_size"]=>
                int(0)
                ["mtime"]=>
                int(1377115594)
                ["comment"]=>
                string(0) ""
                ["folder"]=>
                bool(true)
                ["index"]=>
                int(0)
                ["status"]=>
                string(2) "ok"
                ["crc"]=>
                int(0)
              }
              [1]=>
              array(10) {
                ["filename"]=>
                string(39) "addons/!sak4wp-theme-troubleshooter.php"
                ["stored_filename"]=>
                string(39) "addons/!sak4wp-theme-troubleshooter.php"
                ["size"]=>
                int(2900)
                ["compressed_size"]=>
                int(1112)
                ["mtime"]=>
                int(1377116198)
                ["comment"]=>
                string(0) ""
                ["folder"]=>
                bool(false)
                ["index"]=>
                int(1)
                ["status"]=>
                string(2) "ok"
                ["crc"]=>
                int(-1530906934)
              }
            }
            */

            // the first element should be the folder. e.g. like-gate.zip -> like-gate/ folder
            // listContent returns an array and folder key should be true.
            foreach ($list as $file_or_dir_rec) {
                if (empty($file_or_dir_rec['filename'])
                        || preg_match('#(\.DS_Store|Thumbs\.db)#si', $file_or_dir_rec['filename'])) { // skip hidden or MAC files // \.|
                    continue;
                }

                // We want to check if there is a folder at the root level (index=0).
                if (!empty($file_or_dir_rec['folder']) && empty($file_or_dir_rec['index'])) {
                    $plugin_folder = $file_or_dir_rec['filename'];
                    break;
                }
            }

            if (!empty($plugin_folder)) {
                $status = unzip_file($archive_file, $target_directory);
            } else {
                $status = new WP_Error('100', "Cannot find plugin folder in the zip archive.");
            }

			if (is_wp_error($status)) {
				$error = $status->get_error_message();
			} else {
				$status = 1;
                $main_module_file = self::findMainPluginFile( self::addSlash( self::addSlash($target_directory) . $plugin_folder) );
			}
		} else {
			$error = 'WP_Filesystem is not loaded.';
		}

		$data = array(
            'status' => $status,
            'error' => $error,
            'plugin_folder' => $plugin_folder,
            'main_plugin_file' => $main_module_file,
        );

        esg_log::info( "Completed (2). exec_time: " . App_Sandbox_Util::time(__METHOD__), __METHOD__);

		return $data;
	}

	/**
     * Tries to get the temp directory for php.
     * It checks if this function exists: sys_get_temp_dir (since php 5.2).
     * Otherwise it checks the ENV variables TMP, TEMP, and TMPDIR
     * esg_file_util::getTempDir();
     * @see http://php.net/manual/en/function.sys-get-temp-dir.php
     * @return string
     */
    public static function getTempDir() {
        $dir = '/tmp';

        if (function_exists('sys_get_temp_dir')) {
            $dir = sys_get_temp_dir();
        } else {
            if ($temp = getenv('TMP')) {
                $dir = $temp;
            } elseif ($temp = getenv('TEMP')) {
                $dir = $temp;
            } elseif ($temp = getenv('TMPDIR')) {
                $dir = $temp;
            } else {
                $temp = tempnam(__FILE__, '');

                if (file_exists($temp)) {
                    unlink($temp);
                    $dir = dirname($temp);
                }
            }
        }

        return $dir;
    }

	/**
     * This creates a temp folder int the temp folder.
     * This folder is used to store the extracted zip's contents.
     * The folder is created before returning.
     * @return string
     */
    public static function getArchiveTempDir() {
        $mode = 0775;
        $prefix = '';
        $dir = self::getTempDir();

        $dir = rtrim($dir, '/\\');
        $dir .= '/';

        // we can use this timestamp so we can clean up the old directories
        // in 7 * 24 hours
        $prefix = 'archive_tmp_dir-' . time() . '-';

        // try hard until you find a
        do {
          $path = $dir . $prefix . sprintf("%05d", mt_rand (1000, 99999)) . '/';
        } while (is_dir($path));

        // the folder doesn't exists so let's create it.
        self::mkdir($path, $mode, 1);

        return $path;
    }

    /**
     * This plugin scans the files in a folder and tries to get plugin data.
     * The real plugin file will have Name, Description variables set.
     * If the file doesn't have that info WP will prefill the data with empty values.
     *
     * @param string $folder - plugin's folder e.g. wp-content/plugins/like-gate/
     * @param bool $ if supplied the return result will be like-gate/like-gate.php
     * @return string wp-content/plugins/like-gate/like-gate.php or false if not found.
     */
    static public function findMainPluginFile($folder = '', $only_relative_path = 1) {
        $folder = self::addSlash($folder);
        $files_arr = glob($folder . '*.php'); // list only php files.

        foreach ($files_arr	as $file) {
            $buff = self::readFilePartially($file);

            // Did we find the plugin? If yes, it'll have Name filled in.
            if (stripos($buff, 'Plugin Name') !== false) {
                return $only_relative_path
                        ? preg_replace('#.*?' . dirname(preg_quote($folder)). '/#si', '', $file) // rm all before the folder
                        : $file;
            }
        }

        return false;
    }

    /**
     * This plugin scans the files in a folder and tries to get plugin data.
     * The real plugin file will have Name, Description variables set.
     * If the file doesn't have that info WP will prefill the data with empty values.
     *
     * @param string $folder - plugin's folder e.g. wp-content/plugins/like-gate/
     * @param bool $ if supplied the return result will be like-gate/like-gate.php
     * @return string wp-content/plugins/like-gate/like-gate.php or false if not found.
     */
    static public function findThemeFile($folder = '', $only_relative_path = 1) {
        $folder = self::addSlash($folder);
        $files_arr = glob($folder . '*.css'); // list only php files.

        foreach ($files_arr	as $file) {
            $buff = self::readFilePartially($file);

            // Did we find the plugin? If yes, it'll have Name filled in.
            if (stripos($buff, 'Theme Name') !== false) {
                return $only_relative_path
                        ? preg_replace('#.*?' . dirname(preg_quote($folder)). '/#si', '', $file) // rm all before the folder
                        : $file;
            }
        }

        return false;
    }

    /**
     * Reads a file partially e.g. the first NN bytes.
     * esg_file_util::readFilePartially();
     *
     * @param string $file
     * @param int $len_bytes how much bytes to read
     * @param int $seek_bytes should we start from the start?
     * @return string
     */
    static function readFilePartially($file, $len_bytes = 2048, $seek_bytes = 0) {
        $buff = '';

        if (!file_exists($file)) {
            return false;
        }

        $file_handle = fopen($file, 'rb');

        if (!empty($file_handle)) {
            if ($seek_bytes > 0) {
                fseek($file_handle, $seek_bytes);
            }

            $buff = fread($file_handle, $len_bytes);
            fclose($file_handle);
        }

        return $buff;
    }

    const OUTPUT = 1;
    const DOWNLOAD = 2;

    /**
     * esg_file_util::outputCSV()
     * @todo move to CSV class
     * @param array $header - columns of the CSV file.
     * @param array $data array of arrays
     * @return string
     * @see http://www.andrew-kirkpatrick.com/2013/08/output-csv-straight-to-browser-using-php/
     */
    static public function outputCSV($header, $data = array(), $serve_download = self::OUTPUT, $file_name = '' ) {
        // Open the output stream
        $fh = fopen('php://output', 'w');

        // Start output buffering (to capture stream contents)
        ob_start();

        fputcsv($fh, $header);

        // Loop over the * to export
        if (!empty($data)) {
          foreach ($data as $item) {
            fputcsv($fh, $item);
          }
        }

        fclose($fh);
        $string = ob_get_clean();

        if ( $serve_download == self::DOWNLOAD ) {
            if (empty($file_name)) {
                $filename = $_SERVER['HTTP_HOST'] . '_data_' . date('Y-m-d_H_i_s') . '.csv';
            }

            // Output CSV-specific headers
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private", false);
            header("Content-Type: application/vnd.ms-excel"); // application/octet-stream
            header("Content-Disposition: attachment; filename=\"$filename\";");
            header("Content-Transfer-Encoding: binary");

            echo $string;
            exit;
        }

        echo $string;
    }

    private static $dry_run = false;

    /**
     * esg_file_util::dry_run(1);
     * @param bool $flag
     */
    public static function dry_run($flag) {
        self::$dry_run = $flag;
    }

    const ADD_KEY_PREFIX = 2;

    /**
     * It's currently doing long args
     * esg_file_util::arrayToCmdArgs( );
     *
     * when esg_file_util::ADD_KEY_PREFIX is passed only keys will be prefixed.
     *
     * @param array $params
     * @return str --cmd qs.setup --user-id=$user_id_esc --dl-hash=$dl_hash_esc --dl-url=$dl_url_esc --site=$site_esc
     */
    static public function arrayToCmdArgs( $params = array(), $flags = 1 ) {
        $cmd_pairs = array();

        foreach ( $params as $key => $value ) {
            $new_key = preg_replace( '#[^\w-]#si', '', $key );
            $new_key = trim( $new_key );

            // e.g. if it's 0 keep it as is or if it has a dash already
            if ( substr( $new_key, 0, 1 ) == '-' ) {
                // relax
            } elseif ( is_numeric( $new_key ) ) {
                // relax
            } else {
                $new_key = '--' . $new_key;
            }

            if ( $flags & esg_file_util::ADD_KEY_PREFIX ) {
                $cmd_pairs[ $new_key ] = $value;
            } else {
                $cmd_pairs[] = $new_key . '=' . esg_file_util::escapeShellArg( $value );
            }
        }

        if ( $flags & esg_file_util::ADD_KEY_PREFIX ) {
            $res = $cmd_pairs;
        } else {
            $res = join( ' ', $cmd_pairs );
        }

        return $res;
    }

    /**
     * Runs a command
     * $res = esg_file_util::exec( $cmd, $params );
     * @todo check defined option and start collecting stats for executed programs.
     * and on shutdown generate a file in the log file.exec_time.log which includes
     * microtime, function, params, exec status, exec time.
     * could be CSV or in a text file that separates everything with ---------
     * @return array
     */
    static public function exec( $cmd, $params = array() ) {
        $output_arr = $params_key_val_pairs = array();
        $return_var = false;
        $params = (array) $params;

        $skip_binaries_on_win = array(
            'chown',
            'chmod',
            'useradd',
            'adduser',
            'userdel',
            'deluser',
            'service',
            'apachectl',
        );

        if ( App_Sandbox_Env::isWindows() && in_array( $cmd, $skip_binaries_on_win ) ) {
            return array( 'statuc' => 1, 'output' => 'Skipped command on Windows', 'msg' => '', );
        }

        // Translate word params for background process (linux only)
        if ( ! in_array( '&', $params ) ) { // the dev speaks linux :)
            $bg_run_req_key = null;

            if ( ( $bg_key = array_search( '--bg', $params ) ) !== false ) {
                $bg_run_req_key = $bg_key;
            } elseif ( ( $bg_key = array_search( '--background', $params ) ) !== false ) {
                $bg_run_req_key = $bg_key;
            } elseif ( isset( $params[ '--bg' ] ) ) { // passed as --bg => 1
                $bg_run_req_key = '--bg';
            } elseif ( isset( $params[ '--background' ] ) ) { // passed as --background => 1
                $bg_run_req_key = '--background';
            }

            if ( ! is_null( $bg_run_req_key ) /*&& App_Sandbox_Env::isLinux() */) {
                unset( $params[$bg_run_req_key] );

                if ( App_Sandbox_Env::isLinux() ) {
                    $params[] = '&';
                }
            }
        }

        $time_ms = App_Sandbox_Util::time(__METHOD__);
        $piped_content = '';

        if ( ! empty( $params['_piped_content'] ) ) {
            $piped_content = $params['_piped_content'];
            unset( $params['_piped_content'] );
        }

        // We have attempted to chown/chmod something but the current user doesn't have enough privileges.
        // We'll pass the command through the wrapper.
        // is it too risky?
        // DISABLED for now.
        if ( 0 && in_array( $cmd, array( 'chmod', 'chown' ) )
                && App_Sandbox_Env::getUID() > 0 ) {
            array_unshift( $params, $cmd );
            $wrapper_bin = App_Sandbox_Env::getWrapperBinary();
            $cmd = $wrapper_bin;
        }

        $sleep = 0;

        if ( ! empty( $params['__sleep'] ) ) {
            $sleep = (int) $params['__sleep'];
            unset( $params['__sleep'] );
        } elseif ( ! empty( $params['__wait'] ) ) {
            $sleep = (int) $params['__wait'];
            unset( $params['__wait'] );
        }

        foreach ( $params as $key => $val ) {
            if ( ! is_scalar( $key ) || ! is_scalar( $val ) ) { // too many nested arrays
                $x = array();
                $x['key'] = $key;
                $x['val'] = $val;
                esg_log::error( var_export( $x, 1 ), __LINE__ . __METHOD__ );
                throw new Exception( __METHOD__ . ' key and value must be scalar variables.' );
            }

            // Sometimes one key can be repeated multiple times.
            // php will override other values but we can make the key unique.
            // e.g. -x__uniq123
            $key = preg_replace( '#[_-]{2}uniq[.-_\d\s]*#si', '', $key );

            $upd_val = trim( $val );
            $upd_val = ( empty( $upd_val )
                        || in_array( $upd_val, array( '-', ';', '+', '(', ')', '>', '>>', '<', '2>&1', '1>&2', '&', '&&', '|', '||', '!$', '/dev/null' ) ) ) // ';', - cmd one after another
                        || preg_match( '#^[\w-]+$#si', $val ) // params linux.
                        || preg_match( '#^/\w{1,5}$#si', $val ) // params on windows. e.g. /s
                        ? $upd_val
                        : esg_file_util::escapeShellArg( $upd_val );

            if ( is_numeric( $key ) ) { // param value no key e.g. useradd slavi
                // no need to escape some chars
                $params_key_val_pairs[] = $upd_val;
            } elseif ( $cmd == 'mysql' && preg_match( '#^-[\w-]+$#si', $key ) ) { // special case for mysql commands which require params to be joined e.g. -uroot
                $params_key_val_pairs[] = $key . '' . $upd_val;
            } elseif ( $cmd == 'wp' && preg_match( '#^-[\w-]+$#si', $key ) ) { // wp-cli case
                $params_key_val_pairs[] = $key . '=' . $upd_val;
            } elseif ( preg_match( '#^-[\w-]+$#si', $key ) ) {
                $params_key_val_pairs[] = $key . ' ' . $upd_val;
            } elseif ( preg_match( '#^[\s\<\>$!]$#si', $key ) ) { // special chars
                $params_key_val_pairs[] = $key . ' ' . $upd_val;
            } else { // unknown key format so we'll escape it.
                $params_key_val_pairs[] = esg_file_util::escapeShellArg( $key ) . '=' . $upd_val;
            }
        }

        $cmd_esc = escapeshellcmd( $cmd );

        // Let's wait before doing some work.
        if ( $sleep ) {
            // Windows separates sequantial commands with &
            // Linux with ;
            $cmd_esc = "sleep $sleep " . ( App_Sandbox_Env::isLinux() ? ';' : '&' ) . $cmd_esc;
        }

        // linux error: Insecure $ENV{PATH} while running setuid at /usr/sbin/a2ensite line 483.
        if ( App_Sandbox_Env::isLinux() && preg_match( '#a\d+(en|dis)#si', $cmd ) ) {
            $cmd_esc = "export PATH=''; " . $cmd_esc;
            //$cmd_esc = "export PATH='/bin:/usr/bin'; " . $cmd_esc;
            //$cmd_esc = "export PATH='/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin'; " . $cmd_esc;
            //$cmd_esc = '$ENV{"PATH"} = "/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin";' . $cmd_esc;
        }

        $cmd_params = join( ' ', $params_key_val_pairs );
        $cmd_param_suffix = '';

        // This finally works!!! BG tasks
        // Have we redirected the output yet? If no we'll do it now.
        // @see http://www.askmephp.com/?p=132
        // @see http://salman-w.blogspot.bg/2010/04/running-background-process-in-php.html
        if ( in_array( '&', $params ) ) {
            $suffix = '';

            if ( ! preg_match( '#\s*2\s*\>\s*#si', $cmd_params ) ) { // the user hasn't defined or redirected the stderr
                $suffix .= ' 2>&1 '; // redirect STDERR to STDOUT helps with bg tasks
            }

            if ( ! preg_match( '#\s*\>\s*#si', $cmd_params ) ) { // the user hasn't defined or redirected the stdout
                $suffix .= ' > /dev/null '; // redirect STDOUT to null helps with bg tasks
            }

            // We need the & to be last otherwise it won't be a good background task.
            // This regex will put the chanel redirects before the &
            if ( ! empty( $suffix ) ) {
                $cmd_params = preg_replace( '#(\s+\&\s*)$#si', $suffix . ' ${1}', $cmd_params );
            }
        }

        $return_var = 0;

        // In case we want to debug command line scripts.
        $xdebug_ide_key = ini_get( 'xdebug.idekey' );

        if (    APP_SANDBOX_PHP_ENABLE_XDEBUG_DEBUGGING
                && ! empty( $xdebug_ide_key )
                && ( strpos( $cmd_esc, 'php' ) !== false )
                && extension_loaded( 'xdebug' )
                ) {
            $xdebug_ide_key_esc = escapeshellarg( $xdebug_ide_key );
            $cmd_esc = "export XDEBUG_CONFIG=\"idekey=$xdebug_ide_key_esc\"; " . $cmd_esc;
        }

        $full_cmd = $cmd_esc . ' ' . $cmd_params . $cmd_param_suffix;

        if ( self::$dry_run ) {
            $return_rec['dry_run'] = 1;
            $return_var = 0; // success
        } elseif ( 0 && in_array( '-', $params ) && ! empty( $piped_content ) ) { // pipe NOT TESTED!!!
            // this gives some weird error about not providing pipes
            $pipes = array();
            $descriptor_spec = array(
                0 => array("pipe", "r"), // stdin is a pipe that the child will read from
                1 => array("pipe", "w"), // stdout is a pipe that the child will write to
                2 => array("pipe", "r"), // stderr is a pipe that the child will read from
            );

            $full_cmd = str_replace( '=-', ' -', $full_cmd );
            $process = proc_open( $full_cmd, $descriptor_spec, $pipes );

            if ( is_resource( $process ) ) {
                fwrite($pipes[0], $piped_content);
                fclose($pipes[0]);

                $return_rec['output'] = stream_get_contents($pipes[1]);
                fclose($pipes[1]);

                $return_rec['stderr_output'] = stream_get_contents($pipes[2]);
                fclose($pipes[2]);

                $return_value = proc_close($process);
                $return_var = 0;
            } else {
                $return_var = 1;
            }

            $return_rec['dry_run'] = 0;
        } else {
            exec( $full_cmd, $output_arr, $return_var );
        }

        // there was an error with the command so we'll try to find the binary
        // and execute it again.
        // NOTE: 0 is OK in linux!!! so anything other than 0 is an error exit code.
        if ( ! empty( $return_var ) ) {
            $bin_full_path = esg_file_util::getBinary($cmd);

            if ( ! empty( $bin_full_path ) && $bin_full_path != $cmd ) {
                 esg_log::error( "Exec cmd failed with non success exit code. "
                . " Trying to find the binary [$cmd]."
                . " Bin_full_path val: [$bin_full_path],"
                . " Return val: [$return_var],"
                . " Params: " . ( is_scalar( $params ) ? $params : var_export( $params, 1 ) )
                . " Output: " . ( is_scalar( $output_arr ) ? $output_arr : var_export( $output_arr, 1 ) ),
                __METHOD__ );

                $cmd_esc = escapeshellcmd( $bin_full_path );
                exec( $full_cmd, $output_arr, $return_var );
            }
        }

        $time_ms = App_Sandbox_Util::time( __METHOD__ );
        $output = is_array( $output_arr ) ? join( "\n", array_map( 'trim', $output_arr ) ) : $output_arr;
        $output = trim( $output );

        $dbg_info = "Exec time: $time_ms"
                . "\nExec cmd [$cmd_esc $cmd_params $cmd_param_suffix]."
                . "\nReturn val: [$return_var],"
                . "\nParams: " . ( is_scalar( $params ) ? $params : var_export( $params, 1 ) )
                . ( empty( $output ) ? '' : "\nOutput: " . $output );

        if ( App_Sandbox_Util::isDebugging() ) {
            $dbg_info .= "\nOS user: " . App_Sandbox_Env::getCurrentOSUser( App_Sandbox_Env::FULL );
            $dbg_info .= "\nOS group: " . App_Sandbox_Env::getCurrentGroupId();
            $dbg_info .= "\nOS user_id: " . App_Sandbox_Env::getUID();
            $dbg_info .= "\nBacktrace: " . var_export( debug_backtrace(), 1 )
                        . "\n------------------------------------------------------------------\n";
        }

        esg_log::info( $dbg_info, __METHOD__ );

        // if the old file/dir still exists there must have been an error.
//        $return_var = is_file($old) || is_dir($old) ? false : true;
        $return_rec['cmd'] = $cmd;
        $return_rec['exec_time'] = $time_ms;
        $return_rec['cmd_params'] = $cmd_params;
        $return_rec['full_cmd'] = $full_cmd;
        $return_rec['status'] = empty($return_var); // 0 means success in linux

        if ( ! empty( $output ) ) {
            $return_rec['output'] = $output;
        }

        if ( defined( 'APP_SANDBOX_PROFILER' ) ) {
            self::$profiling_res[] = $return_rec;
        }

        return $return_rec;
    }

    /**
     * Runs a command
     * $res = esg_file_util::normalizePath( $path );
     * @return array
     */
    static public function normalizePath( $folder, $add_trailing_slash = 0 ) {
        $folder = str_replace('\\', '/', $folder); // conv. win slashes

        /*if (function_exists('realpath') && is_dir($folder)) { // could be deactivated by admin
            $folder = realpath($folder); // just in case if symlinks are used.
        }*/

        $folder = preg_replace('#[\\/]+#si', '/', $folder);
        $folder = rtrim($folder, '/');

        // This is indeed folder so append the trailing slash
        if ($add_trailing_slash || !preg_match('#\.\w{2,5}$#si', $folder)) {
            $folder .= '/';
        }

        return $folder;
    }

    /**
     * $res = esg_file_util::isTar( $file );
     * @param str $file local file or URL
     * @return bool
     */
    static public function isTar( $file ) {
        $s = preg_match('#\.tar$#si', $file) ? 1 : 0;
        return $s;
    }

    /**
     * $res = esg_file_util::isGz( $file );
     * @param str $file local file or URL
     * @return bool
     */
    static public function isGz( $file ) {
        $s = preg_match('#\.gz#si', $file) ? 1 : 0;
        return $s;
    }

    /**
     * $res = esg_file_util::isTarGz( $file );
     * @param str $file local file or URL
     * @return bool
     */
    static public function isTarGz( $file ) {
        $s = preg_match('#\.(tar\.gz|tgz)$#si', $file) ? 1 : 0;
        return $s;
    }

    const NL2BR = 2;

    /**
     * Smart replacing of new lines CRLF because it was breaking the strings on new lines.
     * esg_file_util::escapeShellArg()
     * @param str $arg
     * @see http://www.asciitable.com/
     * @see http://stackoverflow.com/questions/5489613/php-exec-and-spaces-in-paths
     * @see http://stackoverflow.com/questions/3200087/how-to-escape-new-line-from-string
     * @see http://www.drupalcontrib.org/api/drupal/contributions!drush!includes!exec.inc/function/_drush_escapeshellarg_linux/7
     * @see http://www.drupalcontrib.org/api/drupal/contributions!drush!includes!exec.inc/function/_drush_escapeshellarg_windows/7
     * @see http://php.net/manual/en/function.escapeshellarg.php
     */
    public static function escapeShellArg( $arg, $flags = 1 ) {
        if ( App_Sandbox_Env::isWindows() ) { // What about linux?
            $special_chars = array( "\r\n", "\n\r", "\r", "\n" );

            $found = 0;

            foreach ( $special_chars as $special_ch ) {
                if ( strpos( $arg, $special_ch ) !== false ) {
                    $found = 1;
                    break;
                }
            }

            if ( $found ) {
                $arg = str_replace( $special_chars, ( $flags & self::NL2BR ) ? '<br/>' : ' ', $arg );
                $arg = str_replace( array( "\t", "\0", "\x0B" ), ' ', $arg ); // \x0B vertical tab?

                // For some weird reason 'escapeshellarg' removes double quotes from php serialized content ?!?
                $arg = '"' . addcslashes( $arg, '\\"' ) . '"';
                return $arg;
            }
        }

        $arg = escapeshellarg( $arg );

        return $arg;
    }

    public function pipiedExec($param) {
        $descriptor_spec = array(
           0 => array("pipe", "r"),
           1 => array("pipe", "w")
        );

        $process = proc_open($cmd, $descriptor_spec, $pipes);

        if (is_resource($process)) {

            //row2xfdf is made-up function that turns HTML-form data to XFDF
            fwrite($pipes[0], raw2xfdf($_POST));
            fclose($pipes[0]);

            $pdf_content = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $return_value = proc_close($process);

            header('Content-type: application/pdf');
            header('Content-Disposition: attachment; filename="output.pdf"');
            echo $pdf_content;
        }
    }


    /**
     * Attempts to find the path to a binary by iterating over several cases.
     *
     * Usage: esg_file_util::getBinary();
     * @param str $file
     * borrowed from SAK4WP
     */
    public static function getBinary($file) {
        $file_esc = escapeshellcmd($file);

        // hmm, what did we receive? that required escaping?
        if ($file != $file_esc) {
            return false;
        }

        $options = $output_arr = array();
        $return_var = false;

        $options[] = $file;
        $options[] = basename($file);
        $options[] = "/usr/bin/$file";
        $options[] = "/usr/local/bin/$file";
        $options[] = "/usr/sbin/$file";

        $options = array_unique($options); // elem0 and 1 could match so we want to test 1 condition just once

        foreach ($options as $file) {
            $cmd = "$file --help 2>&1";
            exec($cmd, $output_arr, $return_var);

            if (empty($return_var)) { // found it! exit code 0 means success in linux
                return $file;
            }
        }

        return false;
    }

    const STRIP_SITES_ROOT = 2;
    const STRIP_DOC_ROOT = 4;
    const STRIP_FULL_DOC_ROOT = 8;

    /**
     * Removes qSandbox root dir e.g. /var/www/vhosts/sites/ or  /var/www/vhosts/qsandbox.com/users/0/1/qsu1/sites
     * $path = esg_file_util::stripRootDirs( $path );
     * $path = esg_file_util::stripRootDirs( $path, esg_file_util::STRIP_SITES_ROOT );
     * @return string
     */
    static public function stripRootDirs( $path, $flags = null ) {
        $flags = is_null( $flags ) ? self::STRIP_SITES_ROOT | self::STRIP_DOC_ROOT : $flags;

        // Let's remove paths that expose directories at qsandbox.
        // using 'm' will allow the regex to work on line by line.
        if ( $flags & self::STRIP_SITES_ROOT ) {
            $path = preg_replace( '#[\:\w-/\.]+/sites/?(?:\w/\w/\w/)?#sim', '/', $path ); // rm dir prefix; : is if it's a Windows path e.g. c:/
        }

        if ( $flags & self::STRIP_FULL_DOC_ROOT ) {
            $path = preg_replace( '#^.*?/htdocs/*#sim', '/', $path ); // no need for htdocs
        } elseif ( $flags & self::STRIP_DOC_ROOT ) {
            $path = preg_replace( '#/htdocs/*#sim', '/', $path ); // no need for htdocs
        }

        return $path;
    }

    private static $sys_notice_rel_path = 'system/system_notice/system_notice.txt';

    /**
     *
     * esg_file_util::readSystemNotice();
     *
     * @param str $file
     * @return str
     */
    static public function readSystemNotice( $file = '' )  {
        $buff = '';
        $file = empty( $file ) ? APP_SANDBOX_DATA_DIR . self::$sys_notice_rel_path : $file;

        if ( file_exists( $file ) && ( time() - filemtime( $file ) <= 7 * 24 * 3600 ) ) { // msg stays for no more than 2 days
            $buff = esg_file_util::read( $file );

            // Links should be in this format
            // [[link our blog=http://blog.qsandbox.com/2015/09/oops-we-broke-one-click-wordpress-admin.html]]

            // Final result should be
            // Oops! We broke our one click WordPress admin login (for new sites only). <a class='bizzbutton' href="http://blog.qsandbox.com/2015/09/oops-we-broke-one-click-wordpress-admin.html" target="_blank">Read our recent blog post</a>
            $buff = preg_replace( '#\[\[link (.*?)\=(.*?)\]\]+#si', '<a class="bizzbutton" href="$2" target="_blank">$1</a>', $buff );
        }

        return $buff;
    }

    /**
     *
     * @param str $notice
     * @param str $file
     */
    static public function writeSystemNotice( $notice, $file = '' )  {
        $file = empty( $file ) ? APP_SANDBOX_DATA_DIR . self::$sys_notice_rel_path : $file;

        if ( file_exists( $file ) ) {
            $new_name = $file . '_old_' . App_Sandbox_String_Util::generateUniqueId( 'microtime' );
            rename( $file, $new_name );
       }

       return esg_file_util::write( $file, $notice );
    }

    /**
     * Parses a text file that contains FAQ stuff.
     * The questions must be separated by multiple dashes (-), underscores (_) or equal signs (=)
     * The first line is assumed to be the title of the question and the rest is the content.
     *
     * esg_file_util::readFAQFile();
     * @param str $file
     * @return array
     */
    static public function readFAQFile( $file )  {
        $buff = esg_file_util::read( $file );

        $questions = preg_split( '#\s*[\-=_]{4,}\s*#si', $buff );
        $questions = array_filter( $questions );

        $faq = array();

        foreach ( $questions as $question_buff ) {
            $question_parts = App_Sandbox_String_Util::splitOnNewLines( $question_buff );

            $title = array_shift( $question_parts );
            $content = join( "\n", $question_parts );
            $content = trim( $content );

            // nl2br will add many new lines and let's clear some from <PRE> so it keeps looking nice.
            $content = preg_replace('#(<pre[^>]*>)\s*(.*?)\s*</pre>#si', '$1$2</pre>', $content );
            $content = preg_replace('#(<code[^>]*>)\s*(.*?)\s*</code>#si', '$1$2</code>', $content );

            $content = nl2br( $content );

            $content = App_Sandbox_String_Util::parseAndRunCodeTemplate( $content );
            $content = App_Sandbox_String_Util::autoLink( $content );

            $faq[] = array( 'title' => $title, 'content' => $content );
        }

        return $faq;
    }

    /**
     * The file contains app\data\system\marketing\features.txt info one each row.
     * title | descr | image name (just basename)
     *
     * esg_file_util::readFeaturesFile();
     * @param str $file void
     * @return array
     */
    static public function readFeaturesFile( $file = '' )  {
        $file = empty( $file ) ? APP_SANDBOX_DATA_DIR . 'system/marketing/features.txt': $file;
        $buff = esg_file_util::read( $file );

        $questions = App_Sandbox_String_Util::splitOnNewLines( $buff );
        $questions = array_filter( $questions );

        $faq = array();

        foreach ( $questions as $question_buff ) {
            $question_buff = trim( $question_buff );
            $question_parts = preg_split( '#\s*\|\s*#si', $question_buff );

            if ( empty( $question_parts ) || count( $question_parts ) < 3 ) {
                continue;
            }

            $title = $question_parts[0];
            $content = $question_parts[1];
            $image = 'assets/marketing/' . $question_parts[2];
            $th_image = 'assets/marketing/' . $question_parts[2];
            $title_fmt = preg_replace( '#\*(.*?)\*#si', '<span>$1</span>', $title );

            $faq[] = array( 'title' => $title, 'title_fmt' => $title_fmt, 'content' => $content, 'image_url' => $image, 'thumbnail_url' => $th_image );
        }

        return $faq;
    }

    /**
     * Serves the file for download. Forces the browser to show Save as and not open the file in the browser.
     * Makes the script run for 12h just in case and after the file is sent the script stops.
     * esg_file_util::downloadFile();
     *
     * Credits:
	 * http://php.net/manual/en/function.readfile.php
     * http://stackoverflow.com/questions/2222955/idiot-proof-cross-browser-force-download-in-php
     *
     * @param string $file
     * @param bool $do_exit - exit after the file has been downloaded.
     */
    public static function downloadFile($file, $do_exit = 1) {
        // When safe mode is enabled: Warning: set_time_limit(): Cannot set max execution time limit due to system policy in ...
        @set_time_limit(1 * 3600); // 1 hour

        if (ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', 0);

            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
        }

        if ( ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
                || $_SERVER['SERVER_PORT'] == 443 ) {
            header( "Cache-control: private" );
            header( 'Pragma: private' );

            // IE 6.0 fix for SSL
            // SRC http://ca3.php.net/header
            // Brandon K [ brandonkirsch uses gmail ] 25-Apr-2007 03:34
            header( 'Cache-Control: maxage=3600' ); //Adjust maxage appropriately
        } else {
            header( 'Pragma: public' );
        }

        // the actual file that will be downloaded
        $download_file_name = self::prepareDownloadFile($file);
        $default_content_type = 'application/octet-stream';
        $get_ext_splits = explode('.', $download_file_name);

        if ( empty( $get_ext_splits ) ) {
            throw new Exception( __METHOD__ . " So sorry but I refuse to serve extenless file." );
        }

        $ext = end( $get_ext_splits );
        $ext = strtolower( $ext );

        // http://en.wikipedia.org/wiki/Internet_media_type
        $content_types_array = array(
            'pdf' => 'application/pdf',
            'exe' => 'application/octet-stream',
            'zip' => 'application/zip',
            'gzip' => 'application/gzip',
            'gz' => 'application/x-gzip',
            'z' => 'application/x-compress',

            'cer' => 'application/x-x509-ca-cert',
            'vcf' => 'application/text/x-vCard',
            'vcard' => 'application/text/x-vCard',

            // doc
            "tsv" => "text/tab-separated-values",
            "txt" => "text/plain",
            'dot' => 'application/msword',
            'rtf' => 'application/msword',
            'doc' => 'application/msword',
            'docx' => 'application/msword',
            'xls' => 'application/vnd.xls',
            'xlsx' => 'application/vnd.ms-excel',
            'csv' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.ms-powerpoint',
            'mdb' => 'application/x-msaccess',
            'mpp' => 'application/vnd.ms-project',

            'js' => 'text/javascript',
            'css' => 'text/css',
            'htm' => 'text/html',
            'html' => 'text/html',

            // images
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpg' => 'image/jpg',
            'jpeg' => 'image/jpg',
            'jfif' => 'image/pipeg',
            'jpe' => 'image/jpeg',
            'bmp' => 'image/bmp',

            'ics' => 'text/calendar',

            // audio & video
            'au' => 'audio/basic',
            'mid' => 'audio/mid',
            'mp3' => 'audio/mpeg',
            'avi' => 'video/x-msvideo',
            'mp4' => 'video/mp4',
            'mp2' => 'video/mpeg',
            'mpa' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mpv2' => 'video/mpeg',
            'mov' => 'video/quicktime',
            'movie' => 'video/x-sgi-movie',
        );

        $content_type = empty($content_types_array[$ext]) ? $default_content_type : $content_types_array[$ext];

		header('Expires: 0');
 		header('Content-Description: File Transfer');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: ' . $content_type);
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) (filesize($file)));
        header('Content-Disposition: attachment; filename="' . $download_file_name . '"');

		ob_clean();
		flush();

        readfile($file);

		if ($do_exit) {
			exit;
		}
    }

    /**
     * Removes the directory, also some extra s1111. chars from a filename that makes it unique.
     * This is primarily used by the download
     * @param str $file
     * @param bool $clean_ext - cleans the extension as well, default:0 -> no
     */
    public static function prepareDownloadFile($file, $clean_ext = 0) {
        $file = trim($file);
        $file = basename($file);

        // if a file with the same name existed we've appended some numbers to the filename but before
        // the extension. Now we'll offer the file without the appended numbers.
        $file = preg_replace('#-sss\d+(\.\w{2,5})$#si', '\\1', $file);

        if ($clean_ext) {
            $file = preg_replace('#\.\w{2,4}$#si', '', $file); // rm ext
        }

        return $file;
    }

    /**
     * esg_file_util::genExtensionByFileType();
     * @param str $file_type
     * @see http://filext.com/faq/office_mime_types.php
     */
    public static function genExtensionByFileType($file_type) {
        $ext = '';

        if ( preg_match( '#image/jpe?g#si', $file_type ) ) {
            $ext = 'jpg';
        } elseif ( preg_match( '#image/png#si', $file_type ) ) {
            $ext = 'png';
        } elseif ( preg_match( '#application/zip#si', $file_type ) ) {
            $ext = 'zip';
        } elseif ( preg_match( '#application/(x-)?pdf#si', $file_type ) ) {
            $ext = 'pdf';
        } elseif ( preg_match( '#application/vnd.ms-excel#si', $file_type ) ) {
            $ext = 'xls';
        } elseif ( preg_match( '#application/msword#si', $file_type ) ) {
            $ext = 'doc';
        } elseif ( preg_match( '#application/vnd.openxmlformats-officedocument.wordprocessingml.document#si', $file_type ) ) {
            $ext = 'docx';
        } elseif ( preg_match( '#application/vnd.openxmlformats-officedocument.spreadsheetml.sheet#si', $file_type ) ) {
            $ext = 'xlsx';
        } elseif ( preg_match( '#application/vnd.ms-powerpoint#si', $file_type ) ) {
            $ext = 'ppt';
        } elseif ( preg_match( '#application/vnd.openxmlformats-officedocument.presentationml.presentation#si', $file_type ) ) {
            $ext = 'pptx';
        }

        if ( !empty($ext)) {
            $ext = '.' . $ext;
        }

        return $ext;
    }

    /**
     * For rsync on Windows c:\zzz_qs\ becomes /cygdrive/c/zzz_qs/
     * esg_file_util::prep4rsync( $target_dir );
     * @param str $target_dir
     * @return str
     */
    public static function prep4rsync( $target_dir ) {
        if ( App_Sandbox_Env::isWindows() ) {
            if ( ! preg_match( '#^\w\:#si', $target_dir ) ) {
                $target_dir = 'c:' . $target_dir;
            }

            $target_dir = preg_replace( '#^(\w)\:#si', '/cygdrive/$1', $target_dir );
        }

        return $target_dir;
    }

    const RSYNC_DELETE = 2;

    /**
     * Syncs 2 local folders. Do we need to sync files or remote dirs?
     *
     * esg_file_util::rsync( $src_dir, $target_dir );
     *
     * Deletes the files from the destination if they don't exist in the source.
     * esg_file_util::rsync( $src_dir, $target_dir, esg_file_util::RSYNC_DELETE );
     *
     * @param str $src_dir source dir
     * @param str $target_dir target dir
     * @return array
     */
    public static function rsync( $src_dir, $target_dir, $flags = 1 ) {
        $src_dir = esg_file_util::normalizePath( $src_dir );
        $target_dir = esg_file_util::normalizePath( $target_dir );

        if ( empty( $src_dir ) || ! is_dir( $src_dir ) ) {
            throw new esg_file_util_Exception( __METHOD__ . " source folder doesn't exist or it's not a folder." );
        }

        if ( empty( $target_dir ) ) {
            throw new esg_file_util_Exception( __METHOD__ . " target folder is empty." );
        }

        $src_dir = esg_file_util::prep4rsync( $src_dir );
        $src_dir = esg_file_util::addSlash( $src_dir );

        $target_dir = esg_file_util::prep4rsync( $target_dir );
        $target_dir = esg_file_util::addSlash( $target_dir );

        esg_file_util::mkdir( $target_dir ); // Create if necessary

        $rsync_params = array(
            '--checksum', // -c, --checksum              skip based on checksum, not mod-time & size
            '-r', // -r, --recursive             recurse into directories
            '-h', // -h is for human-readable, so the transfer rate and file sizes are easier to read (optional)
            '-v', // -v, --verbose: increase verbosity
            '-W', // -W is for copying whole files only, without delta-xfer algorithm which should reduce CPU load
            '--no-compress', // --no-compress as there's no lack of bandwidth between local devices
            '--links', // copy symlinks as symlinks
            '--times', // preserve modification times
            '--msgs2stderr', // special output handling for debugging
            $src_dir,
            $target_dir,
        );

        // if we have this on windows it would copy all the s*** related to the file.
        // there was a bug and it started syncing the root C drive and now I can't delete any of the
        // recycle bin regardless if I change the owner or not.
        if ( ! App_Sandbox_Env::isWindows() ) {
            array_unshift( $rsync_params, '-a' ); // -a is for archive, which preserves ownership, permissions etc.
        }

        /*
        --del/--delete_during: Deletes files from the destination dir as they are copied (saves memory compared to --delete-before: --delete-before makes a separate scan to look for deleteables)
        --delete: Deletes files in the destination directory if they don't exist in the source directory.
        --delete-before: Delete files in the destination directory before coping file-with-same-name from source directory
        --delete-during: Delete files in the destination directory WHILE copying file-with-same-name from source directory
        --delete-delay: Mark deletes during transfer, but wait until transfer is complete
        --delete-after: Receiver deletes after transfer, not before...If some other part of the rsync moved extra files elsewhere, you'd want this instead of --delete-delay, because --delete-delay decides what it's going to delete in the middle of transfer, whereas --delete-after checks the directory for files that should be deleted AFTER everything is finished.
        --delete-excluded: Deletes files from the destination directory that are explicitly excluded from transferring from the source directory.
         */
        /*
         * --delete-after: delete files on the receiving side be done after the transfer has completed
         * @see http://askubuntu.com/questions/172629/how-do-i-move-all-files-from-one-folder-to-another-using-the-command-line
         * @see http://superuser.com/questions/156664/what-are-the-differences-between-the-rsync-delete-options
         * @see http://serverfault.com/questions/225140/rsync-wont-delete-files-on-destination
         * @see http://unix.stackexchange.com/questions/5451/delete-extraneous-files-from-dest-dir-via-rsync
         * @see http://askubuntu.com/questions/476041/how-do-i-make-rsync-delete-files-that-have-been-deleted-from-the-source-folder
         */
        if ( $flags & self::RSYNC_DELETE ) {
            array_unshift( $rsync_params, '--delete-during' );
        }

        $rsync_res = esg_file_util::exec( 'rsync', $rsync_params );

        return $rsync_res;
    }

    /**
     * Sets permissions to files and folders.
     * It doesn't use our ::exec because 2 commands are used: find and chmod.
     * esg_file_util::chmodFilesFolders();
     * @param str $folder
     * @param str but in octal $files_perm
     * @param str but in octal $folders_perm
     * @see http://superuser.com/questions/91935/how-to-chmod-all-directories-except-files-recursively
     */
    public static function chmodFilesFolders( $folder, $files_perm = '0644', $folders_perm = '0755' ) {
        $status = 0;

        if ( App_Sandbox_Env::isLinux() ) { // root;  // && App_Sandbox_Env::getUID() == 0 may not be detected correctly.
            $folder_esc = esg_file_util::escapeShellArg( $folder );

            // This was the original implementation but
            // the xargs option was more resources effective ... that's what people were saying the forums.
            //$cmd_files = "find $folder_esc -type f -exec chmod $files_perm {} +";

            // to reduce chmod spawning
            $cmd_files = "find $folder_esc -type f -print0 | xargs -0 chmod $files_perm";
            $res1 = `$cmd_files 2>&1`;

            $cmd_folders = "find $folder_esc -type d -print0 | xargs -0 chmod $folders_perm";
            $res2 = `$cmd_folders 2>&1`;

            return array( 'res1' => $res1, 'cmd1' => $cmd_files, 'res2' => $res2, 'cmd2' => $cmd_folders, );
        }

        return '';
    }

    /**
     * reads a file (email tpl) and replaces vars that are passed.
     * returns 'subject', 'message' fileds in an array.
     * esg_file_util::load_email_template( $file, $params = []
     * @staticvar array $files
     * @param str $file
     * @param array $params
     * @return array
     */
    static public function load_email_template( $file, $params = [] ) {
        static $files = [];

        if ( empty( $files[ $file ] ) ) {
            $buff = self::load_file( $file );
            $files[ $file ] = $buff;
        } else {
            $buff = $files[ $file ];
        }

        // First line is subject line
        $lines = preg_split( '#[\r\n]+#si', $buff, 2 );

        if ( count( $lines) < 2 ) {
            $file_esc = esc_attr( $file_esc );
            throw new Exception( "Invalid email template [$file_esc]. Doesn't have proper subject" );
        }

        // ... and the rest is the message.
        $lines = array_map( 'trim', $lines );

        $subject = $lines[0]; // first line is subject
        $message = $lines[1];

        $ctx = [
            'subject' => $subject,
            'message' => $message,
            'params' => $params,
            'method' => __METHOD__,
        ];

        // In case somebody wants to add more merge tags to replace.
        $params = apply_filters( 'orb_filter_load_email_template_merge_tags', $params, $ctx );

        $subject = esg_string_util::replace( $params, $subject );
        $message = esg_string_util::replace( $params, $message );

        $subject = apply_filters( 'orb_limit_filter_subject', $subject, $ctx );
        $message = apply_filters( 'orb_limit_filter_message', $message, $ctx );
        
        $message_html = nl2br( $message );
        $message_html = apply_filters( 'orb_limit_filter_message_html', $message_html, $ctx );

        $rec = [
            'subject' => $subject,
            'message' => $message,
            'message_html' => $message_html,
        ];

        return $rec;
    }
}

class esg_file_util_Exception extends Exception {

}