<?php

namespace white\commerce\mollie\plus;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\events\TransactionEvent;
use craft\commerce\records\Transaction;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderHistories;
use craft\commerce\services\Payments;
use craft\events\RegisterComponentTypesEvent;
use nystudio107\codeeditor\autocompletes\CraftApiAutocomplete;
use nystudio107\codeeditor\autocompletes\TwigLanguageAutocomplete;
use nystudio107\codeeditor\events\RegisterCodeEditorAutocompletesEvent;
use nystudio107\codeeditor\services\AutocompleteService;
use white\commerce\mollie\plus\gateways\Gateway;
use yii\base\Event;

/**
 */
class CommerceMolliePlusPlugin extends Plugin
{
    public string $schemaVersion = '1.0.1';

    public function init(): void
    {
        parent::init();

        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function(RegisterComponentTypesEvent $event): void {
                $event->types[] = Gateway::class;
            }
        );

        $this->registerCodeEditorAutocompletes();
        $this->registerOrderEventListeners();
    }

    protected function registerCodeEditorAutocompletes(): void
    {
        Event::on(
            AutocompleteService::class,
            AutocompleteService::EVENT_REGISTER_CODEEDITOR_AUTOCOMPLETES,
            function(RegisterCodeEditorAutocompletesEvent $event) {
                if ($event->fieldType === 'MollieOrderField') {
                    $config = [
                        'elementRouteGlobals' => [
                            'order' => new Order(),
                        ],
                    ];
                    $event->types = [];
                    $event->types[] = [CraftApiAutocomplete::class => $config];
                    $event->types[] = TwigLanguageAutocomplete::class;
                }
            }
        );
    }

    protected function registerOrderEventListeners(): void
    {
        Event::on(
            OrderHistories::class,
            OrderHistories::EVENT_ORDER_STATUS_CHANGE,
            function(OrderStatusEvent $event) {
                $order = $event->order;
                $orderHistory = $event->orderHistory;

                $newStatus = $orderHistory->getNewStatus()->handle;

                $transaction = $order->getLastTransaction();
                $gateway = $transaction?->getGateway();
                if (
                    $gateway instanceof Gateway &&
                    $transaction->canCapture() &&
                    $transaction->type === Transaction::TYPE_AUTHORIZE &&
                    $transaction->status === Transaction::STATUS_SUCCESS &&
                    in_array($newStatus, $gateway->orderStatusToCapture)
                ) {
                    $gateway->createShipment($transaction->reference, $order);
                }
            }
        );

        Event::on(
            Order::class,
            Order::EVENT_BEFORE_COMPLETE_ORDER,
            function(Event $event): void {
                /** @var Order $order */
                $order = $event->sender;

                $transaction = $order->getLastTransaction();
                $gateway = $transaction?->getGateway();
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
        );

        Event::on(
            Payments::class,
            Payments::EVENT_BEFORE_CAPTURE_TRANSACTION,
            static function(TransactionEvent $event): void {
                $transaction = $event->transaction;
                $gateway = $transaction->getGateway();
                if ($gateway instanceof Gateway) {
                    $parentTransaction = $transaction->getParent();
                    $transactionLockName = 'mollieTransaction:' . $parentTransaction->hash;
                    $mutex = Craft::$app->getMutex();

                    if (!$mutex->acquire($transactionLockName, 15)) {
                        throw new \Exception('Unable to acquire a lock for transaction: ' . $parentTransaction->hash);
                    }
                }
            }
        );

        Event::on(
            Payments::class,
            Payments::EVENT_AFTER_CAPTURE_TRANSACTION,
            static function(TransactionEvent $event): void {
                $transaction = $event->transaction;
                $gateway = $transaction->getGateway();
                if ($gateway instanceof Gateway) {
                    $parentTransaction = $transaction->getParent();
                    $transactionLockName = 'mollieTransaction:' . $parentTransaction->code;
                    $mutex = Craft::$app->getMutex();
                    $mutex->release($transactionLockName);
                }
            }
        );
    }
}
