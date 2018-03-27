<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @author     zamoroka https://github.com/zamoroka
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use LiqpayMagento\LiqPay\Sdk\LiqPay;
use LiqpayMagento\LiqPay\Helper\Data as Helper;

/**
 * Class SubmitForm
 *
 * @package LiqpayMagento\LiqPay\Block
 */
class SubmitForm extends Template
{
    /** @var \Magento\Sales\Model\Order|null $order */
    private $order = null;

    /** @var \LiqpayMagento\LiqPay\Sdk\LiqPay $liqPay */
    private $liqPay;

    /** @var \LiqpayMagento\LiqPay\Helper\Data $helper */
    private $helper;

    /**
     * SubmitForm constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \LiqpayMagento\LiqPay\Sdk\LiqPay                 $liqPay
     * @param \LiqpayMagento\LiqPay\Helper\Data              $helper
     * @param \Magento\Framework\Exception\LocalizedException  $localizedException
     * @param array                                            $data
     */
    public function __construct(
        Template\Context $context,
        LiqPay $liqPay,
        Helper $helper,
        LocalizedException $localizedException,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->liqPay = $liqPay;
        $this->helper = $helper;
    }

    /**
     * @return Order
     * @throws \Exception
     */
    public function getOrder()
    {
        if ($this->order === null) {
            throw new LocalizedException(__('Order is not set'));
        }

        return $this->order;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Load block html from cache storage
     *
     * @return string|false
     */
    protected function _loadCache()
    {
        return false;
    }

    /**
     * Render block HTML
     *
     * @return string
     * @throws \Exception
     */
    protected function _toHtml()
    {
        $order = $this->getOrder();
        $html = $this->liqPay->cnb_form(
            [
                'action'      => 'pay',
                'amount'      => $order->getGrandTotal(),
                'currency'    => $order->getOrderCurrencyCode(),
                'description' => $this->helper->getLiqPayDescription($order),
                'order_id'    => $order->getIncrementId(),
            ]
        );

        return $html;
    }
}
