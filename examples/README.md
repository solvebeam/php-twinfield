# Examples

Small, self-contained examples. Each folder shows one thing, with an `example.php` you can run and a `README.md` that explains it.

## Setup

1. Register `http://localhost:8765/callback` as redirect URI for your client in the [Twinfield developer portal](https://developers.twinfield.com).
2. Run any example, for instance:

   ```sh
   php examples/offices/list-offices/example.php
   ```

The first time, the example asks for your client ID, client secret and redirect URI, opens the Twinfield login in your browser and asks for a default company. Everything is saved in `~/.config/twinfield/`, so after that examples start right away. Run `composer twinfield:setup` to change these values.

Every example starts the same way:

```php
require __DIR__ . '/../../../vendor/autoload.php';

$twinfield = Example::twinfield();
```

In your own application you create the client yourself: see [Authentication](getting-started/authentication/).

Add `TWINFIELD_DEBUG=1` to any example to see the SOAP requests and responses.

## Getting started

- [Login](getting-started/login/): let a user log in and get the refresh token.
- [Authentication](getting-started/authentication/): create a client in your own application.
- [Token info](getting-started/token-info/): organisation, user and cluster of an access token.
- [Error handling](getting-started/error-handling/): catch errors and act on their code.

## Offices

- [List offices](offices/list-offices/): all offices the user has access to.
- [Switch company](offices/switch-company/): the same call for several companies.

## Activities

- [List activities](activities/list-activities/): code and name of all activities.
- [Search activities](activities/search-activities/): search by name, change date or group.
- [Read activity](activities/read-activity/): all settings of one activity.
- [Create activity](activities/create-activity/): create an activity (changes data).
- [Update activity](activities/update-activity/): change an existing activity (changes data).
- [Delete activity](activities/delete-activity/): delete or hide an activity (changes data).

## Asset methods

- [List asset methods](asset-methods/list-asset-methods/): code and name of all asset methods, or those matching a pattern.
- [Read asset method](asset-methods/read-asset-method/): calculation, accounts and free text fields of one asset method.
- [Create asset method](asset-methods/create-asset-method/): create an asset method (changes data).
- [Update asset method](asset-methods/update-asset-method/): change an existing asset method (changes data).
- [Delete asset method](asset-methods/delete-asset-method/): delete or hide an asset method (changes data).

## Finder

- [Search dimensions](finder/search-dimensions/): search with pattern, options and limit.
- [Paginate](finder/paginate/): go through all results page by page.

## Periods

- [List periods](periods/list-periods/): years of the company and the periods of a year.
- [Rename period](periods/rename-period/): change a period with a command (changes data).

## ProcessXml

- [List companies](process-xml/list-companies/): all companies, with a request without company.
- [Read dimension](process-xml/read-dimension/): read a customer and handle errors.
