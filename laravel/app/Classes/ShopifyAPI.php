<?php

class ShopifyAPI
{
    const MaxRuntime = 120;

    /**
     * Send data to the Shopify's API.
     *
     * @param  string *URL* $url
     * @param  string $password
     * @param  string *JSON* $json_data
     *
     * @return true|false
     */
    public static function putData($url, $password, $json_data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . $password,
            'Content-Length: ' . strlen($json_data)
        ));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($response['error']) && $response['error']) return false;
        return true;
    }

    /**
     * Ask Shopify's API for data, return an array representation of the JSON returned.
     *
     * @param  string *URL* $url
     *
     * @return array
     */
    public static function getDataForURL($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $response;
    }

    /**
     * Create the HTML necessary to populate a Shopify page with the given Instagram data.
     *
     * @param  array $instagram_data
     *
     * @return string *HTML*|null
     */
    public static function createHTMLForInstagramResults($instagram_data)
    {
        $body_inside_table = '';
        $next_url = true;
        $count = 1;
        $tot_count = 1;

        while ($next_url && $tot_count < 14) {
            $next_url = $instagram_data["pagination"]["next_url"];
            if ($instagram_data) {
                foreach ($instagram_data['data'] as $post) {
                    if ($count === 1) $body_inside_table .= "  <tr style='vertical-align: top;'>";
                    $body_inside_table .= "    <td width='33%' style=\"font-family: 'Helvetica Neue', " .
                        "Helvetica, Arial, sans-serif; font-size: 14;border-style:solid;border-color: " .
                        "gray;border-width:1px; border-top:thick solid gray; " .
                        "border-bottom:thick solid gray;vertical-align:top;\">";
                    $body_inside_table .= "<a href='" . $post['link'] . "' target='_blank'><img src='" .
                        str_replace("http://", "https://", $post["images"]["low_resolution"]['url']) . "' /></a>";
                    $body_inside_table .= "<br>" . $post['caption']['text'];
                    $lower_caption = " " . strtolower($post['caption']['text']);
                    $tags = '';
                    foreach ($post['tags'] as $tag) {
                        $this_hashtag = "#" . $tag;
                        if (!strpos($lower_caption, $this_hashtag)) $tags .= $this_hashtag . ", ";
                    }
                    if ($tags) $body_inside_table .= "<br>" . rtrim($tags, ', ');
                    $body_inside_table .= "</td>";
                    if ($count > 2) {
                        $count = 1;
                        $body_inside_table .= "</tr>";
                    } else $count++;
                    if ($tot_count > 14) break;
                    $tot_count++;
                }
            }
            $s = curl_init();
            curl_setopt($s, CURLOPT_URL, $next_url);
            curl_setopt($s, CURLOPT_RETURNTRANSFER, true);
            $instagram_data = json_decode(curl_exec($s), true);
            curl_close($s);
        }

        if ($body_inside_table) {
            $body_html = "<div style='width:884;'>";
            $body_html .= "<table style='width:884;'>";
            $body_html .= $body_inside_table;
            $body_html .= "</table>";
            $body_html .= "</div>";

            return $body_html;
        } else return null;
    }

    /**
     * Replace the given page on a Shopify site with the HTML provided.
     *
     * @param  string *HTML* $body_html
     * @param  int $page_id
     * @param  Site $site
     *
     * @return string true|false
     */
    public static function replaceHTMLOnPageForSite($body_html, $page_id, $site)
    {
        if ($site &&
            $body_html &&
            is_int($page_id) &&
            $site->cfg_array['SHOPIFY_STORE_URL']->typed_value &&
            $site->cfg_array['SHOPIFY_API_KEY']->typed_value &&
            $site->cfg_array['SHOPIFY_PASSWORD']->typed_value
        ) {
            $url = 'https://' . $site->cfg_array['SHOPIFY_API_KEY']->typed_value . ':' .
                $site->cfg_array['SHOPIFY_PASSWORD']->typed_value . '@' .
                $site->cfg_array['SHOPIFY_STORE_URL']->typed_value . '/admin/pages/' .
                $page_id . '.json';

            $json_data = stripslashes(json_encode(array('page' => array('id' => (int)$page_id,
                'body_html' => str_replace('"', '\\"', $body_html)))));

            return self::putData($url, $site->cfg_array['SHOPIFY_PASSWORD']->typed_value, $json_data);
        }
        return false;
    }

    /**
     * Grab posts and HTML necessary to replace the Instagram page on a Shopify site, and then replace it.
     *
     * @param  Site $site
     *
     * @return string *HTML*|false
     */
    public static function replaceInstagramPageOnShopify($site)
    {
        if ($site)
        {
            return self::replaceHTMLOnPageForSite(
                \ShopifyAPI::createHTMLForInstagramResults(\InstagramAPI::getPosts($site)),
                (int)$site->cfg_array['SHOPIFY_IG_PAGE_ID']->typed_value,
                $site);
        }
        return false;
    }

    /**
     * Find all open orders on Shopify for a specific site and return an array of those orders.
     *
     * @param  Site $site
     *
     * @return array
     */
    public static function getListOfOpenOrders($site)
    {
        if ($site)
        {
            $url = $orders_url = 'https://' . $site->cfg_array['SHOPIFY_API_KEY']->typed_value . ":" .
                $site->cfg_array['SHOPIFY_PASSWORD']->typed_value . "@" .
                $site->cfg_array['SHOPIFY_STORE_URL']->typed_value . '/admin/orders.json';
            return self::getDataForURL($url);
        }
        return [];
    }

    /**
     * Find all orders on Shopify updated after a specific date and return an array of those orders.
     *
     * @param  string *DATETIME* $updated_after
     * @param  Site $site
     * @param  int $page
     *
     * @return array
     */
    public static function getOrdersListLastUpdateAfter($updated_after,$site,$page = 1)
    {
        if ($site)
        {
            $url = $orders_url = 'https://' . $site->cfg_array['SHOPIFY_API_KEY']->typed_value . ":" .
                    $site->cfg_array['SHOPIFY_PASSWORD']->typed_value . "@" .
                    $site->cfg_array['SHOPIFY_STORE_URL']->typed_value .
                                                '/admin/orders.json?page=' . (int)$page .
                                                '&limit=250&status=any&updated_at_min=' . $updated_after;
            return self::getDataForURL($url);
        }
        return [];
    }

    /**
     * Get the specifics for a specific Shopify order by the order's ID and return an array of that order.
     *
     * @param  int $order_id
     * @param  Site $site
     *
     * @return array
     */
    public static function getOrderSpecifics($order_id, $site)
    {
        if ($site)
        {
            $url = $orders_url = 'https://' . $site->cfg_array['SHOPIFY_API_KEY']->typed_value . ":" .
                $site->cfg_array['SHOPIFY_PASSWORD']->typed_value . "@" .
                $site->cfg_array['SHOPIFY_STORE_URL']->typed_value . '/admin/orders/' . $order_id . '.json';

            return self::getDataForURL($url);
        }
        return [];
    }

    /**
     * Create an local, denormalized array for a retail order based on the information retruned by Shopify.
     *
     * @param  array $order
     * @param  int $whsl_site
     * @param  int $retail_site
     *
     * @return array
     */
    public static function createOrderInfoArray($order,$whsl_site,$retail_site)
    {
        if ($order['fulfillment_status']) $orders_status = \App\RetailOrdersStatus::findIDByName('Shipped');
        else if ($order['cancelled_at']) $orders_status = \App\RetailOrdersStatus::findIDByName('Cancelled');
        else $orders_status = \App\RetailOrdersStatus::findIDByName('Processing');

        $order_info = [ 'whsl_site_id' => $whsl_site->id,
                        'retail_site_id' => $retail_site->id,
                        'order_id' => $order['id'],
                        'alt_order_id' => $order['name'],
                        'orders_status' => $orders_status,
                        'date_purchased' => date("Y-m-d H:i:s", strtotime($order['created_at'])),
                        'date_last_updated' => date("Y-m-d H:i:s", strtotime($order['updated_at'])),
                        'delivery_name' => (($order['shipping_address']['first_name'] . $order['shipping_address']['last_name']) ?
                                            strtoupper($order['shipping_address']['first_name'] .
                                            ($order['shipping_address']['first_name'] ? ' ' : '') .
                                             $order['shipping_address']['last_name']) :
                                            null),
                        'delivery_company' => ($order['shipping_address']['company'] ?
                                               strtoupper($order['shipping_address']['company']) :
                                               null),
                        'delivery_street_address' => ($order['shipping_address']['address1'] ?
                                                      strtoupper($order['shipping_address']['address1']) :
                                                      null),
                        'delivery_street_address2' => ((isset($order['shipping_address']['address2']) &&
                                                        $order['shipping_address']['address2']) ?
                                                       strtoupper($order['shipping_address']['address2']) :
                                                       null),
                        'delivery_city' => ($order['shipping_address']['city'] ?
                                            strtoupper($order['shipping_address']['city']) :
                                            null),
                        'delivery_postcode' => ($order['shipping_address']['zip'] ?
                                                strtoupper($order['shipping_address']['zip']) :
                                                null),
                        'delivery_state' => ($order['shipping_address']['province'] ?
                                             strtoupper($order['shipping_address']['province']) :
                                             null),
                        'delivery_country' => ($order['shipping_address']['country'] ?
                                               strtoupper($order['shipping_address']['country']) :
                                               null),
                        'billing_name' => (($order['billing_address']['first_name'] . $order['billing_address']['last_name']) ?
                                           strtoupper($order['billing_address']['first_name'] .
                                           ($order['billing_address']['first_name'] ? ' ' : '') .
                                           $order['billing_address']['last_name']) :
                                           null),
                        'billing_company' => ($order['billing_address']['company'] ?
                                              strtoupper($order['billing_address']['company']) :
                                              null),
                        'billing_street_address' => ($order['billing_address']['address1'] ?
                                                     strtoupper($order['billing_address']['address1']) :
                                                     null),
                        'billing_street_address2' => ((isset($order['billing_address']['address2']) &&
                                                       $order['billing_address']['address2']) ?
                                                      strtoupper($order['billing_address']['address2']) :
                                                      null),
                        'billing_city' => ($order['billing_address']['city'] ?
                                           strtoupper($order['billing_address']['city']) :
                                           null),
                        'billing_postcode' => ($order['billing_address']['zip'] ?
                                               strtoupper($order['billing_address']['zip']) :
                                               null),
                        'billing_state' => ($order['billing_address']['province'] ?
                                            strtoupper($order['billing_address']['province']) :
                                            null),
                        'billing_country' => ($order['billing_address']['country'] ?
                                              strtoupper($order['billing_address']['country']) :
                                              null),
                        'customers_id' => ($order['customer']['id'] ?
                                           $order['customer']['id'] :
                                           null),
                        'customers_telephone' => ($order['billing_address']['phone'] ?
                                                  $order['billing_address']['phone'] :
                                                  null),
                        'customers_email_address' => ($order['customer']['email'] ?
                                                      strtolower($order['customer']['email']) :
                                                      null),
                        'payment_method' => 'Credit Card',
                        'cc_type' => ((isset($order['payment_details']) &&
                                       isset($order['payment_details']['credit_card_company']) &&
                                       $order['payment_details']['credit_card_company']) ?
                                      strtoupper($order['payment_details']['credit_card_company']) :
                                      ((isset($order['payment_gateway_names']) &&
                                        $order['payment_gateway_names'][0]) ?
                                       strtoupper($order['payment_gateway_names'][0]) :
                                       null)),
                        'cc_number' => ((isset($order['payment_details']) &&
                                         isset($order['payment_details']['credit_card_number']) &&
                                         $order['payment_details']['credit_card_number']) ?
                                        'XXXX' . substr($order['payment_details']['credit_card_number'], -4, 4) :
                                        null),
                        'date_shipped' => ((isset($order['fulfillments']) &&
                                            isset($order['fulfillments'][0]) &&
                                            $order['fulfillments'][0]['status'] == 'success') ?
                                           date("Y-m-d H:i:s", strtotime($order['fulfillments'][0]['created_at'])) :
                                           null) ];

        if (isset($order['note_attributes']))
        {
            $heard_about = '';

            foreach($order['note_attributes'] as $note_attribute)
            {
                if ($note_attribute['name'] == 'how-did-you-hear-about-us')
                {
                    $heard_about .= $note_attribute['value'] . "::";
                }
            }

            $heard_about = rtrim($heard_about,"::");

            if ($heard_about) $order_info['heard_about'] = $heard_about;
        }

        return $order_info;
    }

    /**
     * Create an local, denormalized array for a retail order's product based on the information retruned by Shopify.
     *
     * @param  array $order_item
     * @param  int $retail_order_id
     *
     * @return array
     */
    public static function createOrderProductInfoArray($order_item,$retail_order_id)
    {
        $product_info = [];

        if (isset($order_item['quantity']) && (int)$order_item['quantity'] && $retail_order_id)
        {
            $product_info = [ 'retail_order_id' => $retail_order_id,
                              'products_uniq_id' => (isset($order_item['id']) ?
                                                     $order_item['id'] :
                                                     null),
                              'products_id' => (isset($order_item['product_id']) ?
                                                $order_item['product_id'] :
                                                null),
                              'products_model' => (isset($order_item['sku']) ?
                                                   $order_item['sku'] :
                                                   null),
                              'products_name' => (isset($order_item['title']) ?
                                                  $order_item['title'] . ($order_item['variant_title'] ?
                                                                   ' - ' . $order_item['variant_title'] :
                                                            '') :
                                                  ''),
                              'products_quantity' => ((isset($order_item['quantity']) &&
                                                      (int)$order_item['quantity']) ?
                                                      (int)$order_item['quantity'] :
                                                       0),
                              'products_price' => ((isset($order_item['quantity']) &&
                                                   (float)$order_item['price'] > 0) ?
                                                   (float)$order_item['price'] :
                                                    0) ];

            $product_info['final_price'] = $product_info['products_price'];
        }

        return $product_info;
    }

    /**
     * Attach all Shopify products found to a local retail order.
     *
     * @param  array $order_items
     * @param  int $retail_order_id
     *
     * @return array
     */
    public static function findAndUpdateOrInsertAllProductsForOrder($order_items,$retail_order_id)
    {
        if (count($order_items))
        {
            $orders_products_array = [];

            foreach($order_items as $order_item)
            {
  //              var_dump($order_item);
                $single_orders_product_array = self::createOrderProductInfoArray($order_item, $retail_order_id);

                if (count($single_orders_product_array)) $orders_products_array[] = $single_orders_product_array;
            }
            if(count($orders_products_array))
                \App\RetailOrdersProduct::findAndUpdateOrInsertAll($orders_products_array,$retail_order_id);
      //      $orders_product = self::createOrderProductInfoArray($order_item,$retail_order_id);
        }
    }

    /**
     * Attach all Shopify order's totals and discounts to a local retail order.
     *
     * @param  array $order_items
     * @param  int $retail_order_id
     *
     * @return array
     */
    public static function findAndUpdateOrInsertAllTotalsAndDiscounts($order,$retail_order_id)
    {
        $discount_codes = [];
        $totals_array = [];
        $totals_array['ot_subtotal'] = 0;
        $totals_array['ot_total'] = 0;
        $totals_array['ot_shipping'] = 0;
        $subtotal_refunded = 0;
        $total_refund_amount = 0;
        $ordered_items = [];
        $line_items = [];
        $refund_amount = 0;
        $discount_amount = 0;
        $subtotal_amount = 0;
        $shipping_amount = 0;
        $tax_amount = 0;


        if (isset($order['total_tax']) && (float)$order['total_tax'] > 0)
        {
            $tax_amount = (float)$order['total_tax'];
        }

        if (count($order['line_items']))
        {
            $subtotal_ordered = 0;
            $subtotal_shipped = 0;

            foreach($order['line_items'] as $line_item)
            {
                $ordered_items[$line_item['id']] = $line_item;
                $subtotal_ordered += (float)$line_item['price'] * $line_item['quantity'];
            }

            if (count($order['fulfillments']))
            {
                foreach ($order['fulfillments'] as $fulfillment)
                {
                    if (count($fulfillment['line_items']))
                    {
                        foreach($fulfillment['line_items'] as $line_item)
                        {
                            $subtotal_shipped += (float)$line_item['price'] * $line_item['quantity'];
                        }
                    }
                }
            }

            if ($subtotal_shipped)  $subtotal_amount += $subtotal_shipped;
            else $subtotal_amount = $subtotal_ordered;

        }

        if (count($order['refunds']))
        {
            foreach($order['refunds'] as $refund)
            {
                if (count($refund['transactions']))
                {
                    foreach ($refund['transactions'] as $transaction)
                    {
                        $total_refund_amount -= (float)$transaction['amount'];
                    }

                    if (isset($refund['refund_line_items']) && count($refund['refund_line_items']))
                    {
                        foreach ($refund['refund_line_items'] as $refund_line_item)
                        {
                            $subtotal_refunded -= $refund_line_item['line_item']['price'] * ($refund_line_item['line_item']['quantity'] - $refund_line_item['line_item']['fulfillable_quantity']);
                        }
                    }
                }
            }

            $refund_amount = $total_refund_amount - $subtotal_refunded;

            if ($refund_amount < 0)
            {
                $discount_codes[] = [ 'coupons_id' => 'refund',
                                      'value' => $refund_amount,
                                      'type' => 'refund' ];
            }
        }

        if (count($order['discount_codes']))
        {
            foreach($order['discount_codes'] as $discount)
            {
                $discount_amount -= (float)$discount['amount'];

                if (!isset($discount_codes[strtolower($discount['code'])]))
                {
                    $discount_codes[strtolower($discount['code'])] = [ 'coupons_id' => strtolower($discount['code']),
                                                                       'value' => ((float)$discount['amount'] * -1),
                                                                       'type' => ($discount['type'] ? $discount['type'] : null) ];
                }
                else
                {
                    $discount_codes[strtolower($discount['code'])]['value'] -= (float)$discount['amount'];

                }

            }
        }

        if (count($order['shipping_lines']))
        {
            foreach($order['shipping_lines'] as $shipping_line)
            {
                $shipping_amount += (float)$shipping_line['price'];
            }
        }

        $totals_array['ot_subtotal'] = $subtotal_amount;
        $totals_array['ot_total'] = $subtotal_amount + $discount_amount + $refund_amount + $tax_amount + $shipping_amount;
        $totals_array['ot_shipping'] = $shipping_amount;

        if ($tax_amount) $totals_array['ot_tax'] = $tax_amount;

        if (($discount_amount + $refund_amount) < 0) $totals_array['ot_discount_coupon'] = $discount_amount + $refund_amount;

        \App\RetailOrdersTotal::findAndUpdateOrInsertAll($totals_array,$retail_order_id);

        if (count($discount_codes)) \App\RetailOrdersDiscount::findAndUpdateOrInsertAll($discount_codes,$retail_order_id);
    }

    public static function findAndUdateOrInsertOrders($orders,$whsl_site)
    {
        $stop = false;
        $retail_site = \App\RetailSite::findByName('Shopify');

        foreach($orders as $order)
        {
            if (!$order['cancelled_at'])
            {
                $order_array = self::createOrderInfoArray($order, $whsl_site, $retail_site);

                $retail_order = \App\RetailOrder::insertOrUpdate($order_array);

                self::findAndUpdateOrInsertAllProductsForOrder($order['line_items'],$retail_order->id);

                self::findAndUpdateOrInsertAllTotalsAndDiscounts($order,$retail_order->id);
            }
            else if ($order['cancelled_at'])
            {
                $order_obj = \App\RetailOrder::findByOrderIdSiteRetailSite($order['id'],
                                                                           $whsl_site->id,
                                                                           $retail_site->id);

                if ($order_obj)
                {
                    $order_obj->orders_status = \App\RetailOrdersStatus::findIDByName('Cancelled');
                    $order_obj->date_last_updated = date("Y-m-d H:i:s",strtotime($order['updated_at']));
                    $order_obj->date_canceled = date("Y-m-d H:i:s",strtotime($order['cancelled_at']));
                    $order_obj->save();
                }
            }
        }
    }

    public static function findAndUpdateOrInsertSpecificOrder($order_id,$whsl_site)
    {
        $order = self::getOrderSpecifics($order_id,$whsl_site);

        if ($order && isset($order['order']) && is_array($order['order']))
        {
            if ($whsl_site) self::findAndUdateOrInsertOrders([ $order['order'] ], $whsl_site);
        }

        $retail_site = \App\RetailSite::findByName('Shopify');

        \App\RetailOrdersSKUTranslation::translateRetailSiteSKUs($whsl_site->id,$retail_site->id);
    }

    public static function findAndUpdateOrInsertAllOpenOrders($whsl_site)
    {
        set_time_limit(self::MaxRuntime);

        if ($whsl_site)
        {
            $orders_results = self::getListOfOpenOrders($whsl_site);

            if($orders_results &&
               isset($orders_results['orders']) &&
               count($orders_results['orders']))
            {
                self::findAndUdateOrInsertOrders($orders_results['orders'], $whsl_site);
            }

            $retail_site = \App\RetailSite::findByName('Shopify');

            \App\RetailOrdersSKUTranslation::translateRetailSiteSKUs($whsl_site->id,$retail_site->id);
        }
    }


    public static function findAndUpdateOrInsertAllOrdersUpdatedAfter($updated_after,$whsl_site)
    {
        set_time_limit(self::MaxRuntime);

        if ($whsl_site)
        {
            $page = 1;

            $orders_results = self::getOrdersListLastUpdateAfter($updated_after,$whsl_site,$page);

            while ($orders_results &&
                   isset($orders_results['orders']) &&
                   count($orders_results['orders']))
            {
                self::findAndUdateOrInsertOrders($orders_results['orders'], $whsl_site);

                $page++;
                $orders_results = self::getOrdersListLastUpdateAfter($updated_after,$whsl_site,$page);
            }

            $retail_site = \App\RetailSite::findByName('Shopify');

            \App\RetailOrdersSKUTranslation::translateRetailSiteSKUs($whsl_site->id,$retail_site->id);
        }
    }

    public static function updateAllOrderDataFromListForASite($order_ids,$site_id,$site = null)
    {
        if (($site || is_int($site_id)) && is_array($order_ids) && count($order_ids))
        {
            if (!$site) $site = \App\Site::find($site_id);

            foreach($order_ids as $order_id)
            {
                self::getOrderSpecifics($order_id,$site);
            }
        }
        return false;
    }
}