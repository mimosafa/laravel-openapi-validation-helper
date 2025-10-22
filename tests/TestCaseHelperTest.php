<?php

namespace Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelOpenAPIValidationHelper\HttpRequestMethod;
use LaravelOpenAPIValidationHelper\TestCaseHelper;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature test class for TestCaseHelper.
 *
 * This class serves as a sample for the concrete usage of the LaravelOpenAPIValidationHelper\TestCaseHelper trait.
 * Package users can refer to this test code to understand how to introduce the trait and the role of each method.
 *
 * @see \LaravelOpenAPIValidationHelper\TestCaseHelper
 */
class TestCaseHelperTest extends TestCase
{
    use TestCaseHelper;

    /**
     * The OpenAPI schema path for the current test target.
     *
     * Specify the path including placeholders (e.g., `/users/{id}`).
     *
     * @var string
     */
    protected string $path = '/users/1';

    /**
     * The HTTP method for the current test target.
     *
     * @var HttpRequestMethod
     */
    protected HttpRequestMethod $operation = HttpRequestMethod::GET;

    /**
     * The prefix to bridge the gap between the OpenAPI schema path and the application's routing path.
     *
     * Example: If the OpenAPI schema path is `/users/{id}` and
     *          the application's actual path is `/api/v1/users/{id}`,
     *          this property should be set to `/api/v1`.
     *
     * This is optional and only needs to be overridden when a prefix is required.
     *
     * @var string|null
     */
    protected ?string $prefixOverride = null;

    /**
     * Returns a `ValidatorBuilder` instance loaded with the OpenAPI schema file.
     *
     * This method is essential for the `TestCaseHelper` trait to perform validation.
     * Adjust the path according to the location of your project's `openapi.yml` file.
     *
     * @return ValidatorBuilder
     */
    protected function getValidatorBuilder(): ValidatorBuilder
    {
        return (new ValidatorBuilder)->fromYamlFile(__DIR__ . '/openapi.yml');
    }

    /**
     * Passes the prefix to `TestCaseHelper`.
     *
     * Override the default empty string when a prefix is needed.
     *
     * @return string
     */
    protected function prefix(): string
    {
        return $this->prefixOverride ?? '';
    }

    /**
     * Passes the schema path to `TestCaseHelper`.
     *
     * @return string
     */
    protected function path(): string
    {
        return $this->path;
    }

    /**
     * Passes the HTTP method to `TestCaseHelper`.
     *
     * @return HttpRequestMethod
     */
    protected function operation(): HttpRequestMethod
    {
        return $this->operation;
    }

    /**
     * Test setup.
     *
     * Calling `setUpTransparentlyTest()` enables automatic validation of requests and responses.
     * Dummy routes for testing are also defined here.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTransparentlyTest();

        Route::get('/users/{id}', function ($id) {
            return response()->json(['id' => (int)$id, 'name' => 'John Doe']);
        });

        Route::post('/users/{id}', function (Request $request, $id) {
            if ($request->get('invalid_response')) {
                return response()->json(['id' => (int)$id, 'name' => 123]); // Invalid name type
            }
            return response()->json(['id' => (int)$id, 'name' => $request->input('name')]);
        });

        Route::get('/api/users/{id}', function ($id) {
            return response()->json(['id' => (int)$id, 'name' => 'John Doe']);
        });

        Route::post('/api/users/{id}', function (Request $request, $id) {
            if ($request->get('invalid_response')) {
                return response()->json(['id' => (int)$id, 'name' => 123]); // Invalid name type
            }
            return response()->json(['id' => (int)$id, 'name' => $request->input('name')]);
        });
    }

    /**
     * Resets properties to their initial state after each test execution.
     */
    protected function tearDown(): void
    {
        $this->requestAssertion = true;
        $this->responseAssertion = true;
        $this->prefixOverride = null;
        $this->path = '/users/1';
        $this->operation = HttpRequestMethod::GET;
        parent::tearDown();
    }

    #[Test]
    public function successful_validation_on_get_request(): void
    {
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/1';

        $response = $this->getJson('/users/1');
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'John Doe']);
    }

    #[Test]
    public function request_validation_fails_when_required_parameter_is_missing(): void
    {
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/Required property \'name\' must be present in the object/');

        $this->postJson('/users/1', []); // The 'name' property is required but missing.
    }

    #[Test]
    public function response_validation_fails_when_response_data_type_is_incorrect(): void
    {
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/Value expected to be \'string\', but \'integer\' given./');

        // 'name' should be a string, but the route returns an integer.
        $this->postJson('/users/1', ['name' => 'Jane Doe', 'invalid_response' => true]);
    }

    #[Test]
    public function request_validation_can_be_ignored(): void
    {
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->ignoreRequestCompliance();
        $this->ignoreResponseCompliance();

        // This would normally fail request validation, but it is disabled by ignoreRequestCompliance().
        $response = $this->postJson('/users/1', []);
        $response->assertStatus(200);
    }

    #[Test]
    public function response_validation_can_be_ignored(): void
    {
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->ignoreResponseCompliance();

        // This would normally fail response validation, but it is disabled by ignoreResponseCompliance().
        $response = $this->postJson('/users/1', ['name' => 'Jane Doe', 'invalid_response' => true]);
        $response->assertStatus(200);
    }

    #[Test]
    public function prefix_is_correctly_handled_in_get_request(): void
    {
        $this->prefixOverride = '/api';
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/123';

        $response = $this->getJson('/api/users/123');
        $response->assertStatus(200);
        $response->assertJson(['id' => 123]);
    }

    #[Test]
    public function successful_validation_on_post_request_with_prefix(): void
    {
        $this->prefixOverride = '/api';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $response = $this->postJson('/api/users/1', ['name' => 'Test Name']);
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'Test Name']);
    }

    #[Test]
    public function path_with_placeholder_is_validated_correctly(): void
    {
        $userId = 456;

        // 1. Set the concrete path according to the schema's path definition (`/users/{id}`).
        $this->path = '/users/' . $userId;
        $this->prefixOverride = '/api';
        $this->operation = HttpRequestMethod::GET;

        // 2. Send a request to the actual path including the placeholder.
        //    Validation is automatically performed after the request.
        $response = $this->getJson('/api/users/' . $userId);

        // 3. Assert the result.
        $response->assertStatus(200);
        $response->assertJson(['id' => $userId]);
    }

    #[Test]
    public function json_method_uses_default_values_when_arguments_are_omitted(): void
    {
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/1';

        // Method and URI are omitted, automatically retrieved from operation() and prefix() . path()
        $response = $this->json();
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'John Doe']);
    }

    #[Test]
    public function json_method_with_prefix_uses_default_values_when_arguments_are_omitted(): void
    {
        $this->prefixOverride = '/api';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        // Method and URI are automatically set to POST and /api/users/1
        $response = $this->json(data: ['name' => 'Auto Name']);
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'Auto Name']);
    }

    #[Test]
    public function call_method_uses_default_values_when_arguments_are_omitted(): void
    {
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/1';

        // Method and URI are omitted
        $response = $this->call();
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'John Doe']);
    }

    #[Test]
    public function prefix_defaults_to_empty_string_when_not_overridden(): void
    {
        // This test verifies that prefix() returns an empty string by default
        $this->prefixOverride = null; // Explicitly not setting a prefix
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/999';

        $response = $this->getJson('/users/999');
        $response->assertStatus(200);
        $response->assertJson(['id' => 999]);
    }
}
