<?php
/**
 * @package Nabo
 * @version 4.1
 */
/*
Plugin Name: 南博插件
Plugin URI: https://github.com/krait-team/Nabo-wordpress
Description: 南博KAT-RPC
Author: 南博工作室
Version: 4.1
Author URI: https://github.com/krait-team
*/

add_action('query_vars', 'nabo_add_query_vars');
/**
 * @param $public_query_vars
 * @return mixed
 */
function nabo_add_query_vars($public_query_vars)
{
    $public_query_vars[] = 'nabo';
    return $public_query_vars;
}

add_action('template_redirect', 'nabo_template_redirect');
/**
 * @throws Exception
 */
function nabo_template_redirect()
{
    global $wp_query;
    switch ($wp_query->query_vars['nabo']) {
        case 'service':
        {
            include_once 'Service.php';
            $service = new Nabo_Service();
            $service->launch();
            break;
        }
        case 'upload':
        {
            include_once 'Upload.php';
            $upload = new Nabo_Upload();
            $upload->launch();
        }
    }
}

/**
 * @param $data
 * @return string[]
 */
function nabo_test($data)
{
    return ['test'];
}

add_action('rest_api_init', function () {
    register_rest_route('nabo', '/test', [
        'methods' => 'GET',
        'callback' => 'nabo_test',
    ]);
});