<?php

class VirtualHostService {
    private $apacheConfigDir = '/etc/apache2/sites-available/';
    private $enabledSitesDir = '/etc/apache2/sites-enabled/';
    
    /**
     * List all virtual hosts
     */
    public function listAll() {
        $virtualHosts = [];
        
        // Scan sites-available directory
        $files = scandir($this->apacheConfigDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.conf')) {
                continue;
            }
            
            $config = $this->parseVirtualHostFile($this->apacheConfigDir . $file);
            if ($config) {
                $config['enabled'] = file_exists($this->enabledSitesDir . $file);
                $config['id'] = pathinfo($file, PATHINFO_FILENAME);
                $virtualHosts[] = $config;
            }
        }
        
        return $virtualHosts;
    }
    
    /**
     * Get specific virtual host configuration
     */
    public function get($id) {
        $file = $this->apacheConfigDir . $id . '.conf';
        if (!file_exists($file)) {
            throw new Exception('VirtualHost not found', 404);
        }
        
        $config = $this->parseVirtualHostFile($file);
        if (!$config) {
            throw new Exception('Failed to parse VirtualHost configuration', 500);
        }
        
        $config['enabled'] = file_exists($this->enabledSitesDir . $id . '.conf');
        $config['id'] = $id;
        
        return $config;
    }
    
    /**
     * Create new virtual host
     */
    public function create($data) {
        $id = $this->sanitizeFileName($data['serverName']);
        $file = $this->apacheConfigDir . $id . '.conf';
        
        if (file_exists($file)) {
            throw new Exception('VirtualHost already exists', 409);
        }
        
        $content = $this->generateVirtualHostConfig($data);
        
        if (!file_put_contents($file, $content)) {
            throw new Exception('Failed to create VirtualHost configuration file', 500);
        }
        
        // Enable site if requested
        if (isset($data['enabled']) && $data['enabled']) {
            $this->enableVirtualHost($id);
        }
        
        // Reload Apache to apply changes
        $this->reloadApache();
        
        return $this->get($id);
    }
    
    /**
     * Update existing virtual host
     */
    public function update($id, $data) {
        $file = $this->apacheConfigDir . $id . '.conf';
        if (!file_exists($file)) {
            throw new Exception('VirtualHost not found', 404);
        }
        
        // Get existing config and merge with updates
        $existing = $this->parseVirtualHostFile($file);
        $updated = array_merge($existing, $data);
        
        $content = $this->generateVirtualHostConfig($updated);
        
        if (!file_put_contents($file, $content)) {
            throw new Exception('Failed to update VirtualHost configuration', 500);
        }
        
        // Handle enable/disable if specified
        if (isset($data['enabled'])) {
            if ($data['enabled']) {
                $this->enableVirtualHost($id);
            } else {
                $this->disableVirtualHost($id);
            }
        }
        
        // Reload Apache to apply changes
        $this->reloadApache();
        
        return $this->get($id);
    }
    
    /**
     * Delete virtual host
     */
    public function delete($id) {
        $file = $this->apacheConfigDir . $id . '.conf';
        if (!file_exists($file)) {
            throw new Exception('VirtualHost not found', 404);
        }
        
        // Disable first if enabled
        if (file_exists($this->enabledSitesDir . $id . '.conf')) {
            $this->disableVirtualHost($id);
        }
        
        // Delete configuration file
        if (!unlink($file)) {
            throw new Exception('Failed to delete VirtualHost configuration', 500);
        }
        
        // Reload Apache to apply changes
        $this->reloadApache();
        
        return ['message' => 'VirtualHost deleted successfully'];
    }
    
    /**
     * Toggle virtual host enabled/disabled state
     */
    public function toggle($id) {
        $file = $this->apacheConfigDir . $id . '.conf';
        if (!file_exists($file)) {
            throw new Exception('VirtualHost not found', 404);
        }
        
        $enabled = file_exists($this->enabledSitesDir . $id . '.conf');
        
        if ($enabled) {
            $this->disableVirtualHost($id);
        } else {
            $this->enableVirtualHost($id);
        }
        
        // Reload Apache to apply changes
        $this->reloadApache();
        
        return $this->get($id);
    }
    
    /**
     * Parse Apache virtual host configuration file
     */
    private function parseVirtualHostFile($file) {
        $content = file_get_contents($file);
        if (!$content) {
            return null;
        }
        
        $config = [];
        
        // Extract ServerName
        if (preg_match('/ServerName\s+([^\s]+)/', $content, $matches)) {
            $config['serverName'] = $matches[1];
        }
        
        // Extract DocumentRoot
        if (preg_match('/DocumentRoot\s+([^\s]+)/', $content, $matches)) {
            $config['documentRoot'] = $matches[1];
        }
        
        // Extract ServerAlias
        if (preg_match('/ServerAlias\s+(.+)/', $content, $matches)) {
            $config['serverAlias'] = trim($matches[1]);
        }
        
        // Extract CustomLog
        if (preg_match('/CustomLog\s+([^\s]+)/', $content, $matches)) {
            $config['customLog'] = $matches[1];
        }
        
        // Extract ErrorLog
        if (preg_match('/ErrorLog\s+([^\s]+)/', $content, $matches)) {
            $config['errorLog'] = $matches[1];
        }
        
        return $config;
    }
    
    /**
     * Generate Apache virtual host configuration content
     */
    private function generateVirtualHostConfig($data) {
        $config = "<VirtualHost *:80>\n";
        $config .= "    ServerName " . $data['serverName'] . "\n";
        $config .= "    DocumentRoot " . $data['documentRoot'] . "\n";
        
        if (!empty($data['serverAlias'])) {
            $config .= "    ServerAlias " . $data['serverAlias'] . "\n";
        }
        
        // Add default directory configuration
        $config .= "    <Directory " . $data['documentRoot'] . ">\n";
        $config .= "        Options Indexes FollowSymLinks\n";
        $config .= "        AllowOverride All\n";
        $config .= "        Require all granted\n";
        $config .= "    </Directory>\n";
        
        // Add logging configuration
        $config .= "    ErrorLog \${APACHE_LOG_DIR}/" . $this->sanitizeFileName($data['serverName']) . "-error.log\n";
        $config .= "    CustomLog \${APACHE_LOG_DIR}/" . $this->sanitizeFileName($data['serverName']) . "-access.log combined\n";
        
        $config .= "</VirtualHost>";
        
        return $config;
    }
    
    /**
     * Enable a virtual host
     */
    private function enableVirtualHost($id) {
        $result = shell_exec("sudo a2ensite " . escapeshellarg($id));
        if ($result === null) {
            throw new Exception('Failed to enable VirtualHost', 500);
        }
    }
    
    /**
     * Disable a virtual host
     */
    private function disableVirtualHost($id) {
        $result = shell_exec("sudo a2dissite " . escapeshellarg($id));
        if ($result === null) {
            throw new Exception('Failed to disable VirtualHost', 500);
        }
    }
    
    /**
     * Reload Apache configuration
     */
    private function reloadApache() {
        $result = shell_exec("sudo systemctl reload apache2");
        if ($result === null) {
            throw new Exception('Failed to reload Apache', 500);
        }
    }
    
    /**
     * Sanitize filename from server name
     */
    private function sanitizeFileName($serverName) {
        return preg_replace('/[^a-zA-Z0-9-]/', '-', $serverName);
    }
}
