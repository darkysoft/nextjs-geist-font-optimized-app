# Apache VirtualHost Management Dashboard - Backend API

## Overview
This is the backend API for the Apache VirtualHost Management Dashboard. It provides endpoints for managing Apache virtual hosts, server configuration, and monitoring server status.

## Requirements
- PHP 8.x
- Apache2 with mod_rewrite enabled
- Sudo privileges for Apache management
- Write permissions for Apache configuration directories

## Installation

1. Install required Apache modules:
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

2. Configure Apache permissions:
```bash
# Add PHP user to www-data group
sudo usermod -a -G www-data www-data

# Set proper permissions for Apache directories
sudo chown -R www-data:www-data /etc/apache2/sites-available/
sudo chown -R www-data:www-data /etc/apache2/sites-enabled/
sudo chmod 755 /etc/apache2/sites-available/
sudo chmod 755 /etc/apache2/sites-enabled/
```

3. Configure sudo permissions:
```bash
# Add the following to /etc/sudoers.d/apache-manager
www-data ALL=(ALL) NOPASSWD: /usr/sbin/apache2ctl
www-data ALL=(ALL) NOPASSWD: /usr/sbin/a2ensite
www-data ALL=(ALL) NOPASSWD: /usr/sbin/a2dissite
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload apache2
www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart apache2
```

4. Create logs directory:
```bash
mkdir -p logs
chmod 755 logs
```

## API Endpoints

### VirtualHosts

#### List all virtual hosts
- **GET** `/virtualhosts`
- Response: Array of virtual host configurations

#### Get specific virtual host
- **GET** `/virtualhosts/{id}`
- Response: Virtual host configuration details

#### Create virtual host
- **POST** `/virtualhosts`
- Body:
```json
{
    "serverName": "example.com",
    "documentRoot": "/var/www/example",
    "serverAlias": "www.example.com",
    "enabled": true
}
```

#### Update virtual host
- **PUT** `/virtualhosts/{id}`
- Body: Same as create, all fields optional

#### Delete virtual host
- **DELETE** `/virtualhosts/{id}`

#### Toggle virtual host
- **POST** `/virtualhosts/{id}/toggle`

### Apache Server Management

#### Get server status
- **GET** `/apache/status`
- Response: Server status information including version, uptime, and load

#### Get server logs
- **GET** `/apache/logs`
- Query parameters:
  - type: "error" or "access"
  - lines: number of lines (default: 100)
  - filter: text to filter logs

#### Restart server
- **POST** `/apache/restart`

#### Reload configuration
- **POST** `/apache/reload`

#### Check configuration
- **GET** `/apache/check`

### System Information

#### Get system info
- **GET** `/system`
- Response: System information including OS, memory, disk space

## Error Handling

All errors follow this format:
```json
{
    "success": false,
    "error": "Error message",
    "code": 400
}
```

Common error codes:
- 400: Bad Request
- 403: Forbidden
- 404: Not Found
- 409: Conflict
- 500: Internal Server Error
- 503: Service Unavailable

## Security

The API implements several security measures:
- Input validation and sanitization
- Path traversal prevention
- CORS protection
- Error logging
- Secure file operations
- Command injection prevention

## Logging

Logs are stored in the `logs` directory:
- `app.log`: Application logs
- Access to Apache logs via API endpoints

## Development

To run the development server:
```bash
cd backend
php -S localhost:8001
```

The API will be available at `http://localhost:8001`

## Testing

Recommended test endpoints:
1. Check system status: `GET /system`
2. List virtual hosts: `GET /virtualhosts`
3. Check Apache status: `GET /apache/status`

## Troubleshooting

Common issues:
1. Permission denied
   - Check Apache user permissions
   - Verify sudo configuration
   
2. 404 Not Found
   - Ensure mod_rewrite is enabled
   - Check .htaccess configuration

3. 500 Internal Server Error
   - Check logs/app.log for details
   - Verify file permissions
   - Check Apache error logs
