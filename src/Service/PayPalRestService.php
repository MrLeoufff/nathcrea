<?php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayPalRestService
{
    private HttpClientInterface $client;
    private string $clientId;
    private string $clientSecret;
    private string $baseUri;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, string $clientId, string $clientSecret, LoggerInterface $logger, string $mode = 'sandbox')
    {
        $this->client       = $client;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->logger       = $logger;
        $this->baseUri      = rtrim($mode === 'live'
            ? 'https://api-m.paypal.com/'
            : 'https://api-m.sandbox.paypal.com/', '/');
    }

    public function getAccessToken(): string
    {
        try {
            $response = $this->client->request('POST', "{$this->baseUri}/v1/oauth2/token", [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body'    => 'grant_type=client_credentials',
            ]);

            $data = $response->toArray();

            if (! isset($data['access_token'])) {
                throw new \RuntimeException('Impossible de récupérer un token PayPal.');
            }

            return $data['access_token'];
        } catch (TransportExceptionInterface | ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('Erreur lors de la récupération du token PayPal : ' . $e->getMessage());
            throw new \RuntimeException('Unable to get PayPal access token: ' . $e->getMessage());
        }
    }

    public function createOrder(array $orderData): array
    {
        $accessToken = $this->getAccessToken();

        try {
            $response = $this->client->request('POST', "{$this->baseUri}/v2/checkout/orders", [
                'headers' => [
                    'Authorization' => "Bearer $accessToken",
                    'Content-Type'  => 'application/json',
                ],
                'json'    => $orderData,
            ]);

            return $response->toArray();
        } catch (ClientExceptionInterface $e) {
            $errorResponse = $e->getResponse()->toArray(false);
            $this->logger->error('Erreur lors de la création d\'une commande PayPal : ' . json_encode($errorResponse));
            throw new \RuntimeException('Error creating PayPal order: ' . json_encode($errorResponse));
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors de la création d\'une commande PayPal : ' . $e->getMessage());
            throw new \RuntimeException('Erreur inattendue lors de la création de la commande PayPal.');
        }
    }

    public function captureOrder(string $orderId): array
    {
        try {
            $response = $this->client->request('POST', "{$this->baseUri}/v2/checkout/orders/{$orderId}/capture", [
                'headers' => [
                    'Authorization' => "Bearer {$this->getAccessToken()}",
                    'Content-Type'  => 'application/json',
                ],
            ]);

            return $response->toArray();
        } catch (ClientExceptionInterface $e) {
            $errorResponse = $e->getResponse()->toArray(false);
            $this->logger->error('Erreur lors de la capture de la commande PayPal : ' . json_encode($errorResponse));
            throw new \RuntimeException('Error capturing PayPal order: ' . json_encode($errorResponse));
        } catch (\Exception $e) {
            $this->logger->error('Erreur inattendue lors de la capture de la commande PayPal : ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de la capture de la commande PayPal.');
        }
    }

    public function getOrderStatus(string $orderId): string
    {
        try {
            $response = $this->client->request('GET', "{$this->baseUri}/v2/checkout/orders/{$orderId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->getAccessToken()}",
                    'Content-Type'  => 'application/json',
                ],
            ]);

            $orderDetails = $response->toArray();
            return $orderDetails['status'] ?? 'UNKNOWN';
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la récupération du statut de la commande PayPal : ' . $e->getMessage());
            throw new \RuntimeException('Impossible de récupérer le statut de la commande PayPal.');
        }
    }
}
