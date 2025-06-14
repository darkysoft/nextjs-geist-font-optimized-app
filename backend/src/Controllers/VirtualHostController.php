<?php

require_once __DIR__ . '/../Services/VirtualHostService.php';

class VirtualHostController {
    private $service;

    public function __construct() {
        $this->service = new VirtualHostService();
    }

    public function listVirtualHosts() {
        return $this->service->listAll();
    }

    public function getVirtualHost($id) {
        if (!$id) {
            throw new Exception('VirtualHost ID is required', 400);
        }
        return $this->service->get($id);
    }

    public function createVirtualHost($data) {
        if (!isset($data['serverName']) || !isset($data['documentRoot'])) {
            throw new Exception('ServerName and DocumentRoot are required', 400);
        }

        // Validate server name format
        if (!filter_var($data['serverName'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new Exception('Invalid ServerName format', 400);
        }

        // Validate document root path
        if (!is_dir($data['documentRoot'])) {
            throw new Exception('DocumentRoot directory does not exist', 400);
        }

        return $this->service->create($data);
    }

    public function updateVirtualHost($data) {
        if (!isset($data['id'])) {
            throw new Exception('VirtualHost ID is required', 400);
        }

        if (isset($data['serverName']) && !filter_var($data['serverName'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new Exception('Invalid ServerName format', 400);
        }

        if (isset($data['documentRoot']) && !is_dir($data['documentRoot'])) {
            throw new Exception('DocumentRoot directory does not exist', 400);
        }

        return $this->service->update($data['id'], $data);
    }

    public function deleteVirtualHost($id) {
        if (!$id) {
            throw new Exception('VirtualHost ID is required', 400);
        }
        return $this->service->delete($id);
    }

    public function toggleVirtualHost($id) {
        if (!$id) {
            throw new Exception('VirtualHost ID is required', 400);
        }
        return $this->service->toggle($id);
    }
}
