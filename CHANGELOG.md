# Release Notes for Commerce Mollie Plus Plugin

## Unreleased

### Fixed
 - Change strict type comparison of sale price to prevent errors with free lineItems

## 2.6.0 - 2024-09-16

### Fixed
 - Fixed a javascript bug to load the mollie components for credit card payments

### Changed
 - Removed the voucher method from the `fetchPaymentMethods()` response
 - When paying with a different payment currency no tax/shipping costs will be added seperatly to the mollie item to prevent rounding/calculation errors

## 2.5.0 - 2024-08-16

### Fixed

 - Round the converted currencies to prevent precision errors [#22](https://github.com/white-nl/commerce-mollie-plus/issues/22)

## 2.4.0 - 2024-03-26

### Added

 - Added support for multi currency payments

## 2.3.1 - 2024-02-01

- Check the order payment info when processing the webhook if the transaction already exist

## 2.3.0 - 2023-09-27

### Added

- Added the option to send tracking info to mollie.

## 2.2.7 - 2023-05-02

### Fixed
- Fixed an internal server error for orders with no transactions

## 2.2.6 - 2023-05-01

### Fixed
- Fixed and issue where the capturing of Klarna payments would occur twice which results in overpaid orders

## 2.2.5 - 2023-03-22

### Fixed
- Fixed an issue with banktransfers to reset orderStatusId if banktransfer is pending

## 2.2.4 - 2023-03-13

### Fixed
- Fixed an issue with shipping calculating

## 2.2.3 - 2023-02-08

### Fixed
- Fixed an issue with getting the refund response

## 2.2.2 - 2023-01-16

### Changed
- The `fetchPaymentMethods()` function will now return an empty array when unable to fetch the methods instead of throwing an exception which could trigger a server error

## 2.2.1 - 2022-11-28

### Fixed
- Fixed an error that could occur if you complete an order without a transaction

## 2.2.0 - 2022-10-24

### Added
- The `fetchPaymentMethods()` method is now also returning a `logo` object containing the SVG logo from the payment method.
- Added a `completeBanktransferOrders` setting, that if enabled will automatically complete orders with a pending banktransfer transactions. Defaults to `false` which will only mark the cart as completted once the banktransfer is being completed. ([#12](https://github.com/white-nl/commerce-mollie-plus/issues/12))

## 2.1.2 - 2022-07-06

### Changed
- Add the mollie components js file to the asset bundle

## 2.1.1 - 2022-07-04

### Changed
- Update the styling of the example template

## 2.1.0 - 2022-06-29

### Added
- Added support for [Mollie components](https://docs.mollie.com/components/overview)

### Changed
- require omnipay/mollie package ^5.5 

## 2.0.0 - 2022-05-30

### Added
- Added Craft CMS 4 and Craft Commerce 4 compatibility.
- Added en_GB language support

### Fixed
- Use discountAmount on lineItem to set the discount instead of separate lineItem

## 1.0.2 - 2022-04-07

### Added
- `Gateway::EVENT_CREATE_PAYMENT_REQUEST` event added that allows payment request modifications.

## 1.0.1 - 2022-01-27

- Craft Commerce Omnipay package dependency updated.

## 1.0.0 - 2022-01-24

- Initial release.
