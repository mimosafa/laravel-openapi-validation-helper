# Laravel OpenAPI Validation Helper

[日本語のREADMEはこちら](README.ja.md)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mimosafa/laravel-openapi-validation-helper.svg?style=flat-square)](https://packagist.org/packages/mimosafa/laravel-openapi-validation-helper)
[![Total Downloads](https://img.shields.io/packagist/dt/mimosafa/laravel-openapi-validation-helper.svg?style=flat-square)](https://packagist.org/packages/mimosafa/laravel-openapi-validation-helper)

This library provides a helper for transparently validating HTTP requests and responses against an OpenAPI 3.0 schema in Laravel feature tests. It automatically runs validations during your test runs, allowing you to constantly check for discrepancies between your API specification and its implementation.

## Features

- **Transparent Validation**: Utilizes the `RequestHandled` event to introduce validation with almost no changes to your existing test code.
- **Flexible Control**: Easily enable or disable request/response validation on a per-test basis.
- **API Prefix Support**: Handles URL prefixes like `/api` separately from the schema definition.
- **Easy Setup**: Get started by simply using a trait in your `TestCase` and implementing a few methods.
- **Detailed Error Reporting**: Outputs detailed messages on validation failure, indicating which part of the specification was violated.

## Installation

Install via Composer.

```bash
composer require mimosafa/laravel-openapi-validation-helper --dev
```

## Usage

#### 1. Use the Trait

Use the `TestCaseHelper` trait in your base test case (usually `tests/TestCase.php`).

```php
// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LaravelOpenAPIValidationHelper\TestCaseHelper;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use TestCaseHelper; // Add this trait
}
```

#### 2. Call the Setup Method

In the same `tests/TestCase.php`, call `setUpTransparentlyTest()` within the `setUp()` method.

```php
// tests/TestCase.php

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransparentlyTest(); // Call this method
    }
```

#### 3. Implement Abstract Methods

In the test class where you want to perform validation, implement the four abstract methods required to provide validation information. It is often convenient to define properties in the test class and override their values in each test method to set them dynamically.

```php
// tests/Feature/ExampleTest.php

namespace Tests\Feature;

use LaravelOpenAPIValidationHelper\HttpRequestMethod;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Set these properties in each test method
    protected string $prefix = '/api';
    protected string $path = '/users/1';
    protected HttpRequestMethod $operation = HttpRequestMethod::GET;

    protected function prefix(): string
    {
        return $this->prefix;
    }

    protected function path(): string
    {
        return $this->path;
    }

    protected function operation(): HttpRequestMethod
    {
        return $this->operation;
    }

    protected function getValidatorBuilder(): ValidatorBuilder
    {
        // Specify the path to your openapi.yml
        return (new ValidatorBuilder)->fromYamlFile(base_path('tests/openapi.yml'));
    }

    /** @test */
    public function a_user_can_be_retrieved(): void
    {
        // Set properties
        $this->prefix = '/api';
        $this->path = '/users/1';
        $this->operation = HttpRequestMethod::GET;

        // When the test is run, the response is automatically validated
        $this->getJson('/api/users/1')->assertStatus(200);
    }
}
```

## How it Works

This library does not perform validation directly. Instead, it delegates the core validation logic to the `league/openapi-psr7-validator` package. The division of responsibilities is as follows:

### The `TestCaseHelper` Trait (This Library)

-   Acts as a **"bridge"**.
-   It captures the HTTP request and response executed within a Laravel test.
-   It converts them into the PSR-7 standard format that `league/openapi-psr7-validator` can understand.
-   During this conversion, it strips any defined prefix (like `/api`) to align the path with the OpenAPI schema.
-   It then hands over the prepared request and response to the validation engine.

### The `league/openapi-psr7-validator` Package

-   Acts as the **"validation engine"**.
-   It reads the `openapi.yml` file and fully understands the API specification.
-   It automatically determines which schema template (e.g., `/users/{id}`) corresponds to the concrete path of the request (e.g., `/users/456`) passed from `TestCaseHelper`.
-   Based on the matched schema definition, it rigorously checks if the request and response contents (parameters, body, headers, etc.) comply with the specification.

This collaboration allows developers to transparently test for OpenAPI specification compliance simply by writing their Laravel tests as they normally would.

## API

### `ignoreRequestCompliance()`

Temporarily disables request validation for the current test.

```php
public function test_with_invalid_request(): void
{
    $this->ignoreRequestCompliance();

    // Request validation will not run even for an invalid request
    $this->postJson('/api/users', []);
}
```

### `ignoreResponseCompliance()`

Temporarily disables response validation for the current test.

```php
public function test_with_invalid_response(): void
{
    $this->ignoreResponseCompliance();

    // Response validation will not run
    $this->postJson('/api/users', ['generate_invalid_response' => true]);
}
```

## Acknowledgements

This library was heavily inspired by the development blog post "[LaravelアプリケーションのAPIがSwagger/OpenAPIドキュメントに準拠していることを透過的にテストする](https://nextat.co.jp/staff/archives/253)" by 株式会社Nextat（ネクスタット）.

I would like to express my deep gratitude to the author for publishing such a wonderful idea and implementation hints.

## License

This library is open-sourced software licensed under the [MIT license](LICENSE).