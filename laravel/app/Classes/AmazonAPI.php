<?php
/*******************************************************************************
 * Copyright 2009-2015 Amazon Services. All Rights Reserved.
 * Licensed under the Apache License, Version 2.0 (the "License");
 *
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at: http://aws.amazon.com/apache2.0
 * This file is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
 * CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 *******************************************************************************
 * PHP Version 5
 * @category Amazon
 * @package  Marketplace Web Service Orders
 * @version  2013-09-01
 * Library Version: 2015-06-18
 * Generated: Thu Jun 18 19:28:12 GMT 2015
 */
require_once 'Amazon/MarketplaceWebServiceOrders/Client.php';
require_once 'Amazon/MarketplaceWebServiceOrders/Model/GetOrderRequest.php';
require_once 'Amazon/MarketplaceWebServiceOrders/Model/ListOrdersRequest.php';
require_once 'Amazon/MarketplaceWebServiceOrders/Model/ListOrderItemsRequest.php';
require_once 'Amazon/MarketplaceWebServiceOrders/Model/ListOrdersByNextTokenRequest.php';


class AmazonAPI
{
    const RequestThrottledDelay = 120; // 120 = 2 minutes
    const MaxRuntime = 120;

    /**
     * Create a new Amazon order service instance based on the site passed.
     *
     * @param  Site $site
     *
     * @return MarketplaceWebServiceOrders_Client $service
     */
    public static function getOrdersService($site)
    {
        $config = array(
            'ServiceURL' => getenv('AMAZON_MWSORDERS_URL'),
            'ProxyHost' => null,
            'ProxyPort' => -1,
            'ProxyUsername' => null,
            'ProxyPassword' => null,
            'MaxErrorRetry' => 3,
        );

        $service = new MarketplaceWebServiceOrders_Client(
            $site->cfg_array['AMAZON_AWS_ACCESS_KEY_ID']->typed_value,
            $site->cfg_array['AMAZON_SECRET_KEY']->typed_value,
            getenv('AMAZON_APP_NAME'),
            getenv('AMAZON_APP_VERSION'),
            $config);

        return $service;
    }

    /**
     * Invoke specific web service method passed, get response, process response,
     *    and return array of responses.
     *
     * @param  MarketplaceWebServiceOrders_Interface $service
     * @param  MarketplaceWebServiceOrders_Model_GetOrderRequest $request
     * @param  string $method_name
     *
     * @return array
     */
    public static function invokeOrderMethod(MarketplaceWebServiceOrders_Interface $service, $request, $method_name)
    {
        try {
            $response = $service->$method_name($request);

            $dom = new DOMDocument();
            $dom->loadXML($response->toXML());
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            return \XML2Array::createArray($dom->saveXML());

        } catch (MarketplaceWebServiceOrders_Exception $ex) {
            return \XML2Array::createArray($ex->getXML());
        }
    }

    /**
     * Get responses for all orders for the list of specific order IDs passed and return them.
     *
     * @param  array $orderIDs
     * @param  int $site_id
     * @param  Site $site
     *
     * @return array
     */
    public static function getOrders($orderIDs, $site_id, $site = null)
    {
        if ($site || is_int($site_id)) {
            if (!$site) $site = \App\Site::find($site_id);

            $request = new MarketplaceWebServiceOrders_Model_GetOrderRequest();
            $request->setSellerId($site->cfg_array['AMAZON_SELLER_ID']->typed_value);
            $request->setAmazonOrderId($orderIDs);

            return self::invokeOrderMethod(self::getOrdersService($site), $request, 'GetOrder');

        }
    }

    /**
     * Get responses for all orders created after a certain date and return them.
     *
     * @param  string *DATETIME* $createdAfterDate
     * @param  int $site_id
     * @param  Site $site
     *
     * @return array
     */
    public static function getOrdersListCreatedAfter($createdAfterDate, $site_id, $site = null)
    {
        if ($createdAfterDate && ($site || is_int($site_id)))
        {
            if (!$site) $site = \App\Site::find($site_id);

            $request = new MarketplaceWebServiceOrders_Model_ListOrdersRequest();
            $request->setSellerId($site->cfg_array['AMAZON_SELLER_ID']->typed_value);
            $request->setMarketplaceId($site->cfg_array['AMAZON_MARKETPLACE_ID']->typed_value);
            $request->setCreatedAfter($createdAfterDate);

            return self::invokeOrderMethod(self::getOrdersService($site), $request, 'ListOrders');
        }

    }

