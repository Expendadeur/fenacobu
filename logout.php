<?php
// logout.php - Déconnexion sécurisée
require_once 'config.php';
require_once 'session_manager.php';

$sessionManager = new SessionManager();
$sessionManager->destroySession();

// Redirection vers la page de connexion
header('Location: index.php');
exit;

?>
