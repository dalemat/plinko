<?php

namespace Acmeverse\PlinkoFortune\Api\Controller;

use Acmeverse\PlinkoFortune\PlinkoService;
use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class PlinkoController extends AbstractShowController
{
    protected $service;

    public function __construct(PlinkoService $service)
    {
        $this->service = $service;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $method = $request->getMethod();
        
        if ($method === 'POST') {
            // Play game
            $body = $request->getParsedBody();
            $betAmount = (int)($body['bet_amount'] ?? 0);
            $dropPosition = (int)($body['drop_position'] ?? 4);

            return $this->service->playGame($actor, $betAmount, $dropPosition);
        } else {
            // Get stats
            return $this->service->getStats($actor);
        }
    }
}