    /**
     * Get responses for all orders last updated after a certain date and return them.
     *
     * @param  string *DATETIME* $lastUpdatedAfterDate
     * @param  int $site_id
     * @param  Site $site
     *
     * @return array
     */
    public static function getOrdersListLastUpdateAfter($lastUpdatedAfterDate, $site_id, $site = null)
    {
        if ($lastUpdatedAfterDate && ($site || is_int($site_id)))
        {
            if (!$site) $site = \App\Site::find($site_id);

            $request = new MarketplaceWebServiceOrders_Model_ListOrdersRequest();
            $request->setSellerId($site->cfg_array['AMAZON_SELLER_ID']->typed_value);
            $request->setMarketplaceId($site->cfg_array['AMAZON_MARKETPLACE_ID']->typed_value);
            $request->setLastUpdatedAfter($lastUpdatedAfterDate);

            return self::invokeOrderMethod(self::getOrdersService($site), $request, 'ListOrders');
        }

    }

    /**
     * Get responses for a specific order and return them.
     *
     * @param  int $orderID
     * @param  int $site_id
     * @param  Site $site
     *
     * @return array
     */
    public static function getOrderItems($orderID, $site_id, $site = null)
    {
        if ($site || is_int($site_id)) {
            if (!$site) $site = \App\Site::find($site_id);

            $request = new MarketplaceWebServiceOrders_Model_ListOrderItemsRequest();
            $request->setSellerId($site->cfg_array['AMAZON_SELLER_ID']->typed_value);
            $request->setAmazonOrderId($orderID);

            return self::invokeOrderMethod(self::getOrdersService($site), $request, 'ListOrderItems');

        }
    }

    /**
     * Get the next group of orders based on the nextToken passed and return them.
     *
     * @param  string $nextToken
     * @param  int $site_id
     * @param  Site $site
     *
     * @return array
     */
    public static function getOrdersListByNextToken($nextToken, $site_id, $site = null)
    {
        if ($site || is_int($site_id)) {
            if (!$site) $site = \App\Site::find($site_id);

            $request = new MarketplaceWebServiceOrders_Model_ListOrdersByNextTokenRequest();
            $request->setSellerId($site->cfg_array['AMAZON_SELLER_ID']->typed_value);
            $request->setNextToken($nextToken);

            return self::invokeOrderMethod(self::getOrdersService($site), $request, 'ListOrdersByNextToken');

        }
    }

