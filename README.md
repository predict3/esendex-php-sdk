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

#### Sending to several recipients in one batch
```php5
$results = $service->sendMultiple([
    new \Esendex\Model\DispatchMessage("WebApp", "33600000001", "Hello", \Esendex\Model\Message::SmsType),
    new \Esendex\Model\DispatchMessage("WebApp", "33600000002", "Hello", \Esendex\Model\Message::SmsType),
]);

foreach ($results as $result) {
    print "{$result->id()} in batch {$result->batchid()}";
}
```

#### Opt-outs
```php5
$optOutsService = new \Esendex\OptOutsService($authentication);

// Register an opt-out
$optOutsService->add("EX000000", "33600000001");

// List the opt-outs of the authenticated account, page by page
$page = $optOutsService->getMyAccountReference(1, 25);
foreach ($page->optOuts() as $optOut) {
    print "{$optOut->from()->phoneNumber()} at {$optOut->receivedAt()->format('c')}";
}
```

#### Sent messages and batches
```php5
$sentMessages = new \Esendex\SentMessagesService($authentication);
$page = $sentMessages->loadMessages([
    "start" => "2026-01-01T00:00:00+00:00",
    "finish" => "2026-02-01T00:00:00+00:00",
    "startIndex" => 0,
    "count" => 100,
]);

$batches = new \Esendex\MessageBatchesService($authentication);
$messages = $batches->messages("batch-id");

$credits = (new \Esendex\DispatchService($authentication))->getCredits();
```

## Scope of the Predict fork
Since 3.8 the SDK only keeps the features used by Predict projects:

| Service | Methods |
|---|---|
| `DispatchService` | `send`, `sendMultiple`, `getCredits` |
| `OptOutsService` | `add`, `getMyAccountReference` |
| `MessageBatchesService` | `messages` |
| `MessageHeaderService` | `message` |
| `SentMessagesService` | `loadMessages` |
| `Http\HttpClient` | `get`, `post` |

The Inbox, Account, Session, CheckAccess, MessageBody, MessageInformation and Survey services were removed.
Class names, namespaces and method signatures of the kept API are unchanged: `test/Esendex/Compat/ConsumerContractTest.php`
pins the calls made by the projects and must stay green.

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

A suite of tests can be found in the `test` directory. To run them:
```bash
$ vendor/bin/phpunit
```

`phpunit.xml` routes every HTTP request through a dead proxy (`127.0.0.1:9`) and all services are tested with a fake
`IHttp` client: running the test suite can never send a real SMS. Never add tests that call the real Esendex API.

## Issues
We hope you don't run into any issues but if you should please make use of the issue tracker on [github](https://github.com/esendex/esendex-php-sdk/issues) or send an email to [support@esendex.com](mailto:support@esendex.com)

## Feedback
Let us know what you think [@esendex](http://twitter.com/esendex)
