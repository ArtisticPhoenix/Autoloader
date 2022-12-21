<?php
namespace evo\autoloader;

/**
 *
 * (c) 2016 Hugh Durham III
 *
 * For license information please view the LICENSE file included with this source code.
 *
 * Autoloader for classes
 *
 * @author HughDurham {ArtisticPhoenix}
 * @package Evo
 * @subpackage Shutdown
 *
 */
final class Autoloader
{
    /**
     * because I refuse to use DIRECTORY_SEPARATOR
     * @var string
     */
    const DS = DIRECTORY_SEPARATOR;
    
    /**
     *
     * @var int
     */
    const DEFAULT_PRIORITY = 100;
    
    /**
     * namespace / class path storage
     * @var array
     */
    private $paths = array();
    
    /**
     * cashe the loaded files
     * @var array
     */
    private $files = array();
    
    /**
     * namespace / class path storage
     * @var array
     */
    private $debugMode = false;
    
    /**
     *
     * @var Self
     */
    private static $instance;
    
    /**
     *
     * @var string
     */
    private static $debugStart = '_START_';
    
    /**
     *
     * @var string
     */
    private static $debugEnd = '_END_';
    
    /**
     * No public construction allowed - Singleton
     */
    private function __construct($throw, $prepend)
    {
        spl_autoload_register(array( $this,'splAutoload'), $throw, $prepend);
        $this->registerPath('', '');
    }
    
    /**
     * no access
     */
    private function __clone()
    {
    }
    
    
    /**
     *
     * Get an instance of the Autoloader Singleton
     * 
     * Arguments are applied only to the first instance
     * 
     * @param boolean $throw
     * @param boolean $prepend
     * @return self
     */
    public static function I($throw = false, $prepend = false){
        return self::getInstance($throw,$prepend);
    }

    /**
     *
     * Get an instance of the Autoloader Singleton
     * 
     * Arguments are applied only to the first instance 
     * 
     * @param boolean $throw
     * @param boolean $prepend
     * @return self
     */
    public static function getInstance($throw = false, $prepend = false)
    {
        if (!self::$instance) {
            self::$instance = new self($throw, $prepend);
        }
        return self::$instance;
    }
    
    /**
     * set debug output
     * @param boolean $debug
     * @return self
     */
    public function setDebug($debug = false)
    {
        $this->debugMode = $debug;
        return $this;
    }
    
    /**
     * Autoload
     * @param string $class
     */
    public function splAutoload($class)
    {
        $this->debug(self::$debugStart);
        $this->debug(__METHOD__.' '.$class);
        
        //keep the orignal class name
        $_class = str_replace('\\', '/', $class);
        $namespace = '';
        if (false !== ($pos = strrpos($_class, '/'))) {
            $namespace = substr($_class, 0, ($pos));
            $_class = substr($_class, ($pos + 1));
        }
        
        //replace _ in class name only
        if (false !== ($pos = strrpos($_class, '/'))) {
            if (strlen($namespace)) {
                $namespace .= '/'.substr($_class, 0, ($pos));
            } else {
                $namespace = substr($_class, 0, ($pos));
            }
            $_class = substr($_class, ($pos + 1));
        }
        
        $this->debug("Checking class: $_class");
        $this->debug("Checking namespace: $namespace");

        do {
            if (isset($this->paths[ $namespace ])) {
                foreach ($this->paths[ $namespace ] as $registered) {
                    $filepath = $registered['path'] . $_class . '.php';
                    
                    $this->debug("checking pathname:{$filepath}");
                    
                    if (file_exists($filepath)) {
                        $this->debug("Found: $filepath");
                        $this->debug(self::$debugEnd);
                        require_once $filepath;
                        $this->files[$class] = $filepath;
                    }
                }
            }
            
            if (strlen($namespace) == 0) {
                //if the namespace is empty and we couldn't find the class we are done.
                break;
            }
            
            if (false !== ($pos = strrpos($namespace, '/'))) {
                $_class = substr($namespace, ($pos + 1)) . '/' . $_class;
                $namespace = substr($namespace, 0, ($pos));
            } else {
                $_class = (strlen($namespace) ? $namespace : '') . '/' . $_class;
                $namespace = '';
            }
        } while (true);
        
        $this->debug(self::$debugEnd);
    }
    
    /**
     * get the paths regestered for a namespace, leave null go get all paths
     * @param string $namespace
     * @return array or false on falure
     */
    public function getRegisteredPaths($namespace = null)
    {
        if (is_null($namespace)) {
            return $this->paths;
        } else {
            return (isset($this->paths[$namespace])) ? array($namespace => $this->paths[$namespace])  : false;
        }
    }
    
    /**
     *
     * @param string $namespace
     * @param string $path
     * @param int $priority
     * @return self
     */
    public function registerPath($namespace, $path, $priority = self::DEFAULT_PRIORITY)
    {
        $namespace = str_replace('\\', '/', $namespace); //convert to directory seperator
        $path = ($this->normalizePath($path));
        
        $this->paths[$namespace][sha1($path)] = array(
            'path'         => preg_replace("#\\\|/#", DIRECTORY_SEPARATOR, $path),
            'priority'     => $priority
        );
        
        $this->sortByPriority($namespace);
        return $this;
    }
    
    /**
     * un-regester a path
     * @param string $namespace
     * @param string $path
     */
    public function unloadPath($namespace, $path = null)
    {
        if ($path) {
            $path = $this->normalizePath($path);
            unset($this->paths[$namespace][sha1($path)]);
        } else {
            unset($this->paths[$namespace]);
        }
    }
    
    /**
     * check if a namespace is regestered
     * @param string $namespace
     * @param string $path
     * @return bool
     */
    public function isRegistered($namespace, $path = null)
    {
        if ($path) {
            $path = $this->normalizePath($path);
            return isset($this->paths[$namespace][sha1($path)]) ? true : false;
        } else {
            return isset($this->paths[$namespace]) ? true : false;
        }
    }
    
    /**
     * get the file pathname of a loaded class
     * @param string $class
     * @return mixed
     */
    public function getLoadedFile($class = null)
    {
        if (!$class) {
            return $this->files;
        }
        
        if (isset($this->files[$class])) {
            return $this->files[$class];
        }
    }
    
    
    /**
     * output debug message
     * @param string $message
     */
    protected function debug($message, $wrap = false)
    {
        if (!$this->debugMode) {
            return;
        }
        
        if ($wrap || $message == self::$debugStart) {
            echo str_pad("= ".__METHOD__." =", 90, "=", STR_PAD_BOTH) . PHP_EOL;
        }
        if ($message != self::$debugStart && $message != self::$debugEnd) {
            echo $message  . PHP_EOL;
        }
                
        if ($wrap || $message == self::$debugEnd) {
            echo str_pad("", 90, "=", STR_PAD_BOTH) . PHP_EOL . PHP_EOL;
        }
    }
    
    /**
     * sort namespaces by priority
     * @param string $namespace
     */
    protected function sortByPriority($namespace)
    {
        uasort($this->paths[$namespace], function ($a, $b) {
            if ($a['priority'] == $b['priority']) {
                return 0;
            }
            return ($a['priority'] < $b['priority']) ? -1 : 1;
        });
    }
    
    /**
     * convert a path to unix seperators and make sure it has a trailing slash
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
        if (empty($path)) {
            return '';
        }
        
        if (false !== strpos($path, '\\')) {
            $path = str_replace("\\", "/", $path);
        }
        
        return rtrim($path, '/') . '/';
    }
}
