<?php namespace App;

use DB;
use Session;
use Auth;
use Carbon;
use Schema;
use Mail;

class Helpers
{

    /**
     * Change the DATETIME passed from UTC to the user's local time.
     *    Return *DATETIME*.
     *
     * @param  string *DATETIME* $db_date
     *
     * @return string *DATETIME*
     */
    public static function normalizeDatetimeFromUTCtoUserTZ($db_date)
    {
        $tz = getenv('APP_TIMEZONE');

        if (Auth::user() && isset(Auth::user()->timezone))
        {
            $tz = Auth::user()->timezone;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $db_date, 'UTC')
            ->setTimezone($tz)
            ->format('Y-m-d H:i:s');
    }

    /**
     * Change the DATETIME passed from the user's local time to UTC.
     *    Return *DATETIME*.
     *
     * @param  string *DATETIME* $db_date
     *
     * @return string *DATETIME*
     */
    public static function normalizeDatetimeFromUserTZtoUTC($db_date)
    {
        $tz = getenv('APP_TIMEZONE');

        if (Auth::user() && isset(Auth::user()->timezone))
        {
            $tz = Auth::user()->timezone;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $db_date, $tz)
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
    }

    /**
     * Change the DATETIME passed from UTC to the user's local time.
     *    Return *DATE*.
     *
     * @param  string *DATETIME* $db_date
     *
     * @return string *DATE*
     */
    public static function normalizeDatetimeFromUTCtoUserTZDate($db_date)
    {
        $tz = getenv('APP_TIMEZONE');

        if (Auth::user() && isset(Auth::user()->timezone))
        {
            $tz = Auth::user()->timezone;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $db_date, 'UTC')
            ->setTimezone($tz)
            ->format('Y-m-d');
    }

    /**
     * Change the EMAIL variables in running process' environment for the given (or current) site.
     *
     * @param  int $site
     *
     * @return false
     */
    public static function checkAndSetSiteEmailSettings($site = null)
    {
        if (!$site) $site = Auth::user()->site;

        if ($site->cfg_array['MAIL_HOST']->typed_value)
        {
            putenv('MAIL_HOST=' . $site->cfg_array['MAIL_HOST']->typed_value);
        }
        if ($site->cfg_array['MAIL_PORT']->typed_value)
        {
            putenv('MAIL_PORT=' . $site->cfg_array['MAIL_PORT']->typed_value);
        }
        if ($site->cfg_array['MAIL_USERNAME']->typed_value)
        {
            putenv('MAIL_USERNAME=' . $site->cfg_array['MAIL_USERNAME']->typed_value);
        }
        if ($site->cfg_array['MAIL_PASSWORD']->typed_value)
        {
            putenv('MAIL_PASSWORD=' . $site->cfg_array['MAIL_PASSWORD']->typed_value);
        }
        if ($site->cfg_array['MAIL_USER_PLAINNAME']->typed_value)
        {
            putenv('MAIL_USER_PLAINNAME=' . $site->cfg_array['MAIL_USER_PLAINNAME']->typed_value);
        }
        if ($site->cfg_array['MAIL_FROMADDR']->typed_value)
        {
            putenv('MAIL_FROMADDR=' . $site->cfg_array['MAIL_FROMADDR']->typed_value);
        }
    }

    /**
     * If we had password strength logic, we'd add it here.
     *
     * @param  string $password
     *
     * @return true
     */
    public static function passwordPassesStrengthRules($password)
    {
        return true;
    }

    /**
     * Used by cron processes to act as Admin.
     *
     * @return false
     */
    public static function loginAsAdminProcessUser()
    {
        if (Auth::user()) Auth::destroyUser();

        Auth::loginUsingId(getenv('APP_ADMIN_PROCESS_USER_ID'));

        $lookups['orders']['shipping_services'] = \App\ShippingService::getShippingServicesLookup();
        $lookups['orders']['orders_statuses'] = \App\OrdersStatus::getOrdersStatusesLookup();
        Session::put('lookups', $lookups);
    }

