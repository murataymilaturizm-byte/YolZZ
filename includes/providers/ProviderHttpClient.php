<?php
/**
 * ProviderHttpClient
 * cURL tabanlı HTTP istemcisi. Tüm provider'lar bunu kullanır.
 *
 * ÖZELLİKLER:
 * - Timeout (default 8 saniye — API çökerse senin siten çökmesin)
 * - Otomatik retry (max 2 deneme)
 * - Her isteği loglar (debug için)
 * - GET için query string, POST için form-encoded
 */

if (!defined('YOLZZ_APP')) { die('Forbidden'); }

class ProviderHttpClient
{
    private int $timeoutSeconds = 8;
    private int $connectTimeoutSeconds = 4;
    private int $maxRetries = 1; // 1 retry = toplam 2 deneme
    private array $defaultHeaders = [];

    public function __construct(array $options = [])
    {
        if (isset($options['timeout'])) $this->timeoutSeconds = (int)$options['timeout'];
        if (isset($options['connect_timeout'])) $this->connectTimeoutSeconds = (int)$options['connect_timeout'];
        if (isset($options['retries'])) $this->maxRetries = (int)$options['retries'];
        if (isset($options['headers'])) $this->defaultHeaders = $options['headers'];
    }

    /**
     * GET isteği gönder
     * @return array ['ok' => bool, 'status' => int, 'body' => string, 'json' => array|null, 'error' => string|null, 'duration_ms' => int]
     */
    public function get(string $url, array $queryParams = [], array $headers = []): array
    {
        if (!empty($queryParams)) {
            $sep = strpos($url, '?') === false ? '?' : '&';
            $url .= $sep . http_build_query($queryParams);
        }
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * POST isteği (form-encoded)
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * POST isteği (JSON body)
     */
    public function postJson(string $url, array $data = [], array $headers = []): array
    {
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $url, json_encode($data), $headers);
    }

    private function request(string $method, string $url, $body, array $headers): array
    {
        $headers = array_merge($this->defaultHeaders, $headers);

        $attempt = 0;
        $lastError = null;
        $start = microtime(true);

        while ($attempt <= $this->maxRetries) {
            $attempt++;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeoutSeconds);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Yolzz/1.0');

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (is_array($body)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
                    $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                } elseif (is_string($body)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }

            // Header array
            if (!empty($headers)) {
                $headerLines = [];
                foreach ($headers as $k => $v) { $headerLines[] = $k . ': ' . $v; }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
            }

            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            $errNo = curl_errno($ch);
            curl_close($ch);

            $duration = (int)round((microtime(true) - $start) * 1000);

            // Başarılıysa döndür
            if ($response !== false && $status >= 200 && $status < 500) {
                $json = json_decode($response, true);
                return [
                    'ok' => $status >= 200 && $status < 300,
                    'status' => $status,
                    'body' => $response,
                    'json' => is_array($json) ? $json : null,
                    'error' => null,
                    'duration_ms' => $duration,
                    'attempts' => $attempt,
                ];
            }

            // Hata → log ve (retry kaldıysa) tekrar dene
            $lastError = $err ?: "HTTP $status";
            if (function_exists('error_log')) {
                error_log("ProviderHttpClient [attempt $attempt/{$this->maxRetries}]: $method $url => $lastError (curl_errno=$errNo)");
            }

            // Retry arası küçük bekleme
            if ($attempt <= $this->maxRetries) {
                usleep(200000); // 200ms
            }
        }

        $duration = (int)round((microtime(true) - $start) * 1000);
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'json' => null,
            'error' => $lastError ?? 'Unknown error',
            'duration_ms' => $duration,
            'attempts' => $attempt,
        ];
    }
}
