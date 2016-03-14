<?php namespace App;


class GMP_RMC {

    /**
     * This library mimics certain GMP functions since GMP is not found on all servers.
     *
     */
    public static function gmp_init($value)
    {
        $gmp_array = [ 0 => 0 ];

        $array_index = 0;

        while ($value)
        {
            $remainder = fmod($value,2);

            if ($remainder) $gmp_array[$array_index] = 1;
            else $gmp_array[$array_index] = 0;

            $value = (int)($value / 2);
            $array_index++;
        }

        return $gmp_array;
    }

    public static function gmp_intval($gmp_array)
    {
        $value = 0;

        foreach($gmp_array as $k => $v)
        {
            if ($v) $value += pow(2, $k);
        }

        return $value;
    }

    public static function gmp_setbit(&$gmp_array, $array_index)
    {
        $gmp_array[$array_index] = 1;
    }

    public static function gmp_testbit($gmp_array, $array_index)
    {
        if (isset($gmp_array[$array_index])) return $gmp_array[$array_index];
        else return 0;
    }
}