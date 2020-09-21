<?php

if(API!='') {
    $ControllerName=ucfirst(preg_replace("/[^a-zA-Z]/","",strtolower(API)))."Controller";

    if(file_exists(ROOT . 'application/controllers/' . API . '.controller.php')) {
        require ROOT . 'application/controllers/' . API . '.controller.php';
        $C = new $ControllerName();
    } else {
        echo "This api function does not exist";
    } 
} else {
    echo "You must define an api function";
}
