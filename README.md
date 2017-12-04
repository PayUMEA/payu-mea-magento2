# PayU MEA Magento 2 extension #

This guide details how to install the PayU MEA Magento v2.0+ / v2.1+ extension

## Prerequisites
* Magento 2.0 or 2.1 installed application
* SSH access

## Dependencies

The extension requires the following extension in order to work properly:

- [`soap`](https://php.net/manual/en/book.soap.php)
- [`json`](https://php.net/manual/en/book.json.php)

## Installation

You can install the extension via [Composer](http://getcomposer.org/). Run the following command:

```bash
composer require payumea/payu-mea-magento2
```
or add
```bash
payumea/payu-mea-magento2
```
to the **require** section of your composer.json and run `composer update`. After installing the extension you need 
to enable the extension by excuting the following command.

```bash
bin/magento module:enable --clear-static-content PayU_EasyPlus
bin/magento setup:upgrade
bin/magento cache:clean
```
## Configuration
To configure the extension, you have to navigate to **Stores > Configuration > Sales > Payment Methods** and find PayU 
extension listed among other payment methods

### Who do I talk to if I run into any issue? ###

* Just send an email to **support@payu.co.za** or open an issue using the issue tracker