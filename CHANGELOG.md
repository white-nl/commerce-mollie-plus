# Release Notes for Commerce Mollie Plus Plugin

## 1.2.2 - 2023-02-20

### Fixed
- Fixed a error that would occour when completing an order without transactions

## 1.2.1 - 2023-02-08

### Fixed
- Fixed an issue with getting the refund response

## 1.2.0 - 2023-01-23

### Added
- The `fetchPaymentMethods()` method is now also returning a `logo` object containing the SVG logo from the payment method.
- Added a `completeBanktransferOrders` setting, that if enabled will automatically complete orders with a pending banktransfer transactions. Defaults to `false` which will only mark the cart as completted once the banktransfer is being completed. ([#12](https://github.com/white-nl/commerce-mollie-plus/issues/12))

### Changed
- The `fetchPaymentMethods()` function will now return an empty array when unable to fetch the methods instead of throwing an exception which could trigger a server error

## 1.1.2 - 2022-07-04

### Changed
- Update the styling of the example template

## 1.1.1 - 2022-06-29

### Fixed 
- Fixed an error thrown on PHP > 8

## 1.1.0 - 2022-06-29

### Added
- Added support for [Mollie components](https://docs.mollie.com/components/overview)

### Changed
- require omnipay/mollie package ^5.5

## 1.0.3 - 2022-05-30

### Added
- Added en_GB language support

### Fixed
- Use discountAmount on lineItem to set the discount instead of seperate lineItem ([#4](https://github.com/white-nl/commerce-mollie-plus/issues/4))

## 1.0.2 - 2022-04-07

### Added
- `Gateway::EVENT_CREATE_PAYMENT_REQUEST` event added that allows payment request modifications.

## 1.0.1 - 2022-01-27

- Craft Commerce Omnipay package dependency updated.

## 1.0.0 - 2022-01-24

- Initial release.
