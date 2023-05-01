<?php

namespace white\commerce\mollie\plus;

use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\records\Transaction;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderHistories;
use craft\events\RegisterComponentTypesEvent;
use white\commerce\mollie\plus\gateways\Gateway;
use yii\base\Event;

/**
 */
class CommerceMolliePlusPlugin extends Plugin
{
    public $schemaVersion = '1.0.1';

    public function init()
    {
        parent::init();

        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );

        $this->registerOrderEventListeners();
    }

    protected function registerOrderEventListeners()
    {
        Event::on(
            OrderHistories::class,
            OrderHistories::EVENT_ORDER_STATUS_CHANGE,
            function(OrderStatusEvent $event) {
                $order = $event->order;
                $orderHistory = $event->orderHistory;

                $newStatus = $orderHistory->getNewStatus()->handle;

                $transaction = $order->getLastTransaction();
                $gateway = $transaction->getGateway();
                if (
                    $gateway instanceof Gateway &&
                    $transaction->canCapture() &&
                    $transaction->type === Transaction::TYPE_AUTHORIZE &&
                    $transaction->status === Transaction::STATUS_SUCCESS &&
                    in_array($newStatus, $gateway->orderStatusToCapture)
                ) {
                    $gateway->createShipment($transaction->reference);
                }
            }
        );

        Event::on(
            Order::class,
            Order::EVENT_BEFORE_COMPLETE_ORDER,
            function(Event $event) {
                /** @var Order $order */
                $order = $event->sender;

                $transaction = $order->getLastTransaction();
                if ($transaction !== null) {
                    $gateway = $transaction->getGateway();
                    if ($gateway instanceof Gateway && !$gateway->completeBanktransferOrders) {
                        if ($transaction->status === Transaction::STATUS_PROCESSING) {
                            $transactionMessage = json_decode($transaction->message);
                            if ($transactionMessage->method === 'banktransfer') {
                                $order->orderStatusId = null;
                                $order->isCompleted = false;
                            }
                        }
                    }
                }
            }
        );
    }
}
