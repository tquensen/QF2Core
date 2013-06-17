<?php
namespace QF;

use \QF\Exception\HttpException;

class Core
{
    protected $container = null;
    protected $i18n = null;
    protected $security = null;
    protected $view = null;
    protected $eventDispatcher = null;
    
    protected $routes = array();
    
    protected $homeRoute = null;
    
    protected $currentRoute = null;
    protected $currentRouteParameter = null;
    
    protected $requestMethod = null;
    
    public function __construct()
    {       
        if (isset($_REQUEST['REQUEST_METHOD'])) {
            $this->requestMethod = strtoupper($_REQUEST['REQUEST_METHOD']);
        } else {
            $this->requestMethod = !empty($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        }
    }
    
    /**
     * gets the routename and parameters from the requested route
     *
     * @param string $route the raw route string
     * @return array an array containing the route name and parameters
     */
    public function parseRoute($route)
    {
        $method = $this->requestMethod;
        
        $language = !empty($this->i18n) ? $this->i18n->getCurrentLanguage() : false;
        
        if (empty($route) && ($homeRoute = $this->homeRoute) && $routeData = $this->getRoute($homeRoute)) {
            return array('route' => $homeRoute, 'parameter' => array());
        } else {
            foreach ((array)$this->getRoute() as $routeName => $routeData) {
                if (!isset($routeData['url'])) {
                    continue;
                }
                
                if (isset($routeData['method']) && ((is_string($routeData['method']) && strtoupper($routeData['method']) != $method) || (is_array($routeData['method']) && !in_array($method, array_map('strtoupper', $routeData['method']))))) {
                    continue;
                }
                
                if (!$routePattern = $this->generateRoutePattern($routeData, $language)) {
                    continue;
                }
                
                if (preg_match($routePattern, $route, $matches)) {
                    $routeParameters = array();
                    foreach ($matches as $paramKey => $paramValue) {
                        if (!is_numeric($paramKey)) {
                            if (trim($paramValue)) {
                                $routeParameters[urldecode($paramKey)] = urldecode($paramValue);
                            }
                        }
                    }
                    
                    $this->currentRoute = $routeName;
                    $this->currentRouteParameter = $routeParameters;
                    if (array_key_exists('_format', $routeParameters)) {
                        $this->getView()->setFormat($routeParameters['_format']);
                    }
                    if (array_key_exists('_template', $routeParameters)) {
                        $this->getView()->setTemplate($routeParameters['_template']);
                    }
                    

                    return array('route' => $routeName, 'parameter' => $routeParameters);
                }
            }
        }

        throw new HttpException('page not found', 404);
    }
    
    /**
     *
     * @param string $route the key of the route to get
     * @return mixed the routes array or a specifig route (if $route is set)
     */
    public function getRoute($route = null)
    {
        $routes = $this->routes;
        if (!$route) {
            return $routes;
        }
        return isset($routes[$route]) ? $routes[$route] : null;
    }  

    /**
     * redirects to the given url (by setting a location http header)
     *
     * @param string $url the (absolute) target url
     * @param int $code the code to send (302 (default) or 301 (permanent redirect))
     */
    public function redirect($url, $code = 302)
    {
        header('Location: ' . $url, true, $code);
        if (session_name()) {
            session_write_close();
        }
        exit;
    }

    /**
     * redirects to the given route (by setting a location http header)
     *
     * @param string $route the name of the route to link to
     * @param array $params parameter to add to the url
     * @param mixed $language the target language, null for current, false for default/none, string for a specific language (must exist in$qf_config['languages'])
     * @param int $code the code to send (302 (default) or 301 (permanent redirect))
     */
    public function redirectRoute($route, $params = array(), $code = 302, $language = null)
    {
        $this->redirect($this->getUrl($route, $params, $language), $code);
    }
    
    /**
     *
     * @param string $route the key of the route to get
     * @param array $parameter parameters for the page
     * @return @return string the parsed output of the page
     */
    public function callRoute($route, $parameter = array())
    {
        $routeData = $this->getRoute($route);
        if (!$routeData || empty($routeData['controller']) || empty($routeData['action'])) {
            throw new HttpException('page not found', 404);
        }
        
        $this->security->checkRouteRights($routeData);
        
        if (!empty($routeData['parameter'])) {
            $parameter = array_merge($routeData['parameter'], $parameter);
        }
          
        return $this->callAction($routeData['controller'], $routeData['action'], $parameter);       
    }
    
    /**
     * calls the error page defined by $errorCode and shows $message
     *
     * @param string $errorCode the error page name (default error pages are 401, 403, 404, 500)
     * @param string $message a message to show on the error page, leave empty for default message depending on error code
     * @param Exception $exception an exception to display (only if QF_DEBUG = true)
     * @return string the parsed output of the error page
     */
    public function callError($errorCode = 404, $message = '', $exception = null)
    {
        return $this->callRoute('error'.$errorCode, array('message' => $message, 'exception' => $exception));
    }
    
    /**
     * calls the action defined by $controller and $action and returns the output
     *
     * @param string $controller the controller
     * @param string $action the action
     * @param array $parameter parameters for the page
     * @return string the parsed output of the page
     */
    public function callAction($controller, $action, $parameter = array())
    {
        if (!class_exists($controller) || !method_exists($controller, $action)) {
            throw new HttpException('action not found', 404);
        }
        
        $controller = new $controller();
        return $controller->$action($parameter, $this, $this->getView());
    }
    
    /**
     * builds an internal url
     *
     * @param string $route the name of the route to link to
     * @param array $params parameter to add to the url
     * @param mixed $language the target language, null for current, false for default/none, string for a specific language (must exist in$qf_config['languages'])
     * @return string the url to the route including base_url (if available) and parameter
     */
    public function getUrl($route, $params = array(), $language = null)
    {
        $baseurl = $this->getView()->getBaseUrl() ?: '/';
        
        if ($language === null && !empty($this->i18n)) {
            $language = $this->getI18n()->getCurrentLanguage();
        }
        if ($language && !empty($this->i18n) && in_array($language, $this->getI18n()->getLanguages()) && $language != $this->getI18n()->getDefaultLanguage()) {
            if ($baseurlI18n = $this->getView()->getBaseUrlI18n()) {
                $baseurl = str_replace(':lang:', $language, $baseurlI18n);
            }
        }
        
        if ((!$route || $route == $this->homeRoute) && empty($params)) {
            return $baseurl;
        }
        if (!($routeData = $this->getRoute($route)) || empty($routeData['url'])) {
            return false;
        }

        $search = array('(',')');
		$replace = array('','');
        $regexSearch =  array();

        if (is_array($routeData['url'])) {
            if ($language && isset($routeData['url'][$language])) {
                $url = $routeData['url'][$language];
            } elseif (isset($routeData['url']['default'])) {
                $url = $routeData['url']['default'];
            } else {
                return false;
            } 
        } else {
            $url = $routeData['url'];
        }
        
        $allParameter = array_merge(isset($routeData['parameter']) ? $routeData['parameter'] : array(), $params);
		foreach ($allParameter as $param=>$value)
		{
            //remove optional parameters if -it is set to false, -it is the default value or -it doesn't match the parameter pattern
            if (!$value || empty($params[$param]) || (isset($routeData['parameter'][$param]) && $value == $routeData['parameter'][$param]) || (isset($routeData['patterns'][$param]) && !preg_match('#^'.$routeData['patterns'][$param].'$#', $value))) {
                $regexSearch[] = '#\([^:\)]*:'.$param.':[^\)]*\)#U';
            }
            $currentSearch = ':'.$param.':';
            $search[] = $currentSearch;
            $replace[] = urlencode($value);
		}
        if (count($regexSearch)) {
            $url = preg_replace($regexSearch, '', $url);
        }
		$url = str_replace($search, $replace, $url);
        
        return $baseurl.$url;
    }
    
    /**
     * builds a html link element or inline form
     *
     * @param string $title the link text
     * @return string the url to the route including base_url (if available) and parameter
     */
    public function getLink($title, $url, $method = null, $attrs = array(), $tokenName = null, $confirm = null, $postData = array(), $formOptions = array())
    {
        if (!$url) {
            return $title;
        }

        if (!$method) {
            $method = 'GET';
        }

        if ($method == 'GET') {
            $attributes = '';
            foreach ((array) $attrs as $k => $v) {
                $attributes .= ' '.$k.'="'.$v.'"';
            }
            return '<a href="'.htmlspecialchars($url).'"'.($attributes).($confirm ? ' onclick="return confirm(\''.htmlspecialchars($confirm).'\')"' : '').'>'.$title.'</a>';
        } else {
            $form = new \QF\Form\Form(array_merge(array(
                'name' => md5($url).'Form',
                'action' => $url,
                'method' => strtoupper($method),
                'class' => 'inlineForm',
                'wrapper' => 'plain',
                'formTokenName' => $tokenName ?: 'form_token'
            ), $formOptions));
            if ($confirm) {
                $form->setOption('attributes', array('onsubmit' => 'return confirm(\''.htmlspecialchars($confirm).'\')'));
            }
            $form->setElement(new \QF\Form\Element\Button('_submit', array('label' => $title, 'attributes' => $attrs ? $attrs : array())));
            foreach ((array) $postData as $postKey => $postValue) {
                $form->setElement(new \QF\Form\Element\Hidden($postKey, array('alwaysDisplayDefault' => true, 'defaultValue' => $postValue)));
            }
            return $this->getView()->parse('DefaultModule', 'form/form', array('form' => $form, '_format' => false));
        }
    }
    
    public function getContainer()
    {
        return $this->container;
    }

    public function getI18n()
    {
        return $this->i18n;
    }

    public function getSecurity()
    {
        return $this->security;
    }
    
    public function getView()
    {
        return $this->view;
    }
    
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    public function getRoutes()
    {
        return $this->routes;
    }
    
    public function getHomeRoute()
    {
        return $this->homeRoute;
    }

    public function getRequestMethod()
    {
        return $this->requestMethod;
    }
    
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }
    
