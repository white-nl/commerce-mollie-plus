<?php

namespace white\commerce\mollie\plus\web\assets;

use craft\web\AssetBundle;

class MollieFormAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__;

        $this->js = [
            'js/paymentForm.js',
        ];

        parent::init();
    }
}
