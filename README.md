Commerce Mollie Plus Plugin
===========================

Mollie Plus plugin for Craft CMS, official version by WHITE Digital Agency

## Requirements

* This plugin requires Craft CMS **5.0.0** or later.
* This plugin requires Craft Commerce version **5.0** or later.
* A valid Mollie account is required. Don't have an account yet? [Create a Mollie account](https://www.mollie.com/dashboard/signup/white?lang=en).
* The Craft website should be publicly accessible.
* To configure the plugin, changing settings should be allowed in Craft (allow admin changes), and a user who is an Admin in Craft.

## Installation

To install Mollie Plus plugin for Craft CMS, follow these steps:

1. Open your terminal and go to your Craft project:  
   `cd /path/to/project`

2. Then tell Composer to load the plugin:  
   `composer require white-nl/commerce-mollie-plus`

3. Install the plugin via the CLI:  
   `./craft plugin/install commerce-mollie-plus`

You can also install the Mollie Plus plugin using the Plugin Store in the Craft Control Panel. Go to Settings → Plugins and click the “Install” button for Commerce Mollie Plus.

## Customize mollie payload
You can change the payload that's been sent to Mollie by hooking into the event

``` PHP
use white\commerce\mollie\plus\gateways;
use white\commerce\mollie\plus\events\CreatePaymentRequestEvent;

Event::on(
   Gateway::class,
   Gateway::EVENT_CREATE_PAYMENT_REQUEST,
   function (CreatePaymentRequestEvent $event): void
   {
         $event->request['orderNumber'] = $event->transaction->getOrder()->getId();
   }
);
```

## "Mollie Plus" versus "Mollie for Craft Commerce"

Mollie plus is a drop-in replacement[^1] for Pixel & Tonic’s Mollie for Commerce plugin. We advise to replace it by Mollie Plus. It will be a seamless transition.

(Don't directly replace your current Mollie gateway, instead create a new gateway as the webhook handling depends on different responses)

[^1]: The plugin will run completely independently of the Mollie plugin. You even can run both plugins at the same time.

## Documentation

https://white.nl/en/craft-plugins/mollie/docs/

*Mollie Plus for Craft CMS is brought to you by WHITE Digital Agency*
