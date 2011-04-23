<?php defined('SYSPATH') OR die('No direct access allowed.');

require Kohana::find_file('vendor','OAuth');

class Twitter_Core {
	
	private $config;
	private $oauth_token;
	private $oauth_token_secret;
	private $session;
	private $user_consumer;
	private $is_logged_in;
	private $screen_name;
	
	public function __construct($config=FALSE)
	{
		// set up the configuration
		$this->config = $config ? arr::overwrite(Kohana::config('twitter'),$config) : Kohana::config('twitter');
		// kohana instances setup
		$this->session = Session::instance();
		// Oauth classes
		$this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
		// consumer key/secret should be set up in the config
		$this->consumer = new OAuthConsumer($this->config['consumer_key'], $this->config['consumer_secret']);
		
		// Get oauth token & oauth_token_secret from session
		$this->oauth_token = $this->session->get('twitter_oauth_token');
		$this->oauth_token_secret = $this->session->get('twitter_oauth_token_secret');
		if($this->session->get('twitter_logged_in'))
			$this->user_consumer = new OAuthConsumer($this->oauth_token, $this->oauth_token_secret);
    }
    
    function get_screen_name()
    {
    	if(!$this->screen_name)
    		$this->screen_name = $this->temp();
    	return $this->screen_name;
    }
    
    function http($url, $post_data = null) 
    {
		$ch = curl_init();
		if (defined("CURL_CA_BUNDLE_PATH")) curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_BUNDLE_PATH);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		if (isset($post_data)) 
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		}
		$response = curl_exec($ch);
		$this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->last_api_call = $url;
		curl_close ($ch);
		return $response;
	}
	
	function oAuthRequest($url, $args = array(), $method = NULL)
	{
		if (empty($method)) 
			$method = empty($args) ? "GET" : "POST";
		$req = OAuthRequest::from_consumer_and_token($this->consumer, $this->user_consumer ? $this->user_consumer : NULL, $method, $url, $args);
		$req->sign_request($this->sha1_method, $this->consumer, $this->user_consumer ? $this->user_consumer : NULL);
		switch ($method) 
		{
			case 'GET': return $this->http($req->to_url());
			case 'POST': return $this->http($req->get_normalized_http_url(), $req->to_postdata());
		}
	}
	
	function oAuthParseResponse($responseString)
	{
		$r = array();
		foreach (explode('&', $responseString) as $param)
		{
			$pair = explode('=', $param, 2);
			// Its always a key-value pair, but just confirm
			if(count($pair) == 2)
				$r[urldecode($pair[0])] = urldecode($pair[1]);
		}
		return $r;
	}

	function get_authorize_url($oauth_token)
	{
		return 'https://twitter.com/oauth/authorize?oauth_token='.$oauth_token;
	}
	
	function get_access_token_url()
	{
		return 'https://twitter.com/oauth/access_token';
	}
	
	function get_request_token_url()
	{
		return 'https://twitter.com/oauth/request_token';
	}
	
	function get_login_url()
	{
		$r = $this->oAuthRequest($this->get_request_token_url());
		Kohana::log('error', "Request Token:" . $r);
		$token = $this->oAuthParseResponse($r);
		
		$this->oauth_token = $token['oauth_token'];
		$this->oauth_token_secret = $token['oauth_token_secret'];
		$this->user_consumer = new OAuthConsumer($this->oauth_token, $this->oauth_token_secret);
		$this->session->set('twitter_oauth_token', $this->oauth_token);
		$this->session->set('twitter_oauth_token_secret', $this->oauth_token_secret);
		return $this->get_authorize_url($this->oauth_token);
	}
	
	function get_access_tokens()
	{
		$this->user_consumer = new OAuthConsumer($this->oauth_token, $this->oauth_token_secret);
		$r = $this->oAuthRequest($this->get_access_token_url());
		$token = $this->oAuthParseResponse($r);
		Kohana::log('error', "Access Token:" . $r);
		$this->oauth_token = $token['oauth_token'];
		$this->oauth_token_secret = $token['oauth_token_secret'];
		$this->session->set('twitter_oauth_token', $this->oauth_token);
		$this->session->set('twitter_oauth_token_secret', $this->oauth_token_secret);
		$this->user_consumer = new OAuthConsumer($this->oauth_token, $this->oauth_token_secret);
		$this->session->set('twitter_logged_in', 'adad');
		$this->is_logged_in = true;
	}
	
	function is_logged_in()
	{
		return $this->session->get('twitter_logged_in');
		//return $this->is_logged_in;
	}
	
	function temp()
	{
		$r = $this->OAuthRequest('https://twitter.com/account/verify_credentials.json', array(), 'GET');
		$credentials = json_decode($r);
		//Kohana::log('error', "Verify Token:" . $r);
		return $credentials->screen_name;
	}
	
	function logout()
	{
		$this->session->delete('twitter_logged_in', 'twitter_oauth_token', 'twitter_oauth_token_secret');
	}
	
	function getStatus( $args = array(), $type = 'json')
	{
		if($type == 'json')
		{
			return json_decode($this->OAuthRequest('http://twitter.com/statuses/home_timeline.'.$type,$args,'GET'),True);
		}
		return $this->OAuthRequest('http://twitter.com/statuses/user_timeline.'.$type,$args,'GET');
	}
}
?>