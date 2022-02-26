<?php

namespace white\commerce\mollie\plus\events;

use craft\commerce\models\Transaction;
use yii\base\Event;

class CreatePaymentRequestEvent extends Event
{
    /**
     * @var array
     */
    public $request;

    /**
     * @var Transaction
     */
    public $transaction;
}
