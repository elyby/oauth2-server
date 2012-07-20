<?php

namespace oauth2server;

class OAuthServerClientException extends \Exception {}

class OAuthServerUserException extends \Exception {}

class OAuthServerException extends \Exception {}

class AuthServer
{
    private $db = null;

    private $config = array(
        'response_types'    =>  array(
            'code'
        ),
        'scope_delimeter'   =>  ','
    );

    protected $errors = array(
        'invalid_request'   =>  'The request is missing a required parameter, 
            includes an invalid parameter value, includes a parameter more than 
            once, or is otherwise malformed.',
        'unauthorized_client'   =>  'The client is not authorized to request an 
            access token using this method.',
        'access_denied' =>  'The resource owner or authorization server denied 
            the request.',
        'unsupported_response_type' =>  'The authorization server does not 
            support obtaining an access token using this method.',
        'invalid_scope' =>  'The requested scope is invalid, unknown, or 
            malformed.',
        'server_error'  =>  'The authorization server encountered an unexpected 
            condition which prevented it from fulfilling the request.',
        'temporarily_unavailable'   =>  'The authorization server is currently 
        unable to handle the request due to a temporary overloading or 
        maintenance of the server.'
    ); 

    /**
     * Constructor
     * 
     * @access public
     * @param  array $options Optional list of options to overwrite the defaults
     * @return void
     */
    public function __construct(array $options = null)
    {
        if ($options !== null) {
            $this->options = array_merge($this->config, $options);
        }
    }

    /**
     * Register a database abstrator class
     * 
     * @access public
     * @param  object $db A class that implements OAuth2ServerDatabase
     * @return void
     */
    public function registerDbAbstractor($db)
    {
        $this->db = $db;
    }

