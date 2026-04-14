<?php
[$usersFile, $storageError] = users_bootstrap();
$users = $storageError === null ? users_load($usersFile) : [];
$currentUserId = stocks_auth_user_id();
$account = $currentUserId !== '' ? users_find_by_id($users, $currentUserId) : null;

$flashMessage = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update-profile') {
    if (!stocks_auth_verify_csrf($_POST['csrf_token'] ?? null)) {
        $flashMessage = 'Pedido invalido. Atualiza a pagina e tenta novamente.';
        $flashType = 'warning';
    } elseif ($storageError !== null) {
        $flashMessage = $storageError;
        $flashType = 'danger';
    } elseif ($account === null) {
        $flashMessage = 'Conta nao encontrada.';
        $flashType = 'warning';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = users_normalize_email((string) ($_POST['email'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $avatarUpload = $_FILES['avatar'] ?? null;
        $hasAvatarUpload = is_array($avatarUpload)
            && (int) ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $uploadedAvatar = null;

        if ($name === '' || $email === '') {
            $flashMessage = 'Preenche nome e email.';
            $flashType = 'warning';
        } elseif (!users_is_valid_email($email)) {
            $flashMessage = 'Indica um email valido.';
            $flashType = 'warning';
        } else {
            $emailOwner = users_find_by_email($users, $email);
            if (is_array($emailOwner) && (string) ($emailOwner['id'] ?? '') !== $currentUserId) {
                $flashMessage = 'Esse email ja esta associado a outra conta.';
                $flashType = 'warning';
            } elseif ($hasAvatarUpload && (int) ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $flashMessage = 'Falha no envio da foto. Tenta novamente.';
                $flashType = 'warning';
            } elseif ($hasAvatarUpload && !is_uploaded_file((string) ($avatarUpload['tmp_name'] ?? ''))) {
                $flashMessage = 'Nao foi possivel validar o ficheiro da foto.';
                $flashType = 'warning';
            } elseif ($hasAvatarUpload && (int) ($avatarUpload['size'] ?? 0) > 2 * 1024 * 1024) {
                $flashMessage = 'A foto deve ter no maximo 2MB.';
                $flashType = 'warning';
            } elseif ($hasAvatarUpload) {
                $tmpPath = (string) ($avatarUpload['tmp_name'] ?? '');
                $mimeType = '';
                $fileName = (string) ($avatarUpload['name'] ?? '');
                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = (string) $finfo->file($tmpPath);
                }

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/jpg' => 'jpg',
                    'image/pjpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/x-png' => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedTypes[$mimeType]) && function_exists('getimagesize')) {
                    $imageInfo = @getimagesize($tmpPath);
                    $imageMime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
                    if ($imageMime !== '') {
                        $mimeType = $imageMime;
                    }
                }

                $extension = $allowedTypes[$mimeType] ?? '';

                if ($extension === '') {
                    $declaredExtension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
                    if ($declaredExtension === 'jpeg') {
                        $declaredExtension = 'jpg';
                    }

                    $allowedExtensions = ['jpg', 'png', 'webp'];
                    if (in_array($declaredExtension, $allowedExtensions, true) && function_exists('getimagesize')) {
                        $imageInfo = @getimagesize($tmpPath);
                        if (is_array($imageInfo) && (int) ($imageInfo[0] ?? 0) > 0 && (int) ($imageInfo[1] ?? 0) > 0) {
                            $extension = $declaredExtension;
                        }
                    }
                }

                if ($extension === '') {
                    $flashMessage = 'Formato de foto invalido. Usa JPG, PNG ou WEBP.';
                    $flashType = 'warning';
                } else {
                    $uploadedAvatar = [
                        'tmp_path' => $tmpPath,
                        'extension' => $extension,
                    ];
                }
            }

            if ($flashMessage === null && $newPassword !== '' && $currentPassword === '') {
                $flashMessage = 'Indica a palavra-pass atual para confirmar a alteracao.';
                $flashType = 'warning';
            } elseif ($flashMessage === null && $newPassword !== '' && !password_verify($currentPassword, (string) ($account['password_hash'] ?? ''))) {
                $flashMessage = 'A palavra-pass atual esta incorreta.';
                $flashType = 'warning';
            } elseif ($flashMessage === null && $newPassword !== '' && strlen($newPassword) < 8) {
                $flashMessage = 'A palavra-pass deve ter pelo menos 8 caracteres.';
                $flashType = 'warning';
            } elseif ($flashMessage === null && $newPassword !== '' && !hash_equals($newPassword, $confirmPassword)) {
                $flashMessage = 'A confirmacao de palavra-pass nao corresponde.';
                $flashType = 'warning';
            } elseif ($flashMessage === null) {
                $newAvatarRelative = '';
                $newAvatarAbsolute = '';
                $oldAvatarRelative = users_normalize_avatar_path((string) ($account['avatar_path'] ?? ''));
                $oldAvatarAbsolute = $oldAvatarRelative !== '' ? dirname(__DIR__, 2) . '/' . $oldAvatarRelative : '';

                if (is_array($uploadedAvatar)) {
                    $avatarDir = users_avatar_storage_dir();
                    if (!is_dir($avatarDir) && !mkdir($avatarDir, 0775, true) && !is_dir($avatarDir)) {
                        $flashMessage = 'Nao foi possivel preparar a pasta de fotos de perfil.';
                        $flashType = 'danger';
                    } else {
                        try {
                            $token = bin2hex(random_bytes(8));
                        } catch (Throwable $exception) {
                            $token = str_replace('.', '', uniqid('', true));
                        }

                        $newAvatarRelative = users_avatar_relative_dir() . '/' . $currentUserId . '-' . $token . '.' . $uploadedAvatar['extension'];
                        $newAvatarAbsolute = dirname(__DIR__, 2) . '/' . $newAvatarRelative;

                        if (!move_uploaded_file($uploadedAvatar['tmp_path'], $newAvatarAbsolute)) {
                            $flashMessage = 'Falha ao guardar a foto de perfil.';
                            $flashType = 'danger';
                        }
                    }
                }

                if ($flashMessage === null) {
                    $updatedFields = [];
                    foreach ($users as &$item) {
                        if ((string) ($item['id'] ?? '') !== $currentUserId) {
                            continue;
                        }

                        if ((string) ($item['name'] ?? '') !== $name) {
                            $item['name'] = $name;
                            $updatedFields[] = 'name';
                        }

                        if (users_normalize_email((string) ($item['email'] ?? '')) !== $email) {
                            $item['email'] = $email;
                            $updatedFields[] = 'email';
                        }

                        if ($newPassword !== '') {
                            $item['password_hash'] = users_password_hash($newPassword);
                            $updatedFields[] = 'password';
                        }

                        if ($newAvatarRelative !== '') {
                            $item['avatar_path'] = $newAvatarRelative;
                            $updatedFields[] = 'avatar';
                        }

                        break;
                    }
                    unset($item);

                    if (!users_save($usersFile, $users)) {
                        if ($newAvatarAbsolute !== '' && is_file($newAvatarAbsolute)) {
                            @unlink($newAvatarAbsolute);
                        }
                        $flashMessage = 'Erro ao guardar alteracoes do perfil.';
                        $flashType = 'danger';
                    } else {
                        if ($newAvatarRelative !== '' && $oldAvatarAbsolute !== '' && is_file($oldAvatarAbsolute)) {
                            @unlink($oldAvatarAbsolute);
                        }

                        $_SESSION['stocks_auth_name'] = $name;
                        $users = users_load($usersFile);
                        $account = users_find_by_id($users, $currentUserId);

                        stocks_auth_audit_log('profile_updated', (string) ($account['username'] ?? ''), [
                            'fields' => $updatedFields,
                        ]);

                        $flashMessage = count($updatedFields) > 0
                            ? 'Perfil atualizado com sucesso.'
                            : 'Sem alteracoes para guardar.';
                        $flashType = count($updatedFields) > 0 ? 'success' : 'info';
                    }
                }
            }
        }
    }
}

