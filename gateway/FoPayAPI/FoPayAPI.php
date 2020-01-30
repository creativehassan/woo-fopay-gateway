<?php
require 'BackendException.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use \Firebase\JWT\JWT;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;

use function GuzzleHttp\json_encode;

class FoPayAPI
{
    private $client;
    private $clientCodeName;
    private $privateKey;
    private $algorithm;
    private $publicKey;

    public function __construct($clientCodeName, $privateKey, $publicKey)
    {
        $this->client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://api.clients.fopay.io',
            // You can set any number of default request options.
            'timeout' => 5.0,
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);
        $this->algorithm = "RS256";
        $this->clientCodeName = $clientCodeName;
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
    }

    public function accountList()
    {
        $requestBody = [];
        $client = $this->client;
        $jsonBody = $this->makeJsonBody($requestBody);
        try {
            // var_dump($jsonBody);
            $response = $client->request('POST', '/v1/account/list', ['json' => $jsonBody]);
        } catch (RequestException $e) {
            echo Psr7\str($e->getRequest())."\n";
            if ($e->hasResponse()) {
                // echo Psr7\str($e->getResponse());
                $response = $e->getResponse();

                $this->handleError($response);
            }
        } catch(Exception $e) {
            throw new BackendException("Unknown Error!!!");
        }

        return $this->handleResponse($response);
    }

    public function createInvoice(array $requestBody)
    {
        $client = $this->client;
        $jsonBody = $this->makeJsonBody($requestBody);
        try {
            // var_dump($jsonBody);
            $response = $client->request('POST', '/v1/invoice/create', ['json' => $jsonBody]);
        } catch (RequestException $e) {
            echo Psr7\str($e->getRequest())."\n";
            if ($e->hasResponse()) {
                // echo Psr7\str($e->getResponse());
                $response = $e->getResponse();

                $this->handleError($response);
            }
        } catch(Exception $e) {
            throw new BackendException("Unknown Error!!!");
        }

        return $this->handleResponse($response);
    }

    public function getInvoice(array $requestBody)
    {
        $client = $this->client;
        $jsonBody = $this->makeJsonBody($requestBody);
        try {
            // var_dump($jsonBody);
            $response = $client->request('POST', '/v1/invoice/get', ['json' => $jsonBody]);
        } catch (RequestException $e) {
            echo Psr7\str($e->getRequest())."\n";
            if ($e->hasResponse()) {
                // echo Psr7\str($e->getResponse());
                $response = $e->getResponse();

                $this->handleError($response);
            }
        } catch(Exception $e) {
            throw new BackendException("Unknown Error!!!");
        }

        return $this->handleResponse($response);
    }

    public function cancelInvoice(array $requestBody)
    {
        $client = $this->client;
        $jsonBody = $this->makeJsonBody($requestBody);
        try {
            // var_dump($jsonBody);
            $response = $client->request('POST', '/v1/invoice/cancel', ['json' => $jsonBody]);
        } catch (RequestException $e) {
            echo Psr7\str($e->getRequest())."\n";
            if ($e->hasResponse()) {
                // echo Psr7\str($e->getResponse());
                $response = $e->getResponse();

                $this->handleError($response);
            }
        } catch(Exception $e) {
            throw new BackendException("Unknown Error!!!");
        }

        return $this->handleResponse($response);
    }

    public function makeJsonBody(array $requestBody) {
        $data = count($requestBody) == 0? new stdClass: $requestBody;
        // var_dump($data);
        $body = [
            "auth" => [
                "clientCodeName" => $this->clientCodeName,
                "token" => $this->getEncodedToken($data)
            ],
            "data" => $data
        ];
        return $body;
    }

    public function getEncodedToken($requestBody)
    {
        // var_dump($requestBody);
        $jwt = JWT::encode($requestBody, $this->privateKey, $this->algorithm);
        $decoded = JWT::decode($jwt, $this->publicKey, array('RS256'));
        // print("DECOED\n");
        // var_dump($decoded);
        return $jwt;
    }

    public function handleResponse($response)
    {
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }

    public function handleError($response)
    {
        $contents = $response->getBody()->getContents();
        $contents = json_decode($contents);

        $errorData = $contents->errorData;

        // var_dump($contents);
        throw new BackendException("$contents->error\n$errorData->code: $errorData->message");
    }
}
