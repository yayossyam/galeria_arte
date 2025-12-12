<?php
    require_once __DIR__ . '/../../app/config/config.php';
    
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Seguridad
    if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 1) {
        header("Location: " . BASE_URL . "public/login.php");
        exit;
    }

    // --- 1. CONTEOS GENERALES (KPIs) ---
    $total_artistas = $pdo->query("SELECT COUNT(*) FROM ARTISTS")->fetchColumn();
    $total_obras    = $pdo->query("SELECT COUNT(*) FROM ARTWORKS")->fetchColumn();

    // --- 2. LISTAS RECIENTES (Para las tablas del Dashboard) ---
    $recientes_artistas = $pdo->query("SELECT * FROM ARTISTS ORDER BY ID_ARTIST DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    // Usamos "1 as IS_APPROVED" para evitar errores si no has creado la columna en la BD aún
    $sql_obras_recientes = "SELECT A.ID_ARTWORK, A.TITLE, A.IMAGE_COVER, 1 as IS_APPROVED, AR.FULL_NAME
                            FROM ARTWORKS A JOIN ARTISTS AR ON A.ID_ARTIST = AR.ID_ARTIST
                            ORDER BY A.ID_ARTWORK DESC LIMIT 5";
    $recientes_obras = $pdo->query($sql_obras_recientes)->fetchAll(PDO::FETCH_ASSOC);


    // --- 3. LISTAS COMPLETAS (Para los Modales) ---
    
    // Todos los Artistas
    $sql_all_artists = "SELECT A.*, N.NAME as PAIS 
                        FROM ARTISTS A 
                        LEFT JOIN NATIONALITY N ON A.ID_NATIONALITY = N.ID_NATIONALITY 
                        ORDER BY A.FULL_NAME ASC";
    $todos_artistas = $pdo->query($sql_all_artists)->fetchAll(PDO::FETCH_ASSOC);

    // Todas las Obras
    $sql_all_obras = "SELECT A.*, AR.FULL_NAME as ARTISTA 
                        FROM ARTWORKS A 
                        JOIN ARTISTS AR ON A.ID_ARTIST = AR.ID_ARTIST 
                        ORDER BY A.TITLE ASC";
    $todas_obras = $pdo->query($sql_all_obras)->fetchAll(PDO::FETCH_ASSOC);


    require_once __DIR__ . '/../../app/views/layout/header.php';
?>

<div class="container my-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Resumen del Catálogo</h2>
        <span class="text-muted"><?= date('d/m/Y') ?></span>
    </div>

    <div class="row g-4 mb-5">
        
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body d-flex align-items-center justify-content-between p-4">
                    <div>
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Artistas Registrados</h6>
                        <h1 class="display-4 fw-bold text-dark mb-0"><?= $total_artistas ?></h1>
                    </div>
                    <div class="rounded-circle bg-light p-3">
                        <i class="bi bi-palette display-4 text-primary opacity-75"></i>
                    </div>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="#" class="text-decoration-none fw-bold text-primary small" data-bs-toggle="modal" data-bs-target="#modalAllArtists">
                        Ver todos los artistas &rarr;
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body d-flex align-items-center justify-content-between p-4">
                    <div>
                        <h6 class="text-uppercase text-muted fw-bold mb-1">Obras en Galería</h6>
                        <h1 class="display-4 fw-bold text-dark mb-0"><?= $total_obras ?></h1>
                    </div>
                    <div class="rounded-circle bg-light p-3">
                        <i class="bi bi-image display-4 text-success opacity-75"></i>
                    </div>
                </div>
                <div class="card-footer bg-white border-0">
                    <a href="#" class="text-decoration-none fw-bold text-success small" data-bs-toggle="modal" data-bs-target="#modalAllObras">
                        Ver inventario completo &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevos Artistas</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            <?php if(empty($recientes_artistas)): ?>
                                <tr><td class="text-center p-4 text-muted">No hay artistas registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach($recientes_artistas as $art): ?>
                                <tr>
                                    <td class="ps-4" style="width: 60px;">
                                        <img src="<?= BASE_URL ?>public/img/users/<?= $art['PROFILE_IMAGE'] ?? 'default_artist.png' ?>" 
                                            class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= $art['FULL_NAME'] ?></div>
                                        <small class="text-muted"><?= $art['EMAIL'] ?></small>
                                    </td>
                                    <td class="text-end pe-4 text-muted small">
                                        <?= date('d M', strtotime($art['DATE_CREATED'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-brush me-2"></i>Obras Recientes</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            <?php if(empty($recientes_obras)): ?>
                                <tr><td class="text-center p-4 text-muted">No hay obras cargadas.</td></tr>
                            <?php else: ?>
                                <?php foreach($recientes_obras as $obra): ?>
                                <tr>
                                    <td class="ps-4" style="width: 60px;">
                                        <img src="<?= BASE_URL ?>public/img/artworks/<?= $obra['IMAGE_COVER'] ?? 'default.png' ?>" 
                                            class="rounded" width="40" height="40" style="object-fit: cover;">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= $obra['TITLE'] ?></div>
                                        <small class="text-muted">Por: <?= $obra['FULL_NAME'] ?></small>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="badge bg-success rounded-pill" style="font-size: 0.7rem;">Pública</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAllArtists" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-people-fill me-2"></i>Directorio Completo de Artistas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-4">Perfil</th>
                            <th>Nombre</th>
                            <th>Nacionalidad</th>
                            <th>Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($todos_artistas as $a): ?>
                        <tr>
                            <td class="ps-4">
                                <img src="<?= BASE_URL ?>public/img/users/<?= $a['PROFILE_IMAGE'] ?? 'default_artist.png' ?>" 
                                    class="rounded-circle border" width="45" height="45" style="object-fit: cover;">
                            </td>
                            <td>
                                <div class="fw-bold"><?= $a['FULL_NAME'] ?></div>
                                <small class="text-muted"><?= $a['EMAIL'] ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= $a['PAIS'] ?? 'N/A' ?></span></td>
                            <td><?= date('d/m/Y', strtotime($a['DATE_CREATED'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAllObras" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-images me-2"></i>Inventario Completo de Obras</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-4">Img</th>
                            <th>Título</th>
                            <th>Artista</th>
                            <th>Precio</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach($todas_obras as $o): ?>
                        <tr>
                            <td class="ps-4">
                                <img src="<?= BASE_URL ?>public/img/artworks/<?= $o['IMAGE_COVER'] ?? 'default.png' ?>" 
                                    class="rounded border" width="50" height="50" style="object-fit: cover;">
                            </td>
                            <td>
                                <div class="fw-bold"><?= $o['TITLE'] ?></div>
                                <small class="text-muted"><?= $o['DIMENSIONS'] ?? '' ?></small>
                            </td>
                            <td><?= $o['ARTISTA'] ?></td>
                            <td class="fw-bold text-success">$<?= number_format($o['PRICE'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/views/layout/footer.php'; ?>