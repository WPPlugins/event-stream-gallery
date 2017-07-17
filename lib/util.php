<?php

class esg_util {
    
    /**
     * 
     * @return type
     * @see esg_util::is_public_area()
     */
    public static function is_public_area() {
        return ! self::is_admin_area();
    }
    
    /**
     * 
     * @return type
     * @see esg_util::is_admin_area()
     */
    public static function is_admin_area() {
        $stat = function_exists( 'is_admin' )
            && is_admin() 
            && ( ! defined('DOING_AJAX') || DOING_AJAX == 0 );
        return $stat;
    }
    
    /**
    * 
    * Case in-sensitive array_search() with partial matches
    * @param string $needle   The string to search for.
    * @param array  $haystack The array to search in.
    *
    * @author Bran van der Meer <branmovic@gmail.com>
    * @since 29-01-2010
    * @see https://gist.github.com/branneman/951847
    */
   public static function array_search($needle, array $haystack) {
       foreach ($haystack as $key => $value) {
           if (false !== stripos($value, $needle)) {
               return $key;
           }
       }
       return false;
   }
   
   /**
    * This special join doesn't add the glue or joining char if the element
    * starts with an html tag. We don't want the glue added to containers/wrapper elements.
    * esg_util::join();
    * @param glue $needle
    * @param array $haystack
    * @return str
    */
   public static function join( $glue, array $arr ) {
       $str = '';

       foreach ( $arr as $value ) {
           $str .= $value;

           // Is this an opening html tag?
           if ( ! preg_match( '#\s*\<\w+#si', $value ) ) {
               $str .= $glue;
           }
       }

       $last_str = substr( $str, (-1) * strlen( $glue ) );

       // Is it ending in the glue?
       if ( $last_str == $glue ) {
           $str = substr( $str, 0, (-1) * strlen( $glue ) );
       }

       return $str;
   }

   /**
    * esg_util::get_embed_code();
    * @param str $url
    * @param array $params
    * @return str
    */
   public static function get_embed_code( $url, array $params = [] ) {
       $defaults = [
           'hd' => 1,
           'rel' => 0, // related vids
           'autoplay' => 0,
           'autohide' => 0,
           'modestbranding' => 1,
       ];
       
       $params = array_merge( $defaults, $params );
       $buff = wp_oembed_get( $url );
       $append_params = http_build_query( $params );

       if ( strpos( $buff, '?' ) === false ) {
           $sep = '?';
       } else {
           $sep = '&';
       }
       
       // https://www.youtube.com/embed/PCwL3-hkKrg?feature=oembed
       $buff = preg_replace( '#(youtube\.com/embed/[\w\-]+[\?\w\=\-]*)#si', "$1$sep$append_params", $buff );
       $buff = "\n<div class='esg_embed'>" . $buff . "</div>\n";

       return $buff;
   }

   /**
    * esg_util::is_ajax_or_cron();
    * @param void
    * @return bool
    */
   public static function is_ajax_or_cron() {
       return defined( 'DOING_CRON' ) || defined( 'DOING_AJAX' );
   }

   /**
    * esg_util::is_cron();
    * @param void
    * @return bool
    */
   public static function is_cron() {
       return defined( 'DOING_CRON' );
   }
}
