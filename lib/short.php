<?php

/**
 * borrowed from qs short.
 */
class esg_short {
    /**
    * A nice shorting class based on Ryan Charmley's suggestion see the link on stackoverflow below.
    * @author Svetoslav Marinov (Slavi) | http://orbisius.com
    * @see https://github.com/orbisius/
    * @see http://stackoverflow.com/questions/742013/how-to-code-a-url-shortener/10386945#10386945
    */

    /**
     * Explicitely omitted: i, o, 1, 0 because they are confusing. Also use only lowercase ... as
     * dictating this over the phone might be tough.
     * @var string
     */
    private static $dictionary = "abcdfghjklmnpqrstvwxyz23456789";
    private static $code_starts_with_letter = true; // some short links may start with a number

    /**
     * Gets ID and converts it into a string.
     * esg_short::encode();
     * @param int $id
     * @param int $to_upper_case default 1 - returned string is uppercased to be more readable.
     */
    static public function encode($id, $to_upper_case = 0) {
        $id = preg_replace('#\D#', '', $id); // rm non digits

        if (empty($id)) {
            return '';
        }

//        $id = intval($id);
        $id = sprintf("%d", $id); // convert to INT. Hopefully this won't cause an overflow

        $str_id = '';
        $dictionary_array = str_split(self::$dictionary);
        $base = count($dictionary_array);

        while ($id > 0) {
            $rem = $id % $base;
            $id = ($id - $rem) / $base;
            $str_id .= $dictionary_array[$rem];
        }

        // just to make sure it is a letter num combo
        if ( self::$code_starts_with_letter ) {
            $str_id = 'q' . $str_id;
        }

        if ($to_upper_case) {
            $str_id = strtoupper($str_id);
        }

        return $str_id;
    }

    /**
     * Converts /abc into an integer ID
     * esg_short::decode();
     * @param string
     * @return int $id
     */
    static public function decode($str_id) {
        $str_id = preg_replace('#[^a-z0-9]#si', '', $str_id); // rm non alphanum

        if (empty($str_id)) {
            return '';
        }

        $id = 0;

        if ( self::$code_starts_with_letter ) {
            $str_id = substr($str_id, 1); // skip 1st char because it is Q
        }

        $str_id = strtolower($str_id);

        $id_ar = str_split($str_id);
        $dictionary_array = str_split(self::$dictionary);
        $base = count($dictionary_array);

        for ($i = count($id_ar); $i > 0; $i--) {
            $id += array_search($id_ar[$i - 1], $dictionary_array) * pow($base, $i - 1);
        }

        return $id;
    }
}