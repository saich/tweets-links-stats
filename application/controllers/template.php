<?php defined('SYSPATH') OR die('No direct access allowed.');

abstract class Template_Controller extends Controller {

	public $template;
	public $session;

	// Default to do auto-rendering
	public $auto_render = TRUE;

	/**
	 * Template loading and setup routine.
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->session = Session::instance();
		
		// Load the template
		$this->template = new View('theme/web_template');

		if ($this->auto_render == TRUE)
		{
			// Render the template immediately after the controller method
			Event::add('system.post_controller', array($this, '_render'));
		}
	}

	/**
	 * Render the loaded template.
	 */
	public function _render()
	{
		if ($this->auto_render == TRUE)
		{
			$this->template->render(TRUE);
		}
	}
}