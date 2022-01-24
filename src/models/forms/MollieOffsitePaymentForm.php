<?php

namespace white\commerce\mollie\plus\models\forms;

use Craft;
use craft\commerce\models\payments\BasePaymentForm;

class MollieOffsitePaymentForm extends BasePaymentForm
{
    public $paymentMethod;

    public $issuer;
}
