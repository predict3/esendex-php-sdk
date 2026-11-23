esendex-php-sdk (version Predict)
=================================

Bibliothèque d'envoi de SMS utilisée par nos projets (php-lib, unsubscribe-bundle et les applications qui en dépendent).

Depuis la 3.8, elle ne contient que ce que nos projets utilisent réellement, et reste rétrocompatible :
les noms de classes, le namespace `Esendex\` et les signatures des méthodes sont inchangés.

## Installation

Prérequis : PHP 8.3 ou plus, avec les extensions `curl`, `simplexml` et `ctype`.

Le paquet garde le nom `esendex/sdk`. Il faut donc déclarer notre dépôt dans le `composer.json` du projet,
sinon Composer installe la version publique de Packagist, qui n'a pas nos modifications :

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/predict3/esendex-php-sdk.git" }
    ],
    "require": {
        "esendex/sdk": "^3.8"
    }
}
```

## Ce que contient la bibliothèque

| Service | Méthodes | Utilisé par |
|---|---|---|
| `DispatchService` | `send`, `sendMultiple` | envoi des SMS (php-lib et applications) |
| | `getCredits` | healthchecks |
| `OptOutsService` | `add` | commandes d'ajout de désinscription |
| | `getMyAccountReference` | import des désinscriptions (unsubscribe-bundle), healthcheck php-lib |
| `MessageHeaderService` | `message` | statut d'un message (php-lib et applications) |
| `MessageBatchesService` | `messages` | statut des messages d'un lot (php-lib) |
| `SentMessagesService` | `loadMessages` | reporting SMS (via php-lib) |
| `Http\HttpClient` | `get` | appel direct de l'API (monitoring) |

Les erreurs HTTP lèvent une `Exceptions\EsendexException` dont `getCode()` est le code HTTP :
php-lib s'en sert pour distinguer un envoi rejeté (4xx) d'un échec inconnu.

## Exemples

```php
use Esendex\Authentication\LoginAuthentication;
use Esendex\DispatchService;
use Esendex\Model\DispatchMessage;
use Esendex\Model\Message;
use Esendex\Model\MessageBody;
use Esendex\OptOutsService;

$authentication = new LoginAuthentication($account, $login, $password);

// Envoi d'un SMS (plusieurs destinataires possibles, séparés par une virgule)
$result = (new DispatchService($authentication))->send(
    new DispatchMessage($from, '33600000001', 'Alerte', Message::SmsType, 0, 'fr-FR', MessageBody::CharsetGSM)
);
$result->id();
$result->batchid();

// Désinscriptions, page par page
$page = (new OptOutsService($authentication))->getMyAccountReference(1, 25);
foreach ($page->optOuts() as $optOut) {
    $optOut->from()->phoneNumber();
    $optOut->receivedAt();
}
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

- Tous les services sont testés avec un faux client HTTP (`IHttp`)
- `phpunit.xml` fait passer toute requête réelle par un proxy mort (`127.0.0.1:9`)
  `SafetyNetTest` vérifie que cette protection est active

`test/Esendex/Compat/ConsumerContractTest.php` reproduit les appels faits par nos projets.
S'il casse, un projet casse : la modification n'est pas rétrocompatible.

