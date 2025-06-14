<?php

class ApacheService {
    private $accessLogPath = '/var/log/apache2/access.log';
    private $errorLogPath = '/var/log/apache2/error.log';
    
    /**
     * Get Apache server status information
     */
    public function getServerStatus() {
        // Get Apache version
        $version = shell_exec("apache2 -v | grep 'Server version'");
        preg_match('/Apache\/([\d\.]+)/', $version, $matches);
        $apacheVersion = $matches[1] ?? 'Unknown';

        // Get uptime and load
        $uptime = shell_exec("uptime");
        preg_match('/up\s+(.*?),\s+\d+\s+users?,\s+load average:\s+(\d+\.\d+),\s+(\d+\.\d+),\s+(\d+\.\d+)/', $uptime, $matches);
        
        // Get active virtual hosts
        $enabledSites = array_filter(scandir('/etc/apache2/sites-enabled/'), function($file) {
            return $file !== '.' && $file !== '..' && str_ends_with($file, '.conf');
        });

        // Get current connections
        $connections = shell_exec("netstat -an | grep :80 | grep ESTABLISHED | wc -l");
        
        // Check if Apache is running
        $status = shell_exec("systemctl is-active apache2");
        $isRunning = trim($status) === 'active';

        // Get memory usage
        $memory = shell_exec("ps -C apache2 -O rss | gawk '{ count ++; sum += $2 }; END { printf(\"%d\\n\", sum/1024) }'");
        
        return [
            'version' => $apacheVersion,
            'status' => $isRunning ? 'running' : 'stopped',
            'uptime' => $matches[1] ?? 'Unknown',
            'load' => [
                '1m' => $matches[2] ?? 0,
                '5m' => $matches[3] ?? 0,
                '15m' => $matches[4] ?? 0
            ],
            'activeVirtualHosts' => count($enabledSites),
            'connections' => (int)$connections,
            'memoryUsage' => [
                'mb' => (int)$memory,
                'formatted' => $this->formatBytes((int)$memory * 1024 * 1024)
            ],
            'lastCheck' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get Apache server logs
     */
    public function getLogs($type = 'error', $lines = 100, $filter = null) {
        $logFile = $type === 'error' ? $this->errorLogPath : $this->accessLogPath;
        
        if (!file_exists($logFile)) {
            throw new Exception("Log file not found: $logFile", 404);
        }
        
        // Use tail to get last N lines
        $command = "tail -n " . escapeshellarg($lines) . " " . escapeshellarg($logFile);
        
        // Add grep if filter is provided
        if ($filter) {
            $command .= " | grep -i " . escapeshellarg($filter);
        }
        
        $output = shell_exec($command);
        if ($output === null) {
            throw new Exception('Failed to read log file', 500);
        }
        
        // Parse log entries
        $entries = array_filter(explode("\n", $output));
        
        // Format based on log type
        if ($type === 'access') {
            return array_map([$this, 'parseAccessLogEntry'], $entries);
        } else {
            return array_map([$this, 'parseErrorLogEntry'], $entries);
        }
    }
    
    /**
     * Restart Apache server
     */
    public function restartServer() {
        $output = shell_exec('sudo systemctl restart apache2 2>&1');
        if ($output === null) {
            throw new Exception('Failed to restart Apache server', 500);
        }
        
        // Wait briefly and check status
        sleep(2);
        $status = shell_exec('systemctl is-active apache2');
        if (trim($status) !== 'active') {
            throw new Exception('Apache failed to restart properly', 500);
        }
        
        return [
            'message' => 'Apache server restarted successfully',
            'status' => 'active'
        ];
    }
    
    /**
     * Reload Apache configuration
     */
    public function reloadConfiguration() {
        // First check configuration syntax
        $this->checkConfiguration();
        
        $output = shell_exec('sudo systemctl reload apache2 2>&1');
        if ($output === null) {
            throw new Exception('Failed to reload Apache configuration', 500);
        }
        
        return [
            'message' => 'Apache configuration reloaded successfully',
            'status' => 'reloaded'
        ];
    }
    
    /**
     * Check Apache configuration syntax
     */
    public function checkConfiguration() {
        $output = shell_exec('sudo apache2ctl configtest 2>&1');
        if (strpos($output, 'Syntax OK') === false) {
            throw new Exception('Apache configuration test failed: ' . $output, 500);
        }
        
        return [
            'message' => 'Apache configuration syntax is valid',
            'details' => $output
        ];
    }
    
    /**
     * Parse Apache access log entry
     */
    private function parseAccessLogEntry($line) {
        $pattern = '/^(\S+) \S+ \S+ \[([^\]]+)\] "([^"]*)" (\d+) (\d+) "([^"]*)" "([^"]*)"$/';
        if (preg_match($pattern, $line, $matches)) {
            return [
                'ip' => $matches[1],
                'timestamp' => $matches[2],
                'request' => $matches[3],
                'status' => (int)$matches[4],
                'bytes' => (int)$matches[5],
                'referer' => $matches[6],
                'userAgent' => $matches[7],
                'raw' => $line
            ];
        }
        return ['raw' => $line];
    }
    
    /**
     * Parse Apache error log entry
     */
    private function parseErrorLogEntry($line) {
        $pattern = '/^\[(.*?)\] \[([^]]+)\] \[([^]]+)\] (.*)$/';
        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'module' => $matches[3],
                'message' => $matches[4],
                'raw' => $line
            ];
        }
        return ['raw' => $line];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
