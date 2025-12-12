<?php
    // CONTROL DE SESIÓN
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Si existe la variable de sesión 'carrito', contamos cuántos ítems tiene. Si no, es 0.
    $cantidadCarrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0;
    
    // Configuración de ruta de imagen de perfil
    $userImg = isset($_SESSION['USER_IMG']) && !empty($_SESSION['USER_IMG']) ? $_SESSION['USER_IMG'] : null;

    // --- CORRECCIÓN: Definir ruta de perfil dinámica según el ROL ---
    $rutaPerfil = "#"; 
    if (isset($_SESSION['ROLE'])) {
        if ($_SESSION['ROLE'] == 1) {
            // Admin
            $rutaPerfil = BASE_URL . "public/admin/perfil/index.php";
        } elseif ($_SESSION['ROLE'] == 3) {
            // Artista
            $rutaPerfil = BASE_URL . "public/artista/perfil/index.php";
        } 
        // Si tienes perfil para cliente (Rol 2), agrégalo aquí
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeria de Arte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/css/home.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>public/index.php">
                <img src="<?= BASE_URL ?>public/img/logo.png" width="45" height="45" class="me-2">
                <strong>Galeria de Arte</strong>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>


            <div class="collapse navbar-collapse justify-content-end" id="navMenu">
                <ul class="navbar-nav align-items-center">

                    <?php if(!isset($_SESSION['ID_USER'])): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>public/index.php">Inicio</a></li>

                        <li class="nav-item"><a class="nav-link" href="#">Exhibiciones</a></li>

                        <li class="nav-item"><a class="nav-link" href="#">Artistas</a></li>

                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>public/login.php">Iniciar sesión</a>
                        </li>

                    <?php else: ?>
                        <?php if($_SESSION['ROLE'] == 1): ?>
                            <li class="nav-item"><a class="nav-link text-warning fw-bold" href="<?= BASE_URL ?>public/admin/index.php"><i class="bi bi-speedometer2"></i> Dashboard </a></li>

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Gestión</a>

                                <ul class="dropdown-menu">
                                    <li><h6 class="dropdown-header">Catálogos</h6></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/nacionalidades/index.php">Nacionalidades</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/roles/index.php">Roles</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/tecnicas/index.php">Técnicas</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/categorias/index.php">Categorías</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header">Operaciones</h6></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/orders/index.php">Estatus Órdenes</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/orders/order.php">Gestión de Órdenes</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/exhibiciones/index.php">Exhibiciones</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/aprobacion_obras/index.php">Aprobar obras</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>public/admin/usuarios/create_admin.php">Crear Nuevo Admin</a></li>
                                </ul>
                            </li>

                        <?php elseif ($_SESSION['ROLE'] == 2): ?>
                            <li class="nav-item"><a class="nav-link" href="#">Exhibiciones</a></li>
                            <li class="nav-item"><a class="nav-link" href="#">Artistas</a></li>
                            <li class="nav-item"><a class="nav-link" href="#">Categorías</a></li>


                            <li class="nav-item ms-2">
                                <a class="nav-link position-relative" href="#" title="Lista de Deseos">
                                    <i class="bi bi-heart fs-5"></i>
                                </a>
                            </li>
                            <li class="nav-item ms-1 me-2">
                                <a class="nav-link position-relative" href="#" title="Carrito">
                                    <i class="bi bi-cart3 fs-5"></i>
                                    
                                    <?php if($cantidadCarrito > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;"></span>
                                    <?php endif; ?>
                                </a>
                            </li>

                        <?php elseif($_SESSION['ROLE'] == 3): ?>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>public/artista/ventas/index.php"><i class="bi bi-graph-up"></i>Mis Ventas</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>public/artista/exhibiciones/index.php">Exhibiciones</a></li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= BASE_URL ?>public/artista/obras/index.php"><i class="bi bi-plus-lg"></i>Nueva Obra</a>
                            </li>

                        <?php endif; ?>
                        

                        <li class="nav-item dropdown ms-3 border-start ps-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                
                                <?php if ($userImg): ?>
                                    <img src="<?= BASE_URL ?>public/img/profiles/<?= $userImg ?>" 
                                            alt="Perfil" 
                                            class="rounded-circle me-2" 
                                            style="width: 35px; height: 35px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center me-2" style="width: 35px; height: 35px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                    </div>
                                <?php endif; ?>

                                <span class="d-none d-lg-inline"> <?= $_SESSION['USER_NAME'] ?> </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                
                                <li><a class="dropdown-item" href="<?= $rutaPerfil ?>">Mi Perfil</a></li>

                                <?php if($_SESSION['ROLE'] == 2): ?>
                                    <li><a class="dropdown-item" href="#">Mis Compras</a></li>
                                <?php endif; ?>

                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#modalLogout"><i class="bi bi-box-arrow-right"></i>Cerrar Sesión</a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="modal fade" id="modalLogout" tabindex="-1" aria-labelledby="modalLogoutLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" id="modalLogoutLabel">¿Cerrar Sesión?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body text-center py-4">
                    <i class="bi bi-exclamation-circle text-warning display-1 mb-3"></i>
                    <p class="mb-0 fs-5">¿Estás seguro de que quieres cerrar sesión?</p>
                </div>

                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <a href="<?= BASE_URL ?>public/logout.php" class="btn btn-danger px-4">Sí, salir</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-5">