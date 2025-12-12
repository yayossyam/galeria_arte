<?php
    require_once __DIR__ . '/../../../app/config/config.php';
    
    // 1. SESIÓN
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // 2. SEGURIDAD (Solo Admin)
    if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 1) {
        header("Location: " . BASE_URL . "public/login.php");
        exit;
    }

    $url_actual = $_SERVER['PHP_SELF'];
    $msg = ""; $msgType = "";

    // ---------------------------------------------------------
    // LÓGICA: POST (APROBAR O RECHAZAR)
    // ---------------------------------------------------------
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        $id_artwork = $_POST['id'];
        $accion = $_POST['action'];

        try {
            if($accion == 'approve') {
                // CAMBIAR ESTADO A 1 (PÚBLICO)
                $stmt = $pdo->prepare("UPDATE ARTWORKS SET IS_APPROVED = 1 WHERE ID_ARTWORK = :id");
                $stmt->execute([':id' => $id_artwork]);

                $_SESSION['temp_msg'] = "Obra aprobada y publicada exitosamente.";
                $_SESSION['temp_msg_type'] = "success";

            } elseif($accion == 'reject') {
                // ELIMINAR REGISTRO (Limpieza completa)
                // Borramos relaciones primero para mantener la integridad de la BD
                $pdo->prepare("DELETE FROM ARTWORKS_has_CATEGORIES WHERE ID_ARTWORK = :id")->execute([':id' => $id_artwork]);
                $pdo->prepare("DELETE FROM ARTWORKS_has_EXHIBITIONS WHERE ID_ARTWORK = :id")->execute([':id' => $id_artwork]);
                $pdo->prepare("DELETE FROM ARTWORK_IMAGES WHERE ID_ARTWORK = :id")->execute([':id' => $id_artwork]);
                // Finalmente borramos la obra
                $pdo->prepare("DELETE FROM ARTWORKS WHERE ID_ARTWORK = :id")->execute([':id' => $id_artwork]);

                $_SESSION['temp_msg'] = "Obra rechazada y eliminada del sistema.";
                $_SESSION['temp_msg_type'] = "warning";
            }

            header("Location: " . $url_actual);
            exit;

        } catch (Exception $e) {
            $msg = "Error al procesar: " . $e->getMessage();
            $msgType = "error";
        }
    }

    // Mensajes Flash (después del redirect)
    if(isset($_SESSION['temp_msg'])) {
        $msg = $_SESSION['temp_msg'];
        $msgType = $_SESSION['temp_msg_type'];
        unset($_SESSION['temp_msg']); unset($_SESSION['temp_msg_type']);
    }

    // ---------------------------------------------------------
    // LÓGICA: SELECT (SOLO OBRAS PENDIENTES: IS_APPROVED = 0)
    // ---------------------------------------------------------
    $pendientes = [];
    try {
        // Hacemos JOIN con ARTISTS para saber quién subió la obra
        $sql = "SELECT 
                    A.ID_ARTWORK, 
                    A.TITLE, 
                    A.DESCRIPTION, 
                    A.PRICE, 
                    A.DIMENSIONS, 
                    A.IMAGE_COVER, 
                    A.CREATION_YEAR,
                    AR.FULL_NAME as ARTISTA
                FROM ARTWORKS A
                JOIN ARTISTS AR ON A.ID_ARTIST = AR.ID_ARTIST
                WHERE A.IS_APPROVED = 0
                ORDER BY A.ID_ARTWORK ASC";
        
        $pendientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $msg = "Error al cargar datos";
    }

    require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container my-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="../index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Volver</a>
            <h2 class="fw-bold"><i class="bi bi-check-circle-fill text-success"></i> Aprobación de Obras</h2>
        </div>
    </div>

    <?php if(empty($pendientes)): ?>
        <div class="alert alert-success shadow-sm text-center py-5">
            <i class="bi bi-emoji-smile display-1 text-success"></i>
            <h4 class="mt-3 fw-bold">¡Todo al día!</h4>
            <p class="text-muted">No hay obras pendientes de revisión en este momento.</p>
            <a href="../index.php" class="btn btn-outline-success mt-2">Volver al Dashboard</a>
        </div>
    <?php else: ?>
        
        <div class="alert alert-warning border-warning shadow-sm mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i> 
            <strong>Atención:</strong> Estas obras fueron subidas por artistas y no serán visibles al público hasta que las apruebes.
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach($pendientes as $row): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0">
                        <div style="height: 250px; overflow: hidden; background: #f8f9fa;" class="d-flex align-items-center justify-content-center bg-light position-relative">
                            <?php if(!empty($row['IMAGE_COVER'])): ?>
                                <img src="<?= BASE_URL ?>public/img/artworks/<?= $row['IMAGE_COVER'] ?>" class="card-img-top h-100 w-100" style="object-fit: cover;" alt="Portada">
                            <?php else: ?>
                                <div class="text-muted"><i class="bi bi-image display-4"></i><br>Sin Imagen</div>
                            <?php endif; ?>
                            
                            <span class="position-absolute top-0 end-0 badge bg-warning text-dark m-2 shadow-sm">Pendiente</span>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title fw-bold mb-1"><?= $row['TITLE'] ?></h5>
                            <p class="card-text text-muted small mb-3">
                                <i class="bi bi-person-circle"></i> Por: <strong class="text-dark"><?= $row['ARTISTA'] ?></strong>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                <small class="text-muted">Año: <?= $row['CREATION_YEAR'] ?></small>
                                <span class="fw-bold text-success fs-5">$<?= number_format($row['PRICE'], 2) ?></span>
                            </div>

                            <button class="btn btn-outline-primary w-100 btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewModal"
                                    data-title="<?= $row['TITLE'] ?>"
                                    data-desc="<?= $row['DESCRIPTION'] ?>"
                                    data-dim="<?= $row['DIMENSIONS'] ?>"
                                    data-img="<?= $row['IMAGE_COVER'] ?>">
                                <i class="bi bi-eye"></i> Ver Detalles
                            </button>
                        </div>

                        <div class="card-footer bg-white border-top-0 d-flex gap-2 py-3">
                            <form method="POST" class="w-50">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $row['ID_ARTWORK'] ?>">
                                <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm">
                                    <i class="bi bi-check-lg"></i> Aprobar
                                </button>
                            </form>

                            <form method="POST" class="w-50">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= $row['ID_ARTWORK'] ?>">
                                <button type="button" onclick="confirmReject(event, this.form)" class="btn btn-outline-danger w-100 fw-bold">
                                    <i class="bi bi-x-lg"></i> Rechazar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Título de la Obra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3 bg-light rounded p-2">
                    <img id="modalImg" src="" class="img-fluid rounded shadow-sm" style="max-height: 300px;">
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold text-dark"><i class="bi bi-file-text"></i> Descripción:</h6>
                    <p id="modalDesc" class="text-muted small"></p>
                </div>
                
                <div class="mb-2">
                    <h6 class="fw-bold text-dark"><i class="bi bi-aspect-ratio"></i> Dimensiones:</h6>
                    <p id="modalDim" class="text-muted small"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Configurar Modal de Detalles (Pasar datos del botón al modal)
    var viewModal = document.getElementById('viewModal');
    viewModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        
        viewModal.querySelector('#modalTitle').textContent = button.getAttribute('data-title');
        viewModal.querySelector('#modalDesc').textContent = button.getAttribute('data-desc');
        viewModal.querySelector('#modalDim').textContent = button.getAttribute('data-dim');
        
        var imgName = button.getAttribute('data-img');
        // Asegúrate de que la ruta de la imagen sea correcta
        var imgPath = '<?= BASE_URL ?>public/img/artworks/' + (imgName ? imgName : 'default.png');
        viewModal.querySelector('#modalImg').src = imgPath;
    });

    // Confirmación de Rechazo con SweetAlert
    function confirmReject(e, form) {
        e.preventDefault();
        Swal.fire({
            title: '¿Rechazar Obra?',
            text: "La obra será eliminada permanentemente y el artista deberá subirla de nuevo.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, rechazar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        })
    }

    // Mensajes Flash
    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType == "success" ? "success" : ($msgType == "warning" ? "info" : "error") ?>',
            title: '<?= $msg ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>