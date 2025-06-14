<?php

require_once __DIR__ . '/../Services/ApacheService.php';

class ApacheController {
    private $service;

    public function __construct() {
        $this->service = new ApacheService();
    }

    /**
     * Get Apache server status including:
     * - Server version
     * - Server uptime
     * - Server load
     * - Active virtual hosts
     * - Current connections
     */
    public function getStatus() {
        return $this->service->getServerStatus();
    }

    /**
     * Get Apache server logs with optional filtering
     * Supports error logs and access logs
     */
    public function getLogs($params = null) {
        $logType = isset($params['type']) ? $params['type'] : 'error';
        $lines = isset($params['lines']) ? (int)$params['lines'] : 100;
        $filter = isset($params['filter']) ? $params['filter'] : null;
        
        // Validate parameters
        if (!in_array($logType, ['error', 'access'])) {
            throw new Exception('Invalid log type. Must be either "error" or "access"', 400);
        }
        
        if ($lines < 1 || $lines > 1000) {
            throw new Exception('Lines parameter must be between 1 and 1000', 400);
        }

        return $this->service->getLogs($logType, $lines, $filter);
    }

    /**
     * Restart Apache server
     * Requires sudo privileges
     */
    public function restartServer() {
        return $this->service->restartServer();
    }

    /**
     * Reload Apache configuration
     * Less disruptive than restart
     */
    public function reloadConfiguration() {
        return $this->service->reloadConfiguration();
    }

    /**
     * Check Apache configuration syntax
     */
    public function checkConfiguration() {
        return $this->service->checkConfiguration();
    }
}
