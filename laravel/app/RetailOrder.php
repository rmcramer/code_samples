<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;
use Session;
use Auth;
use Carbon;
use Schema;
use Cache;

class RetailOrder extends MyModel {

    protected $connection = 'mysql_retail_data';

    var $table_field_list = null;

    /**
     * Constructor method -- currently empty
     *
     */
    public function __construct()
    {
    }

    /**
     * Replacement method for finding a model by its primary key.
     *    Allows for finding other things along with the base model
     *
     * @param  mixed $id
     * @param  array $columns
     *
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public static function find($id, $columns = array('*'))
    {
        $rtl_order = parent::find($id, $columns);
        return $rtl_order;
    }

    /**
     * Find the specific retail order by passed ID for the application (whsl_site_id) and retail site specified.
     *
     * @param  int $order_id
     * @param  int $whsl_site_id
     * @param  int $retail_site_id
     *
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public static function findByOrderIdSiteRetailSite($order_id,$whsl_site_id,$retail_site_id)
    {
        $obj = self::where('order_id', $order_id)
                        ->where('whsl_site_id', $whsl_site_id)
                        ->where('retail_site_id', $retail_site_id)
                        ->first();

        return $obj;
    }

    /**
     * Find all order IDs for orders with no products attached for a specific application and retail site.
     *
     * @param  int $whsl_site_id
     * @param  int $retail_site_id
     *
     * @return array
     */
    public static function getListOfAllOrdersWithNoProductsAttached($whsl_site_id,$retail_site_id)
    {
        $ids = [];

        $array_of_ids = self::distinct()
                                 ->select(DB::raw('DISTINCT retail_orders.id, retail_orders.order_id'))
                                 ->leftJoin('retail_orders_products','retail_orders_products.retail_order_id','=','retail_orders.id')
                                 ->where('retail_orders.whsl_site_id',$whsl_site_id)
                                 ->where('retail_orders.retail_site_id',$retail_site_id)
                                 ->whereNull('retail_orders_products.id')
                                 ->get();
        if (count($array_of_ids))
        {
            foreach ($array_of_ids as $retail_order)
            {
                $ids[$retail_order->id] = $retail_order->order_id;
            }
        }

        return $ids;
    }

    /**
     * Given the passed array of order information, either find and update the order or create a new one,
     *    and then return the order object.
     *
     * @param  array $order_array
     *
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public static function insertOrUpdate($order_array)
    {
        if (count($order_array))
        {
            $order = self::findByOrderIdSiteRetailSite($order_array['order_id'],
                                                       $order_array['whsl_site_id'],
                                                       $order_array['retail_site_id']);

            if (!$order)
            {
                $order = new self;
            }

            foreach($order_array as $key => $value)
            {
                if (!$value && $value !== 0) $order->$key = null;
                else if ($key !== 'date_shipped' ||
                         ($key == 'date_shipped' &&
                          !$order->date_shipped) ||
                         ($key == 'date_shipped' &&
                          $order->date_shipped &&
                          $value < $order->date_shipped))
                    $order->$key = $value;
            }
            $order->save();
            return $order;
        }
        else return null;
    }

    /**
     * Get sales data and number or products sold for all retail orders for a given site, and if passed, a given date range.
     *
     * @param  int $site_id
     * @param  string *DATETIME* $start
     * @param  string *DATETIME* $end
     *
     * @return array
     */
    public static function getBreakdownForSiteAndRange($site_id,$start = null,$end = null)
    {
        $raw_where = '1 = 1';

        if ($start)
        {
            $raw_where .= " AND retail_orders.date_purchased >= '" . Carbon::parse($start)->format('Y-m-d H:i:s') . "'";
        }
        if ($end)
        {
            $raw_where .= " AND retail_orders.date_purchased <= '" . Carbon::parse($end)->format('Y-m-d H:i:s') . "'";
        }

        $results = self::select(DB::raw('retail_orders.id, ' .
                                        'rot_subtot.value AS subtotal, ' .
                                        'rot_disc.value AS discount, ' .
                                        'rot_tax.value AS tax, ' .
                                        '0 AS other_amt, ' .
                                        'rot_ship.value AS shipping, ' .
                                        'rot_tot.value AS total, ' .
                                        'SUM(rop.products_quantity) AS qty'))
                           ->leftJoin('retail_orders_totals AS rot_subtot',
                                      function($join)
                                      {
                                          $join->on('retail_orders.id','=','rot_subtot.retail_order_id')
                                                   ->where('rot_subtot.class', '=', 'ot_subtotal');
                                      })
                           ->leftJoin('retail_orders_totals AS rot_disc',
                                      function($join)
                                      {
                                          $join->on('retail_orders.id','=','rot_disc.retail_order_id')
                                              ->where('rot_disc.class', '=', 'ot_discount_coupon');
                                      })
                           ->leftJoin('retail_orders_totals AS rot_tax',
                                      function($join)
                                      {
                                          $join->on('retail_orders.id','=','rot_tax.retail_order_id')
                                              ->where('rot_tax.class', '=', 'ot_tax');
                                      })
                           ->leftJoin('retail_orders_totals AS rot_ship',
                                      function($join)
                                      {
                                          $join->on('retail_orders.id','=','rot_ship.retail_order_id')
                                              ->where('rot_ship.class', '=', 'ot_shipping');
                                      })
                           ->leftJoin('retail_orders_totals AS rot_tot',
                                      function($join)
                                      {
                                          $join->on('retail_orders.id','=','rot_tot.retail_order_id')
                                              ->where('rot_tot.class', '=', 'ot_total');
                                      })
                           ->join('retail_orders_products AS rop','rop.retail_order_id','=','retail_orders.id')
                           ->join('retail_orders_statuses AS ros','ros.id','=','retail_orders.orders_status')
                           ->where('retail_orders.whsl_site_id',$site_id)
                           ->where('ros.language_id',1)
                           ->where('retail_orders.orders_type','R')
                           ->where('ros.orders_status_name','<>','Cancelled')
                           ->whereRaw($raw_where)
                           ->groupBy('retail_orders.id')
                           ->get();

        return $results->toArray();
    }
}