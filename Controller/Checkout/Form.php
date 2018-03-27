<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @author     zamoroka https://github.com/zamoroka
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Controller\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use LiqpayMagento\LiqPay\Helper\Data as Helper;

/**
 * Class Form
 *
 * @package LiqpayMagento\LiqPay\Controller\Checkout
 */
class Form extends Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        Helper $helper,
        LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->layoutFactory = $layoutFactory;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        try {
            if (!$this->helper->isEnabled()) {
                throw new LocalizedException(__('Payment is not allow.'));
            }
            $order = $this->getCheckoutSession()->getLastRealOrder();
            if (!($order && $order->getId())) {
                throw new LocalizedException(__('Order not found'));
            }
            if ($this->helper->checkOrderIsLiqPayPayment($order)) {
                /** @var $formBlock \LiqpayMagento\LiqPay\Block\SubmitForm */
                $formBlock = $this->layoutFactory->create()->createBlock('LiqpayMagento\LiqPay\Block\SubmitForm');
                $formBlock->setOrder($order);
                $data = [
                    'status'  => 'success',
                    'content' => $formBlock->toHtml(),
                ];
            } else {
                throw new LocalizedException(__('Order payment method is not a LiqPay payment method'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong, please try again later'));
            $this->helper->getLogger()->critical($e);
            $this->getCheckoutSession()->restoreQuote();
            $data = [
                'status'   => 'error',
                'redirect' => $this->_url->getUrl('checkout/cart'),
            ];
        }
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($data);

        return $result;
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->checkoutSession;
    }
}
