<?php
// admin_edit.php - Редактирование анкеты администратором
require_once 'config.php';

// Проверка HTTP-авторизации
$adminLogin = authenticateAdmin();

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: admin.php');
    exit;
}

// Получаем данные анкеты
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = :id");
$stmt->execute([':id' => $id]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: admin.php');
    exit;
}

// Получаем породы анкеты
$breedsStmt = $pdo->prepare("
    SELECT db.id, db.name FROM application_breeds ab
    JOIN dog_breeds db ON ab.breed_id = db.id
    WHERE ab.application_id = :id
");
$breedsStmt->execute([':id' => $id]);
$userBreeds = $breedsStmt->fetchAll(PDO::FETCH_COLUMN);

// Получаем все породы для списка
$allBreeds = getBreeds($pdo);
$errors = [];

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $breeds = $_POST['breeds'] ?? [];
    $biography = trim($_POST['biography'] ?? '');
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    
    // Валидация
    if (empty($full_name)) {
        $errors['full_name'] = "ФИО обязательно";
    } elseif (strlen($full_name) > 150) {
        $errors['full_name'] = "ФИО не длиннее 150 символов";
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
        $errors['full_name'] = "Только буквы, пробелы и дефис";
    }
    
    $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
    if (empty($phone_clean)) {
        $errors['phone'] = "Телефон обязателен";
    } elseif (!preg_match('/^(\+7|8)[0-9]{10}$/', $phone_clean)) {
        $errors['phone'] = "Формат +7XXXXXXXXXX или 8XXXXXXXXXX";
    }
    
    if (empty($email)) {
        $errors['email'] = "Email обязателен";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Некорректный email";
    }
    
    if (empty($birth_date)) {
        $errors['birth_date'] = "Дата обязательна";
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$date || $date->format('Y-m-d') !== $birth_date) {
            $errors['birth_date'] = "Формат ГГГГ-ММ-ДД";
        } elseif ($date > new DateTime()) {
            $errors['birth_date'] = "Дата не может быть в будущем";
        }
    }
    
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = "Выберите пол";
    }
    
    if (empty($breeds)) {
        $errors['breeds'] = "Выберите хотя бы одну породу";
    }
    
    if (!$contract_accepted) {
        $errors['contract_accepted'] = "Подтвердите согласие";
    }
    
    // Если нет ошибок — сохраняем
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Обновление анкеты
            $sql = "UPDATE applications SET 
                    full_name = :full_name,
                    phone = :phone,
                    email = :email,
                    birth_date = :birth_date,
                    gender = :gender,
                    biography = :biography,
                    contract_accepted = :contract_accepted
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':full_name' => $full_name,
                ':phone' => $phone,
                ':email' => $email,
                ':birth_date' => $birth_date,
                ':gender' => $gender,
                ':biography' => $biography,
                ':contract_accepted' => $contract_accepted,
                ':id' => $id
            ]);
            
            // Удаляем старые породы
            $pdo->prepare("DELETE FROM application_breeds WHERE application_id = :id")->execute([':id' => $id]);
            
            // Вставляем новые породы
            $linkStmt = $pdo->prepare("INSERT INTO application_breeds (application_id, breed_id) VALUES (:app, :breed)");
            foreach ($breeds as $breed_id) {
                $linkStmt->execute([':app' => $id, ':breed' => $breed_id]);
            }
            
            $pdo->commit();
            
            header('Location: admin.php?updated=1');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = "Ошибка БД: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование — Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .error-border { border: 2px solid #f44336 !important; background: #ffebee !important; }
        .field-error { color: #f44336; font-size: 0.8rem; display: block; margin-top: 0.25rem; }
        .admin-edit-container { max-width: 800px; margin: 0 auto; }
        .btn-back { background: #b0bec5; color: white; padding: 0.75rem 1.5rem; border-radius: 40px; text-decoration: none; display: inline-block; }
        .btn-back:hover { color: white; transform: translateY(-2px); }
        .form-box { background: white; padding: 2rem; border-radius: 24px; box-shadow: 0 8px 20px rgba(216,92,155,0.15); }
        .form-box .form-group { margin-bottom: 1.2rem; }
        .form-box label { font-weight: 600; color: #7a2451; display: block; margin-bottom: 0.3rem; }
        .form-box .required { color: #d82c68; }
        .form-box input, .form-box select, .form-box textarea { width: 100%; padding: 0.75rem; border: 2px solid #f0d0e0; border-radius: 12px; transition: all 0.3s; }
        .form-box input:focus, .form-box select:focus, .form-box textarea:focus { outline: none; border-color: #d85a9b; box-shadow: 0 0 0 3px rgba(216,92,155,0.2); }
        .form-box .radio-group { display: flex; gap: 1.5rem; flex-wrap: wrap; padding: 0.5rem 0; }
        .form-box .radio-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; }
        .form-box .checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: normal !important; }
        .form-box .checkbox-label input { width: 1.2rem; height: 1.2rem; }
        .form-box select[multiple] { min-height: 150px; }
        .form-box .submit-btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #d85a9b, #c14a8f); color: white; border: none; border-radius: 40px; font-size: 1.1rem; font-weight: bold; transition: all 0.3s; }
        .form-box .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(216,92,155,0.4); }
        .form-box small { display: block; margin-top: 0.25rem; color: #7a2451; font-size: 0.75rem; }
        .error-summary { background: #ffebee; border-left: 5px solid #f44336; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>🐾 Persik Grooming</h1>
            <p class="subtitle">Редактирование записи #<?php echo $id; ?></p>
            <p class="student-info">Администратор: <?php echo htmlspecialchars($adminLogin); ?></p>
        </div>
    </header>

    <main class="container admin-edit-container" style="padding: 2rem 0;">
        <a href="admin.php" class="btn-back">← Назад к списку</a>
        
        <?php if (!empty($errors)): ?>
            <div class="error-summary" style="margin: 1rem 0;">
                <strong>❌ Ошибки:</strong>
                <ul style="margin:0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-box" style="margin-top:1rem;">
            <form method="POST">
                <div class="form-group">
                    <label>ФИО владельца <span class="required">*</span></label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($application['full_name']); ?>"
                           class="<?php echo isset($errors['full_name']) ? 'error-border' : ''; ?>">
                    <?php if (isset($errors['full_name'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['full_name']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Телефон <span class="required">*</span></label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($application['phone']); ?>"
                           class="<?php echo isset($errors['phone']) ? 'error-border' : ''; ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['phone']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($application['email']); ?>"
                           class="<?php echo isset($errors['email']) ? 'error-border' : ''; ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Дата рождения питомца <span class="required">*</span></label>
                    <input type="date" name="birth_date" value="<?php echo htmlspecialchars($application['birth_date']); ?>"
                           class="<?php echo isset($errors['birth_date']) ? 'error-border' : ''; ?>">
                    <?php if (isset($errors['birth_date'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['birth_date']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Пол питомца <span class="required">*</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="gender" value="male" <?php echo $application['gender'] == 'male' ? 'checked' : ''; ?>> Мальчик</label>
                        <label><input type="radio" name="gender" value="female" <?php echo $application['gender'] == 'female' ? 'checked' : ''; ?>> Девочка</label>
                    </div>
                    <?php if (isset($errors['gender'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['gender']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Породы <span class="required">*</span></label>
                    <select name="breeds[]" multiple size="6" class="<?php echo isset($errors['breeds']) ? 'error-border' : ''; ?>">
                        <?php foreach ($allBreeds as $breed): ?>
                            <option value="<?php echo $breed['id']; ?>" 
                                <?php echo in_array($breed['id'], $userBreeds) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($breed['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['breeds'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['breeds']; ?></span>
                    <?php endif; ?>
                    <small>Зажмите Ctrl для выбора нескольких пород</small>
                </div>

                <div class="form-group">
                    <label>Особые пожелания</label>
                    <textarea name="biography" rows="4"><?php echo htmlspecialchars($application['biography'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="contract_accepted" value="1" <?php echo $application['contract_accepted'] ? 'checked' : ''; ?>>
                        С условиями ознакомлен(а) <span class="required">*</span>
                    </label>
                    <?php if (isset($errors['contract_accepted'])): ?>
                        <span class="field-error">⚠️ <?php echo $errors['contract_accepted']; ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="submit-btn">💾 Сохранить изменения</button>
            </form>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>© Доценко Маргарита | Persik Grooming</p>
        </div>
    </footer>
</body>
</html>