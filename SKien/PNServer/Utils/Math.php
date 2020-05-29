<?php
declare(strict_types=1);

namespace SKien\PNServer\Utils;

/*
 * extracted required classes and functions from package
 *		spomky-labs/jose
 *		https://github.com/Spomky-Labs/Jose 
 *
 * @package PNServer
 * @version 1.0.0
 * @copyright MIT License - see the copyright below and LICENSE file for details
 */

/*
 * *********************************************************************
 * Copyright (c) 2014-2016 Spomky-Labs
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES
 * OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 * ***********************************************************************
 */

/**
 * @internal
 */
class Math
{
    public static function cmp($first, $other)
    {
        return \gmp_cmp($first, $other);
    }

    public static function equals($first, $other)
    {
        return 0 === \gmp_cmp($first, $other);
    }

    public static function mod($number, $modulus)
    {
        return \gmp_mod($number, $modulus);
    }

    public static function add($augend, $addend)
    {
        return \gmp_add($augend, $addend);
    }

    public static function sub($minuend, $subtrahend)
    {
        return \gmp_sub($minuend, $subtrahend);
    }

    public static function mul($multiplier, $multiplicand)
    {
        return \gmp_mul($multiplier, $multiplicand);
    }

    public static function pow($base, $exponent)
    {
        return \gmp_pow($base, $exponent);
    }

    public static function bitwiseAnd($first, $other)
    {
        return \gmp_and($first, $other);
    }

    public static function bitwiseXor($first, $other)
    {
        return \gmp_xor($first, $other);
    }

    public static function toString($value)
    {
        return \gmp_strval($value);
    }

    public static function inverseMod($a, $m)
    {
        return \gmp_invert($a, $m);
    }

    public static function baseConvert($number, $from, $to)
    {
        return \gmp_strval(\gmp_init($number, $from), $to);
    }

    public static function rightShift($number, $positions)
    {
        return \gmp_div($number, \gmp_pow(\gmp_init(2, 10), $positions));
    }

    public static function stringToInt($s)
    {
        $result = \gmp_init(0, 10);
        $sLen = \mb_strlen($s, '8bit');

        for ($c = 0; $c < $sLen; ++$c) {
            $result = \gmp_add(\gmp_mul(256, $result), \gmp_init(\ord($s[$c]), 10));
        }

        return $result;
    }
    
  	public static function modSub($minuend, $subtrahend, $modulus)
   	{
   		return self::mod(self::sub($minuend, $subtrahend), $modulus);
   	}
    
   	public static function modMul($multiplier, $muliplicand, $modulus)
   	{
   		return self::mod(self::mul($multiplier, $muliplicand), $modulus);
   	}
    
   	public static function modDiv($dividend, $divisor, $modulus)
   	{
   		return self::mul($dividend, self::inverseMod($divisor, $modulus), $modulus);
   	}
}
