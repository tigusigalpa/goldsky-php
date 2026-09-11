<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Exceptions\TransportException;

/**
 * Requester performs REST control-plane requests against the Goldsky API,
 * applying bearer authentication, retry policy, redaction, and RFC 9457 error
 * parsing. The REST project token is sent as a Bearer header and never appears
 * in errors or logs.
 */
final class Requester
{
    private GuzzleClient $httpClient;
    private LoggerInterface $logger;
    private readonly string $apiToken;
    private readonly Config $config;

    /**
     * @var callable|null A sleeper invoked with (int $ms) for retry delays.
     *                    Defaults to usleep(). Injectable for deterministic tests.
     */
    private $sleeper;

    public function __construct(string $apiToken, Config $config, ?GuzzleClient $httpClient = null, ?LoggerInterface $logger = null, ?callable $sleeper = null)
    {
        $this->apiToken = $apiToken;
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->sleeper = $sleeper;
        $this->httpClient = $httpClient ?? new GuzzleClient([
            'timeout' => $config->timeoutSec,
            'verify' => $config->verifyTls,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json, application/problem+json',
                'User-Agent' => $config->userAgent,
            ],
        ]);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function setSleeper(callable $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    /**
     * Performs a REST control-plane request.
     *
     * @param string[]              $segments  Path segments, URL-encoded individually.
     * @param array<string, mixed>  $query     Query parameters.
     * @param mixed|null            $json      JSON body payload (encoded as JSON).
     * @param array<int, array{name: string, contents: string|resource, filename?: string, headers?: array<string, string>}> $multipart Multipart parts for file uploads.
     * @param array<string, string> $headers   Extra request headers.
     *
     * @return array{0: int, 1: string} [statusCode, rawBody]
     *
     * @throws ProblemDetails       On a non-2xx response with a problem body.
     * @throws TransportException   On a transport/decode failure.
     */
    public function request(string $method, array $segments, array $query = [], $json = null, array $multipart = [], array $headers = []): array
    {
        $method = strtoupper($method);
        $url = $this->buildURL($segments, $query);
        $safe = self::isSafeMethod($method);
        $retryMutations = $this->config->retryMutations;
        $attempts = max(1, $this->config->retryMaxAttempts);

        $lastError = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $options = $this->buildOptions($query, $json, $multipart, $headers);

            try {
                $response = $this->httpClient->request($method, $url, $options);
            } catch (GuzzleException $e) {
                $lastError = new TransportException($method, 0, $e->getMessage(), $e);
                if (($safe || $retryMutations) && $attempt < $attempts) {
                    $this->sleep($this->backoffMs($attempt));
                    continue;
                }
                throw $lastError;
            }

            $status = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($status >= 200 && $status < 300) {
                return [$status, $body];
            }

            $problem = $this->parseProblem($response, $body);
            $lastError = $problem;

            $retryable = self::isRetryableStatus($status);
            $canRetry = ($safe || $retryMutations) && $attempt < $attempts && $retryable;
            if (!$canRetry) {
                throw $problem;
            }
            $this->sleep($this->backoffWithRetryAfterMs($attempt, $response));
        }

        throw $lastError;
    }

    /**
     * @return array{0: int, 1: string}
     */
    public function rawRequest(string $method, string $url, array $options = [], bool $auth = false): array
    {
        $defaults = [
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => $this->config->userAgent,
            ],
        ];
        if ($auth) {
            $defaults['headers']['Authorization'] = 'Bearer ' . $this->apiToken;
        }
        $options = array_merge_recursive($defaults, $options);
        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new TransportException($method, 0, $e->getMessage(), $e);
        }
        return [$response->getStatusCode(), (string) $response->getBody()];
    }

    private function buildURL(array $segments, array $query): string
    {
        $encoded = array_map('rawurlencode', $segments);
        $base = rtrim($this->config->baseURL, '/');
        $url = $base . '/' . implode('/', $encoded);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(array $query, $json, array $multipart, array $headers): array
    {
        $options = [];
        if (!empty($query)) {
            $options['query'] = $query;
        }
        if (!empty($multipart)) {
            $options['multipart'] = $multipart;
        } elseif ($json !== null) {
            $options['json'] = $json;
        }
        $options['headers'] = array_merge(
            ['Authorization' => 'Bearer ' . $this->apiToken],
            $headers,
        );
        return $options;
    }

    private function parseProblem(ResponseInterface $response, string $body): ProblemDetails
    {
        $headers = [];
        foreach ($response->getHeaders() as $k => $vs) {
            $headers[$k] = count($vs) === 1 ? $vs[0] : $vs;
        }
        $status = $response->getStatusCode();
        $type = 'about:blank';
        $title = '';
        $detail = '';
        $instance = '';
        $errors = [];

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $type = $decoded['type'] ?? 'about:blank';
                $title = $decoded['title'] ?? '';
                $detail = $decoded['detail'] ?? '';
                $instance = $decoded['instance'] ?? '';
                $errors = $decoded['errors'] ?? [];
                if (!is_array($errors)) {
                    $errors = [];
                }
            }
        }

        return new ProblemDetails(
            type: $type,
            title: $title,
            status: $status,
            detail: $detail,
            instance: $instance,
            errors: $errors,
            headers: $headers,
            rawBody: $body,
        );
    }

    private function backoffMs(int $attempt): int
    {
        $initial = $this->config->retryInitialBackoffMs;
        $max = $this->config->retryMaxBackoffMs;
        $d = $initial;
        for ($i = 1; $i < $attempt; $i++) {
            $d *= 2;
            if ($d > $max) {
                $d = $max;
            }
        }
        // Full jitter.
        if ($d > 0) {
            $d = random_int(0, $d);
        }
        return $d;
    }

    private function backoffWithRetryAfterMs(int $attempt, ResponseInterface $response): int
    {
        $d = $this->backoffMs($attempt);
        $retryAfter = $response->getHeader('Retry-After');
        if (!empty($retryAfter)) {
            [$secs, $ok] = ProblemDetails::parseRetryAfter($retryAfter[0]);
            if ($ok && $secs > 0) {
                $ra = $secs * 1000;
                if ($ra > $d) {
                    $d = $ra;
                }
            }
        }
        return $d;
    }

    private function sleep(int $ms): void
    {
        if ($ms <= 0) {
            return;
        }
        if ($this->sleeper !== null) {
            ($this->sleeper)($ms);
        } else {
            usleep($ms * 1000);
        }
    }

    public static function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public static function isRetryableStatus(int $status): bool
    {
        return in_array($status, [429, 500, 502, 503, 504], true);
    }
}
