<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace CraftCompatibility;

use Input;
use Sandbox\Container;
use Sandbox\Route;
use Mimey\MimeTypes;

ini_set('display_errors',0);
error_reporting(E_ERROR);

/**
 * Class Loader
 *
 * @package CraftCompatibility
 */
class Loader
{

    /**
     * Load Craft CMS in Includable Container.
     *
     * @param Container $container
     */
    public static function loadInContainer($container)
    {
        // Disable Scholica UI and output
        $container->response->cancel_standard_output();

        // Project root path
        define('CRAFT_BASE_PATH', $container->module->path);
        $general_config = include 'config/general.php';
        $cp_trigger = '/' . $general_config['cpTrigger'];

        // Fix paths
        $_SERVER['HTTP_X_REWRITE_URL'] = str_replace('/index.php/', '/', $_SERVER['REQUEST_URI']);
        if(Input::get('px')) {
            $_SERVER['HTTP_X_REWRITE_URL'] = '/' . Input::get('px') .
                preg_replace('/[&?]?px=[^&]+/', '', $_SERVER['HTTP_X_REWRITE_URL']);
            if(!strstr($_SERVER['HTTP_X_REWRITE_URL'], '?')) {
                $url = explode('&', $_SERVER['HTTP_X_REWRITE_URL'], 2);
                $_SERVER['HTTP_X_REWRITE_URL'] = implode('?', $url);
            }
            $px = explode('?', trim($_SERVER['HTTP_X_REWRITE_URL'], '/'), 2);
            $_GET['px'] = $px[0];
        }

        // Redirect login or logout requests
        if(in_array($_SERVER['HTTP_X_REWRITE_URL'], ['/actions/site/login', $cp_trigger . '/login'])) {
            redirect('/login?return=/admin');
        }
        if(in_array($_SERVER['HTTP_X_REWRITE_URL'], ['/actions/site/logout', $cp_trigger . '/logout'])) {
            redirect('/logout?redir=/');
        }

        // CP resource loader
        Route::any('cpresources/*', function($name) use ($general_config, $container) {
            $path_segments = [$general_config['resourceBasePath']];
            foreach($name as $key => $value) {
                $path_segments[] = $key;
                if(!empty($value)) {
                    $path_segments[] = $value;
                }
            }
            array_filter($path_segments, function($segment) {
                return $segment != '.' && $segment != '..';
            });
            $name = implode('/', $path_segments);

            if(file_exists($name)) {
                $mimes = new MimeTypes;
                $mime = $mimes->getMimeType(substr($name, strrpos($name, '.') + 1));
                if($mime == 'text/plain') {
                    $mime = 'text/' . substr($name, strrpos($name, '.') + 1);
                }
                $container->response->mime($mime);

                echo file_get_contents($name);

                exit;
            }
        });

        // Check if Composer dependencies are already installed
        if(!file_exists(CRAFT_BASE_PATH . 'vendor/autoload.php')) {
            echo 'Installing Composer dependencies...';
            echo '<meta http-equiv="refresh" content="5">';

            exit;
        }

        // Load Craft bootstrap
        $app = require CRAFT_BASE_PATH . 'vendor/craftcms/cms/bootstrap/web.php';
        $app->run();
    }

}
