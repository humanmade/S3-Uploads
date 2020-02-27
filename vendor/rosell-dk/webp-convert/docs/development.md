# Development

## Setting up the environment.

First, clone the repository:
```
cd whatever/folder/you/want
git clone https://github.com/rosell-dk/webp-convert.git
```

Then install the dev tools with composer:

```
composer install
```

## The builds
For those old-schoolers that prefers one packaged file containing all the code - easily uploaded via ftp - we are maintaining `build/webp-convert.inc`.

It is an aggregation of all the php files needed, with base classes on top. It also includes the files in vendor/rosell-dk/image-mime-type-guesser.

We also maintain `build/webp-on-demand-1.inc` (which only consists of a few classes) and `build/webp-on-demand-2.inc` (which is loaded by webp-on-demand-2, when a conversion is needed, and contains the rest of the library).

Whenever code is changed in `src` - or at least, whenever a new release is released, we must rebuild these files. This can be done like this:

```
composer build
```

This runs `build-scripts/build-webp-on-demand.php`.
That file needs maintaining when new base classes arrives, new folders, or new dependencies.


## Unit Testing
To run all the unit tests do this:
```
composer test
```
This also runs tests on the builds.


Individual test files can be executed like this:
```
composer phpunit tests/Convert/Converters/WPCTest
composer phpunit tests/Serve/ServeConvertedTest
```


## Coding styles
WebPConvert complies with the [PSR-2](https://www.php-fig.org/psr/psr-2/) coding standard.

To validate coding style of all files, do this:
```
composer phpcs src
```

To automatically fix the coding style of all files, using [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer), do this:
```
composer phpcbf src
```

Or, alternatively, you can fix with the use the [PHP-CS-FIXER](https://github.com/FriendsOfPHP/PHP-CS-Fixer) library instead:
```
composer cs-fix
```

## Running all tests in one command
The following script runs the unit tests, checks the coding styles, validates `composer.json` and runs the builds.
Run this before pushing anything to github. "ci" btw stands for *continuous integration*.
```
composer ci
```

## Generating api docs
Install phpdox and run it in the project root:
```
phpdox
```
