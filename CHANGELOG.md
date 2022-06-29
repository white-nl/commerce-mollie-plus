# Release Notes for Commerce Mollie Plus Plugin

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
