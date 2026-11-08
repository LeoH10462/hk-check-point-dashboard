<?php
if (in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/app.js', '/style.css'], true)) {
    return false;
}
require __DIR__.'/index.php';
