<?php

if(!class_exists('Magpie_Post')){

    class Magpie_Post {

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

            $logger = wc_get_logger();
            $logger->info(json_encode($args),array( 'source' => 'payload-checkout-magpie' ));

            $response = wp_remote_request( "https://pay.magpie.im/api/v2/sessions", $args );

            $logger->info(json_encode($response),array( 'source' => 'response-checkout-magpie' ));

            if(!is_wp_error($response)){

                $res_obj = json_decode($response["body"],true);
            
                $url = "https://pay.magpie.im/api/v2/sessions/".$res_obj['id'];
    
                return $this->get_payment_url($url,$header);

            }else{
                $logger = wc_get_logger();
                $logger->info($response->get_error_message(),array( 'source' => 'debug-magpie' ));
            }
            
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

            $logger = wc_get_logger();
            $logger->info(json_encode($args),array( 'source' => 'payload-payment-magpie' ));

            $response = wp_remote_get( $url, $args );

            $logger->info(json_encode($response),array( 'source' => 'response-payment-magpie' ));

            if(!is_wp_error($response)){
                $res_obj = json_decode($response["body"],true);


                
                $success = array(
                    'result' 	=> 'success',
                    'redirect'	=> $res_obj["payment_url"]
                );

                return $success;
            }else{
                $logger = wc_get_logger();
                $logger->info($response->get_error_message(),array( 'source' => 'debug-magpie' ));
            }
        }

        function create_customer($header,$customerObj){

            $args = array(
                'headers' => array(
                    "Authorization" => "Basic " . $header,
                    "Content-Type" => "application/json",
                    "Accept" => "application/json",
                ),
                'body'        => json_encode($customerObj),
                'method'      => 'POST',
                'data_format' => 'body',
                'httpversion' => '1.1',
            );

            $logger = wc_get_logger();
            $logger->info(json_encode($args),array( 'source' => 'payload-customer-magpie' ));

            $response = wp_remote_request( "https://api.magpie.im/v2/customers/", $args );

            $logger->info(json_encode($response),array( 'source' => 'response-customer-magpie' ));

            if(!is_wp_error($response)){
                $res_obj = json_decode($response["body"],true);
                return $res_obj['id'];
            
            }else{
                $logger = wc_get_logger();
                $logger->info($response->get_error_message(),array( 'source' => 'debug-magpie' ));
            }

            
        }
    }

    }


?>