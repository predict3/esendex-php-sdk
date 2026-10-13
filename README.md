Esendex PHP Client
==================

## Installation

### Requirements
 - PHP 8.3 or later (PHP 8.x)
 - ext-curl, ext-simplexml and ext-ctype enabled

The **esendex-php-sdk** is installed through Composer.

#### Composer
Composer is not included within the repository, so you will need to retrieve it using the following command:
```bash
curl -sS https://getcomposer.org/installer | php
```

For installation on other platforms see [getcomposer.org](https://getcomposer.org)

#### Predict fork
This repository is the Predict fork of the SDK, available at `https://github.com/predict3/esendex-php-sdk.git`.
It keeps the original Composer package name `esendex/sdk`, so it must be declared as a VCS repository in your *composer.json*:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/predict3/esendex-php-sdk.git"
        }
    ]
}
```
or with the following command:
```bash
$ php composer.phar config repositories.esendex-sdk vcs https://github.com/predict3/esendex-php-sdk.git
```

Then run
```bash
$ php composer.phar require esendex/sdk
```
Composer will install the latest stable version of the fork and add the matching version constraint to your *composer.json*.

> **Warning:** the [`esendex/sdk`](https://packagist.org/packages/esendex/sdk) package published on Packagist is the upstream SDK, not this fork.
> Without the VCS repository above, `composer require esendex/sdk` installs the upstream version, which does not include the fork's changes.

Then just include Composer's generated *autoload.php* somewhere in your code;
```php5
require_once 'path/to/vendor/autoload.php';
```

## Getting Started

#### Sending SMS
```php5
$message = new \Esendex\Model\DispatchMessage(
    "WebApp", // Send from
    "01234567890", // Send to any valid number
    "My Web App is SMS enabled!",
    \Esendex\Model\Message::SmsType
);
$authentication = new \Esendex\Authentication\LoginAuthentication(
    "EX000000", // Your Esendex Account Reference
    "user@example.com", // Your login email address
    "password" // Your password
);
$service = new \Esendex\DispatchService($authentication);
$result = $service->send($message);

print $result->id();
print $result->uri();
```

#### Retrieving Inbox Messages
```php5
$authentication = new \Esendex\Authentication\LoginAuthentication(
    "EX000000", // Your Esendex Account Reference
    "user@example.com", // Your login email address
    "password" // Your password
);
$service = new \Esendex\InboxService($authentication);

$result = $service->latest();

print "Total Inbox Messages: {$result->totalCount()}";
print "Fetched: {$result->count()}";
foreach ($result as $message) {
    print "Message from: {$message->originator()}, {$message->summary()}";
}
```
#### Track Message Status
```php5
$authentication = new \Esendex\Authentication\LoginAuthentication(
    "EX000000", // Your Esendex account reference
    "user@example.com", // Your login email
    "password" // Your password
);
$headerService = new \Esendex\MessageHeaderService($authentication);
$message = $headerService->message("messageId");
print_r($message->status());
```

#### Retrieving Full Message Body
```php5
$messageId = "unique-id-of-message";
$authentication = new \Esendex\Authentication\LoginAuthentication(
    "EX000000", // Your Esendex Account Reference
    "user@example.com", // Your login email address
    "password" // Your password
);
$service = new \Esendex\MessageBodyService($authentication);

$result = $service->getMessageBodyById($messageId);

print $result;
```

## Would you like to know more?
Full REST API documentation can be found @ [developers.esendex.com](http://developers.esendex.com/)

## Testing
#### Unit Tests

You will need to install composer in order to acquire the pre-requisites. This can be achieved by the following command:
```bash
$ curl -sS https://getcomposer.org/installer | php
```

To retrieve said pre-requisites execute the following:
```bash
$ composer.phar install
```

A suite of tests can be found in the `test` directory. To run them use the [phing](http://www.phing.info) build utility. e.g.
```bash
$ php vendor/bin/phing
```

#### Credentials Test
You can check your account credentials using a phing task we have provided. First, ensure dependencies have been installed with composer:
```bash
$ composer.phar install
```
Run the `check-access` script:
```bash
$ vendor/bin/phing check-access
Buildfile: /home/developer/esendex-php-sdk/build.xml

EsendexSDK > check-access:

Esendex Username ? user@example.com
Esendex Password ? secret
Account Reference? EX000000

    Account credentials OK!

BUILD FINISHED

Total time: 10.0000 seconds
``` 

## Issues
We hope you don't run into any issues but if you should please make use of the issue tracker on [github](https://github.com/esendex/esendex-php-sdk/issues) or send an email to [support@esendex.com](mailto:support@esendex.com)

## Feedback
Let us know what you think [@esendex](http://twitter.com/esendex)