    /**
     * Check client authorise parameters
     * 
     * @access public
     * @param  array $authParams Optional array of parsed $_GET keys
     * @return array             Authorise request parameters
     */
    public function checkClientAuthoriseParams(array $authParams = null)
    {
        $params = array();

        // Client ID
        if ( ! isset($authParams['client_id']) && ! isset($_GET['client_id'])) {

            throw new OAuthServerClientException('invalid_request: ' . 
                $this->errors['invalid_request']);

        } else {

            $params['client_id'] = (isset($authParams['client_id'])) ? 
                $authParams['client_id'] : $_GET['client_id'];

        }

        // Redirect URI
        if ( ! isset($authParams['redirect_uri']) && 
            ! isset($_GET['redirect_uri'])) {

            throw new OAuthServerClientException('invalid_request: ' . 
                $this->errors['invalid_request']);

        } else {

            $params['redirect_uri'] = (isset($authParams['redirect_uri'])) ? 
                $authParams['redirect_uri'] : $_GET['redirect_uri'];

        }

        // Validate client ID and redirect URI
        $clientDetails = $this->db->validateClient($params['client_id'], null, 
            $params['redirect_uri']);

        if ($clientDetails === false) {

            throw new OAuthServerClientException('unauthorized_client: ' . 
                $this->errors['unauthorized_client']);
        }

        // Response type
        if ( ! isset($authParams['response_type']) && 
            ! isset($_GET['response_type'])) {

            throw new OAuthServerClientException('invalid_request: ' . 
                $this->errors['invalid_request']);

        } else {

            $params['response_type'] = (isset($authParams['response_type'])) ? 
                $authParams['response_type'] : $_GET['response_type'];

            // Ensure response type is one that is recognised
            if ( ! in_array($params['response_type'], 
                $this->config['response_types'])) {

                throw new OAuthServerClientException('unsupported_response_type:
                 ' . $this->errors['unsupported_response_type']);

            }
        }

        // Get and validate scopes
        if (isset($authParams['scope']) || isset($_GET['scope'])) {

            $scopes = $_GET['scope'];
            if (isset($authParams['client_id'])) {
                $authParams['scope'];
            }

            $scopes = explode($this->config['scope_delimeter'], $scopes);

            for ($i = 0; $i++; $i < count($scopes)) {
                $scopes[$i] = trim($scopes[$i]);

                if ($scopes[$i] === '') {
                    unset($scopes[$i]);
                }
            }

            if (count($scopes) === 0) {

                throw new OAuthServerClientException('invalid_request: ' . 
                    $this->errors['invalid_request']);
            }

            $params['scopes'] = array();

            foreach ($scopes as $scope) {

                $scopeDetails = $this->db->getScope($scope);

                if ($scopeDetails === false) {

                    throw new OAuthServerClientException('invalid_scope: ' . 
                        $this->errors['invalid_scope']);

                }

                $params['scopes'][] = $scopeDetails;

            }
        }

        return $params;
    }

    /**
     * Parse a new authorise request
     * 
     * @param  string $type            The session owner's type
     * @param  string $typeId          The session owner's ID
     * @param  array  $authoriseParams The authorise request $_GET parameters
     * @return string                  An authorisation code
     */
    public function newAuthoriseRequest(string $type, string $typeId, array $authoriseParams)
    {
        // Check if the user already has an access token
        $accessToken = $this->db->hasAccessToken($typeId, 
            $authoriseParams['client_id']);

        if ($accessToken !== false) {

            // Validate the access token matches the scopes requested
            $originalScopes = $this->db->accessTokenScopes($accessToken);

            foreach ($authoriseParams['scopes'] as $scope) {

                if ( ! in_array($scope, $originalScopes)) {

                    throw new OAuthServerClientException('invalid_scope: ' . 
                        $this->errors['invalid_scope']);

                }

            }

            // The user has authorised the client so generate a new 
            // authorisation code and return it
            
            $authCode = $this->newAuthCode($authoriseParams['client_id'], 
                'user', $typeId, $authoriseParams['redirect_uri'], 
                $authoriseParams['scopes'], $accessToken);

            return $authCode;
        
        } else {

            $authCode = $this->newAuthCode($authoriseParams['client_id'], 
                'user', $typeId, $authoriseParams['redirect_uri'], 
                $authoriseParams['scopes']);

            return $authCode;
        }
    }

    /**
     * Generate a unique code
     * 
     * Generate a unique code for an authorisation code, or token
     * 
     * @return string A unique code
     */
    private function generateCode()
    {
        return sha1(uniqid(microtime()));
    }

    /**
     * Create a new authorisation code
     * 
     * @param  string $clientId    The client ID
     * @param  string $type        The type of the owner of the session
     * @param  string $typeId      The session owner's ID
     * @param  string $redirectUri The redirect URI
     * @param  array  $scopes      The requested scopes
     * @param  string $accessToken The access token (default = null)
     * @return string              An authorisation code
     */
    public function newAuthCode(string $clientId, $type = 'user',
     string $typeId, string $redirectUri, $scopes = array(),
     string $accessToken = null)
    {
        $authCode = $this->generateCode();

        // If an access token exists then update the existing session with the 
        // new authorisation code otherwise create a new session
        if ($accessToken !== null) {

            $this->db->updateSession(
                $clientId,
                $type,
                $typeId,
                $authCode,
                $accessToken,
                'request'
            );
        
        } else {

            // Delete any existing sessions just to be sure
            $this->db->deleteSession($clientId, $type, $typeId);
               
            // Create a new session     
            $sessionId = $this->db->newSession(
                $clientId,
                $redirectUri,
                $type,
                $typeId,
                $authCode,
                null,
                'request'
            );
                        
            // Add the scopes
            foreach ($scopes as $scope) {

                $this->db->addSessionScope($sessionId, $scope);

            }

        }
        
        return $authCode;
    }    

    /**
     * Generates the redirect uri with appended params
     * 
     * @param  string $redirectUri     The redirect URI
     * @param  array  $params          The parameters to be appended to the URL
     * @param  string $query_delimeter The query string delimiter (default: ?)
     * @return string                  The updated redirect URI
     */
    public function redirectUri(string $redirectUri, $params = array(), 
     $queryDelimeter = '?')
    {
      
        if (strstr($redirectUri, $queryDelimeter)) {

            $redirectUri = $redirectUri . '&' . http_build_query($params);

        } else {

            $redirectUri = $redirectUri . $queryDelimeter . 
            http_build_query($params);

        }
        
        return $redirectUri;

    }

}