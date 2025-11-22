<?php
// test_endpoint.php
require_once 'config.php';

$_POST = [
    'action' => 'add_agent',
    'csrf_token' => 'test',
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => 'Caissier',
    'username' => 'testuser' . time(),
    'email' => 'test' . time() . '@example.com',
    'password' => 'TestPass123!',
    'confirm_password' => 'TestPass123!',
    'telephone' => '+257 12345678'
];

$_SESSION['csrf_token'] = 'test';

include 'ajax_actions.php';
?>