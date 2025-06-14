<?php

class ErrorHandler {
    private static $instance = null;
    private $logger;
    
    private const ERROR_MESSAGES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        409 => 'Conflict',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable'
    ];
    
    private function __construct() {
        $this->logger = Logger::getInstance();
        
        // Set error and exception handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    public static function getInstance(): ErrorHandler {
        if (self::$instance === null) {
            self::$instance = new ErrorHandler();
        }
        return self::$instance;
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // Error is not specified in error_reporting
            return false;
        }
        
        $error = [
            'type' => $this->getErrorType($errno),
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ];
        
        $this->logger->error('PHP Error', $error);
        
        if ($errno == E_USER_ERROR) {
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Internal Server Error',
                'code' => 500
            ], 500);
            exit(1);
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception) {
        $error = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        $this->logger->error('Uncaught Exception', $error);
        
        $code = $exception->getCode();
        if (!array_key_exists($code, self::ERROR_MESSAGES)) {
            $code = 500;
        }
        
        $this->sendJsonResponse([
            'success' => false,
            'error' => $exception->getMessage(),
            'code' => $code
        ], $code);
    }
    
    /**
     * Handle fatal errors
     */
    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error !== null && $error['type'] === E_ERROR) {
            $this->logger->error('Fatal Error', $error);
            
            $this->sendJsonResponse([
                'success' => false,
                'error' => 'Fatal Error: ' . $error['message'],
                'code' => 500
            ], 500);
        }
    }
    
    /**
     * Send standardized error response
     */
    public function sendError($message, $code = 500, $details = null) {
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $code
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        $this->logger->error($message, ['code' => $code, 'details' => $details]);
        $this->sendJsonResponse($response, $code);
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    /**
     * Get error type string from error number
     */
    private function getErrorType($errno) {
        switch ($errno) {
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Check if error is fatal
     */
    private function isFatalError($errno) {
        return in_array($errno, [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR
        ]);
    }
    
    /**
     * Get standard error message for HTTP code
     */
    public function getErrorMessage($code) {
        return self::ERROR_MESSAGES[$code] ?? 'Unknown Error';
    }
}
