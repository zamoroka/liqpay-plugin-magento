<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @author     zamoroka https://github.com/zamoroka
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Api;

/**
 * Interface LiqPayCallbackInterface
 *
 * @package LiqpayMagento\LiqPay\Api
 */
interface LiqPayCallbackInterface
{
    /**
     * @api
     * @return null
     */
    public function callback();
}
