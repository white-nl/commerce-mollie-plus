<?php

namespace white\commerce\mollie\plus\gateways;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use Omnipay\Common\Message\AbstractRequest;
use white\commerce\mollie\plus\models\RequestResponse;
use craft\commerce\omnipay\base\OffsiteGateway;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\web\Response;
use craft\web\View;
use white\commerce\mollie\plus\models\forms\MollieOffsitePaymentForm;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Mollie\Gateway as OmnipayGateway;
use yii\base\NotSupportedException;

class Gateway extends OffsiteGateway
{
    /**
     * @var string
     */
    public $apiKey;

    /**
     * @var array|string[]
     */
    public $orderStatusToCapture = [];

    /**
     * @inheritdoc
     */
    public $sendCartInfo = true;

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
    public function rules()
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
    public function getSettingsHtml()
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
    public function getTransactionHashFromWebhook()
    {
        return Craft::$app->getRequest()->getParam('commerceTransactionHash');
    }
    
    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        try {
            $defaults = [
                'gateway' => $this,
                'paymentForm' => $this->getPaymentFormModel(),
                'paymentMethods' => $this->fetchPaymentMethods(['resource' => 'orders']),
                'issuers' => $this->fetchIssuers(),
            ];
        } catch (\Throwable $exception) {
            // In case this is not allowed for the account
            return parent::getPaymentFormHtml($params);
        }

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('commerce-mollie-plus/paymentForm', $params);
        $view->setTemplateMode($previousMode);

