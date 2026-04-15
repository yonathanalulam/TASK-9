<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client wrapper for scraping with proxy support and detection
 * of CAPTCHA / ban markers.
 */
class HttpScrapeClient
{
    /** Common CAPTCHA marker patterns. */
    private const CAPTCHA_PATTERNS = [
        'captcha',
        'recaptcha',
        'hcaptcha',
        'cf-challenge',
        'challenge-platform',
        'g-recaptcha',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Fetch a URL, optionally through a proxy.
     *
     * @return array{status: int, body: string, headers: array<string, list<string>>, captcha_detected: bool}
     */
    public function fetch(string $url, ?string $proxyUrl = null): array
    {
        $options = [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
        ];

        if ($proxyUrl !== null) {
            $options['proxy'] = $proxyUrl;
        }

        $response = $this->httpClient->request('GET', $url, $options);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent(false);
        $headers = $response->getHeaders(false);

        $captchaDetected = $this->detectCaptcha($body);

        return [
            'status' => $statusCode,
            'body' => $body,
            'headers' => $headers,
            'captcha_detected' => $captchaDetected,
        ];
    }

    private function detectCaptcha(string $body): bool
    {
        $lowerBody = strtolower($body);

        foreach (self::CAPTCHA_PATTERNS as $pattern) {
            if (str_contains($lowerBody, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
