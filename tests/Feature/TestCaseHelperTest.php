<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaravelOpenAPIValidationHelper\HttpRequestMethod;
use LaravelOpenAPIValidationHelper\TestCaseHelper;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Tests\TestCase;

class TestCaseHelperTest extends TestCase
{
    use TestCaseHelper;

    protected bool $requestAssertion = true;
    protected bool $responseAssertion = true;
    protected string $prefix = '';
    protected string $path = '/users/1';
    protected HttpRequestMethod $operation = HttpRequestMethod::GET;

    protected function getValidatorBuilder(): ValidatorBuilder
    {
        return (new ValidatorBuilder)->fromYamlFile(__DIR__ . '/../openapi.yml');
    }

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

    // Reset properties for each test
    protected function tearDown(): void
    {
        $this->requestAssertion = true;
        $this->responseAssertion = true;
        $this->prefix = '';
        $this->path = '/users/1';
        $this->operation = HttpRequestMethod::GET;
        parent::tearDown();
    }

    public function test_successful_validation()
    {
        $this->prefix = '';
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/1';

        $response = $this->getJson('/users/1');
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'John Doe']);
    }

    public function test_request_validation_fails()
    {
        $this->prefix = '';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/Required property \'name\' must be present in the object/');

        $this->postJson('/users/1', []); // Missing 'name'
    }

    public function test_response_validation_fails()
    {
        $this->prefix = '';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/Value expected to be \'string\', but \'integer\' given./');

        $this->postJson('/users/1', ['name' => 'Jane Doe', 'invalid_response' => true]);
    }

    public function test_ignore_request_validation()
    {
        $this->prefix = '';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->ignoreRequestCompliance();
        $this->ignoreResponseCompliance();

        // This would fail request validation, but it should be ignored.
        $response = $this->postJson('/users/1', []);
        $response->assertStatus(200);
    }

    public function test_ignore_response_validation()
    {
        $this->prefix = '';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $this->ignoreResponseCompliance();

        // This would fail response validation, but it should be ignored.
        $response = $this->postJson('/users/1', ['name' => 'Jane Doe', 'invalid_response' => true]);
        $response->assertStatus(200);
    }

    public function test_prefix_handling()
    {
        $this->prefix = '/api';
        $this->operation = HttpRequestMethod::GET;
        $this->path = '/users/123';

        $response = $this->getJson('/api/users/123');
        $response->assertStatus(200);
        $response->assertJson(['id' => 123]);
    }

    public function test_successful_post_with_prefix()
    {
        $this->prefix = '/api';
        $this->operation = HttpRequestMethod::POST;
        $this->path = '/users/1';

        $response = $this->postJson('/api/users/1', ['name' => 'Test Name']);
        $response->assertStatus(200);
        $response->assertJson(['id' => 1, 'name' => 'Test Name']);
    }

    public function test_path_with_placeholder_is_validated_correctly()
    {
        $userId = 456;

        // 1. Set the concrete path with the placeholder resolved.
        $this->path = '/users/' . $userId;
        $this->prefix = '/api';
        $this->operation = HttpRequestMethod::GET;

        // 2. The validation is triggered automatically when the request is made.
        $response = $this->getJson('/api/users/' . $userId);

        // 3. Assert the result.
        $response->assertStatus(200);
        $response->assertJson(['id' => $userId]);
    }
}
