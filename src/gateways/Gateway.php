<?php

namespace white\commerce\mollie\plus\gateways;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\omnipay\base\OffsiteGateway;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\App;
use craft\web\Response;
use craft\web\View;
use Exception;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Issuer;
use Omnipay\Common\ItemBag;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Mollie\Gateway as OmnipayGateway;
use Omnipay\Mollie\Item;
use Omnipay\Mollie\Message\Request\FetchTransactionRequest;
use Omnipay\Mollie\Message\Response\FetchOrderResponse;
use Omnipay\Mollie\Message\Response\FetchPaymentMethodsResponse;
use Omnipay\Mollie\Message\Response\FetchTransactionResponse;
use white\commerce\mollie\plus\events\CreatePaymentRequestEvent;
use white\commerce\mollie\plus\models\forms\MollieOffsitePaymentForm;
use white\commerce\mollie\plus\models\RequestResponse;
use white\commerce\mollie\plus\web\assets\MollieFormAsset;
use yii\base\NotSupportedException;

/**
 *
 * @property-write null|string $apiKey
 * @property-write null|string $profileId
 * @property-write bool|string $testmode
 * @property-read null|string $settingsHtml
 */
class Gateway extends OffsiteGateway
{
    /**
     * @var string|null
     */
    private ?string $_apiKey = null;

    /**
     * @var string|null
     */
    private ?string $_profileId = null;

    /**
     * @var bool|string
     */
    private bool|string $_testmode = false;

    /**
     * @var array|string[]
     */
    public array $orderStatusToCapture = [];

    public bool $trackingEnabled = false;

    public string $carrier = '';

    public string $trackAndTrace = '';

    public string $trackingUrl = '';

    /**
     * @var bool
     */
    public bool $completeBanktransferOrders = false;

    /**
     * @var array
     */
    private array $supportedLocales = [
        'en_US',
        'en_GB',
        'nl_NL',
        'nl_BE',
        'fr_FR',
        'fr_BE',
        'de_DE',
        'de_AT',
        'de_CH',
        'es_ES',
        'ca_ES',
        'pt_PT',
        'it_IT',
        'nb_NO',
        'sv_SE',
        'fi_FI',
        'da_DK',
        'is_IS',
        'hu_HU',
        'pl_PL',
        'lv_LV',
        'lt_LT',
    ];

    /**
     * @inheritdoc
     */
    public bool $sendCartInfo = true;

    /**
     * Event to mutate the payment request payload
     * @var string
     */
    public const EVENT_CREATE_PAYMENT_REQUEST = 'createPaymentRequestEvent';

    /**
     * @inheritdoc
     */
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        $settings['apiKey'] = $this->getApiKey(false);
        $settings['profileId'] = $this->getProfileId(false);
        $settings['testMode'] = $this->getTestMode(false);
        $settings['sendCartInfo'] = $this->sendCartInfo;

