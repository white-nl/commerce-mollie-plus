<?php

namespace white\commerce\mollie\plus\models;

use Craft;
use Omnipay\Mollie\Message\Response\CompleteOrderResponse;
use Omnipay\Mollie\Message\Response\CreateShipmentResponse;
use Omnipay\Mollie\Message\Response\RefundResponse;

class RequestResponse extends \craft\commerce\omnipay\base\RequestResponse
{
    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        $transaction = $this->transaction;

        /** @var Gateway $gateway */
        $gateway = $transaction->getGateway();

        /** @var FetchOrderResponse $order */
        $order = $gateway->fetchOrder(['transactionReference' => $this->getTransactionReference(), 'includePayments' => true]);

        $transactionReference = $order->getData()['_embedded']['payments'][0]['id'] ?? null;

        if ($transactionReference) {
            $transaction = $gateway->fetchTransaction($transactionReference);
            $data = $transaction->getData();
        } else {
            $data = $this->response->getData();
        }

        if (is_array($data) && !empty($data['status'])) {
            switch ($data['status']) {
                case 'canceled':
                    return Craft::t('commerce-mollie-plus', 'The payment was canceled.');
                case 'failed':
                    return Craft::t('commerce-mollie-plus', 'The payment failed.');
                case 'expired':
                    return Craft::t('commerce-mollie-plus', 'The payment expired.');
            }
        }

        return (string)$transaction->getMessage();
    }

    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        $data = $this->response->getData();

        if ($this->response instanceof CompleteOrderResponse && isset($data['method'], $data['status']) && $data['method'] === 'banktransfer' && $this->response->isOpen()) {
            return true;
        }

        return parent::isProcessing();
    }

    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        $data = $this->response->getData();

        if ($this->response instanceof CompleteOrderResponse && isset($data['method'], $data['status']) && $data['method'] === 'banktransfer' && $this->response->isOpen()) {
            return false;
        }

        return parent::isRedirect();
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return ($this->response instanceof CreateShipmentResponse || $this->response instanceof RefundResponse)
            ? ($this->response->getData()['orderId'] ?? $this->response->getTransactionReference())
            : $this->response->getTransactionReference();
    }
}
