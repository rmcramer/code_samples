<?php namespace App;

use Illuminate\Support\Facades\Session;

class AuthorizeNetAPI
{

    /**
     * Create the array passed to Authorize.net to chanrge a credit card,
     *    given the order and charge information passed.
     *
     * @param  \App\Order $order
     * @param  array $request_values
     *
     * @return array
     */
    public static function populateAuthorizeNetPackage($order, $request_values)
    {
        $post_values = [
            // the API Login ID and Transaction Key must be replaced with valid values
            "x_version" => "3.1",
            "x_delim_data" => "TRUE",
            "x_delim_char" => "|",
            "x_relay_response" => "FALSE",
            "x_type" => "AUTH_CAPTURE",
            "x_method" => "CC",
            "x_card_num" => $request_values['card_number'],
            "x_card_code" => $request_values['cvv'],
            "x_exp_date" => $request_values['expiry_month'] . substr($request_values['expiry_year'], -2),
            "x_amount" => sprintf("%01.2f", $request_values['cc_amount']),
            "x_invoice_num" => $order->orders_id,
            "x_po_num" => '',
            "x_freight" => sprintf("%01.2f", $order->total_objects['ot_shipping']->value),
            "x_tax" => "0.00",
            "x_description" => "Wholesale Transaction",
            "x_cust_id" => $order->customers_id,
            "x_company" => $order->billing_company,
            "x_address" => $order->billing_street_address,
            "x_city" => $order->billing_city,
            "x_state" => $order->billing_state,
            "x_zip" => $request_values['postal_cd'],
            "x_country" => "US",
            "x_ship_to_company" => $order->billing_company,
            "x_ship_to_address" => $order->billing_street_address,
            "x_ship_to_city" => $order->billing_city,
            "x_ship_to_state" => $order->billing_state,
            "x_ship_to_zip" => $request_values['postal_cd'],
            "x_ship_to_country" => "US"
        ];

        return $post_values;
    }

    /**
     * Given the credit card information inputted (in the form of a request),
     *    ask Authorize.net to charge the card for the specific order. Return
     *    an array of information from Authorize.net as to the success or failure
     *    of the credit card run.
     *
     * @param  Order $order
     * @param  Request $request
     *
     * @return array
     */
    public static function processAuthorizeNetCCPaymentReturnErrors($request)
    {
        $return_array['failure'] = ['RESPONSE' => 0,
                                    'RESPONSE_SUB' => 0,
                                    'REASON' => 'No order found',
                                    'REASON_TXT' => 'No order found' ];

        $order = \App\Orders::find($request->orders_id);

        if ($order)
        {
            $post_values = self::populateAuthorizeNetPackage($order, $request->all());

            if (isset($site->cfg_array['AUTHNET_X_TRANS_TYPE']))
                $post_values['x_type'] = $site->cfg_array['AUTHNET_X_TRANS_TYPE']->typed_value;

            $site = \App\Site::find($order->site_id);

            if ($site &&
                isset($site->cfg_array['AUTHNET_PROD_MODE']) &&
                getenv('AUTHNET_X_URL') &&
                isset($site->cfg_array['AUTHNET_X_LOGIN']) &&
                isset($site->cfg_array['AUTHNET_X_TRAN_KEY']) &&
                $site->cfg_array['AUTHNET_PROD_MODE']->typed_value)
            {
                $post_url = getenv('AUTHNET_X_URL');
                $post_values['x_login'] = $site->cfg_array['AUTHNET_X_LOGIN']->typed_value;
                $post_values['x_tran_key'] = $site->cfg_array['AUTHNET_X_TRAN_KEY']->typed_value;
            }
            else if ($site &&
                     isset($site->cfg_array['AUTHNET_PROD_MODE']) &&
                     getenv('AUTHNET_TST_X_URL') &&
                     isset($site->cfg_array['AUTHNET_TST_X_LOGIN']) &&
                     isset($site->cfg_array['AUTHNET_TST_X_TRAN_KEY']) &&
                     !$site->cfg_array['AUTHNET_PROD_MODE']->typed_value)
            {
                $post_url = getenv('AUTHNET_TST_X_URL');
                $post_values['x_login'] = $site->cfg_array['AUTHNET_TST_X_LOGIN']->typed_value;
                $post_values['x_tran_key'] = $site->cfg_array['AUTHNET_TST_X_TRAN_KEY']->typed_value;
            }
            else
            {
                $return_array['failure']['REASON'] = 'Environment Not Set up correctly';
                $return_array['failure']['REASON_TXT'] = 'Environment Not Set up correctly';
                return $return_array;
            }

            // This section takes the input fields and converts them to the proper format
            // for an http post.  For example: "x_login=username&x_tran_key=a1B2c3D4"
            $post_string = "";
            foreach ($post_values as $key => $value) {
                $post_string .= "$key=" . urlencode($value) . "&";
            }
            $post_string = rtrim($post_string, "& ");

            // This sample code uses the CURL library for php to establish a connection,
            // submit the post, and record the response.
            // If you receive an error, you may want to ensure that you have the curl
            // library enabled in your php configuration
            $request = curl_init($post_url); // initiate curl object
            curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
            curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
            curl_setopt($request, CURLOPT_POSTFIELDS, $post_string); // use HTTP POST to send form data
            curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment this line if you get no gateway response.
            $post_response = curl_exec($request); // execute curl post and store results in $post_response

            // additional options may be required depending upon your server configuration
            // you can find documentation on curl options at http://www.php.net/curl_setopt
            curl_close($request); // close curl object

            // This line takes the response and breaks it into an array using the specified delimiting character
            $response_array = explode($post_values["x_delim_char"], $post_response);

            // The results are output to the screen in the form of an html numbered list.

            $response_code = $response_array[0];
//            $response_subcode = $response_array[1];
//            $reason_code = $response_array[2];
//            $reason_txt = $response_array[3];
//            $auth_code = $response_array[4];
//            $trans_id = $response_array[6];

            $return_array = ['success' => null, 'failure' => null];

            switch ($response_code) {
                case '1' :
                    $return_array['success'] =
                        ['CC_TYPE' => $response_array[51],
                            'CC_NUM' => $response_array[50],
                            'AMT' => $response_array[9],
                            'AUTH' => $response_array[4],
                            'TRANS' => $response_array[6]];
                    break;
                default  :
                    $return_array['failure'] =
                        ['RESPONSE' => $response_code,
                            'RESPONSE_SUB' => $response_array[1],
                            'REASON' => $response_array[2],
                            'REASON_TXT' => $response_array[3]];
                    break;
            }
//            $cc_err_msg = 'Unknown Error';
//            switch ($response_code) {
//                case '2':
//                    $cc_err_msg = 'This transaction has been declined. ';
//                    break;
//                case '3':
//                    $cc_err_msg = 'There has been an error processing this transaction. ';
//                    break;
//                case '4':
//                    $cc_err_msg = 'This transaction is being held for review. ';
//                    break;
//            }

        }
        return $return_array;
    }
}