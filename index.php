<?php
# index.php
# This file only relates defined routes to methods of class Sms in Sms.php

require_once './vendor/autoload.php';
require_once 'Sms.php';

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

try{
    $fileLocator = new FileLocator(array(__DIR__));
 
    $requestContext = new RequestContext();
    $requestContext->fromRequest(Request::createFromGlobals());
 
    # load all routes from routes.yaml
    $router = new Router(
        new YamlFileLoader($fileLocator),
        'routes.yaml',
        [/* 'cache_dir' => __DIR__.'/cache' */],
        $requestContext
    );
 
    # find the current route
    $parameters = $router->match($requestContext->getPathInfo());
    
    # call the related function
    $passParameters = [];
    foreach($parameters as $key => $value)
        if($key[0] != "_")
            $passParameters[$key] = $value;
    call_user_func_array($parameters['_controller'], $passParameters);

}
catch (ResourceNotFoundException $e){
    echo $e->getMessage();
}