    /**
     * Get the order info from Amazon (passed as order), normalize it,
     *    and return the normalized version of the order.
     *
     * @param  array $order
     * @param  int $whsl_site_id
     * @param  int $retail_site_id
     *
     * @return array
     */
    public static function createOrderInfoArray($order,$whsl_site_id,$retail_site_id)
    {

        $order_info = [];
        $orders_status = \App\RetailOrdersStatus::findIDByName($order['OrderStatus']);
        if (!$orders_status) $orders_status = \App\RetailOrdersStatus::findIDByName('Processing');

        if (isset($order['ShippingAddress']) && $whsl_site_id && $retail_site_id)
        {
            $order_info = [ 'whsl_site_id' => $whsl_site_id,
                            'retail_site_id' => $retail_site_id,
                            'order_id' => $order['AmazonOrderId'],
                            'orders_status' => $orders_status,

                            'date_purchased' => date("Y-m-d H:i:s",strtotime($order['PurchaseDate'])),
                            'date_last_updated' => date("Y-m-d H:i:s",strtotime($order['LastUpdateDate'])),
                            'delivery_name' => ((isset($order['ShippingAddress']['Name']) &&
                                                 $order['ShippingAddress']['Name']) ?
                                                strtoupper($order['ShippingAddress']['Name']) :
                                                null),
                            'delivery_street_address' => ((isset($order['ShippingAddress']['AddressLine1']) &&
                                                           $order['ShippingAddress']['AddressLine1']) ?
                                                          strtoupper($order['ShippingAddress']['AddressLine1']) :
                                                          null),
                            'delivery_street_address2' => ((isset($order['ShippingAddress']['AddressLine2']) &&
                                                           $order['ShippingAddress']['AddressLine2']) ?
                                                           strtoupper($order['ShippingAddress']['AddressLine2']) :
                                                           null),
                            'delivery_city' => ((isset($order['ShippingAddress']['City']) &&
                                                 $order['ShippingAddress']['City']) ?
                                                strtoupper($order['ShippingAddress']['City']) :
                                                null),
                            'delivery_postcode' => ((isset($order['ShippingAddress']['PostalCode']) &&
                                                     $order['ShippingAddress']['PostalCode']) ?
                                                    strtoupper($order['ShippingAddress']['PostalCode']) :
                                                    null),
                            'delivery_state' => ((isset($order['ShippingAddress']['StateOrRegion']) &&
                                                  $order['ShippingAddress']['StateOrRegion']) ?
                                                 strtoupper($order['ShippingAddress']['StateOrRegion']) :
                                                 null),
                            'delivery_country' => ((isset($order['ShippingAddress']['CountryCode']) &&
                                                   $order['ShippingAddress']['CountryCode']) ?
                                                   strtoupper($order['ShippingAddress']['CountryCode']) :
                                                   null),
                            'customers_telephone' => ((isset($order['ShippingAddress']['Phone']) &&
                                                       $order['ShippingAddress']['Phone']) ?
                                                      $order['ShippingAddress']['Phone'] :
                                                      null),
                            'customers_email_address' => ((isset($order['BuyerEmail']) &&
                                                           $order['BuyerEmail']) ?
                                                          strtolower($order['BuyerEmail']) :
                                                          null),
                            'billing_name' => ((isset($order['BuyerName']) &&
                                                $order['BuyerName']) ?
                                               strtoupper($order['BuyerName']) :
                                               null),
                            'payment_method' => 'Credit Card' ];

            if ($order['OrderStatus'] == 'Shipped')
                     $order_info['date_shipped'] = date("Y-m-d H:i:s",strtotime($order['LastUpdateDate']));
        }

        return $order_info;
    }

    /**
     * Update existing or create new local, retail order for Amazon based on information provided.
     *
     * @param  array $orders_results
     * @param  Site $site
     *
     * @return null
     */
    public static function findAndUdateOrInsertOrders($orders_results,$site)
    {
        $stop = false;
        $retail_site = \App\RetailSite::findByName('Amazon');

        while (!$stop)
        {
            if (isset($orders_results['ListOrdersResponse']) || isset($orders_results['ListOrdersByNextTokenResponse']))
            {
                if (isset($orders_results['ListOrdersResponse']))
                    $orders = $orders_results['ListOrdersResponse']['ListOrdersResult']['Orders']['Order'];
                else $orders = $orders_results['ListOrdersByNextTokenResponse']['ListOrdersByNextTokenResult']['Orders']['Order'];

                if (count($orders))
                {
                    foreach ($orders as $order)
                    {
                        if (isset($order['ShippingAddress']))
                        {
                            $retail_order = \App\RetailOrder::insertOrUpdate(self::createOrderInfoArray($order, $site->id, $retail_site->id));

                            self::findAndUpdateOrInsertAllProductsForOrder($retail_order->id, $retail_order->order_id, $site->id);
                        }
                        else if (isset($order['OrderStatus']) && $order['OrderStatus'] == 'Canceled')
                        {
                            $order_obj = \App\RetailOrder::findByOrderIdSiteRetailSite($order['AmazonOrderId'],
                                $site->id,
                                $retail_site->id);

                            if ($order_obj)
                            {
                                $order_obj->orders_status = \App\RetailOrdersStatus::findIDByName('Cancelled');
                                $order_obj->date_last_updated = date("Y-m-d H:i:s", strtotime($order['LastUpdateDate']));
                                $order_obj->date_canceled = $order_obj->date_last_updated;
                                $order_obj->save();
                            }
                        }
                    }
                }
                else
                {
                    var_dump($orders_results);
                    die();
                }

                $next_token = null;

                if (isset($orders_results['ListOrdersResponse']['ListOrdersResult']['NextToken']))
                    $next_token = $orders_results['ListOrdersResponse']['ListOrdersResult']['NextToken'];
                else if (isset($orders_results['ListOrdersByNextTokenResponse']['ListOrdersByNextTokenResult']['NextToken']))
                    $next_token = $orders_results['ListOrdersByNextTokenResponse']['ListOrdersByNextTokenResult']['NextToken'];

                sleep(20); //sleep for 20 seconds
                if ($next_token)
                {
                    $orders_results = self::getOrdersListByNextToken($next_token, $site->id, $site);

                    if (isset($orders_results['ErrorResponse']) &&
                        isset($orders_results['ErrorResponse']['Error']) &&
                        $orders_results['ErrorResponse']['Error']['Code'] == 'RequestThrottled')
                    {
             //           echo "Sleeping for " . self::RequestThrottledDelay . " seconds...<br>\n";

             //           sleep(self::RequestThrottledDelay); //sleep for 5 minutes
                        $orders_results = self::getOrdersListByNextToken($next_token, $site->id, $site);
                    }
                }
                else $stop = true;
            }
            else $stop = true;
        }
    }