    public function getCurrentRouteParameter()
    {
        return $this->currentRouteParameter;
    }
    
    public function getRequestHash($includeI18n = false)
    {
        return md5(serialize(array($this->currentRoute, $this->currentRouteParameter, $this->requestMethod, $includeI18n && !empty($this->i18n) ? $this->getI18n()->getCurrentLanguage() : '')));
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function setI18n($i18n)
    {
        $this->i18n = $i18n;
    }

    public function setSecurity($security)
    {
        $this->security = $security;
    }
   
    public function setView($view)
    {
        $this->view = $view;
    }
    
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setRoutes($routes)
    {
        $this->routes = $routes;
    }

    public function setHomeRoute($homeRoute)
    {
        $this->homeRoute = $homeRoute;
    }
    
    public function setRequestMethod($requestMethod)
    {
        $this->requestMethod = strtoupper($requestMethod);
    }
    
    public function setCurrentRoute($currentRoute)
    {
        $this->currentRoute = $currentRoute;
    }
    
    public function setCurrentRouteParameter($currentRouteParameter)
    {
        $this->currentRouteParameter = $currentRouteParameter;
    }
        
    protected function generateRoutePattern($routeData, $language) {
        if (is_array($routeData['url'])) {
            if ($language && isset($routeData['url'][$language])) {
                $url = $routeData['url'][$language];
            } elseif (isset($routeData['url']['default'])) {
                $url = $routeData['url']['default'];
            } else {
                return false;
            }        
        } else {
            $url = $routeData['url'];
        }
        $routePattern = str_replace(array('?','(',')','[',']','.'), array('\\?','(',')?','\\[','\\]','\\.'), $url);
        if (isset($routeData['patterns'])) {
            $search = array();
            $replace = array();
            foreach ($routeData['patterns'] as $param => $regex) {
                $search[] = ':' . $param . ':';
                $replace[] = '(?P<' . $param . '>' . $regex . ')';
            }

            $routePattern = str_replace($search, $replace, $routePattern);
        }
        $routePattern = preg_replace('#:([^:]+):#i', '(?P<$1>[^\./]+)', $routePattern);

        $routePattern = '#^' . $routePattern . '$#';

        return $routePattern;
    }

}