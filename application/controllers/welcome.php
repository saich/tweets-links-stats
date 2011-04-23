<?php defined('SYSPATH') OR die('No direct access allowed.');

class Welcome_Controller extends Template_Controller {

	private $twitter;
	
	private $next_since = FALSE;
	
	// TODO: Reduce this number
	const HOME_TIMELINE_MAX_COUNT = 50;
	
	function __construct()
	{
		parent::__construct();
		$this->twitter = new Twitter();
	}
	
	function index()
	{
		$this->template->title = "Home Page";
		$this->template->content = new View('home/index');
		return;
		if($this->twitter->is_logged_in()) 
		{
			$screen_name = $this->twitter->get_screen_name();
			$user = $this->get_user_from_db($screen_name);
			
			$statuses = $this->get_desired_statuses($user->since_id);
			
			echo "Total: ", count($statuses), '<br/>';
			foreach($statuses as $tweet)
			{
				// Insert the tweet in the DB
				$tweet_orm = ORM::factory('tweet')->where('tweet_id', $tweet['id_str'])->find();
				if($tweet_orm->loaded === FALSE) 
				{
					$tweet_orm->user_id =  $user->id;
					$tweet_orm->tweet_id = $tweet['id_str'];
					$tweet_orm->content = $tweet['text'];
					
					$hosts = array();
					foreach ($tweet['entities']['urls'] as $url)
					{
						$hosts[] = $this->get_host($this->get_host_from_url($url['url']));
					}
					$tweet_orm->domains = $hosts;
					$tweet_orm->save();
					if($tweet_orm->saved === FALSE)
					{
						Kohana::log('error', 'Error in saving a tweet in the database');
					}
				}
			}
			
			// Save the since in the database
			// TODO: Add logs on failure
			// TODo: User must be this
			if($user->since_id) 
			{
				$user->since_id = $this->next_since;
				$user->save();
			}
		}
		else
		{
			// Get Request Token & generate access URL
			//$this->twitter->getRequestTokens();
			//$twitter_access_url = $this->twitter->get_login_url();
			$this->template->content = new View('home/index');
		}
	}
	
	function completed()
	{
		// Get Access tokens
		$this->twitter->get_access_tokens();
		// Redirect to home page
		 url::redirect('');
	}
	
	function logout()
	{
		$this->twitter->logout();
	}
	
	public function get_login_status()
	{
		$this->auto_render = FALSE;
		if($this->twitter->is_logged_in() === FALSE)
		{
			echo json_encode(array('login_url' => $this->twitter->get_login_url()));
		}
		else
		{
			echo json_encode(array('logged_in' => 'true'));
		}
	}
	
	public function get_tweets()
	{
		$this->auto_render = FALSE;
		if($this->twitter->is_logged_in() === FALSE)
		{
			$this->get_login_status();
			return;
		}
		
		$screen_name = $this->session->get('twitter_screen_name');
		$user = $this->get_user_from_db($screen_name);
		$i = $this->input->get('last_id', 0);
		$tweets = ORM::factory('tweet')
			->where('user_id', $user->id)
			->orderby('id', 'asc')
			->where('id>', $i)->find_all();
		$tweets_arr = array();
		foreach($tweets as $tweet) {
			$tweet->content = text::auto_link_urls($tweet->content);
			$tweets_arr[] = ($tweet->as_array());
		}
			
		$data = array('tweets' => $tweets_arr);
		if(count($tweets_arr) > 0)
		{
			// Update the popular sites in the user's network
			$query = "select domains.name, count(domain_id) as counter from tweets 
					join domains_tweets join domains where 
					domains.id = domains_tweets.domain_id && tweets.id = domains_tweets.tweet_id 
					and user_id = {$user->id} group by domain_id limit 0,5"; 
			
			$data['stats'] = Database::instance()->query($query)->as_array();
			$last_tweet = end($tweets_arr);
			$data['last_id'] = $last_tweet['id'];
		}
		
		echo json_encode($data);
	}
	
	private function &get_statuses($count, $since = FALSE, $max = FALSE)
	{
		$options = array(
			'count' => $count,
			'include_entities' => TRUE
		);
		
		if($since != FALSE)
			$options['since_id'] = $since;
		
		if($max != FALSE)
			$options['max_id'] = $max;
		
		$statuses = $this->twitter->getStatus($options);

		return $statuses;
	}
	
	// TODO: Get the Max ID & store in the DB to use wih 'since'
	private function get_desired_statuses($since = FALSE)
	{
		$get_statuses = TRUE;
		$max = FALSE;
		$current_time = time();
		$filtered_array = array();
		$next_since = FALSE;
		
		while ($get_statuses)
		{
			$statuses = $this->get_statuses(self::HOME_TIMELINE_MAX_COUNT, $since, $max);
			$count = count($statuses);			
			foreach($statuses as $element)
			{
				// Add the filtered array if satisfies the required condition
				if($current_time - strtotime($element['created_at']) <= 5 * 24 * 60 * 60)
				{
					if($element && array_key_exists('entities', $element))
					{
						$entities = $element['entities'];
						if(is_array($entities) && array_key_exists('urls', $entities))
						{
							$urls = $entities['urls'];
							if(is_array($urls) && count($urls) > 0)
							{
								$filtered_array[] = $element;
							}
						}
					}
				}
				// Obtain the 'since_id' value
				if($count > 0 && $next_since === FALSE)
				{
					$first_item = $statuses[0];
					$next_since = $first_item['id_str'];
				}
			}
			
			// Determine if I need to go with another loop
			$get_statuses = FALSE;
			if($count === self::HOME_TIMELINE_MAX_COUNT)
			{
				$last_item = end($statuses);
				if($current_time - strtotime($last_item['created_at']) <= 5 * 24 * 60 * 60)
				{
					// Update the max value from the last element itself.
					$max = $last_item['id_str'];
					// TODO: Add 1 to max_id
					$get_statuses = TRUE;
				}
			}
		}
		// TODO: Save the next_since in the DB
		$this->next_since = $next_since;
		
		return $filtered_array;
	}
	
	private function get_user_from_db($screen_name)
	{
		$user = ORM::factory('user')->where('screen_name', $screen_name)->find();
		if($user->loaded === FALSE)
		{
			// Add entry in the user table
			$details = array('screen_name' => $screen_name);
			if( $user->validate($details, TRUE) === FALSE )
			{
				// Strange - Validation Error
				Kohana::log('error', 'Validation Error - Not saved');
			}
			else if($user->saved === FALSE)
			{
				// Not saved to DB, no idea why
				Kohana::log('error', 'DB Error - Not saved');
			}
		}
		return $user;
	}
	
	private function get_host($hostname)
	{
		$host = ORM::factory('domain', $hostname);
		if($host->loaded === FALSE)
		{
			$host->name = $hostname;
			$host->save();
			if($host->saved === FALSE)
			{
				Kohana::log('error', 'Saving a domain name in the table failed!');
			}
		}
		return $host->id;
	}
	
	private function get_host_from_url($url)
	{
		return parse_url($url, PHP_URL_HOST);
	}
}
?>