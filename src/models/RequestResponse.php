<?php

namespace white\commerce\mollie\plus\models;

use Craft;
use Omnipay\Mollie\Message\Response\CreateShipmentResponse;

class RequestResponse extends \craft\commerce\omnipay\base\RequestResponse
{
    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        $data = $this->response->getData();

        if (is_array($data) && !empty($data['status'])) {
            if ($data['status'] == 'canceled') {
                return Craft::t('commerce-mollie-plus', 'The payment was canceled.');
            } elseif ($data['status'] == 'failed') {
                return Craft::t('commerce-mollie-plus', 'The payment failed.');
            }
        }

        return (string)$this->response->getMessage();
    }

    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return $this->response instanceof CreateShipmentResponse
            ? ($this->response->getData()['orderId'] ?? $this->response->getTransactionReference())
            : $this->response->getTransactionReference();
    }
}
