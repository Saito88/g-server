<?php
/**
 * Created by PhpStorm.
 * User: Saito88
 * Date: 29.08.2017
 * Time: 10:43
 */

declare(strict_types=1);

namespace gserver\core;


final class Router
{
    /**
     * @var Router|null
     */
    private static $Instance = NULL;

    /**
     * @var bool|string
     */
    protected $rawLink = '';

    /**
     * @var object|null
     */
    protected $Repository = NULL;

    /**
     * @var string
     */
    protected $module = '';

    /**
     * @var string
     */
    protected $locale = '';

    /**
     * @var string
     */
    protected $controller = '';

    /**
     * @var string
     */
    protected $action = '';

    /**
     * @var array
     */
    protected $params = [];

    /**
     * Router constructor.
     */
    private function __construct() {

        $uri = strtolower($_SERVER['REQUEST_URI']);
        $rawLink = strpos($uri, '/') === 0 ? substr($uri,1) : $uri;
        $rawLink = str_replace('g-server/', '', $rawLink);
        $params = '';

        if (strpos($rawLink, '?') === 1) {

            $parts = explode('?', $rawLink);
            $this->rawLink = $parts[0];
            $params = $parts[1];

        } else {
            $this->rawLink = $rawLink;
        }

        $this->setModule($rawLink);

        // Sets the database connection
        Gserver()->Db($this->module);

        $Reflection = new \ReflectionClass($this);

        $this->Repository = Gserver()->RepositoryManager(
            ['namespace' => 'repositorities',
            'repositority' => $Reflection->getShortName()]
        )->getRepository();

        $this->Repository->getTable('rewrite_urls')->getDataset();

        if (!$this->isLinkMapped($rawLink)) {

            $this->setLocale($rawLink);
            $this->setController($rawLink);
            $this->setAction($rawLink);
            $this->setParams($params);

        }

    }

    /**
     * Singleton pattern don't allow clone
     */
    private function __clone() {}

    /**
     * Initiate the Instance and return it
     *
     * @return Router
     */
    public static function getInstance() : Router {

        if (self::$Instance === NULL) {
            $Router = new Router();
            self::$Instance = $Router;
        }

        return self::$Instance;

    }

    /**
     * @param string $link
     */
    private function setModule(string $link): void {
        $this->module = substr_count($link, 'backend') === 1 ? 'backend' : 'frontend';
    }

    /**
     * @return string
     */
    public function getModule(): string {
        return $this->module;
    }

    /**
     * @param string $link
     */
    private function setLocale(string $link): void {

        $locale = explode('/',$link);
        $this->locale = array_shift($locale);

        if (!$this->localeExists()) {

            $Table = $this->Repository->getTable('locale');
            $Table->setDataset(['main','yes']);

            $this->locale = strtolower($Table->getDataset()['iso2']);

        }

    }

    /**
     * @return string
     */
    public function getLocale(): string {
        return $this->locale;
    }

    /**
     * Includes the controller file and set the class name of the controller
     *
     * @param string $link
     */
    private function setController(string $link): void {

        $path = __DIR__ . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR;
        $modulePath = $this->module . DIRECTORY_SEPARATOR;
        $parts = explode('/',$link);

        if(empty($parts[0])) {
            $controller = "Index";
        } else {
            if ($this->module === "backend") {
                $controller = empty($parts[2]) ? 'Index' : $parts[2];
            } else {
                $controller = empty($parts[1]) ? 'Index' : $parts[1];
            }
        }

        $file = realpath($path . $modulePath . $controller . '.php');

        require_once($path . 'Controller.php');

        if (file_exists($file)) {

            require_once($file);

            // TODO: There is a better solution for this
            if ($this->module !== "backend") {
                require_once(realpath($path . $modulePath . 'Maintenance' . '.php'));
            }

        } else {
            $controller = 'NotFound';
            require_once($path . 'NotFound.php');
        }

        $this->controller = ucfirst($controller);

    }

    /**
     * @return string
     */
    public function getController(): string {
        return $this->controller;
    }

    /**
     * @param string $link
     */
    private function setAction(string $link): void {

        $parts = explode('/', $link);

        if ($this->module === 'backend') {

            if (!empty($parts[3])) {
                $this->action = explode('/', $link)[3];
            }

        } else {

            if (!empty($parts[2])) {
                $this->action = explode('/', $link)[2];
            }

        }

        if(empty($this->action)) {
            $this->action = 'index';
        }

    }

    /**
     * @return string
     */
    public function getAction(): string {
        return $this->action;
    }

    /**
     * Parse and set the params property
     *
     * @param string $paramString
     */
    private function setParams(string $paramString): void {

        $parts = explode('&', $paramString);

        $params = [];

        if(!empty($parts[0])) {
            foreach ($parts as $part) {

                $controllerParams = explode('=', $part);

                $key = $controllerParams[0];
                $value = $controllerParams[1];

                $params[$key] = $value;

            }
        }

        $this->params = $params;

    }

    /**
     * @return array
     */
    public function getParams(): array {
        return $this->params;
    }

    /**
     * Check if the requested locale exists
     *
     * @return bool
     */
    private function localeExists(): bool {

        $Table = $this->Repository->getTable('locale');
        $Table->setDataset(['iso2', $this->locale]);

        if ($Table->getDataset()['id'] === 0) {
            return false;
        } else {
            return true;
        }

    }

    /**
     * @return bool
     */
    public function isLinkMapped(): bool {

        $Table = $this->Repository->getTable('rewrite_urls');
        $Table->setDataset(['link' => $this->rawLink]);

        if ($Table->getDataset()['id'] === 0) {
            return false;
        } else {

            $dataset = $Table->getDataset();

            $parts = explode('/', $dataset['intern']);

            $this->locale = $dataset['iso2'];
            $this->module = array_shift($parts);
            $this->controller = array_shift($parts);
            $this->action = array_shift($parts);

            if (!empty($parts)) {

                if(count($parts) === 1) {
                    $this->setParams($parts[0]);
                } else {
                    // Failure
                }

            }

            return true;

        }

    }

    public function getRootLink(): string {
        $protocol = $this->getProtocol();
        return $protocol . $_SERVER['SERVER_NAME'] . '/g-server/';
    }

    public function getMediaLink(): string {
        return $this->getRootLink() . 'media/';
    }

    private function getProtocol(): string {

        if (isset($_SERVER['HTTPS'])) {
            return  'https://';
        } else {
            return 'http://';
        }

    }

}