<?php

if(!class_exists('WC_Magpie_Post')){

    class WC_Magpie_Post {

        public function checkout_session($header,$sessionObj){

            $args = array(
                'headers' => array(
                    "Authorization" => "Basic " . $header,
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                ),
                'body'        => json_encode($sessionObj),
                'method'      => 'POST',
                'data_format' => 'body',
                'httpversion' => '1.1',
            );

            $response = wp_remote_request( "https://pay.magpie.im/api/v2/sessions", $args );
            $res_obj = json_decode($response["body"],true);
            
            $url = "https://pay.magpie.im/api/v2/sessions/".$res_obj['id'];

            return $this->get_payment_url($url,$header);
            
        }

        function get_payment_url($url,$header){

            $args = array(
                'headers'     => array(
                    "Authorization" => "Basic " . $header,
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                ),
                'method'      => 'GET',
                'data_format' => 'body',
                'httpversion' => '1.1',
            );

            $response = wp_remote_get( $url, $args );
            $res_obj = json_decode($response["body"],true);

            $logger = wc_get_logger();
            $logger->info($response['body'],array( 'source' => 'debug-magpie' ));
            $success = array(
                'result' 	=> 'success',
                'redirect'	=> $res_obj["payment_url"]
            );

            return $success;
        }
    }
}

?>