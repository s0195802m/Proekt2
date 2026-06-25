<?php
// process.php - Обработчик формы (GET метод, валидация, Cookies, БД, авторизация)
session_start();
require_once 'config.php';

// Функция генерации случайного логина
function generateLogin($full_name) {
    $base = strtolower(preg_replace('/[^a-zA-Zа-яА-Я]/u', '', $full_name));
    $base = substr($base, 0, 8);
    $suffix = rand(100, 999);
    return $base . '_' . $suffix;
}

// Функция генерации случайного пароля
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Очищаем старые Cookies об ошибках
foreach (['full_name', 'phone', 'email', 'birth_date', 'gender', 'breeds', 'biography', 'contract_accepted'] as $field) {
    setcookie("error_$field", "", time() - 3600, '/');
}

// Получаем данные из GET-запроса
$full_name = trim($_GET['full_name'] ?? '');
$phone = trim($_GET['phone'] ?? '');
$email = trim($_GET['email'] ?? '');
$birth_date = trim($_GET['birth_date'] ?? '');
$gender = $_GET['gender'] ?? '';
$breeds = $_GET['breeds'] ?? [];
$biography = trim($_GET['biography'] ?? '');
$contract_accepted = isset($_GET['contract_accepted']) && $_GET['contract_accepted'] == '1' ? 1 : 0;
$edit_id = isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

$errors = [];
$formData = [];

// ==================== ВАЛИДАЦИЯ РЕГУЛЯРНЫМИ ВЫРАЖЕНИЯМИ ====================

// 1. ФИО
$formData['full_name'] = $full_name;
if (empty($full_name)) {
    $errors['full_name'] = "ФИО обязательно для заполнения.";
} elseif (strlen($full_name) < 2) {
    $errors['full_name'] = "ФИО должно содержать минимум 2 символа.";
} elseif (strlen($full_name) > 150) {
    $errors['full_name'] = "ФИО не должно превышать 150 символов.";
} elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
    $errors['full_name'] = "ФИО может содержать только буквы (русские/английские), пробелы и дефис.";
}

// 2. Телефон
$formData['phone'] = $phone;
$phone_clean = preg_replace('/[^0-9+]/', '', $phone);
if (empty($phone_clean)) {
    $errors['phone'] = "Телефон обязателен для заполнения.";
} elseif (!preg_match('/^(\+7|8)[0-9]{10}$/', $phone_clean)) {
    $errors['phone'] = "Телефон должен быть в формате +7XXXXXXXXXX или 8XXXXXXXXXX (10 цифр после кода).";
} else {
    if (preg_match('/^8([0-9]{10})$/', $phone_clean, $matches)) {
        $formData['phone'] = '+7' . $matches[1];
    } else {
        $formData['phone'] = $phone_clean;
    }
}

// 3. Email
$formData['email'] = $email;
if (empty($email)) {
    $errors['email'] = "E-mail обязателен для заполнения.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Введите корректный E-mail (например, user@domain.ru).";
} elseif (strlen($email) > 100) {
    $errors['email'] = "E-mail не должен превышать 100 символов.";
}

// 4. Дата рождения
$formData['birth_date'] = $birth_date;
if (empty($birth_date)) {
    $errors['birth_date'] = "Дата рождения обязательна для заполнения.";
} else {
    $date_obj = DateTime::createFromFormat('Y-m-d', $birth_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $birth_date) {
        $errors['birth_date'] = "Дата рождения должна быть в формате ГГГГ-ММ-ДД.";
    } else {
        $today = new DateTime();
        $age = $today->diff($date_obj)->y;
        if ($date_obj > $today) {
            $errors['birth_date'] = "Дата рождения не может быть в будущем.";
        } elseif ($age > 120) {
            $errors['birth_date'] = "Возраст не может превышать 120 лет.";
        }
    }
}

// 5. Пол
$formData['gender'] = $gender;
$allowed_genders = ['male', 'female'];
if (empty($gender)) {
    $errors['gender'] = "Выберите пол питомца.";
} elseif (!in_array($gender, $allowed_genders)) {
    $errors['gender'] = "Выберите 'Мальчик' или 'Девочка'.";
}

