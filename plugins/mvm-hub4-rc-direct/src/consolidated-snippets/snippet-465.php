<?php
// Consolidated from production Code Snippet #465.
defined('ABSPATH') || exit;

add_filter('rest_request_before_callbacks', static function ($response, $handler, $request) {
    if (!$request instanceof WP_REST_Request) {
        return $response;
    }

    $route = (string) $request->get_route();
    if (0 !== strpos($route, '/wp/v2/pages')) {
        return $response;
    }

    if (function_exists('UM')) {
        $um = UM();
        if (is_object($um) && method_exists($um, 'config')) {
            $um->config();
        }
    }

    return $response;
}, 1, 3);