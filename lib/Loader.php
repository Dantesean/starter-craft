<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace Includable\CraftCompatibility;

use Core\HTTP;
use Exception;
use Input;
use Sandbox\Container;
use Sandbox\Route;
use Mimey\MimeTypes;

ini_set('display_errors', 0);
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
     * @throws Exception
     */
    public static function loadInContainer($container)
    {
        // Disable Scholica UI and output
        $container->response->cancel_standard_output();
        global $controller;
        $controller->noHeaders = true;

        // Make sure there is a licence file that can be written to
        define('CRAFT_LICENSE_KEY_PATH', '/tmp/craft.license.' . crc32(community()->id));
        $license_key = community()->attr('craft-license-key');
        if($license_key) {
            file_put_contents(CRAFT_LICENSE_KEY_PATH, $license_key);
        }

        // Storage directory path
        $temp_storage_path = '/tmp/craft.storage.' . crc32(community()->id);
        if(!file_exists($temp_storage_path)) {
            mkdir($temp_storage_path, 0777);
        }
        define('CRAFT_STORAGE_PATH', $temp_storage_path);

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
            $_GET['px'] = preg_replace('/\/index\.php$/', '', $px[0]);
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
                HTTP::contentType($mime);

                echo file_get_contents($name);

                exit;
            }
        });

        // Load Craft bootstrap
        $app = require CRAFT_BASE_PATH . 'vendor/craftcms/cms/bootstrap/web.php';
        $app->run();

        // Save the licence back to the database
        if(file_exists(CRAFT_LICENSE_KEY_PATH)) {
            community()->attr('craft-license-key', file_get_contents(CRAFT_LICENSE_KEY_PATH));
        }
    }

}
