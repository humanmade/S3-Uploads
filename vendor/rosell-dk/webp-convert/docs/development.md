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

## Committing
Before committing, first make sure to:
- run `composer ci`

## Releasing
Before releasing:
- Update the version number in `Converters/AbstractConverter.php` (search for "WebP Convert")
- Make sure that travis build is successful

When releasing:
- update the [webp-convert-concat](https://github.com/rosell-dk/webp-convert-concat) library
- consider updating the require in the composer file in libraries that uses webp-convert (ie `webp-convert-cloud-service` and `webp-express`)