$createdAt = (string) ($account['created_at'] ?? '');
$createdLabel = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '-';
$showConfirmField = $_SERVER['REQUEST_METHOD'] === 'POST'
    && trim((string) ($_POST['current_password'] ?? '')) !== '';
$profileAvatarPath = users_avatar_url($account);
$profileName = trim((string) ($account['name'] ?? stocks_auth_username()));
$profileInitial = strtoupper(substr($profileName !== '' ? $profileName : 'U', 0, 1));
?>

<div class="row g-3">
    <div class="col-12">
        <div class="metric-card dashboard-panel">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="dashboard-kicker">Conta</span>
                    <h1 class="dashboard-title mb-2">Perfil</h1>
                    <p class="dashboard-copy mb-0">Atualiza os teus dados e protege a tua conta.</p>
                </div>
            </div>

            <?php if ($storageError !== null): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php elseif ($account === null): ?>
                <div class="alert alert-warning" role="alert">Conta nao encontrada.</div>
            <?php endif; ?>

            <?php if ($flashMessage !== null): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8'); ?>" role="alert"><?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($account !== null): ?>
                <form method="post" enctype="multipart/form-data" class="row g-3" data-submit-lock>
                    <input type="hidden" name="action" value="update-profile">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(stocks_auth_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="col-12">
                        <label class="form-label">Foto de perfil</label>
                        <div class="profile-avatar-editor">
                            <?php if ($profileAvatarPath !== ''): ?>
                                <img
                                    src="<?php echo htmlspecialchars($profileAvatarPath, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="profile-avatar-preview"
                                    alt="Foto de perfil de <?php echo htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else: ?>
                                <span class="profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($profileInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <div class="profile-avatar-input-wrap">
                                <input class="form-control" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted d-block mt-1">Formatos: JPG, PNG ou WEBP. Maximo 2MB.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nome</label>
                        <input class="form-control" name="name" required value="<?php echo htmlspecialchars((string) ($account['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input class="form-control" name="email" type="email" required value="<?php echo htmlspecialchars((string) ($account['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Utilizador</label>
                        <input class="form-control" value="<?php echo htmlspecialchars((string) ($account['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Perfil</label>
                        <input class="form-control" value="<?php echo htmlspecialchars(users_role_label(users_normalize_role((string) ($account['role'] ?? 'employee'))), ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Criado em</label>
                        <input class="form-control" value="<?php echo htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Palavra-pass atual</label>
                        <input class="form-control" name="current_password" type="password" placeholder="Obrigatoria para alterar a palavra-pass" data-current-password-input>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nova palavra-pass (opcional)</label>
                        <input class="form-control" name="new_password" type="password" minlength="8" placeholder="Deixa vazio para manter">
                    </div>

                    <div class="col-md-6<?php echo $showConfirmField ? '' : ' d-none'; ?>" data-confirm-password-group>
                        <label class="form-label">Confirmar nova palavra-pass</label>
                        <input class="form-control" name="confirm_password" type="password" minlength="8" placeholder="Repete apenas se alterares" data-confirm-password-input>
                    </div>

                    <div class="col-md-3 d-grid align-items-end">
                        <label class="form-label invisible">Acoes</label>
                        <button class="btn btn-primary" type="submit" data-loading-text="A guardar...">Guardar perfil</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    (() => {
        const currentPasswordInput = document.querySelector('[data-current-password-input]');
        const confirmPasswordGroup = document.querySelector('[data-confirm-password-group]');
        const confirmPasswordInput = document.querySelector('[data-confirm-password-input]');

        if (!currentPasswordInput || !confirmPasswordGroup || !confirmPasswordInput) {
            return;
        }

        const syncConfirmVisibility = () => {
            const hasCurrentPassword = currentPasswordInput.value.trim() !== '';
            confirmPasswordGroup.classList.toggle('d-none', !hasCurrentPassword);

            if (!hasCurrentPassword) {
                confirmPasswordInput.value = '';
            }
        };

        currentPasswordInput.addEventListener('input', syncConfirmVisibility);
        syncConfirmVisibility();
    })();
</script>