    /**
     * Find all orders on Amazon that were updated after the specified date
     *    and update/insert them in the local retail database.
     *
     * @param  string *DATETIME* $lastUpdatedAfterDate
     * @param  int $site_id
     * @param  Site $site
     *
     * @return null
     */
    public static function findAndUpdateOrInsertAllOrdersLastUpdateAfter($lastUpdatedAfterDate,$site_id,$site = null)
    {
        set_time_limit(self::MaxRuntime);

        if ($site || is_int($site_id))
        {
            if (!$site) $site = \App\Site::find($site_id);

            self::findAndUdateOrInsertOrders(
                self::getOrdersListLastUpdateAfter($lastUpdatedAfterDate, $site->id, $site), $site);

            $retail_site = \App\RetailSite::findByName('Amazon');

            \App\RetailOrdersSKUTranslation::translateRetailSiteSKUs($site->id,$retail_site->id);
        }
    }

    /**
     * Find all orders on Amazon that were created after the specified date
     *    and update/insert them in the local retail database.
     *
     * @param  string *DATETIME* $lastUpdatedAfterDate
     * @param  int $site_id
     * @param  Site $site
     *
     * @return null
     */
    public static function findAndUpdateOrInsertAllOrdersSince($createdAfterDate,$site_id,$site = null)
    {
        set_time_limit(self::MaxRuntime);

        if ($site || is_int($site_id))
        {
            if (!$site) $site = \App\Site::find($site_id);

            self::findAndUdateOrInsertOrders(
                self::getOrdersListCreatedAfter($createdAfterDate, $site->id, $site), $site);

            $retail_site = \App\RetailSite::findByName('Amazon');

            \App\RetailOrdersSKUTranslation::translateRetailSiteSKUs($site->id,$retail_site->id);
        }
    }

    /**
     * Create and return a normalized array for an order item connected to a retail order.
     *
     * @param  array $order_item
     * @param  int $retail_order_id
     *
     * @return array
     */
    public static function createOrderProductInfoArray($order_item,$retail_order_id)
    {
        $product_info = [];

        if (isset($order_item['QuantityOrdered']) && (int)$order_item['QuantityOrdered'] && $retail_order_id)
        {
            $promo_ids = [];

            if(isset($order_item['PromotionIds']) &&
               is_array($order_item['PromotionIds']) &&
               count($order_item['PromotionIds']))
            {
                foreach($order_item['PromotionIds'] as $promo_id)
                {
                    $promo_ids[strtolower($promo_id)] = [ 'coupons_id' => strtolower($promo_id),
                                                          'value' => null,
                                                          'type' => 'amz_promo' ];
                }
            }

            $product_info = [ 'retail_order_id' => $retail_order_id,
                              'products_uniq_id' => (isset($order_item['OrderItemId']) ?
                                                     $order_item['OrderItemId'] :
                                                     null),
                              'products_id' => (isset($order_item['ASIN']) ?
                                                $order_item['ASIN'] :
                                                null),
                              'products_model' => (isset($order_item['SellerSKU']) ?
                                                   $order_item['SellerSKU'] :
                                                   null),
                              'products_name' => (isset($order_item['Title']) ?
                                                  $order_item['Title'] :
                                                  null),
                              'products_quantity' => (isset($order_item['QuantityOrdered']) ?
                                                      ((int)$order_item['QuantityShipped'] ?
                                                       (int)$order_item['QuantityShipped'] :
                                                       (int)$order_item['QuantityOrdered']) :
                                                      0),
                              'products_price' => ((isset($order_item['QuantityOrdered']) &&
                                                    ((int)$order_item['QuantityOrdered'] ||
                                                     (int)$order_item['QuantityShipped'])) ?
                                                   ((float)$order_item['ItemPrice']['Amount'] /
                                                    ((int)$order_item['QuantityShipped'] ?
                                                     (int)$order_item['QuantityShipped'] :
                                                     (int)$order_item['QuantityOrdered'])) :
                                                   0),
                              'other_info' => [ 'ot_tax' => ((isset($order_item['ItemTax']['Amount']) ?
                                                              (float)$order_item['ItemTax']['Amount'] :
                                                              0) +
                                                             (isset($order_item['ShippingTax']['Amount']) ?
                                                              (float)$order_item['ShippingTax']['Amount'] :
                                                              0) +
                                                             (isset($order_item['GiftWrapTax']['Amount']) ?
                                                              (float)$order_item['GiftWrapTax']['Amount'] :
                                                              0)),
                                                'ot_shipping' => (isset($order_item['ShippingPrice']['Amount']) ?
                                                                        (float)$order_item['ShippingPrice']['Amount'] :
                                                                        0),
                                                'ot_discount_coupon' => (isset($order_item['PromotionDiscount']['Amount']) ?
                                                                         ((float)$order_item['PromotionDiscount']['Amount'] * -1) :
                                                                         0),
                                                'ot_giftwrap' => (isset($order_item['GiftWrapPrice']['Amount']) ?
                                                                   (float)$order_item['GiftWrapPrice']['Amount'] :
                                                                   0),
                                                'discount_codes' => $promo_ids ] ];

            $product_info['final_price'] = $product_info['products_price'];
        }

        return $product_info;
    }

