<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @author     zamoroka https://github.com/zamoroka
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use LiqpayMagento\LiqPay\Model\Payment as LiqPayPayment;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Class Data
 *
 * @package LiqpayMagento\LiqPay\Helper
 */
class Data extends AbstractHelper
{
    const XML_PATH_IS_ENABLED = 'payment/liqpaymagento_liqpay/active';

    const XML_PATH_PUBLIC_KEY = 'payment/liqpaymagento_liqpay/public_key';

    const XML_PATH_PRIVATE_KEY = 'payment/liqpaymagento_liqpay/private_key';

    const XML_PATH_TEST_MODE = 'payment/liqpaymagento_liqpay/sandbox';

    const XML_PATH_TEST_ORDER_SURFIX = 'payment/liqpaymagento_liqpay/sandbox_order_surfix';

    const XML_PATH_DESCRIPTION = 'payment/liqpaymagento_liqpay/description';

    const XML_PATH_CALLBACK_SECURITY_CHECK = 'payment/liqpaymagento_liqpay/security_check';

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * Data constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Payment\Helper\Data          $paymentHelper
     */
    public function __construct(
        Context $context,
        PaymentHelper $paymentHelper
    ) {
        parent::__construct($context);
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->scopeConfig->getValue(
            static::XML_PATH_IS_ENABLED,
            ScopeInterface::SCOPE_STORE
        )
        ) {
            if ($this->getPublicKey() && $this->getPrivateKey()) {
                return true;
            } else {
                $this->_logger->error(
                    __('The LiqpayMagento\LiqPay module is turned off, because public or private key is not set')
                );
            }
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function isTestMode()
    {
        return $this->scopeConfig->getValue(
            static::XML_PATH_TEST_MODE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return mixed
     */
    public function isSecurityCheck()
    {
        return $this->scopeConfig->getValue(
            static::XML_PATH_CALLBACK_SECURITY_CHECK,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return string
     */
    public function getPublicKey()
    {
        return trim(
            $this->scopeConfig->getValue(static::XML_PATH_PUBLIC_KEY, ScopeInterface::SCOPE_STORE)
        );
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return trim(
            $this->scopeConfig->getValue(static::XML_PATH_PRIVATE_KEY, ScopeInterface::SCOPE_STORE)
        );
    }

    /**
     * @return string
     */
    public function getTestOrderSurfix()
    {
        return trim(
            $this->scopeConfig->getValue(static::XML_PATH_TEST_ORDER_SURFIX, ScopeInterface::SCOPE_STORE)
        );
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface|null $order
     * @return string
     */
    public function getLiqPayDescription(\Magento\Sales\Api\Data\OrderInterface $order = null)
    {
        $description = trim(
            $this->scopeConfig->getValue(static::XML_PATH_DESCRIPTION, ScopeInterface::SCOPE_STORE)
        );
        $params = [
            '{order_id}' => $order->getIncrementId(),
        ];

        return strtr($description, $params);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function checkOrderIsLiqPayPayment(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        $method = $order->getPayment()->getMethod();
        $methodInstance = $this->paymentHelper->getMethodInstance($method);

        return $methodInstance instanceof LiqPayPayment;
    }

    /**
     * @param $data
     * @param $receivedPublicKey
     * @param $receivedSignature
     * @return bool
     */
    public function securityOrderCheck($data, $receivedPublicKey, $receivedSignature)
    {
        if ($this->isSecurityCheck()) {
            $publicKey = $this->getPublicKey();
            if ($publicKey !== $receivedPublicKey) {
                return false;
            }

            $privateKey = $this->getPrivateKey();
            $generatedSignature = base64_encode(sha1($privateKey . $data . $privateKey, 1));

            return $receivedSignature === $generatedSignature;
        } else {
            return true;
        }
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }
}
