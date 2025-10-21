<?php

namespace LaravelOpenAPIValidationHelper;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Enables transparent, event-driven OpenAPI validation for Laravel feature tests.
 *
 * This trait hooks into the Laravel request lifecycle to automatically validate
 * requests and responses against a specified OpenAPI 3.0 schema. It is designed
 * to be used within test classes that extend Laravel's base TestCase.
 */
trait TestCaseHelper
{
    /**
     * Whether to perform request validation. Can be disabled per-test.
     *
     * @var bool
     */
    protected bool $requestAssertion = true;

    /**
     * Whether to perform response validation. Can be disabled per-test.
     *
     * @var bool
     */
    protected bool $responseAssertion = true;

    /**
     * Get the API prefix that should be stripped from the request URI before validation.
     *
     * This is used to reconcile differences between an application's full request URI and the path defined in the OpenAPI schema.
     * For example, if the application route is '/api/v1/users' and the schema path is '/users', this method should return '/api/v1'.
     *
     * @return string The URI prefix, or an empty string if none.
     */
    abstract protected function prefix(): string;

    /**
     * Get the OpenAPI schema path for the current operation.
     *
     * This path should correspond to a path defined in the OpenAPI specification file.
     * e.g., '/users/{id}'
     *
     * @return string
     */
    abstract protected function path(): string;

    /**
     * Get the HTTP method for the current operation.
     *
     * @return HttpRequestMethod
     */
    abstract protected function operation(): HttpRequestMethod;

    /**
     * Get the ValidatorBuilder instance configured with the OpenAPI schema.
     *
     * This is typically where you load your openapi.yml file.
     * e.g., `return (new ValidatorBuilder)->fromYamlFile('path/to/openapi.yml');`
     *
     * @return ValidatorBuilder
     */
    abstract protected function getValidatorBuilder(): ValidatorBuilder;

    /**
     * Set up the event listener for transparent validation.
     *
     * This method should be called from the test class's `setUp()` method.
     */
    protected function setUpTransparentlyTest(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            if ($this->requestAssertion) {
                $this->assertCompliantRequest($event->request);
            }
            if ($this->responseAssertion) {
                $this->assertCompliantResponse($event->response);
            }
        });
    }

    /**
     * Disable request validation for the current test.
     */
    protected function ignoreRequestCompliance(): void
    {
        $this->requestAssertion = false;
    }

    /**
     * Disable response validation for the current test.
     */
    protected function ignoreResponseCompliance(): void
    {
        $this->responseAssertion = false;
    }

    /**
     * Assert that the given HTTP request complies with the OpenAPI schema.
     *
     * @param Request $request The request to validate.
     */
    protected function assertCompliantRequest(Request $request): void
    {
        $validator = $this->getValidatorBuilder()->getRequestValidator();
        if ($prefix = $this->prefix()) {
            $requestUri = $request->server('REQUEST_URI');
            $pattern = '/^' . preg_quote($prefix, '/') . '/';
            $testingUri = preg_replace($pattern, '', $requestUri, 1);

            $server = $request->server;
            $server->set('REQUEST_URI', $testingUri);
            $request = $request->duplicate(server: $server->all());
        }
        $psrRequest = self::psrHttpFactory()->createRequest($request);

        try {
            $validator->validate($psrRequest);
        } catch (ValidationFailed $e) {
            TestCase::fail(self::failedMessage($e));
        }
    }

    /**
     * Assert that the given HTTP response complies with the OpenAPI schema.
     *
     * @param Response|JsonResponse $response The response to validate.
     */
    protected function assertCompliantResponse(Response|JsonResponse $response): void
    {
        $validator = $this->getValidatorBuilder()->getResponseValidator();
        $operation = new OperationAddress($this->path(), strtolower($this->operation()->name));
        $psrResponse = self::psrHttpFactory()->createResponse($response);

        try {
            $validator->validate($operation, $psrResponse);
        } catch (ValidationFailed $e) {
            TestCase::fail(self::failedMessage($e));
        }
    }

    /**
     * Get a PSR-7 HTTP message factory.
     *
     * @return PsrHttpFactory
     */
    protected static function psrHttpFactory(): PsrHttpFactory
    {
        static $factory;
        if (! $factory) {
            $psr17Factory = new Psr17Factory;
            $factory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        }
        return $factory;
    }

    /**
     * Create a detailed failure message from a ValidationFailed exception.
     *
     * This method traverses previous exceptions to build a comprehensive error message.
     *
     * @param ValidationFailed $e
     * @return string
     */
    protected static function failedMessage(ValidationFailed $e): string
    {
        $message = '';
        do {
            $message .= $e->getMessage() . PHP_EOL;
        } while ($e = $e->getPrevious());
        return $message;
    }
}