[![Travis CI build](https://travis-ci.org/gonzie/pdoload.svg?branch=master)]()
[![PHP from Packagist](https://img.shields.io/packagist/php-v/gonzie/pdoload.svg)]()
[![License from pugx](https://poser.pugx.org/gonzie/pdoload/license.svg)]()

# PDOLoad
***PDOLoad*** is a small PDO wrapper to provide read/write endpoints and load balancing capabilities in one line of code.

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

*Documentation will be updated soon.*


## Contributing
Any changes to the Project must abide by PSR Standards (PSR 2 - 12, PSR4 and others where applicable).

Please run phpcs before committing any code.
```
./vendor/bin/phpcs --standard=psr2 src/
```
