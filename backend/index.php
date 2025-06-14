<?php

// Load bootstrap and configuration
$config = require_once __DIR__ . '/bootstrap.php';

// Get request method and path
$request = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Get request body for POST/PUT requests
$body = null;
if (in_array($request, ['POST', 'PUT'])) {
    $body = json_decode(file_get_contents('php://input'), true);
}

try {
    $logger = Logger::getInstance();
    $logger->info('Received request', [
        'method' => $request,
        'path' => $path,
        'body' => $body
    ]);

    // Initialize response
    $response = null;
    $statusCode = 200;

    // Route handling
    if (count($pathParts) >= 1) {
        switch ($pathParts[0]) {
            case 'virtualhosts':
                $controller = new VirtualHostController();
                
                if (count($pathParts) == 1) {
                    // /virtualhosts
                    switch ($request) {
                        case 'GET':
                            $response = $controller->listVirtualHosts();
                            break;
                        case 'POST':
                            $response = $controller->createVirtualHost($body);
                            $statusCode = 201;
                            break;
                        default:
                            throw new Exception('Method not allowed', 405);
                    }
                } elseif (count($pathParts) == 2) {
                    // /virtualhosts/{id}
                    $id = $pathParts[1];
                    switch ($request) {
                        case 'GET':
                            $response = $controller->getVirtualHost($id);
                            break;
                        case 'PUT':
                            $response = $controller->updateVirtualHost(array_merge(['id' => $id], $body ?? []));
                            break;
                        case 'DELETE':
                            $response = $controller->deleteVirtualHost($id);
                            break;
                        default:
                            throw new Exception('Method not allowed', 405);
                    }
                } elseif (count($pathParts) == 3 && $pathParts[2] === 'toggle') {
                    // /virtualhosts/{id}/toggle
                    if ($request !== 'POST') {
                        throw new Exception('Method not allowed', 405);
                    }
                    $response = $controller->toggleVirtualHost($pathParts[1]);
                }
                break;

            case 'apache':
                $controller = new ApacheController();
                
                if (count($pathParts) == 2) {
                    switch ($pathParts[1]) {
                        case 'status':
                            if ($request !== 'GET') {
                                throw new Exception('Method not allowed', 405);
                            }
                            $response = $controller->getStatus();
                            break;
                            
                        case 'logs':
                            if ($request !== 'GET') {
                                throw new Exception('Method not allowed', 405);
                            }
                            $response = $controller->getLogs($body);
                            break;
                            
                        case 'restart':
                            if ($request !== 'POST') {
                                throw new Exception('Method not allowed', 405);
                            }
                            $response = $controller->restartServer();
                            break;
                            
                        case 'reload':
                            if ($request !== 'POST') {
                                throw new Exception('Method not allowed', 405);
                            }
                            $response = $controller->reloadConfiguration();
                            break;
                            
                        case 'check':
                            if ($request !== 'GET') {
                                throw new Exception('Method not allowed', 405);
                            }
                            $response = $controller->checkConfiguration();
                            break;
                    }
                }
                break;

            case 'system':
                $util = SystemUtil::getInstance();
                if ($request !== 'GET') {
                    throw new Exception('Method not allowed', 405);
                }
                $response = $util->getSystemInfo();
                break;

            default:
                throw new Exception('Route not found', 404);
        }
    }

    if ($response === null) {
        throw new Exception('Route not found', 404);
    }

    // Log successful response
    $logger->info('Request completed successfully', [
        'method' => $request,
        'path' => $path,
        'status' => $statusCode
    ]);

    // Send response
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    $errorHandler = ErrorHandler::getInstance();
    $errorHandler->handleException($e);
}
