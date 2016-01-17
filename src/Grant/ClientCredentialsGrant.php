<?php
/**
 * OAuth 2.0 Client credentials grant
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server\Grant;

use League\OAuth2\Server\Entities\Interfaces\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Client credentials grant class
 */
class ClientCredentialsGrant extends AbstractGrant
{
    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'client_credentials';

    /**
     * @inheritdoc
     */
    public function respondToRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $tokenTTL
    ) {
        // Validate request
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request), $client);

        // Issue and persist access token
        $accessToken = $this->issueAccessToken($tokenTTL, $client, $client->getIdentifier(), $scopes);
        $this->accessTokenRepository->persistNewAccessToken($accessToken);

        // Inject access token into response type
        $responseType->setAccessToken($accessToken);

        return $responseType;
    }
}
