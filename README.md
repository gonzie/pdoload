[![Travis CI build](https://travis-ci.org/gonzie/pdoload.svg?branch=master)]()
[![PHP from Packagist](https://img.shields.io/packagist/php-v/gonzie/pdoload.svg)]()
[![License from pugx](https://poser.pugx.org/gonzie/pdoload/license.svg)]()

# PDOLoad
***PDOLoad*** is a small PDO wrapper/abstraction layer to provide read/write endpoints and load balancing capabilities in one line of code. Load balancing is usually include within most major PHP Frameworks however when working with large legacy projects it's hard to come by.

**Main Features**
* Add multiple read and write endpoints.
* ***One line implementation***. No need to rewrite your PDO queries.
* Transaction aware. Any reads while a transaction is active will be performed on the write connection.


## Getting started

* PHP >= 7.0 is required
* Install PDOLoad using composer (recommmended) or manually
* Configure your connections and you're ready to go!


## Installing with Composer

```
composer require gonzie/pdoload
```

## How to use

PDOLoad gives you as much choice as possible with little to no modification required.
A normal PDO connection would be set like:
```php
$dbh = new PDO('mysql:host=localhost;dbname=testdb;charset=utf8mb4', 'username', 'password');
```
(most basic set up)


To use a minimalistic version of PDOLoad you would amend the code as follows:
```php
$dbh = new Gonzie\PDOLoad\PDOLoad('mysql:host=localhost;dbname=testdb;charset=utf8mb4', 'username', 'password');
```


Alternatively, to use PDOLoad to it's full abilities, and for what it was actually made for, you can set up by passing an `array` with settings.

```php
<?php
$settings = [
    'driver' => 'mysql',
    'reader' => [
        [
            'host' => 'mysuperduperdbreader_1.com',
            'dbname' =>  'test',
            'user' => 'gonzie',
            'password' => 'password',
            'port' => '3306'
            'charset' => 'utf8mb4',
        ],
        [
            'host' => 'mysuperduperdbreader_2.com',
            'dbname' =>  'test',
            'user' => 'gonzie',
            'password' => 'password',
            'charset' => 'utf8mb4',
        ],
    ],
    'writer' => [
        [
            'host' => 'mysuperduperdbwriter_1.com',
            'dbname' =>  'test',
            'user' => 'gonzie',
            'password' => 'password',
            'charset' => 'utf8mb4',
        ],
    ],
    'balancer' => 'round-robin',
];

$dbh = new Gonzie\PDOLoad\PDOLoad($settings);
```

Easy right?
Here's a breakdown of the settings array:

Field  | Type | Description
------------- | ------------- | -------------
driver  | string | Optional, default is `mysql`. Available options: `pdo_mysql, drizzle_pdo_mysql, mysqli, pdo_sqlite, pdo_pgsql, pdo_oci, pdo_sqlsrv, sqlsrv, oci8, sqlanywhere`
reader  | array[[connection](#connection-array "Goto connection-array")] | Required. Array of `connection` arrays.
writer  | array[[connection](#connection-array "Goto connection-array")] | Required. Array of `connection` arrays.
balancer  | string | Optional, default is ` `. Available options: `round-robin` - go from top to bottom of connection's array, `fixed` - PDOLoad will pick a connection at random and stick to it, `random` - each query will pick a random connection.
overwrite_allowed  | string | Optional, default is `false`. Some `driver` options require additional options other than the ones in the `connection` array, set this value to `true` if you need to add custom options to the array otherwise they won't be allowed.

### Connection array
Other options can be added to your liking as long as `overwrite_allowed` is set to `true`.

Field  | Type | Description
------------- | ------------- | -------------
host  | string | Required.
dbname  | string | Optional.
user  | string | Required.
password  | string | Required.
charset  | string | Optional, default is `utf8mb4`.




## Contributing
Any changes to the Project must abide by PSR Standards (PSR 2 - 12, PSR4 and others where applicable).

Please run phpcs before committing any code.
```
./vendor/bin/phpcs --standard=psr2 src/
```
