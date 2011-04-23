<?php

// require the Oauth component
require_once Kohana::find_file('vendor','oauth');

class Twitter_Core {
	
	// Stores the logged in Twitter_user_Model
	public $user = NULL;
	
	private $api_url_base = 'https://api.twitter.com';

	private $urls = array(
		'authorize'		=> '/oauth/authorize',
		'access_token'	=> '/oauth/access_token',
		'request_token'	=> '/oauth/request_token'
	);
	
	private $config;
	private $sess;
	private $sha1_method;
	private $consumer;
	private $http_status;
	private $last_api_call;
	
	/**
	 *  Class Constructor
	 *
	 *  Sets up the Oauth consumers and other general cfg
	 *
	 *	@return nothing
	 *	@access public
	 */
	public function __construct($config=FALSE)
	{	
		// set up the configuration
		$this->config = $config ? arr::overwrite(Kohana::config('twitter'),$config) : Kohana::config('twitter');
		// kohana instances setup
		$this->sess = Session::instance();
		// Oauth classes
		$this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
		// consumer key/secret should be set up in the config
		$this->consumer = new OAuthConsumer($this->config['consumer_key'], $this->config['consumer_secret']);
	}
	
	/**
	 *	Obtain request tokens from Twitter
	 *
	 *	Queries Twitter for some tokens to use in the Oauth exchange
	 *	then stores them in a session for later 
	 *
	 *	@access public
	 *	@return Oauth Consumer object
	 *
	 */
	public function getRequestTokens()
	{
		// user model
		$this->user = new Twitter_user_Model;
		
		$r = $this->oAuthRequest($this->getUrl('request_token'));
		
		$token = $this->oAuthParseResponse($r);
		
		$this->user->set_keys($token['oauth_token'],$token['oauth_token_secret']);
		
		$this->user->set_session('twitter_request',FALSE);
		
		return $this->user->consumer;
	}
	
		
	/**
	 *	Retrieve the Oauth Request Keys
	 *
	 *	Gets the request keys out of the stored session and
	 *	creates an Oauth consumer with them with which to make
	 *	the Request/Access key trade
	 *
	 *	@return Oauth Consumer
	 *	@access public
	 *
	 */
	public function sessionRequestTokens()
	{
		// user model
		if ($this->user == NULL)
		{
			$this->user = new Twitter_user_Model;
		}
		
		$token = $this->sess->get('twitter_request',FALSE);
		
		if(is_array($token))
		{
			$this->user->set_keys($token[0],$token[1]);
			
			return $this->user->consumer;
		}
		
		return FALSE;   
	}
	
	/**
	 *	Trades Request keys for user Access keys
	 *
	 *	Makes an Oauth request to Twitter to trade the
	 *	request keys for the users Access keys
	 *
	 *	@return void
	 *	@access public
	 *
	 */
	public function tradeRequestForAccess()
	{
		// make sure we have a reasonable consumer to make use of
		if($this->user == NULL) { $this->sessionRequestTokens(); }
		$r = $this->oAuthRequest($this->getUrl('access_token'));
		$token = $this->oAuthParseResponse($r);
		$this->user->set_keys($token['oauth_token'],$token['oauth_token_secret']);
	}
	
