<?php

class esg_mailer extends esg_singleton {
    /**
     *
     * @param array $params
     */
    public function send( $params = [] ) {
        $host = empty($_SERVER['HTTP_HOST']) ? '' : str_ireplace('www.', '', $_SERVER['HTTP_HOST']);
        
        $to = $params['to'];
        $subject = $params['subject'];
        
        $params['cc'] = empty( $params['cc'] ) ? [] : (array) $params['cc'];
        $params['bcc'] = empty( $params['bcc'] ) ? [] : (array) $params['bcc'];

        if ( stripos( $to, ESG_ADMIN_EMAIL ) === false ) { // we're not emailing the admin so we'll add him to bcc
            $params['bcc'][] = ESG_ADMIN_EMAIL;
        }

        $headers = array();
        $headers[] = sprintf( "From: %s <%s>", ESG_MAILER_FROM_NAME, ESG_MAILER_FROM_EMAIL );
        $headers[] = sprintf( "Reply-To: %s <%s>", ESG_MAILER_REPLY_TO_NAME, ESG_MAILER_REPLY_TO_EMAIL ); // I hate no-reply emails!
        $headers[] = sprintf( "X-QS-APP-HOST: %s", $host );

        if ( ! empty( $params['message_html'] ) ) {
            $message = $params['message_html'];
            $headers[] = sprintf( "Content-type: text/html; charset=%s", get_bloginfo( 'charset' ) );
            //add_filter( 'wp_mail_content_type', [ $this, 'set_content_type' ] );
        } else {
            $message = $params['message'];
        }

        foreach ( $params['cc'] as $email ) {
            $headers[] = sprintf( "Cc: %s", $email );
        }
        
        foreach ( $params['bcc'] as $email ) {
            $headers[] = sprintf( "Bcc: %s", $email );
        }
        
        $attachments = array();
        $mail_sent = wp_mail( $to, $subject, $message, $headers, $attachments );

        if ( ! empty( $params['message_html'] ) ) {
            //remove_filter( 'wp_mail_content_type', [ $this, 'set_content_type' ] );
        }

        $mail_log_file = esg_log::getCurrentLogDir() . '/mail/mail_' . date('Y-m-d') . '.log';
        $buff = json_encode(array_merge( $params, [
            'date' => date( 'r' ),
            'mail_sent' => $mail_sent,
        ] ) ) . "\n";
        
        esg_file_util::write( $mail_log_file, $buff, esg_file_util::FILE_APPEND );

        return $mail_sent;
    }

    function set_content_type() {
        return "text/html";
    }
}