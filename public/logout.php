<?php
    require_once __DIR__ . '/../app/config/config.php';

    // Iniciamos sesión para poder acceder a la sesión
    session_start();

    
    // Variable para borrar las variables de sesion
    $_SESSION = [];

    // Borramos las cookies del servidor
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    // Redirigimos al LOGIN
    header("Location: " . BASE_URL . "public/login.php");
    exit;
?>