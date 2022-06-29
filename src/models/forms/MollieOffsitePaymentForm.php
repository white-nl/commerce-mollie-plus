<?php

namespace white\commerce\mollie\plus\models\forms;

use craft\commerce\models\payments\BasePaymentForm;

class MollieOffsitePaymentForm extends BasePaymentForm
{
    public $paymentMethod;

    public $issuer;

    public $cardToken;
}