    /**
     * Create new or replace all products attached to an Amazon order.
     *
     * @param  int $retail_order_id
     * @param  int $amazon_order_id
     * @param  int $whsl_site_id
     *
     * @return null
     */
    public static function findAndUpdateOrInsertAllProductsForOrder($retail_order_id,$amazon_order_id,$whsl_site_id)
    {
        set_time_limit(self::MaxRuntime);

        $orders_results = \AmazonAPI::getOrderItems($amazon_order_id,$whsl_site_id);

        if (isset($orders_results['ErrorResponse']) &&
            isset($orders_results['ErrorResponse']['Error']) &&
            $orders_results['ErrorResponse']['Error']['Code'] == 'RequestThrottled')
        {
            sleep(self::RequestThrottledDelay);

            $orders_results = \AmazonAPI::getOrderItems($amazon_order_id,$whsl_site_id);
        }

        if (isset($orders_results['ListOrderItemsResponse']))
        {
            if (isset($orders_results['ListOrderItemsResponse']['ListOrderItemsResult']['OrderItems']['OrderItem'][0]))
                $order_items = $orders_results['ListOrderItemsResponse']['ListOrderItemsResult']['OrderItems']['OrderItem'];
            else $order_items[] = $orders_results['ListOrderItemsResponse']['ListOrderItemsResult']['OrderItems']['OrderItem'];

            if (count($order_items))
            {
                $orders_products_array = [];

                foreach($order_items as $order_item)
                {
                    $single_orders_product_array = self::createOrderProductInfoArray($order_item, $retail_order_id);
                    if (count($single_orders_product_array)) $orders_products_array[] = $single_orders_product_array;
                }
                if(count($orders_products_array))
                    \App\RetailOrdersProduct::findAndUpdateOrInsertAll($orders_products_array,$retail_order_id);
            }
        }
        else dd($orders_results);
    }

    /**
     * Find all orders with no products attached and attach the products the Amazon says the order has.
     *
     * @param  int $whsl_site_id
     * @param  Site $whsl_site
     *
     * @return null
     */
    public static function findAndUpdateOrInsertAllOrphanProducts($whsl_site_id,$whsl_site = null)
    {
        if ($whsl_site || is_int($whsl_site_id))
        {
            if (!$whsl_site) $whsl_site = \App\Site::find($whsl_site_id);
            $retail_site = \App\RetailSite::findByName('Amazon');

            $orphans = \App\RetailOrder::getListOfAllOrdersWithNoProductsAttached($whsl_site->id, $retail_site->id);

            if (count($orphans))
            {
                foreach ($orphans as $retail_order_id => $amazon_order_id)
                {
                    self::findAndUpdateOrInsertAllProductsForOrder($retail_order_id,$amazon_order_id,$whsl_site->id);
                }
            }
            return $orphans;
        }
    }
}