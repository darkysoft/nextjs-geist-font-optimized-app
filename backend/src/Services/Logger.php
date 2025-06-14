<?php

class Logger {
    private static $instance = null;
    private $config;
    private $logFile;
    private $logLevel;
    
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];
    
    private function __construct() {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->logFile = $this->config['logging']['file'];
        $this->logLevel = $this->config['logging']['level'];
        
        // Create logs directory if it doesn't exist
        $logsDir = dirname($this->logFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }
    
    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }
    
    /**
     * Log a debug message
     */
    public function debug($message, array $context = []) {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Log an info message
     */
    public function info($message, array $context = []) {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public function warning($message, array $context = []) {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log an error message
     */
    public function error($message, array $context = []) {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log a message with the specified level
     */
    private function log($level, $message, array $context = []) {
        if (!$this->config['logging']['enabled']) {
            return;
        }
        
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        
        $logMessage = sprintf(
            "[%s] %s: %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextStr
        );
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Get recent log entries
     */
    public function getRecentLogs($lines = 100, $level = null) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $command = "tail -n " . escapeshellarg($lines) . " " . escapeshellarg($this->logFile);
        if ($level) {
            $command .= " | grep " . escapeshellarg(strtoupper($level));
        }
        
        $output = shell_exec($command);
        if (!$output) {
            return [];
        }
        
        $logs = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (preg_match('/\[(.*?)\] (\w+): (.*?)( {.*})?$/', $line, $matches)) {
                $logs[] = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3],
                    'context' => isset($matches[4]) ? json_decode($matches[4], true) : null
                ];
            }
        }
        
        return $logs;
    }
    
    /**
     * Clear the log file
     */
    public function clearLogs() {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
            return true;
        }
        return false;
    }
    
    /**
     * Get log file size
     */
    public function getLogSize() {
        if (!file_exists($this->logFile)) {
            return 0;
        }
        return filesize($this->logFile);
    }
    
    /**
     * Rotate log file if it exceeds size limit
     */
    public function rotateLogIfNeeded($maxSize = 10485760) { // 10MB default
        if ($this->getLogSize() > $maxSize) {
            $backupFile = $this->logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
            rename($this->logFile, $backupFile);
            return true;
        }
        return false;
    }
}
