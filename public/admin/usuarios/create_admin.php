<?php
    require_once __DIR__ . '/../../../app/config/config.php';
    
    if (session_status() === PHP_SESSION_NONE) session_start();

    // 1. SEGURIDAD
    if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 1) {
        header("Location: " . BASE_URL . "public/login.php");
        exit;
    }

    $url_actual = $_SERVER['PHP_SELF'];
    $current_user = $_SESSION['ID_USER'];
    
    // Variables de mensaje
    $msg = ""; 
    $msgType = "";

    // Recuperar Flash Messages
    if(isset($_SESSION['temp_msg'])) {
        $msg = $_SESSION['temp_msg'];
        $msgType = $_SESSION['temp_msg_type'];
        if($msgType == 'danger') $msgType = 'error'; 
        unset($_SESSION['temp_msg']); unset($_SESSION['temp_msg_type']);
    }

    // ---------------------------------------------------------
    // LÓGICA POST (CREAR, EDITAR, ELIMINAR)
    // ---------------------------------------------------------
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        $accion = $_POST['action'];
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $pass = trim($_POST['pass']); // Sin Hash
        
        // Regex
        $regexNombre = "/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*$/u";
        $regexPass = "/^[A-Z](?=.*[\W_]).{7,}$/";

        try {
            // --- CREAR ---
            if($accion == 'create') {
                // Validaciones básicas
                if(empty($nombre) || empty($email) || empty($pass)) {
                    throw new Exception("Todos los campos son obligatorios.");
                }
                if (!preg_match($regexNombre, $nombre)) throw new Exception("Formato de nombre incorrecto.");
                if (!preg_match($regexPass, $pass)) throw new Exception("La contraseña no es segura.");

                // Validar Email único
                $check = $pdo->prepare("SELECT ID_USER FROM USERS WHERE EMAIL = :email");
                $check->execute([':email' => $email]);
                if($check->rowCount() > 0) throw new Exception("El correo ya existe.");

                // Insertar (ID_ROLE 1 = Admin)
                $sql = "INSERT INTO USERS (ID_ROLE, FULL_NAME, EMAIL, PASS, DATE_CREATED, PROFILE_IMAGE) 
                        VALUES (1, :name, :email, :pass, NOW(), 'default_user.png')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $nombre, ':email' => $email, ':pass' => $pass]);

                $_SESSION['temp_msg'] = "Administrador creado exitosamente.";
                $_SESSION['temp_msg_type'] = "success";

            // --- EDITAR ---
            } elseif($accion == 'update') {
                $id = $_POST['id'];
                
                // Si la contraseña viene vacía, no la actualizamos
                if(!empty($pass)) {
                    if (!preg_match($regexPass, $pass)) throw new Exception("La nueva contraseña no es segura.");
                    $sql = "UPDATE USERS SET FULL_NAME = :name, EMAIL = :email, PASS = :pass WHERE ID_USER = :id";
                    $params = [':name'=>$nombre, ':email'=>$email, ':pass'=>$pass, ':id'=>$id];
                } else {
                    $sql = "UPDATE USERS SET FULL_NAME = :name, EMAIL = :email WHERE ID_USER = :id";
                    $params = [':name'=>$nombre, ':email'=>$email, ':id'=>$id];
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $_SESSION['temp_msg'] = "Datos actualizados.";
                $_SESSION['temp_msg_type'] = "success";

            // --- ELIMINAR ---
            } elseif($accion == 'delete') {
                $id = $_POST['id'];
                
                // Protección: No puedes borrarte a ti mismo
                if($id == $current_user) {
                    throw new Exception("No puedes eliminar tu propia cuenta mientras estás logueado.");
                }

                $stmt = $pdo->prepare("DELETE FROM USERS WHERE ID_USER = :id AND ID_ROLE = 1");
                $stmt->execute([':id' => $id]);
                
                $_SESSION['temp_msg'] = "Administrador eliminado.";
                $_SESSION['temp_msg_type'] = "success";
            }

            header("Location: " . $url_actual);
            exit;

        } catch (Exception $e) {
            $msg = $e->getMessage();
            $msgType = "error";
        }
    }

    // ---------------------------------------------------------
    // CONSULTA: LISTAR SOLO ADMINISTRADORES (Rol 1)
    // ---------------------------------------------------------
    $admins = [];
    try {
        $admins = $pdo->query("SELECT * FROM USERS WHERE ID_ROLE = 1 ORDER BY DATE_CREATED DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}

    require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container my-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="../index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Volver</a>
            <h2 class="fw-bold"><i class="bi bi-shield-lock-fill"></i> Gestión de Administradores</h2>
        </div>
        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-person-plus-fill"></i> Nuevo Admin
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark text-white">
                    <tr>
                        <th class="ps-4">Nombre</th>
                        <th>Email</th>
                        <th>Registro</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($admins)): ?>
                        <tr><td colspan="4" class="text-center py-4">No hay administradores registrados.</td></tr>
                    <?php else: ?>
                        <?php foreach($admins as $row): ?>
                        <tr class="<?= ($row['ID_USER'] == $current_user) ? 'table-active' : '' ?>">
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white fw-bold me-2" style="width: 35px; height: 35px;">
                                        <?= substr($row['FULL_NAME'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= $row['FULL_NAME'] ?></div>
                                        <?php if($row['ID_USER'] == $current_user): ?>
                                            <span class="badge bg-success" style="font-size: 0.6rem;">TÚ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= $row['EMAIL'] ?></td>
                            <td class="text-muted small"><?= date('d/m/Y', strtotime($row['DATE_CREATED'])) ?></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-warning me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal"
                                    data-id="<?= $row['ID_USER'] ?>"
                                    data-name="<?= $row['FULL_NAME'] ?>"
                                    data-email="<?= $row['EMAIL'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <?php if($row['ID_USER'] != $current_user): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['ID_USER'] ?>">
                                        <button type="button" onclick="confirmDelete(event, this.form)" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
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
                <h5 class="modal-title fw-bold">Registrar Colaborador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" autocomplete="off">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="alert alert-warning d-flex align-items-center p-2 mb-3 small">
                        <i class="bi bi-shield-lock-fill me-2"></i>
                        <div>Acceso total al sistema.</div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Nombre Completo</label>
                        <input type="text" class="form-control" name="nombre" id="add_nombre" required placeholder="Ej. Ana Lopez">
                        <small id="helpNombreAdd" class="text-muted small d-block mt-1"></small>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Contraseña</label>
                        <input type="password" class="form-control" name="pass" id="add_pass" required>
                        <small id="helpPassAdd" class="text-muted small d-block mt-1"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark fw-bold">Crear</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Editar Administrador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" autocomplete="off">
                <div class="modal-body py-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="fw-bold">Nombre Completo</label>
                        <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        <small id="helpNombreEdit" class="text-muted small d-block mt-1"></small>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Correo Electrónico</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Nueva Contraseña <span class="fw-normal text-muted">(Opcional)</span></label>
                        <input type="password" class="form-control" name="pass" id="edit_pass" placeholder="Dejar vacío para no cambiar">
                        <small id="helpPassEdit" class="text-muted small d-block mt-1"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- 1. VALIDACIONES REGEX ---
    const regexNombre = /^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*$/;
    const regexPass = /^[A-Z](?=.*[\W_]).{7,}$/;

    function validarInput(input, help, regex, msgError, msgSuccess) {
        input.addEventListener('keyup', function() {
            if(input.value.length === 0) { // Si está vacío (caso editar opcional)
                input.classList.remove('is-invalid', 'is-valid');
                help.innerText = "";
                return;
            }
            if (regex.test(input.value)) {
                input.classList.remove('is-invalid'); input.classList.add('is-valid');
                help.classList.remove('text-danger'); help.classList.add('text-success');
                help.innerText = msgSuccess;
            } else {
                input.classList.remove('is-valid'); input.classList.add('is-invalid');
                help.classList.remove('text-success'); help.classList.add('text-danger');
                help.innerText = msgError;
            }
        });
    }

    // Configurar validaciones para AGREGAR
    validarInput(document.getElementById('add_nombre'), document.getElementById('helpNombreAdd'), regexNombre, "Inicia con Mayúscula, sin números.", "Formato correcto.");
    validarInput(document.getElementById('add_pass'), document.getElementById('helpPassAdd'), regexPass, "Mayúscula + Símbolo especial + 8 caracteres.", "Contraseña segura.");

    // Configurar validaciones para EDITAR
    validarInput(document.getElementById('edit_nombre'), document.getElementById('helpNombreEdit'), regexNombre, "Inicia con Mayúscula, sin números.", "Formato correcto.");
    validarInput(document.getElementById('edit_pass'), document.getElementById('helpPassEdit'), regexPass, "Mayúscula + Símbolo especial + 8 caracteres.", "Contraseña segura.");


    // --- 2. POPULAR MODAL EDITAR ---
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        editModal.querySelector('#edit_id').value = btn.getAttribute('data-id');
        editModal.querySelector('#edit_nombre').value = btn.getAttribute('data-name');
        editModal.querySelector('#edit_email').value = btn.getAttribute('data-email');
        // Limpiar validaciones previas
        editModal.querySelector('#edit_pass').value = '';
        editModal.querySelector('#edit_pass').classList.remove('is-valid', 'is-invalid');
        document.getElementById('helpPassEdit').innerText = "";
    });

    // --- 3. SWEET ALERT ---
    function confirmDelete(e, form) {
        e.preventDefault();
        Swal.fire({
            title: '¿Eliminar Administrador?',
            text: "Esta acción quitará el acceso a este usuario.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar'
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