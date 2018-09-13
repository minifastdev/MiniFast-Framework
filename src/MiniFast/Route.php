<?php

namespace MiniFast;

class Route
{
    private $route;
    private $routeToUse;
    private $default = [];
    private $vars = [];
    private $controllerDir;
    private $controllers = [];
    private $templateDir;

    public function __construct()
    {
        $basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        $uri = substr($_SERVER['REQUEST_URI'], strlen($basepath));
        if(strstr($uri, '?')) $uri = substr($uri, 0, strpos($uri, '?'));
        $uri = '/' . trim($uri, '/');
        $this->route = $uri;
    }
    
    public function fromFile($file, string $controllerDir = '', string $templateDir = '')
    {
        if(!empty($controllerDir))
        {
            if(is_array($controllerDir))
            {
                $this->controllerDir = array_merge($this->controllerDir, $controllerDir);
            }
            elseif(is_string($controllerDir))
            {
                $this->controllerDir = $controllerDir;
            }
        }
        
        if(!empty($templateDir))
        {
            $this->templateDir = $templateDir;
        }
        
        // If there are multiple routing files, check all files
        if(is_array($file))
        {
            foreach($file as $f)
            {
                if(is_string($f))
                {
                    // Does the file exists?
                    if(file_exists($f))
                    {
                        $this->fromFile($f);
                    }
                }
            }
        }
        elseif(is_string($file))
        {
            // Does the file exists?
            if(file_exists($file))
            {
                $routes = json_decode(file_get_contents($file), true);

                if($routes === null)
                {
                    die("$file is not a valid JSON." . PHP_EOL);
                }
                else
                {
                    // If all seems ok, start parsing
                    // If the route if bigger than 1
                    $route = $this->findBySection($routes);

                    if($route)
                    {
                        $this->routeToUse = $route;
                        $this->useRoute($this->routeToUse);
                    }
                    elseif(!empty($this->default))
                    {
                        $this->useRoute($this->default);
                    }
                }
            }
            else
            {
                die("The file $file does not exists." . PHP_EOL);
            }
        }
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function getRouteAsArray()
    {
        $route = trim($this->route, '/');
        $routes = explode('/', $route);
        $cleanRoute = [];

        foreach($routes as $route)
        {
            if(trim($route) != '')
            {
                $cleanRoute[] = $route;
            }
        }

        return $cleanRoute;
    }

    public function getRouteAsJSON()
    {
        return json_encode($this->getRouteAsArray());
    }
    
    /**
     * Search in a section if there is the route we want
     * @param  array $routes The route to search into
     * @param int   $index  The current index of the route
     * @return array The route we wanted, thank you
     */
    private function findBySection(array $routes, int $index = 0)
    {
        $currentRoute = $this->getRouteAsArray();
        $route = [];
        $testVar = true;
        
        if(isset($routes['default']))
        {
            $this->mergeDefault($routes['default']);
        }
        
        if(sizeof($currentRoute) > 1)
        {
            $match = (sizeof($currentRoute) > ($index + 1)) ? 'sections' : 'routes';
            
            if(isset($routes[$match]))
            {
                foreach($routes[$match] as $section)
                {
                    if(isset($section['name']))
                    {
                        if($section['name'] == $currentRoute[$index])
                        {
                            $testVar = false;
                            if(sizeof($currentRoute) > $index + 1)
                            {
                                $route = $this->findBySection($section, $index + 1);
                            }
                            else
                            {
                                $route = $section;
                            }
                            
                            break;
                        }
                    }
                }
                
                if($testVar)
                {
                    foreach($routes[$match] as $section)
                    {
                        if(isset($section['name']))
                        {
                            if($this->is_var($section['name']))
                            {
                                $this->vars[$this->get_var($section['name'])] = $currentRoute[$index];
                                
                                if(sizeof($currentRoute) > $index + 1)
                                {
                                    $route = $this->findBySection($section, $index + 1);
                                }
                                else
                                {
                                    $route = $section;
                                }
                                
                                break;
                            }
                        }
                    }
                }
            }
        }
        else
        {
            if(isset($routes['routes']))
            {
                foreach($routes['routes'] as $section)
                {
                    if(isset($section['name']))
                    {
                        if(trim($section['name'], '/') == (isset($currentRoute[$index]) ? $currentRoute[$index] : ''))
                        {
                            $testVar = false;
                            $route = $section;
                            
                            break;
                        }
                    }
                }
                
                if($testVar)
                {
                    foreach($routes['routes'] as $section)
                    {
                        if(isset($section['name']))
                        {
                            if($this->is_var($section['name']))
                            {
                                $this->vars[$this->get_var($section['name'])] = $currentRoute[$index];
                                $route = $section;
                                
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        return $route;
    }
    
    
    private function mergeDefault(array $default)
    {
        $this->default = array_merge($this->default, $default);
    }

    /**
     * Test if $key is a route variable.
     * @param string  $key The key to found.
     * @return boolean True if it is a route variable, else false.
     */
    private function is_var(string $key)
    {
        if($key[0] === '{' and $key[strlen($key) - 1] === '}')
        {
            return true;
        }
        
        return false;
    }
    
    /**
     * Remove first and last character.
     * @param  string $var The variable.
     * @return string The variable without its first and last character.
     */
    protected function get_var($var)
    {
        if($this->is_var($var))
        {
            return substr(substr($var, 0, -1), 1);
        }
        
        return null;
    }
    
    /**
     * Add controllers in controllers array
     * @param mixed $controllers One ore more controllers names
     */
    private function add_controllers($controllers)
    {
        if($controllers != null)
        {
            if(is_array($controllers))
            {
                $this->controllers = array_merge($this->controllers, $controllers);
            }
            elseif(is_string($controllers))
            {
                $this->controllers[] = $controllers;
            }
        }
    }

    /**
     * Use the route specified
     * @param array $route The route found in routing file given by the user
     */
    private function useRoute(array $route)
    {
        if(isset($route['controller']) and $route['controller'] !== null)
        {
            $this->add_controllers($route['controller']);
        }
        
        // Invoke all controllers
        if(!empty($this->controllers))
        {
            $controller = new Controller($this->controllerDir);
            
            foreach($this->controllers as $c)
            {
                $controller->useController($c);
            }
        }
        
        // Redirect
        if(isset($route['redirect']))
        {
            if(is_string($route['redirect']))
            {
                header('Location: /' . trim($route['redirect'], '/'));
                exit;
            }
        }
        
        // Update response
        if(isset($route['response']))
        {
            http_response_code(intval($route['response']));
        }
        
        // Render view
        if(isset($route['view']))
        {
            if($route['view'] != null)
            {
                $container = new Container();
                $container->getStorage()->mergeAttributes($this->vars);
                $view = new View($this->templateDir);
                $view->render($route['view']);
            }
        }
    }
}