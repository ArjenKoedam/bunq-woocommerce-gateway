
# Changelog

All notable changes to the Bunq Payment Gateway for WooCommerce will be documented in this file.

## [1.1.0] - 2026-01-29

### Added
- Configurable Bunq username setting in admin panel
- Customizable payment URL template with %s placeholders
- Validation for Bunq username (required field)
- Validation for URL template (must contain exactly 4 %s placeholders)
- URL format validation for payment link templates

### Changed
- Bunq username is now configurable via WooCommerce settings instead of hardcoded
- Payment URL generation now uses customizable template
- Documentation updated with configuration examples and FAQ updates

### Improved
- Plugin is now more flexible for different merchants and payment URL formats
- No code modifications needed to change Bunq username or URL format
- Better error messages for invalid configurations
- Enhanced admin settings UI with helpful descriptions

## [1.0.0] - 2024-11-22

### Added
- Initial release of Bunq Payment Gateway for WooCommerce
- Support for three payment methods: iDeal, Credit Card, and Bancontact
- Payment method logos displayed at checkout
- Responsive design for mobile, tablet, and desktop devices
- Admin settings page with configuration options
- Manual payment confirmation from order admin page
- Test mode for development and testing
- Debug logging functionality
- Auto-complete orders option
- Accessibility features (keyboard navigation, screen reader support)
- Dark mode and high contrast mode CSS support
- Comprehensive README with installation and usage instructions
- HPOS (High-Performance Order Storage) compatibility
- WordPress and WooCommerce coding standards compliance

### Payment Features
- Automatic Bunq.me URL generation based on order amount and selected payment method
- Order status management (Pending → Processing/Completed)
- Return URL handling after payment
- Order notes for payment tracking

### Security
- Nonce protection for admin actions
- Capability checks for administrative functions
- Secure payment data handling via Bunq platform

### Developer Features
- Clean, well-documented code
- Extensible architecture
- Standard WordPress hooks and filters
- Logging for debugging