        return $html;
    }

    /**
     * @param array $parameters
     * @return mixed
     */
    public function fetchPaymentMethods(array $parameters = [])
    {
        $paymentMethodsRequest = $this->createGateway()->fetchPaymentMethods($parameters);

        return $paymentMethodsRequest->sendData($paymentMethodsRequest->getData())->getPaymentMethods();
    }

    /**
     * @param array $parameters
     * @return mixed
     */
    public function fetchIssuers(array $parameters = [])
    {
        $issuersRequest = $this->createGateway()->fetchIssuers($parameters);

        return $issuersRequest->sendData($issuersRequest->getData())->getIssuers();
    }

    /**
     * @inheritdoc
     */
    public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
    {
        if ($paymentForm) {
            /** @var MollieOffsitePaymentForm $paymentForm */
            if ($paymentForm->paymentMethod) {
                $request['paymentMethod'] = $paymentForm->paymentMethod;
            }

            if ($paymentForm->issuer) {
                $request['issuer'] = $paymentForm->issuer;
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
        
        $request = $mollieGateway->createOrder($request);

        return $request;
    }

    /**
     * @inheritdoc
     */
    protected function prepareRefundRequest($request, string $reference): RequestInterface
    {
        $res = $this->gateway()
            ->fetchOrder(['transactionReference' => $reference, 'includePayments' => true])
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
        
        /** @var AbstractRequest $refundRequest */
        $refundRequest = $this->gateway()->refund($request);
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

        $request = $mollieGateway->completeOrder($request);

        return $request;
    }

    /**
     * @inheritdoc
     */
    protected function prepareCaptureRequest($request, string $reference): RequestInterface
    {
        /** @var OmnipayGateway $mollieGateway */
        $mollieGateway = $this->gateway();

        $request['transactionReference'] = $reference;
        $request = $mollieGateway->createShipment($request);

        return $request;
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

        if (!$transaction) {
            Craft::warning('Transaction with the hash “'.$transactionHash.'“ not found.', 'commerce');
            $response->data = 'ok';

            return $response;
        }

        $gateway = $this->createGateway();

        $request = $gateway->fetchOrder(['transactionReference' => $transaction->reference]);
        $res = $request->send();

        if (!$res->isSuccessful()) {
            Craft::warning('Mollie request was unsuccessful.', 'commerce');
            $response->data = 'ok';

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
                    if ($authorizeTransaction) {
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
                Craft::warning('Successful capture child transaction for “'.$transactionHash.'“ already exists.', 'commerce');
                $response->data = 'ok';

                return $response;
            }
            
            $childTransaction->type = TransactionRecord::TYPE_CAPTURE;
            $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
        } else if ($res->isAuthorized()) {
            // Check to see if a successful authorize child transaction already exist and skip out early if they do
            $successfulAuthorizeChildTransaction = TransactionRecord::find()->where([
                'parentId' => $transaction->id,
                'status' => TransactionRecord::STATUS_SUCCESS,
                'type' => TransactionRecord::TYPE_AUTHORIZE,
            ])->count();

            if ($successfulAuthorizeChildTransaction) {
                Craft::warning('Successful authorize child transaction for “'.$transactionHash.'“ already exists.', 'commerce');
                $response->data = 'ok';

                return $response;
            }

            $childTransaction->type = TransactionRecord::TYPE_AUTHORIZE;
            $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
        } else if ($res->isExpired()) {
            $childTransaction->status = TransactionRecord::STATUS_FAILED;
        } else if ($res->isCancelled()) {
            $childTransaction->status = TransactionRecord::STATUS_FAILED;
        } else if (isset($this->data['status']) && 'failed' === $this->data['status']) {
            $childTransaction->status = TransactionRecord::STATUS_FAILED;
        } else {
            $response->data = 'ok';
            return $response;
        }

        $childTransaction->response = $res->getData();
        $childTransaction->code = $res->getTransactionId();
        $childTransaction->reference = $res->getTransactionReference();
        $childTransaction->message = $res->getMessage();
        Commerce::getInstance()->getTransactions()->saveTransaction($childTransaction);

        $response->data = 'ok';


        Craft::info('Transaction created: #'.$childTransaction->id, 'commerce-mollie-plus');

        return $response;
    }

    public function getOrderStatusOptions($optional = null)
    {
        $statuses = Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();
        $options = [];

        if ($optional !== null) {
            $options[] = ['value' => null, 'label' => $optional];
        }

        foreach ($statuses as $status) {
            $options[] = ['value' => $status->handle, 'label' => $status->displayName];
        }

        return $options;
    }
    
    /**
     * @inheritdoc
     */
    protected function createPaymentRequest(Transaction $transaction, $card = null, $itemBag = null): array
    {
        if ($card !== null) {
            $card->setPhone(null);
        }
        
        $request = parent::createPaymentRequest($transaction, $card, $itemBag);
	    $referenceTemplate = Commerce::getInstance()->getSettings()->orderReferenceFormat;

	    try {
		    $orderNumber = Craft::$app->getView()->renderObjectTemplate($referenceTemplate, $transaction->order);
	    } catch (Throwable $exception) {
		    $orderNumber = $transaction->order->number;
	    }

	    $request['orderNumber'] = $orderNumber;
        
        if (!empty($transaction->note)) {
            $request['description'] = $transaction->note;
        }

        $orderLanguage = $transaction->order->orderLanguage;
        $orderLanguage = str_replace('-', '_', $orderLanguage);

        // If the Craft locale isn't language specific, add it
        if (!str_contains($orderLanguage, '_')) {
            $orderLanguage = $orderLanguage . '_' . strtoupper($orderLanguage);
        }

        // Check if the $orderLanguage is in the list of Mollie languages
        // Otherwise fallback to en_US
        if (!in_array($orderLanguage, [
            'en_US',
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
            'lt_LT'
        ])) {
            $orderLanguage = 'en_US';
        }

        $request['locale'] = $orderLanguage;

        return $request;
    }

    /**
     * @inheritdoc
     */
    protected function getItemListForOrder(Order $order): array
    {
        $items = [];

        $priceCheck = 0;
        $count = -1;

        foreach ($order->getLineItems() as $item) {
            $price = Currency::round($item->salePrice);
            
            if ($price !== 0) {
                $count++;
                /** @var Purchasable $purchasable */
                $purchasable = $item->getPurchasable();
                $defaultDescription = Craft::t('commerce', 'Item ID').' '.$item->id;
                $purchasableDescription = $purchasable ? $purchasable->getDescription() : $defaultDescription;
                $description = isset($item->snapshot['description']) ? $item->snapshot['description'] : $purchasableDescription;
                $description = empty($description) ? 'Item '.$count : $description;

                $totalTax = $item->getTaxIncluded();
                
                $vatRate = null;
                foreach ($item->getAdjustments() as $adjustment) {
                    if ($adjustment->type == 'tax' && $adjustment->included) {
                        $snapshot = $adjustment->getSourceSnapshot();
                        if (isset($snapshot['rate'])) {
                            $vatRate = $snapshot['rate'];
                            break;
                        }
                    }
                }

                if ($vatRate === null) {
                    $vatRate = ($totalTax) / (($price * $item->qty) - $totalTax);
                }
                
                $items[] = [
                    'name' => $description,
                    'description' => $description,
                    'quantity' => $item->qty,
                    'price' => $price,
                    'unitPrice' => $price,
                    'vatRate' => sprintf('%0.2f', $vatRate * 100),
                    'vatAmount' => $totalTax,
                ];

                $priceCheck += ($item->qty * $item->salePrice);
            }
        }

        $count = -1;

        foreach ($order->getAdjustments() as $adjustment) {
            $price = Currency::round($adjustment->amount);
            if (($adjustment->included == 0 || $adjustment->included == false) && $price !== 0) {
                $count++;
                $items[] = [
                    'name' => empty($adjustment->name) ? $adjustment->type." ".$count : $adjustment->name,
                    'description' => empty($adjustment->description) ? $adjustment->type.' '.$count : $adjustment->description,
                    'quantity' => 1,
                    'price' => $price,
                    'unitPrice' => $price,
                    'vatRate' => '0.00',
                ];
                $priceCheck += $adjustment->amount;
            }
        }

        $priceCheck = Currency::round($priceCheck);
        $totalPrice = Currency::round($order->totalPrice);
        $same = (bool)($priceCheck === $totalPrice);

        if (!$same) {
            Craft::error('Item bag total price does not equal the orders totalPrice, some payment gateways will complain.', __METHOD__);
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function createItemBagForOrder(Order $order)
    {
        return $this->getItemListForOrder($order);
    }

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());

        $gateway->setApiKey(Craft::parseEnv($this->apiKey));

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
    protected function getGatewayClassName()
    {
        return '\\' . OmnipayGateway::class;
    }

    /**
     * @inheritdoc
     */
    protected function prepareResponse(ResponseInterface $response, Transaction $transaction): RequestResponseInterface
    {
        return new RequestResponse($response, $transaction);
    }
}
