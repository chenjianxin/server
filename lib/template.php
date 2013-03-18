<?php
/**
 * ownCloud
 *
 * @author Frank Karlitschek
 * @author Jakob Sack
 * @copyright 2012 Frank Karlitschek frank@owncloud.org
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once __DIR__.'/template/functions.php';

/**
 * This class provides the templates for owncloud.
 */
class OC_Template{
	private $renderas; // Create a full page?
	private $application; // template Application
	private $vars; // Vars
	private $template; // The path to the template
	private $l10n; // The l10n-Object
	private $headers=array(); //custom headers

	/**
	 * @brief Constructor
	 * @param string $app app providing the template
	 * @param string $file name of the template file (without suffix)
	 * @param string $renderas = ""; produce a full page
	 * @return OC_Template object
	 *
	 * This function creates an OC_Template object.
	 *
	 * If $renderas is set, OC_Template will try to produce a full page in the
	 * according layout. For now, renderas can be set to "guest", "user" or
	 * "admin".
	 */
	public function __construct( $app, $name, $renderas = "" ) {
		// Set the private data
		$this->renderas = $renderas;
		$this->application = $app;
		$this->vars = array();
		$this->vars['requesttoken'] = OC::$session ? OC_Util::callRegister() : '';
		$parts = explode('/', $app); // fix translation when app is something like core/lostpassword
		$this->l10n = OC_L10N::get($parts[0]);

		// Some headers to enhance security
		header('X-XSS-Protection: 1; mode=block'); // Enforce browser based XSS filters
		header('X-Content-Type-Options: nosniff'); // Disable sniffing the content type for IE

		// iFrame Restriction Policy
		$xFramePolicy = OC_Config::getValue('xframe_restriction', true);
		if($xFramePolicy) {
			header('X-Frame-Options: Sameorigin'); // Disallow iFraming from other domains
		}
		
		// Content Security Policy
		// If you change the standard policy, please also change it in config.sample.php
		$policy = OC_Config::getValue('custom_csp_policy',
			'default-src \'self\'; '
			.'script-src \'self\' \'unsafe-eval\'; '
			.'style-src \'self\' \'unsafe-inline\'; '
			.'frame-src *; '
			.'img-src *; '
			.'font-src \'self\' data:; '
			.'media-src *');
		header('Content-Security-Policy:'.$policy); // Standard

		$this->findTemplate($name);
	}

	/**
	 * autodetect the formfactor of the used device
	 * default -> the normal desktop browser interface
	 * mobile -> interface for smartphones
	 * tablet -> interface for tablets
	 * standalone -> the default interface but without header, footer and
	 *	sidebar, just the application. Useful to use just a specific
	 *	app on the desktop in a standalone window.
	 */
	public static function detectFormfactor() {
		// please add more useragent strings for other devices
		if(isset($_SERVER['HTTP_USER_AGENT'])) {
			if(stripos($_SERVER['HTTP_USER_AGENT'], 'ipad')>0) {
				$mode='tablet';
			}elseif(stripos($_SERVER['HTTP_USER_AGENT'], 'iphone')>0) {
				$mode='mobile';
			}elseif((stripos($_SERVER['HTTP_USER_AGENT'], 'N9')>0)
				and (stripos($_SERVER['HTTP_USER_AGENT'], 'nokia')>0)) {
				$mode='mobile';
			}else{
				$mode='default';
			}
		}else{
			$mode='default';
		}
		return($mode);
	}

	/**
	 * @brief Returns the formfactor extension for current formfactor
	 */
	static public function getFormFactorExtension()
	{
		if (!\OC::$session) {
			return '';
		}
		// if the formfactor is not yet autodetected do the
		// autodetection now. For possible formfactors check the
		// detectFormfactor documentation
		if (!\OC::$session->exists('formfactor')) {
			\OC::$session->set('formfactor', self::detectFormfactor());
		}
		// allow manual override via GET parameter
		if(isset($_GET['formfactor'])) {
			\OC::$session->set('formfactor', $_GET['formfactor']);
		}
		$formfactor = \OC::$session->get('formfactor');
		if($formfactor=='default') {
			$fext='';
		}elseif($formfactor=='mobile') {
			$fext='.mobile';
		}elseif($formfactor=='tablet') {
			$fext='.tablet';
		}elseif($formfactor=='standalone') {
			$fext='.standalone';
		}else{
			$fext='';
		}
		return $fext;
	}

