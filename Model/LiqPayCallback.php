<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @author     zamoroka https://github.com/zamoroka
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace LiqpayMagento\LiqPay\Model;

use LiqpayMagento\LiqPay\Api\LiqPayCallbackInterface;
use Magento\Sales\Model\Order;
use LiqpayMagento\LiqPay\Sdk\LiqPay;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use LiqpayMagento\LiqPay\Helper\Helper as Helper;
use Magento\Framework\App\RequestInterface;

class LiqPayCallback implements LiqPayCallbackInterface
{
    /**
     * @var \Magento\Sales\Model\Order
     */
    private $order;

    /**
     * @var \LiqpayMagento\LiqPay\Sdk\LiqPay
     */
    private $liqPay;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    private $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    private $transaction;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * LiqPayCallback constructor.
     *
     * @param \Magento\Sales\Model\Order                  $order
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\Transaction           $transaction
     * @param \LiqpayMagento\LiqPay\Helper\Helper         $helper
     * @param \LiqpayMagento\LiqPay\Sdk\LiqPay            $liqPay
     * @param \Magento\Framework\App\RequestInterface     $request
     */
    public function __construct(
        Order $order,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        Helper $helper,
        LiqPay $liqPay,
        RequestInterface $request
    ) {
        $this->order = $order;
        $this->liqPay = $liqPay;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->helper = $helper;
        $this->request = $request;
    }

    /**
     * @return null
     */
    public function callback()
    {
        $post = $this->request->getParams();
        if (!(isset($post['data']) && isset($post['signature']))) {
            $this->helper->getLogger()->error(
                __('In the response from LiqPay server there are no POST parameters "data" and "signature"')
            );

            return null;
        }

        $data = $post['data'];
        $receivedSignature = $post['signature'];

        $decodedData = $this->liqPay->getDecodedData($data);
        $orderId = $decodedData['order_id'] ?? null;
        $receivedPublicKey = $decodedData['public_key'] ?? null;
        $status = $decodedData['status'] ?? null;
        $amount = $decodedData['amount'] ?? null;
        $currency = $decodedData['currency'] ?? null;
        $transactionId = $decodedData['transaction_id'] ?? null;
        $senderPhone = $decodedData['sender_phone'] ?? null;

        try {
            $order = $this->getRealOrder($status, $orderId);
            if (!($order && $order->getId() && $this->helper->checkOrderIsLiqPayPayment($order))) {
                return null;
            }

            // ALWAYS CHECK signature field from Liqpay server!!!!
            // DON'T delete this block, be careful of fraud!!!
            if (!$this->helper->securityOrderCheck($data, $receivedPublicKey, $receivedSignature)) {
                $order->addStatusHistoryComment(__('LiqPay security check failed!'));
                $this->orderRepository->save($order);

                return null;
            }

            $historyMessage = [];
            $state = null;
            switch ($status) {
                case LiqPay::STATUS_SANDBOX:
                case LiqPay::STATUS_WAIT_COMPENSATION:
                    // case LiqPay::STATUS_SUBSCRIBED:
                case LiqPay::STATUS_SUCCESS:
                    if ($order->canInvoice()) {
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->register()->pay();
                        $transactionSave = $this->transaction->addObject(
                            $invoice
                        )->addObject(
                            $invoice->getOrder()
                        );
                        $transactionSave->save();
                        if ($status == LiqPay::STATUS_SANDBOX) {
                            $historyMessage[] = __('Invoice #%1 created (sandbox).', $invoice->getIncrementId());
                        } else {
                            $historyMessage[] = __('Invoice #%1 created.', $invoice->getIncrementId());
                        }
                        $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    } else {
                        $historyMessage[] = __('Error during creation of invoice.');
                    }
                    if ($senderPhone) {
                        $historyMessage[] = __('Sender phone: %1.', $senderPhone);
                    }
                    if ($amount) {
                        $historyMessage[] = __('Amount: %1.', $amount);
                    }
                    if ($currency) {
                        $historyMessage[] = __('Currency: %1.', $currency);
                    }
                    break;
                case LiqPay::STATUS_FAILURE:
                    $state = \Magento\Sales\Model\Order::STATE_CANCELED;
                    $historyMessage[] = __('Liqpay error.');
                    break;
                case LiqPay::STATUS_ERROR:
                    $state = \Magento\Sales\Model\Order::STATE_CANCELED;
                    $historyMessage[] = __('Liqpay error.');
                    break;
                case LiqPay::STATUS_WAIT_SECURE:
                    $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for verification from the Liqpay side.');
                    break;
                case LiqPay::STATUS_WAIT_ACCEPT:
                    $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for accepting from the buyer side.');
                    break;
                case LiqPay::STATUS_WAIT_CARD:
                    $state = \Magento\Sales\Model\Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for setting refund card number into your Liqpay shop.');
                    break;
                default:
                    $historyMessage[] = __('Unexpected status from LiqPay server: %1', $status);
                    break;
            }
            if ($transactionId) {
                $historyMessage[] = __('LiqPay transaction id %1.', $transactionId);
            }
            if (empty($historyMessage)) {
                $order->addStatusHistoryComment(implode(' ', $historyMessage))
                      ->setIsCustomerNotified(true);
            }
            if ($state) {
                $order->setState($state);
                $order->setStatus($state);
                $order->save();
            }
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->helper->getLogger()->critical($e);
        }

        return null;
    }

    /**
     * @param $status
     * @param $orderId
     * @return \Magento\Sales\Model\Order
     */
    private function getRealOrder($status, $orderId)
    {
        if ($status == LiqPay::STATUS_SANDBOX) {
            $testOrderSurfix = $this->helper->getTestOrderSurfix();
            if (!empty($testOrderSurfix)) {
                $testOrderSurfix = LiqPay::TEST_MODE_SURFIX_DELIM . $testOrderSurfix;
                if (strlen($testOrderSurfix) < strlen($orderId)
                    && substr($orderId, -strlen($testOrderSurfix)) == $testOrderSurfix
                ) {
                    $orderId = substr($orderId, 0, strlen($orderId) - strlen($testOrderSurfix));
                }
            }
        }

        return $this->order->loadByIncrementId($orderId);
    }
}