// 6. Породы (проверяем, что все ID существуют в БД)
$formData['breeds'] = implode(',', $breeds);
if (empty($breeds)) {
    $errors['breeds'] = "Выберите хотя бы одну породу питомца.";
} else {
    // Проверяем, что все выбранные породы существуют
    $placeholders = implode(',', array_fill(0, count($breeds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM dog_breeds WHERE id IN ($placeholders)");
    $stmt->execute($breeds);
    $existingBreeds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($existingBreeds) !== count($breeds)) {
        $errors['breeds'] = "Выбраны недопустимые породы.";
    }
}

// 7. Биография
$formData['biography'] = $biography;
if (strlen($biography) > 5000) {
    $errors['biography'] = "Биография не должна превышать 5000 символов.";
}

// 8. Чекбокс
$formData['contract_accepted'] = $contract_accepted;
if (!$contract_accepted) {
    $errors['contract_accepted'] = "Вы должны согласиться с условиями записи.";
}

// ==================== ЕСЛИ ЕСТЬ ОШИБКИ ====================
if (!empty($errors)) {
    foreach ($errors as $field => $message) {
        setcookie("error_$field", $message, 0, '/');
    }
    foreach ($formData as $field => $value) {
        setcookie("form_$field", $value, 0, '/');
    }
    $query = http_build_query(array_filter($formData, function($v) { return $v !== '' && $v !== []; }));
    header("Location: index.html?" . $query);
    exit;
}

// ==================== УСПЕШНАЯ ВАЛИДАЦИЯ ====================

try {
    $pdo->beginTransaction();
    
    // Проверяем, есть ли редактирование
    $isEdit = ($edit_id > 0 && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $edit_id);
    
    if ($isEdit) {
        // Обновление существующей записи
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
            ':full_name' => $formData['full_name'],
            ':phone' => $formData['phone'],
            ':email' => $formData['email'],
            ':birth_date' => $formData['birth_date'],
            ':gender' => $formData['gender'],
            ':biography' => $formData['biography'],
            ':contract_accepted' => $formData['contract_accepted'],
            ':id' => $edit_id
        ]);
        $application_id = $edit_id;
        
        // Удаляем старые породы
        $delStmt = $pdo->prepare("DELETE FROM application_breeds WHERE application_id = :id");
        $delStmt->execute([':id' => $edit_id]);
        
        $isNewUser = false;
        $newLogin = '';
        $newPassword = '';
        $credentialsShown = 1;
    } else {
        // Вставка новой записи
        $sql = "INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted) 
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $formData['full_name'],
            ':phone' => $formData['phone'],
            ':email' => $formData['email'],
            ':birth_date' => $formData['birth_date'],
            ':gender' => $formData['gender'],
            ':biography' => $formData['biography'],
            ':contract_accepted' => $formData['contract_accepted']
        ]);
        $application_id = $pdo->lastInsertId();
        
        // Генерируем логин и пароль для нового пользователя
        $newLogin = generateLogin($formData['full_name']);
        $newPassword = generatePassword();
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Сохраняем логин и хеш пароля в БД
        $updateStmt = $pdo->prepare("UPDATE applications SET login = :login, password_hash = :hash, credentials_shown = 0 WHERE id = :id");
        $updateStmt->execute([
            ':login' => $newLogin,
            ':hash' => $passwordHash,
            ':id' => $application_id
        ]);
        
        $isNewUser = true;
        $credentialsShown = 0;
    }
    
    // Сохраняем породы
    $linkStmt = $pdo->prepare("INSERT INTO application_breeds (application_id, breed_id) VALUES (:app_id, :breed_id)");
    foreach ($breeds as $breed_id) {
        $linkStmt->execute([
            ':app_id' => $application_id,
            ':breed_id' => $breed_id
        ]);
    }
    
    $pdo->commit();
    
    // Сохраняем данные в Cookies на 1 год
    foreach ($formData as $field => $value) {
        setcookie("form_$field", $value, time() + 365 * 24 * 3600, '/');
    }
    
    // Если новый пользователь — показываем логин/пароль
    if ($isNewUser) {
        // Автоматически авторизуем пользователя
        $_SESSION['user_id'] = $application_id;
        $_SESSION['user_name'] = $formData['full_name'];
        
        header("Location: index.html?new_login=" . urlencode($newLogin) . 
               "&new_password=" . urlencode($newPassword) . 
               "&shown=" . $credentialsShown);
    } else {
        header("Location: index.html?updated=1");
    }
    exit;
    
} catch (PDOException $e) {
    $pdo->rollBack();
    setcookie("error_general", "Ошибка базы данных: " . $e->getMessage(), 0, '/');
    header("Location: index.html");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    setcookie("error_general", "Ошибка: " . $e->getMessage(), 0, '/');
    header("Location: index.html");
    exit;
}
?>