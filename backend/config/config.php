<?php

return [
    'apache' => [
        // Apache directories
        'sites_available' => '/etc/apache2/sites-available/',
        'sites_enabled' => '/etc/apache2/sites-enabled/',
        'log_dir' => '/var/log/apache2/',
        
        // Log files
        'access_log' => '/var/log/apache2/access.log',
        'error_log' => '/var/log/apache2/error.log',
        
        // Commands
        'restart_command' => 'sudo systemctl restart apache2',
        'reload_command' => 'sudo systemctl reload apache2',
        'status_command' => 'systemctl is-active apache2',
        'version_command' => 'apache2 -v',
        'config_test_command' => 'sudo apache2ctl configtest',
        
        // Site management commands
        'enable_site_command' => 'sudo a2ensite',
        'disable_site_command' => 'sudo a2dissite',
    ],
    
    'security' => [
        // Allowed file operations
        'allowed_dirs' => [
            '/var/www/',
            '/etc/apache2/sites-available/',
            '/etc/apache2/sites-enabled/'
        ],
        
        // Command execution timeout (seconds)
        'command_timeout' => 30,
        
        // Maximum log lines to return
        'max_log_lines' => 1000,
    ],
    
    'cors' => [
        'allowed_origins' => [
            'http://localhost:8000',  // Next.js frontend
        ],
        'allowed_methods' => [
            'GET',
            'POST',
            'PUT',
            'DELETE',
            'OPTIONS'
        ],
        'allowed_headers' => [
            'Content-Type',
            'Authorization'
        ],
    ],
    
    'validation' => [
        'virtualhost' => [
            'required_fields' => [
                'serverName',
                'documentRoot'
            ],
            'optional_fields' => [
                'serverAlias',
                'customLog',
                'errorLog',
                'enabled'
            ],
            'server_name_regex' => '/^[a-zA-Z0-9.-]+$/',
            'max_aliases' => 10,
        ]
    ],
    
    'logging' => [
        'enabled' => true,
        'file' => __DIR__ . '/../logs/app.log',
        'level' => 'debug',  // debug, info, warning, error
    ]
];
