<?php

use GuzzleHttp\Client;

require '../vendor/autoload.php';

function get_access_token($force_update = false)
{
    $access_token_cache_file = 'storage/access_token.txt';

    if (!$force_update) {
        $access_token = file_exists($access_token_cache_file) ? file_get_contents($access_token_cache_file) : '';
        if ($access_token) {
            return $access_token;
        }
    }

    $guzzle = new Client();
    $response = $guzzle->post('http://localhost/oauth/token', [
        'headers' => [
            'Accept' => 'application/json',
        ],
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => '3', // TODO: replace with registered client id
            'client_secret' => '7IUHJc4B161VXmXV1iNmtA0yqISnNqRAAJqnsIuS', // TODO: replace with registered client secret
            'scope' => '*',
        ],
    ]);

    /**
     * You'd typically save this payload in the session.
     * Note: no refresh token is returned. See "A refresh token SHOULD NOT be included."
     * in <https://tools.ietf.org/html/rfc6749#section-4.4.3> .
     * And in passport code,
     * \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface::setRefreshToken
     * is called only by AuthCodeGrant, PasswordGrant and RefreshTokenGrant .
     */
    $auth = json_decode((string)$response->getBody(), true);
    $access_token = array_get($auth, 'access_token');

    file_put_contents($access_token_cache_file, $access_token);

    return $access_token;
}


$body = [];

$guzzle = new Client();

$retry_times = 3;
$tried_time = 0;

$updated = false;
$access_token = get_access_token();
do {
    try {
        $response = $guzzle->get('http://localhost/api', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
        ]);
        $body = (string)$response->getBody();
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $statusCode = $e->getResponse()->getStatusCode();
        if ($statusCode == 401) { // revoked, expired or so
            $updated = true;
            $access_token = get_access_token(true);
        }
    }
} while (++$tried_time < $retry_times && empty($body));


echo json_encode(compact('updated', 'body'));