	/**
	 * @brief find the template with the given name
	 * @param string $name of the template file (without suffix)
	 *
	 * Will select the template file for the selected theme and formfactor.
	 * Checking all the possible locations.
	 */
	protected function findTemplate($name)
	{
		// Read the selected theme from the config file
		$theme = OC_Util::getTheme();

		// Read the detected formfactor and use the right file name.
		$fext = self::getFormFactorExtension();

		$app = $this->application;
		// Check if it is a app template or not.
		if( $app != "" ) {
			// Check if the app is in the app folder or in the root
			if( file_exists(OC_App::getAppPath($app)."/templates/" )) {
				// Check if the template is overwritten by the selected theme
				if ($this->checkPathForTemplate(OC::$SERVERROOT."/themes/$theme/apps/$app/templates/", $name, $fext)) {
				}elseif ($this->checkPathForTemplate(OC_App::getAppPath($app)."/templates/", $name, $fext)) {
				}
			}else{
				// Check if the template is overwritten by the selected theme
				if ($this->checkPathForTemplate(OC::$SERVERROOT."/themes/$theme/$app/templates/", $name, $fext)) {
				}elseif ($this->checkPathForTemplate(OC::$SERVERROOT."/$app/templates/", $name, $fext)) {
				}else{
					echo('template not found: template:'.$name.' formfactor:'.$fext
						.' webroot:'.OC::$WEBROOT.' serverroot:'.OC::$SERVERROOT);
					die();
				}

			}
		}else{
			// Check if the template is overwritten by the selected theme
			if ($this->checkPathForTemplate(OC::$SERVERROOT."/themes/$theme/core/templates/", $name, $fext)) {
			} elseif ($this->checkPathForTemplate(OC::$SERVERROOT."/core/templates/", $name, $fext)) {
			}else{
				echo('template not found: template:'.$name.' formfactor:'.$fext
					.' webroot:'.OC::$WEBROOT.' serverroot:'.OC::$SERVERROOT);
				die();
			}
		}
	}

	/**
	 * @brief check Path For Template with and without $fext
	 * @param string $path to check
	 * @param string $name of the template file (without suffix)
	 * @param string $fext formfactor extension
	 * @return bool true when found
	 *
	 * Will set $this->template and $this->path if there is a template at
	 * the specific $path
	 */
	protected function checkPathForTemplate($path, $name, $fext)
	{
		if ($name =='') return false;
		$template = null;
		if( is_file( $path.$name.$fext.'.php' )) {
			$template = $path.$name.$fext.'.php';
		}elseif( is_file( $path.$name.'.php' )) {
			$template = $path.$name.'.php';
		}
		if ($template) {
			$this->template = $template;
			$this->path = $path;
			return true;
		}
		return false;
	}

	/**
	 * @brief Assign variables
	 * @param string $key key
	 * @param string $value value
	 * @return bool
	 *
	 * This function assigns a variable. It can be accessed via $_[$key] in
	 * the template.
	 *
	 * If the key existed before, it will be overwritten
	 */
	public function assign( $key, $value) {
		$this->vars[$key] = $value;
		return true;
	}

	/**
	 * @brief Appends a variable
	 * @param string $key key
	 * @param string $value value
	 * @return bool
	 *
	 * This function assigns a variable in an array context. If the key already
	 * exists, the value will be appended. It can be accessed via
	 * $_[$key][$position] in the template.
	 */
	public function append( $key, $value ) {
		if( array_key_exists( $key, $this->vars )) {
			$this->vars[$key][] = $value;
		}
		else{
			$this->vars[$key] = array( $value );
		}
	}

	/**
	 * @brief Add a custom element to the header
	 * @param string $tag tag name of the element
	 * @param array $attributes array of attrobutes for the element
	 * @param string $text the text content for the element
	 */
	public function addHeader( $tag, $attributes, $text='') {
		$this->headers[]=array('tag'=>$tag,'attributes'=>$attributes, 'text'=>$text);
	}

	/**
	 * @brief Prints the proceeded template
	 * @return bool
	 *
	 * This function proceeds the template and prints its output.
	 */
	public function printPage() {
		$data = $this->fetchPage();
		if( $data === false ) {
			return false;
		}
		else{
			print $data;
			return true;
		}
	}

