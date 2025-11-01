<?php

class AmoCrmClient
{
    private ?\CurlHandle $curl = null;
    private string $subDomain;

    private string $clientId;
    private string $clientSecret;
    private string $code;
    private string $redirectUri;

    private ?string $accessToken = null;
    private string $tokenFile = "TOKEN.txt";

    /**
     * Constructs a new instance of the AmoCrmV4Client class.
     *
     * @param string $subDomain The AmoCRM subdomain.
     * @param string $clientId The AmoCRM client ID.
     * @param string $clientSecret The AmoCRM client secret.
     * @param string $code The AmoCRM authorization code.
     * @param string $redirectUri The AmoCRM redirect URI.
     */
    public function __construct(string $subDomain, string $clientId, string $clientSecret, string $code, string $redirectUri)
    {
        $this->subDomain = $subDomain;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->code = $code;
        $this->redirectUri = $redirectUri;

        $this->initializeToken();
    }

    /**
     * Initializes the access token.
     *
     * If the token file exists, it checks whether the token has expired.
     * If the token has expired, it requests a new token. If not, it sets the access token.
     * If the token file does not exist, it requests a new token.
     */
    private function initializeToken(): void
    {
        if (file_exists($this->tokenFile)) {
            $tokenData = json_decode(file_get_contents($this->tokenFile), true);
            if ($tokenData && isset($tokenData['expires_in'])) {
                if ($tokenData['expires_in'] < time()) {
                    $this->getToken(true);
                } else {
                    $this->accessToken = $tokenData['access_token'];
                }
            } else {
                $this->getToken();
            }
        } else {
            $this->getToken();
        }
    }

    /**
     * Saves the access token to a file.
     *
     * @param array $tokenData The access token data to save.
     */
    private function saveToken(array $tokenData): void
    {
        file_put_contents($this->tokenFile, json_encode($tokenData));
    }

    public function getToken(bool $refresh = false): void
    {
        $url = 'https://' . $this->subDomain . '.amocrm.ru/oauth2/access_token';

        if ($refresh) {
            $tokenData = json_decode(file_get_contents($this->tokenFile), true);
            if (!$tokenData || !isset($tokenData['refresh_token'])) {
                throw new \Exception("No refresh token available");
            }
            $data = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $tokenData['refresh_token'],
                'redirect_uri' => $this->redirectUri
            ];
        } else {
            $data = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $this->code,
                'redirect_uri' => $this->redirectUri
            ];
        }

        $response = $this->curlRequest($url, 'POST', $data);
        $responseData = json_decode($response, true);

        if (isset($responseData['access_token'])) {
            $this->accessToken = $responseData['access_token'];
            $tokenData = [
                'access_token' => $responseData['access_token'],
                'refresh_token' => $responseData['refresh_token'] ?? '',
                'token_type' => $responseData['token_type'] ?? '',
                'expires_in' => time() + ($responseData['expires_in'] ?? 0)
            ];
            $this->saveToken($tokenData);
        } else {
            throw new \Exception("Failed to obtain access token");
        }
    }

    /**
     * Sends a request to the specified URL using cURL.
     *
     * @param string $url The URL to send the request to.
     * @param string $method The HTTP method to use for the request (e.g. GET, POST, PATCH).
     * @param array $data The data to send with the request (if applicable).
     *
     * @return string The response of the request.
     *
     * @throws \Exception If the request fails, either due to a cURL error or an HTTP error status.
     */
    private function curlRequest(string $url, string $method, array $data = []): string
    {
        $ch = curl_init();

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'amoCRM-oAuth-client/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        // Если метод POST или PUT, добавляем поля
        if ($method === 'POST' || $method === 'PATCH') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL error: $errorMsg");
        }

        curl_close($ch);

        // Обработка HTTP статуса
        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable'
        ];

        if ($httpCode < 200 || $httpCode > 204) {
            $errorMsg = $errors[$httpCode] ?? 'Undefined error';
            throw new \Exception("HTTP $httpCode ($errorMsg). Response: $response");
        }

        return $response;
    }

    /**
     * Sends a GET request to the AmoCRM API.
     *
     * @param string $service The AmoCRM API endpoint.
     * @param array $params The query parameters.
     *
     * @return array|null The response from the server, or null if an error occurred.
     */
    public function APIGet(string $service, array $params = []): ?array
    {
        $baseUrl = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service;
        $query = '';

        if (!empty($params)) {
            $query = '?' . http_build_query($params);
        }

        $url = $baseUrl . $query;

        $response = $this->curlRequest($url, 'GET');

        return json_decode($response, true);
    }

    /**
     * Sends a POST request to the AmoCRM API.
     *
     * @param string $service The AmoCRM API endpoint.
     * @param array $data The request data.
     * @param string $method The HTTP method to use (default: "POST").
     *
     * @return array|null The response from the server, or null if an error occurred.
     */
    public function APIPost(string $service, array $data = [], string $method = 'POST'): ?array
    {
        $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service;
        $response = $this->curlRequest($url, $method, $data);
        return json_decode($response, true);
    }

    /**
     * Fetches all records from the specified entity, with optional custom parameters.
     *
     * @param string $entity The entity to fetch records from (e.g. "leads", "contacts").
     * @param array|null $customParams Optional custom parameters to pass to the API.
     *
     * @return array The fetched records.
     */
    public function getAll($entity, ?array $customParams = null): array
    {
        $results = [];
        $page = 1;

        $with = match ($entity) {
            'leads' => 'contacts',
            'contacts' => 'leads',
            default => 'leads,contacts'
        };

        $params = [
            'limit' => 250,
            'with' => $with
        ];

        if ($customParams !== null) {
            $params = array_merge($params, $customParams);
        }

        do {
            $params['page'] = $page;
            $response = $this->APIGet($entity, $params);

            if (!isset($response['_embedded'][$entity]) || empty($response['_embedded'][$entity])) {
                break;
            }

            foreach ($response['_embedded'][$entity] as $item) {
                $results[] = $item;
            }

            $page++;
            usleep(250000);
        } while (true);

        return $results;
    }

    /**
     * Gets the value of a custom field by its ID.
     *
     * @param array $customFieldsValues The array of custom fields values.
     * @param int $id The ID of the custom field.
     *
     * @return string The value of the custom field, or an empty string if the field is not found.
     */
    public function getCustomFieldValue(array $customFieldsValues, int $id): string
    {
        $value = '';
        foreach ($customFieldsValues as $field) {
            if ($field['field_id'] == $id) {
                $value = str_replace("г", "Г", $field['values'][0]['value']);
            }
        }
        return $value;
    }
}
