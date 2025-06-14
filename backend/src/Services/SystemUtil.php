<?php

class SystemUtil {
    private static $instance = null;
    private $config;
    private $logger;
    
    private function __construct() {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->logger = Logger::getInstance();
    }
    
    public static function getInstance(): SystemUtil {
        if (self::$instance === null) {
            self::$instance = new SystemUtil();
        }
        return self::$instance;
    }
    
    /**
     * Execute a system command safely
     */
    public function executeCommand($command, $timeout = null) {
        // Use configured timeout if not specified
        $timeout = $timeout ?? $this->config['security']['command_timeout'];
        
        // Log command execution
        $this->logger->debug('Executing command', ['command' => $command]);
        
        // Create temporary file for output
        $outputFile = tempnam(sys_get_temp_dir(), 'cmd');
        $errorFile = tempnam(sys_get_temp_dir(), 'cmd_error');
        
        // Build command with timeout and output redirection
        $fullCommand = sprintf(
            'timeout %d %s > %s 2> %s',
            $timeout,
            escapeshellcmd($command),
            escapeshellarg($outputFile),
            escapeshellarg($errorFile)
        );
        
        // Execute command
        exec($fullCommand, $output, $returnCode);
        
        // Read output and error
        $output = file_get_contents($outputFile);
        $error = file_get_contents($errorFile);
        
        // Clean up temporary files
        unlink($outputFile);
        unlink($errorFile);
        
        // Log results
        if ($returnCode !== 0) {
            $this->logger->error('Command execution failed', [
                'command' => $command,
                'return_code' => $returnCode,
                'error' => $error
            ]);
        }
        
        return [
            'success' => $returnCode === 0,
            'output' => $output,
            'error' => $error,
            'return_code' => $returnCode
        ];
    }
    
    /**
     * Validate file path is within allowed directories
     */
    public function validatePath($path) {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }
        
        foreach ($this->config['security']['allowed_dirs'] as $allowedDir) {
            if (strpos($realPath, $allowedDir) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Create directory if it doesn't exist
     */
    public function ensureDirectory($path, $permissions = 0755) {
        if (!$this->validatePath($path)) {
            throw new Exception('Invalid directory path', 403);
        }
        
        if (!is_dir($path)) {
            if (!mkdir($path, $permissions, true)) {
                throw new Exception('Failed to create directory', 500);
            }
        }
        
        return true;
    }
    
    /**
     * Check if user has sudo privileges
     */
    public function checkSudoPrivileges() {
        $result = $this->executeCommand('sudo -n true');
        return $result['success'];
    }
    
    /**
     * Get system information
     */
    public function getSystemInfo() {
        $info = [
            'os' => php_uname(),
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'hostname' => gethostname(),
            'disk_free' => disk_free_space('/'),
            'disk_total' => disk_total_space('/'),
            'memory' => $this->getMemoryInfo(),
            'load_average' => sys_getloadavg(),
            'timezone' => date_default_timezone_get(),
            'user' => get_current_user(),
            'group' => getmygid(),
            'sudo_access' => $this->checkSudoPrivileges()
        ];
        
        return $info;
    }
    
    /**
     * Get memory information
     */
    private function getMemoryInfo() {
        if (!is_readable('/proc/meminfo')) {
            return null;
        }
        
        $meminfo = file_get_contents('/proc/meminfo');
        $data = [];
        
        foreach (explode("\n", $meminfo) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                $data[$matches[1]] = (int)$matches[2];
            }
        }
        
        return [
            'total' => $data['MemTotal'] ?? 0,
            'free' => $data['MemFree'] ?? 0,
            'available' => $data['MemAvailable'] ?? 0,
            'cached' => $data['Cached'] ?? 0,
            'swap_total' => $data['SwapTotal'] ?? 0,
            'swap_free' => $data['SwapFree'] ?? 0
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    public function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Validate domain name format
     */
    public function validateDomain($domain) {
        return (bool)preg_match($this->config['validation']['virtualhost']['server_name_regex'], $domain);
    }
    
    /**
     * Check if a port is in use
     */
    public function isPortInUse($port) {
        $result = $this->executeCommand("netstat -an | grep ':$port.*LISTEN'");
        return !empty($result['output']);
    }
    
    /**
     * Get active network connections for a port
     */
    public function getPortConnections($port) {
        $result = $this->executeCommand("netstat -an | grep ':$port.*ESTABLISHED'");
        return array_filter(explode("\n", $result['output']));
    }
}
