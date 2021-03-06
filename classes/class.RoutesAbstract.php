<?php
/**
 * Routes container to be inherited by the Config class
 * Has helpers to manipulates URLs and pages parameters
 */

abstract class RoutesAbstract {
	
	/**
	 * Returns the page address by route name and parameters
	 * 
	 * @param string $route	Name of the route
	 * @param array $attrs	Optionnals parameters (associative array)
	 * @return string	Page address
	 */
	public static function getPage($route, $attrs=array()){
		if(!isset($attrs) || !is_array($attrs))
			$attrs = array();
		
		if(isset(Routes::$routes[$route])){
			if(isset(Routes::$routes[$route]['extend'])){
				foreach(Routes::$routes[$route]['extend'] as $vars => $route_){
					$vars = explode('&', $vars);
					foreach($vars as $var){
						if(!isset($attrs[$var]))
							continue 2;
					}
					return self::getPage($route_, $attrs);
				}
			}
			
			$address = Routes::$routes[$route]['url'];
			foreach($attrs as $key => $value)
				$address = str_replace('{'.$key.'}', $value, $address);
			return $address;
		}
		return '';
	}
	
	// Retourne les variables d'une adresse pas l'adresse de la page
	/**
	 * Returns the variables corresponding to a page address
	 * 
	 * @param string $address	Page address
	 * @return array	Variables as an associative array
	 */
	public static function getVars($address){
		
		foreach(Routes::$routes as $route){
			if(preg_match('#'.$route['regexp'].'#', $address)){
				$address = preg_replace('#'.$route['regexp'].'#', $route['vars'], $address);
				break;
			}
		}
		
		$address = str_replace('?', '&', $address);
		parse_str($address, $vars);
		return $vars;
	}
	
	
	/**
	 * Extracts vars from the URL, and calls the relevant controllers and actions
	 */
	public static function dispatch(){
		$params = self::getVars(preg_replace('#^'.preg_quote(Config::URL_ROOT).'#', '', urldecode($_SERVER['REQUEST_URI'])));
		
		if(!__autoload('Layout_Controller'))
			throw new Exception('"Layout_Controller" class not found!');
		
		// Loading the main Controller
		$controller = new Layout_Controller();
		
		if(!isset($params['mode']) || !method_exists($controller, $params['mode']))
			$params['mode'] = 'index';
		
		$controller_name = isset($params['controller']) ? $params['controller'] : '';
		$controller_action = isset($params['action']) ? $params['action'] : '';
		
		// Call of the method __beforeAction if it exists
		if(method_exists($controller, '__beforeAction'))
			$controller->__beforeAction($params['mode'], $params);
		
		if($controller_name=='' || !__autoload($controller_name.'_Controller')){
			$controller_name = 'Page';
			$controller_action = 'error404';
			if(!__autoload($controller_name.'_Controller'))
				throw new Exception('"'.$controller_name.'_Controller" class not found!');
		}
		
		// Loading the sepcific Controller
		try {
			$controller_class = $controller_name.'_Controller';
			$controller->specificController = new $controller_class();
			
			if(!method_exists($controller->specificController, $controller_action))
				throw new Exception('"'.$controller_action.'" method in "'.$controller_name.'_Controller" class not found!');
			if(method_exists($controller->specificController, '__beforeAction'))
				$controller->specificController->__beforeAction($controller_action, $params);
			
			$controller->specificController->{$controller_action}($params);
		
		// If an ActionException is thrown, another controller is called
		}catch(ActionException $e){
			$controller_name = $e->getController();
			$controller_action = $e->getAction();
			$controller_class = $controller_name.'_Controller';
			if(!__autoload($controller_name.'_Controller'))
				throw new Exception('"'.$controller_name.'_Controller" class not found!');
			
			$controller->specificController = new $controller_class();
			
			if(!method_exists($controller->specificController, $controller_action))
				throw new Exception('"'.$controller_action.'" method in "'.$controller_name.'_Controller" class not found!');
			if(method_exists($controller->specificController, '__beforeAction'))
				$controller->specificController->__beforeAction($controller_action, $params);
			
			$controller->specificController->{$controller_action}($e->getParams());
			
		// If an Exception is thrown, the "Page" controller is called and the "error" action displays the error
		}catch(Exception $e){
			$controller_name = 'Page';
			$controller_action = 'error';
			if(!__autoload($controller_name.'_Controller'))
				throw $e;
			
			$controller->specificController = new Page_Controller();
			
			if(!method_exists($controller->specificController, $controller_action))
				throw $e;
			
			$controller->specificController->error($e);
		}
		
		// Call of the main Controller's action
		$controller->{$params['mode']}();
		
		// Rendering the view
		$controller->render();
		
	}
	
	
	/**
	 * Redirects to a page by route name and parameters
	 * 
	 * @param string $route		Name of the route
	 * @param array	 $attrs		Optionnals parameters (associative array)
	 * @param int $http_status	HTTP Status (200, 301, ...)
	 */
	public static function redirect($route, $attrs=array(), $http_status=200){
		switch($http_status){
			case 300:
				header('HTTP/1.1 300 Multiple Choices');
				break;
			case 301:
				header('HTTP/1.1 301 Moved Permanently');
				break;
			case 302:
				header('HTTP/1.1 302 Found');
				break;
			case 303:
				header('HTTP/1.1 303 See Other');
				break;
			case 304:
				header('HTTP/1.1 304 Not Modified');
				break;
			case 305:
				header('HTTP/1.1 305 Use Proxy');
				break;
			case 307:
				header('HTTP/1.1 307 Temporary Redirect');
				break;
		}
		header('Location: '.Config::URL_ROOT.self::getPage($route, $attrs));
		exit;
	}
	
}
