<?php

class esg_html_util {
    /**
     * esg_html_util::html_select();
     *
     * @param type $name
     * @param type $sel
     * @param type $options
     * @param type $attr
     * @return string
     */
    public static function html_select($name = '', $sel = null, $options = array(), $attr = '') {
        if ( ! preg_match( '#id=#si', $attr ) ) {
            $attr .= sprintf( ' id="%s" ', esc_attr($name) );
        }

        $html = "\n" . '<select name="' . esc_attr($name) . '" ' . $attr . '>' . "\n";

        foreach ($options as $key => $label) {
            $label = esc_html( $label );
            $selected = $sel == $key ? ' selected="selected"' : '';
            $html .= "\t<option value='$key' $selected>$label</option>\n";
        }

        $html .= '</select>';
        $html .= "\n";

        return $html;
    }

    /**
     * esg_html_util::strip_some_html($buff);
     * @param str $buff
     * @return str
     */
    public static function strip_some_html($buff) {
        $buff = strip_tags($buff, '<p><a><img><div><ul><li><ol><strong><br><span><h1><h2><h3><h4><h5>');
        $buff = trim($buff);
        return $buff;
    }
    
    /**
     * esg_html_util::generate_embed_code($buff);
     * @param type $code
     * @return type
     */
    public static function generate_embed_code( $code ) {
        $buff = sprintf( '<input type="text" readonly="readonly" style="width:100%%" value="%s" class="uk-alert uk-alert-success app_select_on_focus" />', 
            esc_attr( $code ) );
        return $buff;
    }
    
}
