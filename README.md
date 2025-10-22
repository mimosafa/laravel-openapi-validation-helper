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

### Basic Usage

#### 1. Use the Trait

Use the `TestCaseHelper` trait in your base test case (usually `tests/TestCase.php`) or in individual test classes.

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

In your test class's `setUp()` method, call `setUpTransparentlyTest()`.

```php
protected function setUp(): void
{
    parent::setUp();
    $this->setUpTransparentlyTest(); // Call this method
}
```

#### 3. Implement Required Methods

Implement the three required abstract methods for validation. It is often convenient to define properties in the test class and override their values in each test method to set them dynamically.

```php
// tests/Feature/ExampleTest.php

namespace Tests\Feature;

use LaravelOpenAPIValidationHelper\HttpRequestMethod;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Set these properties in each test method
    protected string $path = '/users/1';
    protected HttpRequestMethod $operation = HttpRequestMethod::GET;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransparentlyTest();
    }

    /** @test */
    public function a_user_can_be_retrieved(): void
    {
        // Set properties
        $this->path = '/users/1';
        $this->operation = HttpRequestMethod::GET;

        // Method and URI can be omitted (automatically retrieved from operation() and path())
        $response = $this->json();
        $response->assertStatus(200);

        // Or you can explicitly specify them as before
        // $response = $this->getJson('/users/1');
    }
}
```

#### 4. When API Prefix is Needed

If your application routes have a prefix like `/api/users`, override the `prefix()` method.

```php
class ExampleTest extends TestCase
{
    protected string $path = '/users/1';
    protected HttpRequestMethod $operation = HttpRequestMethod::GET;

    // Override the method to return the prefix
    protected function prefix(): string
    {
        return '/api';
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
        return (new ValidatorBuilder)->fromYamlFile(base_path('tests/openapi.yml'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransparentlyTest();
    }

    /** @test */
    public function a_user_can_be_retrieved(): void
    {
        $this->path = '/users/1';
        $this->operation = HttpRequestMethod::GET;

        // Request to /api/users/1, validated against schema /users/{id}
        $response = $this->json();
        $response->assertStatus(200);
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

### Required Methods

#### `path(): string`

Returns the OpenAPI schema path for the current test.

```php
protected function path(): string
{
    return '/users/{id}'; // or a concrete value like '/users/1'
}
```

#### `operation(): HttpRequestMethod`

Returns the HTTP method to validate for the current test.

```php
protected function operation(): HttpRequestMethod
{
    return HttpRequestMethod::GET;
}
```

#### `getValidatorBuilder(): ValidatorBuilder`

Returns a `ValidatorBuilder` instance loaded with your OpenAPI schema.

```php
protected function getValidatorBuilder(): ValidatorBuilder
{
    return (new ValidatorBuilder)->fromYamlFile(base_path('tests/openapi.yml'));
}
```

### Optional Methods

#### `prefix(): string`

Returns the application's routing prefix. Defaults to an empty string. Override this method only if your application uses a prefix.

```php
protected function prefix(): string
{
    return '/api'; // Default is '' (empty string)
}
```

### Validation Control Methods

#### `ignoreRequestCompliance()`

Temporarily disables request validation for the current test.

```php
public function test_with_invalid_request(): void
{
    $this->ignoreRequestCompliance();

    // Request validation will not run even for an invalid request
    $this->postJson('/api/users', []);
}
```

#### `ignoreResponseCompliance()`

Temporarily disables response validation for the current test.

```php
public function test_with_invalid_response(): void
{
    $this->ignoreResponseCompliance();

    // Response validation will not run
    $this->postJson('/api/users', ['generate_invalid_response' => true]);
}
```

### HTTP Request Methods

The `TestCaseHelper` trait overrides the `json()` and `call()` methods to automatically set default values when arguments are omitted.

#### `json($method = '', $uri = '', array $data = [], array $headers = [], $options = 0)`

When HTTP method and URI are omitted, they are automatically retrieved from `operation()` and `prefix() . path()`.

```php
// Shorthand (recommended)
$this->json(); // Uses operation() and prefix() . path()

// Explicit specification also possible
$this->json('GET', '/api/users/1');
$this->getJson('/api/users/1'); // Traditional usage still works
```

#### `call($method = '', $uri = '', $parameters = [], $cookies = [], $files = [], $server = [], $content = null)`

Like `json()`, HTTP method and URI are automatically set to default values when omitted.

```php
// Shorthand
$this->call(); // Uses operation() and prefix() . path()

// Explicit specification also possible
$this->call('POST', '/api/users', ['name' => 'John']);
```

## Acknowledgements

This library was heavily inspired by the development blog post "[LaravelアプリケーションのAPIがSwagger/OpenAPIドキュメントに準拠していることを透過的にテストする](https://nextat.co.jp/staff/archives/253)" by 株式会社Nextat（ネクスタット）.

I would like to express my deep gratitude to the author for publishing such a wonderful idea and implementation hints.

## License

This library is open-sourced software licensed under the [MIT license](LICENSE).