	/**
	 * @brief Proceeds the template
	 * @return bool
	 *
	 * This function proceeds the template. If $this->renderas is set, it
	 * will produce a full page.
	 */
	public function fetchPage() {
		$data = $this->_fetch();

		if( $this->renderas ) {
			$page = new OC_TemplateLayout($this->renderas);

			// Add custom headers
			$page->assign('headers', $this->headers, false);
			foreach(OC_Util::$headers as $header) {
				$page->append('headers', $header);
			}

			$page->assign( "content", $data, false );
			return $page->fetchPage();
		}
		else{
			return $data;
		}
	}

	/**
	 * @brief doing the actual work
	 * @return string content
	 *
	 * Includes the template file, fetches its output
	 */
	private function _fetch() {
		// Register the variables
		$_ = $this->vars;
		$l = $this->l10n;

		// Execute the template
		ob_start();
		include $this->template; // <-- we have to use include because we pass $_!
		$data = ob_get_contents();
		@ob_end_clean();

		// return the data
		return $data;
	}

	/**
	 * @brief Include template
	 * @return string returns content of included template
	 *
	 * Includes another template. use <?php echo $this->inc('template'); ?> to
	 * do this.
	 */
	public function inc( $file, $additionalparams = null ) {
		$_ = $this->vars;
		$l = $this->l10n;

		if( !is_null($additionalparams)) {
			$_ = array_merge( $additionalparams, $this->vars );
		}

		// Include
		ob_start();
		include $this->path.$file.'.php';
		$data = ob_get_contents();
		@ob_end_clean();

		// Return data
		return $data;
	}

	/**
	 * @brief Shortcut to print a simple page for users
	 * @param string $application The application we render the template for
	 * @param string $name Name of the template
	 * @param array $parameters Parameters for the template
	 * @return bool
	 */
	public static function printUserPage( $application, $name, $parameters = array() ) {
		$content = new OC_Template( $application, $name, "user" );
		foreach( $parameters as $key => $value ) {
			$content->assign( $key, $value );
		}
		print $content->printPage();
	}

	/**
	 * @brief Shortcut to print a simple page for admins
	 * @param string $application The application we render the template for
	 * @param string $name Name of the template
	 * @param array $parameters Parameters for the template
	 * @return bool
	 */
	public static function printAdminPage( $application, $name, $parameters = array() ) {
		$content = new OC_Template( $application, $name, "admin" );
		foreach( $parameters as $key => $value ) {
			$content->assign( $key, $value );
		}
		return $content->printPage();
	}

	/**
	 * @brief Shortcut to print a simple page for guests
	 * @param string $application The application we render the template for
	 * @param string $name Name of the template
	 * @param string $parameters Parameters for the template
	 * @return bool
	 */
	public static function printGuestPage( $application, $name, $parameters = array() ) {
		$content = new OC_Template( $application, $name, "guest" );
		foreach( $parameters as $key => $value ) {
			$content->assign( $key, $value );
		}
		return $content->printPage();
	}

	/**
		* @brief Print a fatal error page and terminates the script
		* @param string $error The error message to show
		* @param string $hint An optional hint message 
		* Warning: All data passed to $hint needs to get sanitized using OC_Util::sanitizeHTML
		*/
	public static function printErrorPage( $error_msg, $hint = '' ) {
		$content = new OC_Template( '', 'error', 'error' );
		$errors = array(array('error' => $error_msg, 'hint' => $hint));
		$content->assign( 'errors', $errors );
		$content->printPage();
		die();
	}
	
	/**
	 * print error page using Exception details
	 * @param Exception $exception
	 */
	
	public static function printExceptionErrorPage(Exception $exception) {
		$error_msg = $exception->getMessage();
		if ($exception->getCode()) {
			$error_msg = '['.$exception->getCode().'] '.$error_msg;
		}
		$hint = $exception->getTraceAsString();
		if (!empty($hint)) {
			$hint = '<pre>'.$hint.'</pre>';
		}
		while (method_exists($exception,'previous') && $exception = $exception->previous()) {
			$error_msg .= '<br/>Caused by: ';
			if ($exception->getCode()) {
				$error_msg .= '['.$exception->getCode().'] ';
			}
			$error_msg .= $exception->getMessage();
		};
		self::printErrorPage($error_msg, $hint);
	}
}
