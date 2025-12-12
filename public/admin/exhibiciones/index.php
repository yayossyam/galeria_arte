<?php
    require_once __DIR__ . '/../../../app/config/config.php';
    
    // 1. INICIAR SESIÓN
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // 2. SEGURIDAD (Solo Admin)
    if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 1) {
        header("Location: " . BASE_URL . "public/login.php");
        exit;
    }

    $url_actual = $_SERVER['PHP_SELF'];
    $current_user = $_SESSION['ID_USER'];
    $msg = ""; $msgType = "";

    // FLASH MESSAGES
    if(isset($_SESSION['temp_msg'])) {
        $msg = $_SESSION['temp_msg'];
        $msgType = $_SESSION['temp_msg_type'];
        if($msgType == 'danger') $msgType = 'error'; 
        unset($_SESSION['temp_msg']); unset($_SESSION['temp_msg_type']);
    }

    // ---------------------------------------------------------
    // LÓGICA: POST (Crear, Editar, Aprobar, Eliminar)
    // ---------------------------------------------------------
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $accion = $_POST['action'];

        try {
            if($accion == 'create') {
                // CASO 1: Admin crea exhibición -> Aprobada directo (1)
                $sql = "INSERT INTO EXHIBITIONS (NAME, START_DATE, END_DATE, DESCRIPTION, LOCATION, ID_CREATOR, IS_APPROVED) 
                        VALUES (:name, :start, :end, :desc, :loc, :creator, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $_POST['nombre'],
                    ':start' => $_POST['start_date'],
                    ':end' => $_POST['end_date'],
                    ':desc' => $_POST['description'],
                    ':loc' => $_POST['location'],
                    ':creator' => $current_user
                ]);
                $_SESSION['temp_msg'] = "Exhibición creada y publicada.";
                $_SESSION['temp_msg_type'] = "success";

            } elseif($accion == 'update') {
                // Editar
                $sql = "UPDATE EXHIBITIONS SET NAME=:name, START_DATE=:start, END_DATE=:end, DESCRIPTION=:desc, LOCATION=:loc WHERE ID_EXHIBITION=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $_POST['nombre'],
                    ':start' => $_POST['start_date'],
                    ':end' => $_POST['end_date'],
                    ':desc' => $_POST['description'],
                    ':loc' => $_POST['location'],
                    ':id' => $_POST['id']
                ]);
                $_SESSION['temp_msg'] = "Exhibición actualizada.";
                $_SESSION['temp_msg_type'] = "success";

            } elseif($accion == 'approve') {
                // CASO 2: APROBAR exhibición de un artista
                $stmt = $pdo->prepare("UPDATE EXHIBITIONS SET IS_APPROVED = 1 WHERE ID_EXHIBITION = :id");
                $stmt->execute([':id' => $_POST['id']]);
                $_SESSION['temp_msg'] = "Exhibición autorizada y publicada.";
                $_SESSION['temp_msg_type'] = "success";

            } elseif($accion == 'delete') {
                // Eliminar
                $id = $_POST['id'];
                $pdo->prepare("DELETE FROM EXHIBITION_REQUESTS WHERE ID_EXHIBITION = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM ARTWORKS_has_EXHIBITIONS WHERE ID_EXHIBITION = :id")->execute([':id' => $id]);
                $pdo->prepare("DELETE FROM EXHIBITIONS WHERE ID_EXHIBITION = :id")->execute([':id' => $id]);
                
                $_SESSION['temp_msg'] = "Exhibición eliminada.";
                $_SESSION['temp_msg_type'] = "success";
            }

            header("Location: " . $url_actual);
            exit;

        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    }

    // ---------------------------------------------------------
    // CONSULTAS
    // ---------------------------------------------------------
    
    // A. Exhibiciones PENDIENTES (Creadas por Artistas)
    // CORRECCIÓN: Aquí unimos con la tabla ARTISTS en lugar de USERS
    $pendientes = [];
    try {
        $sql = "SELECT E.*, A.FULL_NAME as CREADOR 
                FROM EXHIBITIONS E
                JOIN ARTISTS A ON E.ID_CREATOR = A.ID_ARTIST
                WHERE E.IS_APPROVED = 0
                ORDER BY E.START_DATE ASC";
        $pendientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }

    // B. Exhibiciones ACTIVAS (Públicas - Creadas por Admin o Artistas Aprobados)
    // Nota: Aquí mantenemos USERS para los admin, pero podrías necesitar un UNION si quieres ver nombres de artistas aprobados también.
    $activas = [];
    try {
        $sql = "SELECT E.*, U.FULL_NAME as CREADOR,
                (SELECT COUNT(*) FROM EXHIBITION_REQUESTS 
                 WHERE ID_EXHIBITION = E.ID_EXHIBITION 
                 AND STATUS = 'Pending') as TOTAL_SOLICITUDES 
                FROM EXHIBITIONS E
                LEFT JOIN USERS U ON E.ID_CREATOR = U.ID_USER
                WHERE E.IS_APPROVED = 1
                ORDER BY E.START_DATE DESC";
        $activas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }

    require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="<?= BASE_URL ?>public/admin/index.php" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
            <h2 class="fw-bold"><i class="bi bi-easel"></i> Gestión de Exhibiciones</h2>
        </div>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Crear Evento
        </button>
    </div>

    <div class="card shadow-sm border-warning mb-5">
        <div class="card-header bg-warning text-dark fw-bold py-3">
            <h5 class="mb-0"><i class="bi bi-exclamation-circle-fill me-2"></i>Solicitudes de Apertura (Creadas por Artistas)</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Nombre del Evento</th>
                        <th>Solicitante</th>
                        <th>Fechas Propuestas</th>
                        <th>Ubicación</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($pendientes)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-secondary mb-2"></i><br>
                                No hay solicitudes de exhibición pendientes.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($pendientes as $row): ?>
                        <tr class="table-warning">
                            <td class="ps-3 fw-bold"><?= $row['NAME'] ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-palette-fill text-warning"></i> <?= $row['CREADOR'] ?? 'Desconocido' ?>
                                </span>
                            </td>
                            <td>
                                <small><?= date('d/m/Y', strtotime($row['START_DATE'])) ?> - <br>
                                <?= date('d/m/Y', strtotime($row['END_DATE'])) ?></small>
                            </td>
                            <td><?= $row['LOCATION'] ?></td>
                            <td class="text-end pe-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= $row['ID_EXHIBITION'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm fw-bold shadow-sm" title="Aprobar evento">
                                        <i class="bi bi-check-lg"></i> Aprobar
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Seguro que deseas rechazar y eliminar esta solicitud de evento?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['ID_EXHIBITION'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm shadow-sm" title="Rechazar evento">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Exhibiciones Públicas</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th class="ps-4">Nombre</th>
                        <th>Creador</th>
                        <th>Fechas</th>
                        <th>Ubicación</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($activas)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay exhibiciones activas.</td></tr>
                    <?php else: ?>
                        <?php foreach($activas as $row): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= $row['NAME'] ?></td>
                                <td>
                                    <?php if($row['ID_CREATOR'] == $current_user): ?>
                                        <span class="badge bg-primary">Tú (Admin)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= $row['CREADOR'] ?? 'Artista' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= date('d M', strtotime($row['START_DATE'])) ?> - <?= date('d M Y', strtotime($row['END_DATE'])) ?></small>
                                </td>
                                <td><?= $row['LOCATION'] ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        
                                        <button class="btn btn-sm btn-outline-secondary border-0" 
                                                title="Ver Detalles"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal"
                                                data-name="<?= $row['NAME'] ?>"
                                                data-desc="<?= $row['DESCRIPTION'] ?>"
                                                data-loc="<?= $row['LOCATION'] ?>"
                                                data-start="<?= date('d/m/Y', strtotime($row['START_DATE'])) ?>"
                                                data-end="<?= date('d/m/Y', strtotime($row['END_DATE'])) ?>">
                                            <i class="bi bi-eye fs-5"></i>
                                        </button>

                                        <a href="solicitudes.php?id=<?= $row['ID_EXHIBITION'] ?>" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" title="Gestionar Artistas">
                                            <i class="bi bi-people-fill"></i> 
                                            Solicitudes
                                            <?php if($row['TOTAL_SOLICITUDES'] > 0): ?>
                                                <span class="badge bg-danger rounded-pill"><?= $row['TOTAL_SOLICITUDES'] ?></span>
                                            <?php endif; ?>
                                        </a>

                                        <button class="btn btn-sm btn-outline-warning text-dark border-warning" 
                                                title="Editar"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal"
                                                data-id="<?= $row['ID_EXHIBITION'] ?>"
                                                data-name="<?= $row['NAME'] ?>"
                                                data-start="<?= $row['START_DATE'] ?>"
                                                data-end="<?= $row['END_DATE'] ?>"
                                                data-desc="<?= $row['DESCRIPTION'] ?>"
                                                data-loc="<?= $row['LOCATION'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <form method="POST" class="m-0">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $row['ID_EXHIBITION'] ?>">
                                            <button type="button" onclick="confirmDelete(event, this.form)" class="btn btn-sm btn-outline-danger border-danger" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Nueva Exhibición</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="fw-bold">Nombre:</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><label class="fw-bold">Inicio:</label><input type="date" name="start_date" class="form-control" required></div>
                        <div class="col"><label class="fw-bold">Fin:</label><input type="date" name="end_date" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="fw-bold">Ubicación:</label><input type="text" name="location" class="form-control" required></div>
                    <div class="mb-3"><label class="fw-bold">Descripción:</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark">Publicar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">Editar Exhibición</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3"><label class="fw-bold">Nombre:</label><input type="text" name="nombre" id="edit_name" class="form-control" required></div>
                    <div class="row g-2 mb-3">
                        <div class="col"><label class="fw-bold">Inicio:</label><input type="date" name="start_date" id="edit_start" class="form-control" required></div>
                        <div class="col"><label class="fw-bold">Fin:</label><input type="date" name="end_date" id="edit_end" class="form-control" required></div>
                    </div>
                    <div class="mb-3"><label class="fw-bold">Ubicación:</label><input type="text" name="location" id="edit_loc" class="form-control" required></div>
                    <div class="mb-3"><label class="fw-bold">Descripción:</label><textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Detalles del Evento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <h4 id="view_name" class="fw-bold mb-3 text-dark"></h4>
                <div class="text-muted mb-3">
                    <i class="bi bi-calendar-event"></i> <span id="view_dates"></span>
                </div>
                <div class="mb-3">
                    <strong class="d-block text-secondary">Ubicación:</strong>
                    <span id="view_loc"></span>
                </div>
                <div class="p-3 bg-light rounded border">
                    <strong class="d-block text-secondary mb-2">Descripción:</strong>
                    <p id="view_desc" class="mb-0 text-dark"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        editModal.querySelector('#edit_id').value = btn.getAttribute('data-id');
        editModal.querySelector('#edit_name').value = btn.getAttribute('data-name');
        editModal.querySelector('#edit_start').value = btn.getAttribute('data-start');
        editModal.querySelector('#edit_end').value = btn.getAttribute('data-end');
        editModal.querySelector('#edit_loc').value = btn.getAttribute('data-loc');
        editModal.querySelector('#edit_desc').value = btn.getAttribute('data-desc');
    });

    var viewModal = document.getElementById('viewModal');
    viewModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        document.getElementById('view_name').textContent = btn.getAttribute('data-name');
        document.getElementById('view_loc').textContent = btn.getAttribute('data-loc');
        document.getElementById('view_desc').textContent = btn.getAttribute('data-desc');
        document.getElementById('view_dates').textContent = btn.getAttribute('data-start') + ' - ' + btn.getAttribute('data-end');
    });

    function confirmDelete(e, form) {
        e.preventDefault();
        Swal.fire({
            title: '¿Eliminar Exhibición?',
            text: "Se borrará permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, borrar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        })
    }

    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType ?>',
            title: '<?= $msg ?>',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>