<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server\Entities\Traits;

use DateTimeImmutable;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

trait AccessTokenTrait
{
    /**
     * @var CryptKeyInterface
     */
    private $privateKey;

    /**
     * Set the private key used to encrypt this access token.
     */
    public function setPrivateKey(CryptKeyInterface $privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * Generate a JWT from the access token
     *
     * @param CryptKeyInterface $privateKey
     *
     * @return Token
     */
    private function convertToJWT(CryptKeyInterface $privateKey)
    {
        return (new Builder())
            ->setAudience($this->getClient()->getIdentifier())
            ->setId($this->getIdentifier())
            ->setIssuedAt(time())
            ->setNotBefore(time())
            ->setExpiration($this->getExpiryDateTime()->getTimestamp())
            ->setSubject((string) $this->getUserIdentifier())
            ->set('scopes', $this->getScopes())
            ->sign(new Sha256(), new Key($privateKey->getKeyPath(), $privateKey->getPassPhrase()))
            ->getToken();
    }

    /**
     * Generate a string representation from the access token
     */
    public function __toString()
    {
        return (string) $this->convertToJWT($this->privateKey);
    }

    /**
     * @return ClientEntityInterface
     */
    abstract public function getClient();

    /**
     * @return DateTimeImmutable
     */
    abstract public function getExpiryDateTime();

    /**
     * @return string|int
     */
    abstract public function getUserIdentifier();

    /**
     * @return ScopeEntityInterface[]
     */
    abstract public function getScopes();
}
