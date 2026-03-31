<?php

use Phalcon\Db\Adapter\Pdo\Mysql;

$config = include __DIR__ . '/app/config/config.php';

$connection = new Mysql([
    'host'     => $config->database->host,
    'username' => $config->database->username,
    'password' => $config->database->password,
    'dbname'   => $config->database->dbname,
]);

$password = password_hash('Adm!n123456789', PASSWORD_DEFAULT); // securely hashed

$connection->insert(
    'users',
    [
        '18724', // idNo
        $password,  // hashed password
        'John',   // first_name
        'Doe',    // last_name
        json_encode([
            "dashboard.access",
            "applicantList.access",
            "user.create", 
            "user.change_self_password", 
            "user.change_user_password", 
            "user.edit_self_permissions", 
            "user.edit_user_permissions", 
            "user.can_access_users", 
            "user.can_view_assessment",
            "assessment.manage"
        ]),
        'Administrative Aide VI', // designation
        '09123456789',   // phone
        0                // is_locked
    ],
    [
        'idNo', 'password', 'first_name', 'last_name', 'permissions', 'designation', 'phone', 'is_locked'
    ]
);

echo "Admin user inserted successfully.\n";