    /**
     * The following section is self explanatory, these are tests used for logic array evaluations.
     */
    public static function is_equal($op1,$op2)
    {
        return $op1 === $op2;
    }

    public static function now()
    {
        return Carbon::now()->format('Y-m-d H:i:s');
    }

    public static function is_less_than($op1,$op2)
    {
        return $op1 < $op2;
    }

    public static function is_greater_than($op1,$op2)
    {
        return $op1 > $op2;
    }

    public static function is_less_than_or_equal($op1,$op2)
    {
        return $op1 <= $op2;
    }

    public static function is_greater_than_or_equal($op1,$op2)
    {
        return $op1 >= $op2;
    }

    public static function is_not_equal($op1,$op2)
    {
        return $op1 !== $op2;
    }

    public static function is($op1,$c,$op2)
    {
        $meth = [ '===' => 'is_equal',
                  '<' => 'is_less_than',
                  '>' => 'is_greater_than',
                  '<=' => 'is_less_than_or_equal',
                  '>=' => 'is_greater_than_or_equal',
                  '!==' => 'is_not_equal' ];

        if($method = $meth[$c])
        {
            return self::$method($op1,$op2);
        }
        return null; // or throw excp.
    }

    /**
     * Function that allows for the evaluation of a well formed array of logic
     *    NOTE: it could be potentially recursive.
     *
     * Sample logic array:
     *
     * [ "and" => [ [ "left_side" => [ "obj" => "order", "var" => "date_paid" ],
     *                "op" => "===",
     *                "right_side" => null ],
     *              [ "left_side" => [ "obj" => "order", "var" => "total_owed" ],
     *                "op" => "===",
     *                "right_side" => null ] ] ]
     *
     * The above would evaluate 'true' if the order object's date_paid and total_owed both equal NULL
     *
     * @param  string $op_type
     * @param  array $logic_array
     * @param  array $objs

     * @return true
     */
    public static function logicArrayAndOrOperation($op_type,$logic_array,$objs)
    {
        $evaluated_true = null;

        if (isset($logic_array['and'])) $evaluated_true = self::logicArrayAndOrOperation('and',$logic_array['and'],$objs);
        else if (isset($logic_array['or'])) $evaluated_true = self::logicArrayAndOrOperation('or',$logic_array['or'],$objs);
        else
        {
            foreach ($logic_array as $test)
            {
                if (isset($test['and'])) $evaluated_true = self::logicArrayAndOrOperation('and',$test['and'],$objs);
                else if (isset($test['or'])) $evaluated_true = self::logicArrayAndOrOperation('or',$test['or'],$objs);
                else
                {
                    if (is_array($test['left_side']))
                    {
                        $left_side = $objs[$test['left_side']['obj']]->$test['left_side']['var'];
                    }
                    else $left_side = $test['left_side']['var'];

                    if (is_array($test['right_side']))
                    {
                        $right_side = $objs[$test['right_side']['obj']]->$test['right_side']['var'];
                    }
                    else $right_side = $test['right_side']['var'];

                    if ($evaluated_true === null) $evaluated_true = \App\Helpers::is($left_side, $test['op'], $right_side);
                    else
                    {
                        if ($op_type == 'and') $evaluated_true = ($evaluated_true && \App\Helpers::is($left_side, $test['op'], $right_side));
                        else $evaluated_true = ($evaluated_true || \App\Helpers::is($left_side, $test['op'], $right_side));
                    }
                }
            }
        }

        return $evaluated_true;
    }

    public static function logicArrayEvaulation($logic_array,$objs)
    {
        $return_value = null;

        if (isset($logic_array['and'])) $return_value = self::logicArrayAndOrOperation('and',$logic_array['and'],$objs);
        else if (isset($logic_array['or'])) $return_value = self::logicArrayAndOrOperation('or',$logic_array['or'],$objs);

        return $return_value;
    }
}