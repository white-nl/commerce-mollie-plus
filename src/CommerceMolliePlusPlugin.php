<?php

namespace white\commerce\mollie\plus;

use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\services\Gateways;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use white\commerce\mollie\plus\gateways\Gateway;
use yii\base\Event;

/**
 */
class CommerceMolliePlusPlugin extends Plugin
{
    public function init()
    {
        parent::init();

        Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES,  function(RegisterComponentTypesEvent $event) {
            $event->types[] = Gateway::class;
        });

        $this->registerOrderEventListeners();
    }

    protected function registerOrderEventListeners()
    {
        Event::on(
            Order::class,
            Order::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                /** @var Order $order */
                $order = $event->sender;
                if ($order->propagating || !$order->orderStatusId) {
                    return;
                }

                // Automatic transaction capture based on order status configured in the gateway settings
                foreach ($order->getTransactions() as $transaction) {
                    $gateway = $transaction->getGateway();
                    if ($gateway instanceof Gateway && $transaction->canCapture()) {
                        if (is_array($gateway->orderStatusToCapture) && in_array($order->getOrderStatus()->handle, $gateway->orderStatusToCapture)) {
                            $child = CommercePlugin::getInstance()->getPayments()->captureTransaction($transaction);
                            if ($child->status == TransactionRecord::STATUS_SUCCESS) {
                                $child->order->updateOrderPaidInformation();
                            }
                        }
                    }
                }
            }
        );
    }
}
