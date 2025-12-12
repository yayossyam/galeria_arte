<?php
    require_once __DIR__ . '/../../../app/config/config.php';
    
    // 1. INICIAR SESIÓN
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 2. SEGURIDAD (Solo Admin)
    if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 1) {
        header("Location: " . BASE_URL . "public/login.php");
        exit;
    }

    $url_actual = $_SERVER['PHP_SELF'];
    $msg = ""; $msgType = "";

    // RECUPERAR MENSAJES FLASH
    if(isset($_SESSION['temp_msg'])) {
        $msg = $_SESSION['temp_msg'];
        $msgType = $_SESSION['temp_msg_type'];
        if($msgType == 'danger') $msgType = 'error'; 
        unset($_SESSION['temp_msg']); unset($_SESSION['temp_msg_type']);
    }

    // ---------------------------------------------------------
    // LÓGICA: INSERT Y UPDATE
    // ---------------------------------------------------------
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        $nombre = trim($_POST['nombre']);
        $accion = $_POST['action'];
        
        if(!empty($nombre)) {
            try {
                if($accion == 'create') {
                    // INSERT
                    $stmt = $pdo->prepare("INSERT INTO STATUS_ORDERS (NAME) VALUES (:name)");
                    $stmt->execute([':name' => $nombre]);
                    
                    $_SESSION['temp_msg'] = "Estatus agregado correctamente.";
                    $_SESSION['temp_msg_type'] = "success";

                } elseif($accion == 'update') {
                    // UPDATE
                    $id = (int) $_POST['id'];
                    $stmt = $pdo->prepare("UPDATE STATUS_ORDERS SET NAME = :name WHERE ID_STATUS = :id");
                    $stmt->execute([':name' => $nombre, ':id' => $id]);

                    $_SESSION['temp_msg'] = "Estatus actualizado correctamente.";
                    $_SESSION['temp_msg_type'] = "success";
                }

                header("Location: " . $url_actual);
                exit;

            } catch (Exception $e) {
                $msg = "Error en base de datos: " . $e->getMessage();
                $msgType = "error";
            }
        } else {
            $msg = "El campo nombre no puede estar vacío.";
            $msgType = "error";
        }
    }

    // ---------------------------------------------------------
    // LÓGICA: DELETE
    // ---------------------------------------------------------
    if(isset($_GET['delete_id'])) {
        $id = (int) $_GET['delete_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM STATUS_ORDERS WHERE ID_STATUS = :id");
            $stmt->execute([':id' => $id]);

            $_SESSION['temp_msg'] = "Estatus eliminado.";
            $_SESSION['temp_msg_type'] = "success";

        } catch (Exception $e) {
            $_SESSION['temp_msg'] = "No se puede eliminar este estatus porque hay órdenes usándolo.";
            $_SESSION['temp_msg_type'] = "error";
        }

        header("Location: " . $url_actual);
        exit;
    }

    // ---------------------------------------------------------
    // LÓGICA: SELECT
    // ---------------------------------------------------------
    $registros = [];
    try {
        $stmt = $pdo->query("SELECT * FROM STATUS_ORDERS ORDER BY ID_STATUS ASC"); // Ordenado por ID lógico (1,2,3...)
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $msg = "Error al cargar los datos";
        $msgType = "error";
    }

    require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-list-check"></i> Catálogo de Estatus de Órdenes</h2>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-dark text-white">
                    <tr>
                        <th scope="col" class="ps-4">Nombre del Estatus</th>
                        <th scope="col" class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($registros)): ?>
                        <tr>
                            <td colspan="2" class="text-center py-4 text-muted">No hay estatus registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($registros as $row): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?= array_values($row)[1] ?></td>
                                
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal" 
                                        data-id="<?= array_values($row)[0] ?>" 
                                        data-nombre="<?= array_values($row)[1] ?>">
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>
                                    
                                    <a href="#" 
                                       onclick="confirmDelete(event, '?delete_id=<?= array_values($row)[0] ?>')" 
                                       class="btn btn-sm btn-outline-danger">
                                       <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Agregar Nuevo Estatus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre:</label>
                        <input type="text" class="form-control form-control-lg" name="nombre" id="add_nombre" required placeholder="Ej. Enviado, Cancelado..." autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark px-4">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Editar Estatus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="status_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre:</label>
                        <input type="text" class="form-control form-control-lg" name="nombre" id="status_nombre" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Script para el Modal de Edición
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var nombre = button.getAttribute('data-nombre');
        
        var modalInputId = editModal.querySelector('#status_id');
        var modalInputNombre = editModal.querySelector('#status_nombre');
        
        modalInputId.value = id;
        modalInputNombre.value = nombre;
        
        setTimeout(function() { modalInputNombre.focus(); }, 500);
    });

    // 2. Auto-focus para el Modal Agregar
    var addModal = document.getElementById('addModal');
    addModal.addEventListener('shown.bs.modal', function () {
        document.getElementById('add_nombre').focus();
    });

    // 3. Alerta SweetAlert para Eliminar
    function confirmDelete(e, url) {
        e.preventDefault();
        Swal.fire({
            title: '¿Estás seguro?',
            text: "No podrás revertir esto. Se eliminará el estatus.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        })
    }

    // 4. Mostrar Mensajes PHP con SweetAlert (Toast)
    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType ?>',
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