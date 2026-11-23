<?php
/**
 * router.php - Used only by PHP's built-in server.
 * Blocks direct web access to config/ and includes/; everything else works normally.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/(config|includes)(/|$)#', (string) $path)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"success":false,"error":{"message":"Access denied."}}';
    return true;
}
return false; // let the built-in server handle the request
