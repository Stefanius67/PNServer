<?php
spl_autoload_register(function($strFullQualifiedClassName) {
    $strInclude = '';
    if (strpos($strFullQualifiedClassName, '\\') > 1) {
        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $strInclude = str_replace('\\', DIRECTORY_SEPARATOR, $strFullQualifiedClassName) . '.php';
    }

    // if the file exists, require it
    if (strlen($strInclude) > 0) {
        $strInclude = dirname(__FILE__) . '/' . $strInclude;
        if (file_exists($strInclude)) {
            require $strInclude;
        }
    }
});

