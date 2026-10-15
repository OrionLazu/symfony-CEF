<?php

namespace App\Tests\Support;

use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;

/**
 * Faux client HTTP branché à la place de cURL dans la bibliothèque Stripe.
 *
 * Il enregistre les requêtes envoyées et renvoie des réponses JSON préparées par le test :
 * le vrai code de stripe-php est exécuté, sans aucun appel réseau.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
    public array $requests = [];

    /** @var list<array{int, array<string, mixed>}> */
    private array $responses = [];

    /**
     * @param array<string, mixed> $body
     */
    public function queueResponse(array $body, int $status = 200): void
    {
        $this->responses[] = [$status, $body];
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => strtoupper($method), 'url' => $absUrl, 'params' => (array) $params];

        if ([] === $this->responses) {
            throw new \LogicException('Aucune réponse Stripe préparée pour '.$method.' '.$absUrl);
        }

        [$status, $body] = array_shift($this->responses);

        return [json_encode($body, JSON_THROW_ON_ERROR), $status, new CaseInsensitiveArray(['request-id' => 'req_test'])];
    }
}
