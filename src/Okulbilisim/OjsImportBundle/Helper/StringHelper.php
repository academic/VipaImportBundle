<?php
/**
 * Created by PhpStorm.
 * User: emreyilmaz
 * Date: 2.04.15
 * Time: 10:23
 */

namespace Okulbilisim\OjsImportBundle\Helper;


class StringHelper
{
    public static function roman2int($roman)
    {
        $roman = strtoupper($roman);
        $romans = array(
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        );

        $result = 0;

        foreach ($romans as $key => $value) {
            while (strpos($roman, $key) === 0) {
                $result += $value;
                $roman = substr($roman, strlen($key));
            }
        }
        return $result;
    }
} 