# Release Notes for Commerce Mollie Plus Plugin

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