	/**
	 *	Stores Access tokens
	 *
	 *	Stores the users access tokens in the database
	 *	and creates a cookie to persist the login
	 *	if set to do so in the cfg
	 *
	 *	@return boolean
	 *	@access public
	 *
	 */
	public function storeTokens()
	{
		if($this->user == NULL) { return FALSE; }
		
		$credentials = json_decode($this->OAuthRequest('https://twitter.com/account/verify_credentials.json', array(), 'GET'));
		
		if($credentials != NULL)
		{
			$this->user->set_username($credentials->screen_name);
			$this->user->store_keys();
			$this->user->set_session();
			
			// store the user cookie
			if($this->config['use_cookie'] == TRUE)
			{
				cookie::set(arr::merge($this->config['cookie'],array('value'=>$this->user->username.'.'.sha1($this->user->access_key.$this->user->secret_key))));
			}
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 *	Revoke the current Session
	 *
	 *	Delete any stored session data. Otionally delete
	 *	any stored keys for the current user
	 *
	 *	@return void
	 *	@access public
	 *
	 */
	public function revokeSession($delete_keys=FALSE)
	{
		if($delete_keys)
		{
			// delete the keys stored in the db
			$this->user->delete_keys();
		}
		
		// remove all session data
		$this->sess->delete('twitter_oauth','twitter_request');
		
		// delete cookie
		cookie::delete($this->config['cookie']['name']);
		
		$this->user = NULL;
		
	}
	
	/**
	 *	Retrieve an API formatted URL
	 *
	 *	Constructs a url in the correct Twitter API form.
	 *
	 *	@return string
	 *  @access public
	 *
	 */
	public function getUrl($type,$token=FALSE)
	{
		if ( array_key_exists($type,$this->urls) )
		{
			if($token)
			{
				return $this->api_url_base.$this->urls[$type].'?oauth_token='.$token;   
			}
			return $this->api_url_base.$this->urls[$type];
		}
	}
	
	/**
	 *	Construct Authorize URL
	 *
	 *	Returns a formatted API url for making initial auth request
	 *	(convenience function)
	 *
	 *	@return string
	 *
	 */
	public function getAuthorizeUrl()
	{
		return $this->getUrl('authorize',$this->user->access_key);
	}
	
	/**
	 *  Check for a current login
	 *
	 *  Check if there is a user access key/secret for us to load
	 *  From session or cookie (currently cookie is unimplemented)
	 *
	 *	@return boolean
	 *	@access public
	 *
	 */
	public function check_login()
	{
		// first check if we have not already run this..
		if($this->user != NULL)
		{
			return TRUE;
		}
		// first check the session
		$tokens = $this->sess->get('twitter_oauth',FALSE);
		if(!$tokens)
		{
			// no? well check cookies for a valid auth
			$tokens = cookie::get($this->config['cookie']['name'], FALSE, TRUE);
			if(!$tokens)
			{
				// no login to get :(
				return FALSE;
			}
			else
			{
				// otherwise we need to process the cookie
				if ( ($this->user = Twitter_user_Model::get_from_cookie($tokens)) == NULL)
				{
					return FALSE;
				}
			}
			
		}
		else
		{
			// here we process the session
			if ( !is_array($tokens) && ($this->user = Twitter_user_Model::get_from_session($tokens)) == NULL)
			{
				return FALSE;
			}
		}
		
		// finally return TRUE
		return TRUE;
	}
	
	/**
	  * Parse a URL-encoded OAuth response
	  *
	  *	Takes an Oauth URL coded response and converts it into an array format
	  *
	  * @return array
	  *	@access public
	  *
	  */
	function oAuthParseResponse($responseString)
	{
		$r = array();
		foreach (explode('&', $responseString) as $param)
		{
			$pair = explode('=', $param, 2);
			if (count($pair) != 2) continue;
			$r[urldecode($pair[0])] = urldecode($pair[1]);
		}
		return $r;
	}
	
	/**
	  * Format and sign an OAuth / API request
	  *
	  *	@return string
	  */
	function oAuthRequest($url, $args = array(), $method = NULL)
	{
		if (empty($method)) $method = empty($args) ? "GET" : "POST";
		$req = OAuthRequest::from_consumer_and_token($this->consumer, ($this->user) ? $this->user->consumer : NULL, $method, $url, $args);
		$req->sign_request($this->sha1_method, $this->consumer, ($this->user) ? $this->user->consumer : NULL);
		switch ($method) {
			case 'GET': return $this->http($req->to_url());
			case 'POST': return $this->http($req->get_normalized_http_url(), $req->to_postdata());
		}
	}
	
	
  /**
   * Make an HTTP request
   *
   * Uses Curl to retrieve a specified URL and return the page content if successful
   *
   * @return string
   *
   */
  function http($url, $post_data = NULL) {
	$ch = curl_init();
	if (defined("CURL_CA_BUNDLE_PATH")) curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE_PATH);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	if (isset($post_data)) {
	  curl_setopt($ch, CURLOPT_POST, 1);
	  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	}
	$response = curl_exec($ch);
	$this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$this->last_api_call = $url;
	curl_close ($ch);
	return $response;
  }
   
   /* wrap some API methods up for ease of use! */
   
   /**
	*	Set Twitter Status
	*
	*	API Call: updates the status of the current Twitter user to
	*	$message contents
	*
	*	@return array
	*	@access public
	*/
   public function setStatus($message)
   {
		if($this->user == NULL || !$this->user->username)
		{
			return FALSE;
		}
		return json_decode($this->OAuthRequest($this->api_url_base.'/statuses/update.json', array('status' => $message), 'POST'));
	}

	/**
	 *	Get Status
	 *
	 *	API Call: Retrieve the current users statuses (allows passing
	 *	Twitter API options as arguments)
	 *
	 *	@return OauthRequest Object / array
	 */
	function getStatus( $args = array(), $type = 'json')
	{
		if($type == 'json')
		{
			return json_decode($this->OAuthRequest($this->api_url_base.'/statuses/home_timeline.'.$type,$args,'GET'),TRUE);
		}
		return $this->OAuthRequest('http://twitter.com/statuses/home_timeline.'.$type,$args,'GET');
	}
}