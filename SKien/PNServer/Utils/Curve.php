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

use SKien\PNServer\Utils\Math;
use SKien\PNServer\Utils\Point;

/**
 * @internal
 */
class Curve
{
    /**
     * Elliptic curve over the field of integers modulo a prime.
     *
     * @var \GMP
     */
    private $a;

    /**
     * @var \GMP
     */
    private $b;

    /**
     * @var \GMP
     */
    private $prime;

    /**
     * Binary length of keys associated with these curve parameters.
     *
     * @var int
     */
    private $size;

    /**
     * @var Point
     */
    private $generator;

    public function __construct($size, $prime, $a, $b, $generator)
    {
        $this->size = $size;
        $this->prime = $prime;
        $this->a = $a;
        $this->b = $b;
        $this->generator = $generator;
    }

    public function getA()
    {
        return $this->a;
    }

    public function getB()
    {
        return $this->b;
    }

    public function getPrime()
    {
        return $this->prime;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getPoint($x, $y, $order = null)
    {
        if (!$this->contains($x, $y)) {
            throw new \RuntimeException('Curve ' . $this->__toString() . ' does not contain point (' . Math::toString($x) . ', ' . Math::toString($y) . ')');
        }
        $point = Point::create($x, $y, $order);
        if (!\is_null($order)) {
            $mul = $this->mul($point, $order);
            if (!$mul->isInfinity()) {
                throw new \RuntimeException('SELF * ORDER MUST EQUAL INFINITY. (' . (string) $mul . ' found instead)');
            }
        }

        return $point;
    }
    
    public function getPublicKeyFrom($x, $y)
    {
        $zero = \gmp_init(0, 10);
        if (Math::cmp($x, $zero) < 0 || Math::cmp($this->generator->getOrder(), $x) <= 0 || Math::cmp($y, $zero) < 0 || Math::cmp($this->generator->getOrder(), $y) <= 0) {
            throw new \RuntimeException('Generator point has x and y out of range.');
        }
        $point = $this->getPoint($x, $y);

        return $point;
    }

    public function contains($x, $y)
    {
        $eq_zero = Math::equals(
            Math::modSub(
                Math::pow($y, 2),
                Math::add(
                    Math::add(
                        Math::pow($x, 3),
                        Math::mul($this->getA(), $x)
                    ),
                    $this->getB()
                ),
                $this->getPrime()
            ),
            \gmp_init(0, 10)
        );

        return $eq_zero;
    }

    public function add($one, $two)
    {
        if ($two->isInfinity()) {
            return clone $one;
        }

        if ($one->isInfinity()) {
            return clone $two;
        }

        if (Math::equals($two->getX(), $one->getX())) {
            if (Math::equals($two->getY(), $one->getY())) {
                return $this->getDouble($one);
            } else {
                return Point::infinity();
            }
        }

        $slope = Math::modDiv(
            Math::sub($two->getY(), $one->getY()),
            Math::sub($two->getX(), $one->getX()),
            $this->getPrime()
        );

        $xR = Math::modSub(
            Math::sub(Math::pow($slope, 2), $one->getX()),
            $two->getX(),
            $this->getPrime()
        );

        $yR = Math::modSub(
            Math::mul($slope, Math::sub($one->getX(), $xR)),
            $one->getY(),
            $this->getPrime()
        );

        return $this->getPoint($xR, $yR, $one->getOrder());
    }

    public function mul($one, $n)
    {
        if ($one->isInfinity()) {
            return Point::infinity();
        }

        /** @var \GMP $zero */
        $zero = \gmp_init(0, 10);
        if (Math::cmp($one->getOrder(), $zero) > 0) {
            $n = Math::mod($n, $one->getOrder());
        }

        if (Math::equals($n, $zero)) {
            return Point::infinity();
        }

        /** @var Point[] $r */
        $r = [
            Point::infinity(),
            clone $one,
        ];

        $k = $this->getSize();
        $n = \str_pad(Math::baseConvert(Math::toString($n), 10, 2), $k, '0', STR_PAD_LEFT);

        for ($i = 0; $i < $k; ++$i) {
            $j = $n[$i];
            Point::cswap($r[0], $r[1], $j ^ 1);
            $r[0] = $this->add($r[0], $r[1]);
            $r[1] = $this->getDouble($r[1]);
            Point::cswap($r[0], $r[1], $j ^ 1);
        }

        return $r[0];
    }

    public function __toString()
    {
        return 'curve(' . Math::toString($this->getA()) . ', ' . Math::toString($this->getB()) . ', ' . Math::toString($this->getPrime()) . ')';
    }

    public function getDouble($point)
    {
        if ($point->isInfinity()) {
            return Point::infinity();
        }

        $a = $this->getA();
        $threeX2 = Math::mul(\gmp_init(3, 10), Math::pow($point->getX(), 2));

        $tangent = Math::modDiv(
            Math::add($threeX2, $a),
            Math::mul(\gmp_init(2, 10), $point->getY()),
            $this->getPrime()
        );

        $x3 = Math::modSub(
            Math::pow($tangent, 2),
            Math::mul(\gmp_init(2, 10), $point->getX()),
            $this->getPrime()
        );

        $y3 = Math::modSub(
            Math::mul($tangent, Math::sub($point->getX(), $x3)),
            $point->getY(),
            $this->getPrime()
        );

        return $this->getPoint($x3, $y3, $point->getOrder());
    }
}