        return $settings;
    }

    /**
     * @param bool $parse
     * @return string|null
     */
    public function getApiKey(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_apiKey) : $this->_apiKey;
    }

    /**
     * @param string|null $apiKey
     * @return void
     */
    public function setApiKey(?string $apiKey): void
    {
        $this->_apiKey = $apiKey;
    }

    /**
     * @param bool $parse
     * @return string|null
     */
    public function getProfileId(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_profileId) : $this->_profileId;
    }

    /**
     * @param string|null $profileId
     * @return void
     */
    public function setProfileId(?string $profileId): void
    {
        $this->_profileId = $profileId;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getTestMode(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_testmode) : $this->_testmode;
    }

    /**
     * @param bool|string $testMode
     * @return void
     */
    public function setTestMode(bool|string $testMode): void
    {
        $this->_testmode = $testMode;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Mollie Plus');
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = ['paymentType', 'compare', 'compareValue' => 'authorize'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('commerce-mollie-plus/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new MollieOffsitePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionHashFromWebhook(): ?string
    {
        return Craft::$app->getRequest()->getParam('commerceTransactionHash');
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        try {
            $defaults = [
                'gateway' => $this,
                'paymentForm' => $this->getPaymentFormModel(),
                'paymentMethods' => $this->fetchPaymentMethods(['resource' => 'orders']),
                'issuers' => $this->fetchIssuers(),
                'locales' => $this->supportedLocales,
                'handle' => $this->handle,
            ];
        } catch (\Throwable) {
            // In case this is not allowed for the account
            return parent::getPaymentFormHtml($params);
        }

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $view->registerAssetBundle(MollieFormAsset::class);

        $html = $view->renderTemplate('commerce-mollie-plus/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @param array $parameters
     * @return array
     * @throws InvalidRequestException
     */
    public function fetchPaymentMethods(array $parameters = []): array
    {
        /** @var OmnipayGateway $gateway */
        $gateway = $this->createGateway();

        $paymentMethodsRequest = $gateway->fetchPaymentMethods($parameters);

        try {
            /** @var FetchPaymentMethodsResponse $response */
            $response = $paymentMethodsRequest->sendData($paymentMethodsRequest->getData());
        } catch (\Exception) {
        }

        if (!isset($response) || isset($response->getData()['_embedded']["methods"]) === false) {
            return [];
        }

        $paymentMethods = [];
        foreach ($response->getData()['_embedded']["methods"] as $method) {
            if ($method['id'] !== 'voucher') {
                $paymentMethods[] = [
                    'id' => $method['id'],
                    'name' => $method['description'],
                    'logo' => $method['image']['svg'],
                ];
            }
        }

        return $paymentMethods;
    }

    /**
     * @param array $parameters
     * @return Issuer[]
     * @throws InvalidRequestException
     */
    public function fetchIssuers(array $parameters = []): array
    {
        /** @var OmnipayGateway $gateway */
        $gateway = $this->createGateway();
        $issuersRequest = $gateway->fetchIssuers($parameters);

        return $issuersRequest->sendData($issuersRequest->getData())->getIssuers();
    }

    /**
     * @param  array $parameters
     * @return FetchOrderResponse
     */
    public function fetchOrder(array $parameters = [])
    {
        /** @var OmnipayGateway $gateway */
        $gateway = $this->createGateway();
        $orderRequest = $gateway->fetchOrder($parameters);

        return $orderRequest->send();
    }

    /**
     * @param string $id
     * @return FetchTransactionResponse
     */
    public function fetchTransaction(string $id)
    {
        $gateway = $this->createGateway();
        /** @var FetchTransactionRequest $request */
        $request = $gateway->fetchTransaction(['transactionReference' => $id]);
        $res = $request->send();
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function populateRequest(array &$request, BasePaymentForm $paymentForm = null): void
    {
        if ($paymentForm !== null) {
            /** @var MollieOffsitePaymentForm $paymentForm */
            if ($paymentForm->paymentMethod) {
                $request['paymentMethod'] = $paymentForm->paymentMethod;
            }

            if ($paymentForm->issuer) {
                $request['issuer'] = $paymentForm->issuer;
            }

            if ($paymentForm->cardToken) {
                $request['cardToken'] = $paymentForm->cardToken;
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function prepareAuthorizeRequest(array $request): RequestInterface
    {
        /** @var OmnipayGateway $mollieGateway */
        $mollieGateway = $this->gateway();

        /** @var ItemBag $orderItems */
        $orderItems = $request['items'];

        $items = [];
        /** @var Item $orderItem */
        foreach ($orderItems->all() as $orderItem) {
            $items[] = [
                'type' => $orderItem->getType(),
                'name' => $orderItem->getName(),
                'sku' => $orderItem->getSku(),
                'quantity' => $orderItem->getQuantity(),
                'unitPrice' => $orderItem->getUnitPrice(),
                'discountAmount' => $orderItem->getDiscountAmount(),
                'vatRate' => $orderItem->getVatRate(),
                'vatAmount' => $orderItem->getVatAmount(),
                'totalAmount' => $orderItem->getTotalAmount(),
            ];
        }

        $request['items'] = $items;

        return $mollieGateway->createOrder($request);
    }

    /**
     * @inheritdoc
     */
    protected function prepareRefundRequest($request, string $reference): RequestInterface
    {
        /** @var OmnipayGateway $gateway */
        $gateway = $this->gateway();
        $res = $gateway->fetchOrder(['transactionReference' => $reference, 'includePayments' => true])
            ->send();

        if (!$res->isSuccessful()) {
            throw new \Exception(Craft::t('commerce-mollie-plus', 'Mollie Order #{reference} not found.', ['reference' => $reference]));
        }

        // Find a payment suitable for refund
        $data = $res->getData();
        $payments = $data['_embedded']['payments'] ?? [];
        $targetPaymentId = null;
        foreach ($payments as $payment) {
            if ($payment['amountRemaining']['value'] >= $request['amount']) {
                $targetPaymentId = $payment['id'];
            }
        }

        if ($targetPaymentId === null) {
            throw new \Exception(Craft::t('commerce-mollie-plus', 'Unable to find a payment to refund.'));
        }

        $refundRequest = $gateway->refund($request);
        $refundRequest->setTransactionReference($targetPaymentId);

        return $refundRequest;
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        if (!$this->supportsCompleteAuthorize()) {
            throw new NotSupportedException(Craft::t('commerce', 'Completing purchase is not supported by this gateway'));
        }

        $request = $this->createRequest($transaction);
        $request['transactionReference'] = $transaction->reference;
        $completeRequest = $this->prepareCompleteAuthorizeRequest($request);

        return $this->performRequest($completeRequest, $transaction);
    }

    /**
     * @inheritdoc
     */
    protected function prepareCompleteAuthorizeRequest($request): RequestInterface
    {
        /** @var OmnipayGateway $mollieGateway */
        $mollieGateway = $this->gateway();

        return $mollieGateway->completeOrder($request);
    }

    /**
     * @inheritdoc
     */
    protected function prepareCaptureRequest($request, string $reference): RequestInterface
    {
        /** @var OmnipayGateway $mollieGateway */
        $mollieGateway = $this->gateway();

        $request['transactionReference'] = $reference;

        $request = $this->createShipmentRequest($request);

        return $mollieGateway->createShipment($request);
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        $transactionLockName = 'mollieTransaction:' . $reference;
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire($transactionLockName, 15)) {
            throw new Exception('Unable to acquire a lock for transaction: ' . $reference);
        }
        try {
            $capture = parent::capture($transaction, $reference);
            $mutex->release($transactionLockName);
            return $capture;
        } finally {
            $mutex->release($transactionLockName);
        }
    }

    public function createShipment(string $reference, Order $order): void
    {
        /** @var OmnipayGateway $mollieGateway */
        $mollieGateway = $this->gateway();
        $request['transactionReference'] = $reference;
        $request['order'] = $order;
        $request = $this->createShipmentRequest($request);
        $shipmentRequest = $mollieGateway->createShipment($request);
        $shipmentRequest->send();
    }


    /**
     * @inheritdoc
     */
    public function processWebHook(): Response
    {
        $response = Craft::$app->getResponse();
        Craft::debug('Webhook received.', 'commerce-mollie-plus');


        $transactionHash = $this->getTransactionHashFromWebhook();
        $transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionHash);

        if (!$transaction instanceof \craft\commerce\models\Transaction) {
            Craft::warning('Transaction with the hash “' . $transactionHash . '“ not found.', 'commerce');
            $response->data = 'ok';

            return $response;
        }

        $transactionLockName = 'mollieTransaction:' . $transaction->reference;
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire($transactionLockName, 15)) {
            throw new Exception('Unable to acquire a lock for transaction: ' . $transaction->reference);
        }

        /** @var OmnipayGateway $gateway */
        $gateway = $this->createGateway();

        $request = $gateway->fetchOrder(['transactionReference' => $transaction->reference]);
        $res = $request->send();

        if (!$res->isSuccessful()) {
            Craft::warning('Mollie request was unsuccessful.', 'commerce');
            $response->data = 'ok';

            $mutex->release($transactionLockName);
            return $response;
        }

        $childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $transaction);
        $childTransaction->type = $transaction->type;

        Craft::info('Mollie ORDER STATUS received: ' . $res->getStatus(), 'commerce-mollie-plus');

        if ($res->isPaid() || $res->getStatus() === 'completed') {
            // Try to find child successful authorize transaction and if found, make it the parent one
            if ($transaction->status != TransactionRecord::STATUS_SUCCESS) {
                $authorizeTransactionId = TransactionRecord::find()->where([
                    'parentId' => $transaction->id,
                    'status' => TransactionRecord::STATUS_SUCCESS,
                    'type' => TransactionRecord::TYPE_AUTHORIZE,
                ])->limit(1)->select(['id'])->scalar();

                if ($authorizeTransactionId) {
                    $authorizeTransaction = Commerce::getInstance()->getTransactions()->getTransactionById($authorizeTransactionId);
                    if ($authorizeTransaction !== null) {
                        $transaction = $authorizeTransaction;
                        $childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $authorizeTransaction);
                        $childTransaction->type = $transaction->type;
                    }
                }
            }
            $successfulCaptureChildTransaction = TransactionRecord::find()->where([
                'parentId' => $transaction->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => TransactionRecord::TYPE_CAPTURE,
            ])->count();
            if ($successfulCaptureChildTransaction) {
                Craft::warning('Successful capture child transaction for “' . $transactionHash . '“ already exists.', 'commerce');
                $response->data = 'ok';

                $transaction->order->updateOrderPaidInformation();
                $mutex->release($transactionLockName);
                return $response;
            }
            $transactionOrder = $transaction->getOrder();
            $lastTransaction = $transactionOrder->getLastTransaction();
            if (
                $lastTransaction->status === TransactionRecord::STATUS_SUCCESS &&
                $lastTransaction->type === TransactionRecord::TYPE_CAPTURE
            ) {
                Craft::warning('Successful capture child transaction for “' . $transactionHash . '“ already exists.', 'commerce');
                $response->data = 'ok';

                $lastTransaction->order->updateOrderPaidInformation();
                $mutex->release($transactionLockName);
                return $response;
            }
            $childTransaction->type = TransactionRecord::TYPE_CAPTURE;
            $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
        } elseif ($res->isAuthorized()) {
            // Check to see if a successful authorize child transaction already exist and skip out early if they do
            $successfulAuthorizeChildTransaction = TransactionRecord::find()->where([
                'parentId' => $transaction->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => TransactionRecord::TYPE_AUTHORIZE,
            ])->count();
            if ($successfulAuthorizeChildTransaction) {
                Craft::warning('Successful authorize child transaction for “' . $transactionHash . '“ already exists.', 'commerce');
                $response->data = 'ok';

                $mutex->release($transactionLockName);
                return $response;
            }
            $childTransaction->type = TransactionRecord::TYPE_AUTHORIZE;
            $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
        } elseif ($res->isExpired()) {
            $childTransaction->status = TransactionRecord::STATUS_FAILED;
        } elseif ($res->isCancelled()) {
            $childTransaction->status = TransactionRecord::STATUS_FAILED;
        } elseif (isset($this->data['status']) && 'failed' === $this->data['status']) {
            $childTransaction->status = TransactionRecord::STATUS_FAILED;
        } else {
            $response->data = 'ok';

            $mutex->release($transactionLockName);
            return $response;
        }

        $childTransaction->response = $res->getData();
        $childTransaction->code = $res->getTransactionId();
        $childTransaction->reference = $res->getTransactionReference();
        $childTransaction->message = $res->getMessage();
        Commerce::getInstance()->getTransactions()->saveTransaction($childTransaction);

        $response->data = 'ok';


        Craft::info('Transaction created: #' . $childTransaction->id, 'commerce-mollie-plus');

        $mutex->release($transactionLockName);
        return $response;
    }

    /**
     * @return array<int, array{value: string|null, label: mixed}>
     */
    public function getOrderStatusOptions($optional = null): array
    {
        $statuses = Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = [];

        if ($optional !== null) {
            $options[] = ['value' => null, 'label' => $optional];
        }

        foreach ($statuses as $status) {
            $options[] = ['value' => $status->handle, 'label' => $status->getDisplayName()];
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    protected function createPaymentRequest(Transaction $transaction, ?CreditCard $card = null, ?ItemBag $itemBag = null): array
    {
        $card?->setPhone('');

        $request = parent::createPaymentRequest($transaction, $card, $itemBag);
        $request['orderNumber'] = $transaction->getOrder()->number;

        if (!empty($transaction->note)) {
            $request['description'] = $transaction->note;
        }

        $orderLanguage = $transaction->getOrder()->orderLanguage;
        $orderLanguage = str_replace('-', '_', $orderLanguage);

        // If the Craft locale isn't language specific, add it
        if (!str_contains($orderLanguage, '_')) {
            $orderLanguage .= '_' . strtoupper($orderLanguage);
        }

        // Check if the $orderLanguage is in the list of Mollie languages
        // Otherwise fallback to en_US
        if (!in_array($orderLanguage, $this->supportedLocales, true)) {
            $orderLanguage = 'en_US';
        }

        $request['locale'] = $orderLanguage;

        if ($this->hasEventHandlers(static::EVENT_CREATE_PAYMENT_REQUEST)) {
            $event = new CreatePaymentRequestEvent(['request' => $request, 'transaction' => $transaction]);
            $this->trigger(static::EVENT_CREATE_PAYMENT_REQUEST, $event);

            $request = $event->request;
        }

        return $request;
    }

    /**
     * @inheritdoc
     */
    protected function getItemListForOrder(Order $order): array
    {
        $currency = $order->currency;
        $paymentCurrency = $order->paymentCurrency;
        $items = [];

        $priceCheck = 0;
        $count = -1;

        $totalPrice = $order->getPaymentAmount();

        foreach ($order->getLineItems() as $item) {
            if ($item->getSalePrice() != 0) {
                $count++;
                $defaultDescription = Craft::t('commerce', 'Item ID') . ' ' . $item->id;
                $description = !empty($item->getDescription()) ? $item->getDescription() : $defaultDescription;

                $vatRate = null;
                $taxIncluded = false;
                $itemShipping = 0;

                // If the paymentCurrency is not the primary currency don't take VAT in account
                if ($currency !== $paymentCurrency) {
                    $price = Commerce::getInstance()
                        ->getPaymentCurrencies()
                        ->convertCurrency(
                            $item->getTotal(),
                            $currency,
                            $paymentCurrency,
                            true,
                        );
                    $items[] = $this->setMollieLineItem(
                        'physical',
                        $description,
                        $item->qty,
                        Currency::round($price / $item->qty),
                        0,
                        '0.00',
                        0,
                        $price,
                        $item->getSku(),
                    );
                    $priceCheck += $price;
                } else {
                    $price = Commerce::getInstance()
                        ->getPaymentCurrencies()
                        ->convertCurrency(
                            $item->getSalePrice(),
                            $currency,
                            $paymentCurrency,
                            true,
                        );
                    foreach ($item->getAdjustments() as $adjustment) {
                        if ($adjustment->type == 'tax') {
                            if ($adjustment->included) {
                                $taxIncluded = true;
                            }
                            $snapshot = $adjustment->getSourceSnapshot();
                            if (isset($snapshot['rate'])) {
                                $vatRate = $snapshot['rate'];
                                continue;
                            }
                        }
                        if ($adjustment->type == 'shipping') {
                            $itemShipping = Commerce::getInstance()
                                ->getPaymentCurrencies()
                                ->convertCurrency(
                                    $item->getShippingCost() / $item->qty,
                                    $currency,
                                    $paymentCurrency,
                                    true,
                                );
                        }
                    }

                    if ($taxIncluded) {
                        if ($currency === $paymentCurrency) {
                            $totalTax = Commerce::getInstance()
                                ->getPaymentCurrencies()
                                ->convertCurrency(
                                    $item->getTaxIncluded(),
                                    $currency,
                                    $paymentCurrency,
                                    true,
                                );
                        } else {
                            $totalTax = Currency::round($price - ($price / ($vatRate + 1)));
                        }
                    } else {
                        $totalTax = Commerce::getInstance()
                            ->getPaymentCurrencies()
                            ->convertCurrency(
                                $item->getTax(),
                                $currency,
                                $paymentCurrency,
                                true,
                            );
                    }

                    if ($vatRate === null) {
                        $vatRate = ($totalTax) / (($price * $item->qty) - $totalTax);
                    }

                    $totalItemPrice = Commerce::getInstance()
                        ->getPaymentCurrencies()
                        ->convertCurrency(
                            $item->getTotal(),
                            $currency,
                            $paymentCurrency,
                            true,
                        );

                    $vatAmountUnit = 0;
                    if (!$taxIncluded) {
                        $vatAmountUnit = Commerce::getInstance()
                            ->getPaymentCurrencies()
                            ->convertCurrency(
                                $totalTax / $item->qty,
                                $currency,
                                $paymentCurrency,
                                true,
                            );
                    }

                    $discountAmount = Commerce::getInstance()
                        ->getPaymentCurrencies()
                        ->convertCurrency(
                            $item->getDiscount(),
                            $currency,
                            $paymentCurrency,
                            true,
                        );

                    $items[] = $this->setMollieLineItem(
                        'physical',
                        $description,
                        $item->qty,
                        $price + $vatAmountUnit + $itemShipping,
                        abs($discountAmount),
                        sprintf('%0.2f', $vatRate * 100),
                        $totalTax,
                        $totalItemPrice,
                        $item->getSku()
                    );

                    $priceCheck += $totalItemPrice;
                }
            }
        }

        $count = -1;

        $adjustments = $order->getAdjustments();

        foreach ($adjustments as $key => $adjustment) {
            if ($adjustment->included || $adjustment->lineItemId || $adjustment->amount === 0.0) {
                unset($adjustments[$key]);
            }
        }
        foreach ($adjustments as $key => $adjustment) {
            $price = Commerce::getInstance()
                ->getPaymentCurrencies()
                ->convertCurrency(
                    $adjustment->amount,
                    $currency,
                    $paymentCurrency,
                    true,
                );

            $priceCheck += $price;

            $name = empty($adjustment->name) ? $adjustment->type . " " . $count : $adjustment->name . (!empty($adjustment->description) ? " - " . $adjustment->description : '');
            if ($adjustment->type == 'shipping') {
                $count++;
                $items[] = $this->setMollieLineItem(
                    'shipping_fee',
                    $name,
                    1,
                    $price,
                    0.0,
                    '0.00',
                    0.0,
                    $price
                );
            }

            if ($adjustment->type == 'discount') {
                $count++;
                $items[] = $this->setMollieLineItem(
                    'discount',
                    $name,
                    1,
                    $price,
                    0.0,
                    '0.00',
                    0.0,
                    $price
                );
            }

            if ($adjustment->type == 'tax') {
                $count++;
                $items[] = $this->setMollieLineItem(
                    'physical',
                    $name,
                    1,
                    $price,
                    0.0,
                    '0.00',
                    0.0,
                    $price,
                );
            }
        }

        $same = Currency::round($priceCheck) === Currency::round($totalPrice);

        if (!$same) {
            if (
                ($currency !== $paymentCurrency)
                && (
                    (round($priceCheck - $totalPrice, 2) === 0.01)
                    || (round($totalPrice - $priceCheck, 2) === 0.01)
                )
            ) {
                if (round($priceCheck - $totalPrice, 2) === 0.01) {
                    $items[] = $this->setMollieLineItem(
                        'discount',
                        'price conversion correction',
                        1,
                        -0.01,
                        0.0,
                        '0.00',
                        0.00,
                        -0.01,
                    );
                } else {
                    $items[] = $this->setMollieLineItem(
                        'surcharge',
                        'price conversion correction',
                        1,
                        0.01,
                        0.0,
                        '0.00',
                        0.00,
                        0.01,
                    );
                }
            } else {
                Craft::error('Item bag total price does not equal the orders totalPrice, some payment gateways will complain.', __METHOD__);
            }
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function createItemBagForOrder(Order $order): ?ItemBag
    {
        $items = $this->getItemListForOrder($order);
        $itemBagClassName = $this->getItemBagClassName();

        return new $itemBagClassName($items);
    }

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());

        $gateway->setApiKey($this->getApiKey());

        $plugin = Craft::$app->getPlugins()->getPluginInfo('commerce-mollie-plus');
        if ($plugin) {
            $gateway->addVersionString('CraftCommerceMolliePlus/' . $plugin['version']);
        }

        $commerce = Craft::$app->getPlugins()->getPluginInfo('commerce');
        if ($commerce) {
            $gateway->addVersionString('CraftCommerce/' . $commerce['version']);
        }

        $gateway->addVersionString('Craft/' . Craft::$app->getVersion());
        $gateway->addVersionString('uap/eSEEG6szENBK5BjD');

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName(): string
    {
        return '\\' . OmnipayGateway::class;
    }

    /**
     * @inheritdoc
     */
    protected function prepareResponse(ResponseInterface $response, Transaction $transaction): RequestResponseInterface
    {
        /** @var AbstractResponse $response */
        return new RequestResponse($response, $transaction);
    }

    /**
     * @param string $type
     * @param string $name
     * @param int $quantity
     * @param float $unitPrice
     * @param float $discountAmount
     * @param string $vatRate
     * @param float $vatAmount
     * @param float $totalAmount
     * @param string $sku
     * @return Item
     */
    private function setMollieLineItem(string $type, string $name, int $quantity, float $unitPrice, float $discountAmount, string $vatRate, float $vatAmount, float $totalAmount, string $sku = ''): Item
    {
        $mollieItem = new Item();
        $mollieItem->setType($type);
        $mollieItem->setName($name);
        $mollieItem->setSku($sku);
        $mollieItem->setQuantity($quantity);
        $mollieItem->setUnitPrice($unitPrice);
        $mollieItem->setDiscountAmount($discountAmount);
        $mollieItem->setVatRate($vatRate);
        $mollieItem->setVatAmount($vatAmount);
        $mollieItem->setTotalAmount($totalAmount);

        return $mollieItem;
    }

    private function createShipmentRequest(array $request): array
    {
        if ($this->trackingEnabled) {
            $carrierTemplate = $this->carrier;
            $trackAndTraceTemplate = $this->trackAndTrace;
            $trackingUrlTemplate = $this->trackingUrl;

            $vars = ['order' => $request['order']];
            try {
                $carrier = Craft::$app->getView()->renderString($carrierTemplate, $vars);
            } catch (\Throwable $exception) {
                Craft::warning('Unable to get carrier for transaction: ' . $request['transactionReference'], __METHOD__);
            }
            try {
                $trackAndTrace = Craft::$app->getView()->renderString($trackAndTraceTemplate, $vars);
            } catch (\Throwable $exception) {
                Craft::warning('Unable to get track and trace code for transaction: ' . $request['transactionReference'], __METHOD__);
            }
            if ($trackingUrlTemplate !== '') {
                try {
                    $trackingUrl = Craft::$app->getView()->renderString($trackingUrlTemplate, $vars);
                } catch (\Throwable $exception) {
                    Craft::warning('Unable to get trackingUrl code for transaction: ' . $request['transactionReference'], __METHOD__);
                }
            }

            if (!empty($carrier) && !empty($trackAndTrace)) {
                $request['tracking'] = [
                    'carrier' => $carrier,
                    'code' => $trackAndTrace,
                    'url' => $trackingUrl ?? '',
                ];
            }
        }
        return $request;
    }
}
