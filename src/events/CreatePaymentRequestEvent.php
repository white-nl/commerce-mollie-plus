<?php

namespace white\commerce\mollie\plus\events;

use craft\commerce\models\Transaction;
use yii\base\Event;

class CreatePaymentRequestEvent extends Event
{
    /**
     * @var array
     */
    public array $request = [];

    /**
     * @var Transaction
     */
    public Transaction $transaction;
}
