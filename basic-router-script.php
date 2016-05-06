<?php

$request_path_info = (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/');
$request_method = $_SERVER['REQUEST_METHOD'];

list($root, $resourceroot, $api, $version, $resource, $resource_id) = explode('/',$request_path_info) +  array_fill(0,6,'');

if ($api!=='api' || $version!=='v1') {
    $resourceroot = '';
}

switch ($resourceroot) {
  case 'cust': $classpath='cust'; break;
  case 'manage' : $classpath='manageweb'; break;
  case 'driver' : $classpath='driver'; break;
  case 'city' : $classpath='resources'; break;
  default: $resourceroot = $classpath = '';
}

switch ($request_method) {
    case 'GET': $method = "index"; break;
    case 'POST': $method = (!$resource_id) ? "add" : 'update'; break;
    case 'DELETE': $method = ($resource_id) ? "delete" : false; break;
    default: $method = false;
}

$resource = strtolower($resource);
$resource_file="../Resources/$classpath/$resource.php";

if (!$classpath || !file_exists($resource_file)) {
    $resource_file = false;
}

if (!$resourceroot || !$resource_file || !$method) {
    header("HTTP/1.1 404 Not Found");
    die();
}

require_once($resource_file);
$resource_class = ucwords($resource);
$resource_obj = new $resource_class();
$resource_obj->$method($resource_id);
