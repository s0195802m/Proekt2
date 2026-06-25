<?php
// index.html - теперь PHP для работы с БД
session_start();
require_once 'config.php';

// Получаем список пород
$breeds = getBreeds($pdo);

// Функции для Cookies
function getValue($name, $default = '') {
    if (isset($_GET[$name]) && $_GET[$name] !== '') {
        return htmlspecialchars($_GET[$name]);
    }
    if (isset($_COOKIE['form_' . $name])) {
        return htmlspecialchars($_COOKIE['form_' . $name]);
    }
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && isset($user[$name])) {
            return htmlspecialchars($user[$name]);
        }
    }
    return $default;
}

function getError($name) {
    return $_COOKIE['error_' . $name] ?? '';
}

function hasError($name) {
    return isset($_COOKIE['error_' . $name]);
}

// Получаем породы пользователя
$userBreeds = [];
if (isset($_SESSION['user_id'])) {
    $userBreedsData = getBreedsForApplication($pdo, $_SESSION['user_id']);
    foreach ($userBreedsData as $breed) {
        $userBreeds[] = $breed['id'];
    }
}

// Значения полей
$full_name = getValue('full_name');
$phone = getValue('phone');
$email = getValue('email');
$birth_date = getValue('birth_date');
$gender = getValue('gender');
$biography = getValue('biography');

// Породы из Cookies или GET
$selectedBreeds = [];
if (isset($_COOKIE['form_breeds']) && $_COOKIE['form_breeds'] !== '') {
    $selectedBreeds = explode(',', $_COOKIE['form_breeds']);
} elseif (!empty($userBreeds)) {
    $selectedBreeds = $userBreeds;
}
if (isset($_GET['breeds']) && is_array($_GET['breeds'])) {
    $selectedBreeds = array_map('intval', $_GET['breeds']);
}

$contract_accepted = (isset($_COOKIE['form_contract_accepted']) && $_COOKIE['form_contract_accepted'] == '1') ||
                     (isset($_GET['contract_accepted']) && $_GET['contract_accepted'] == '1');
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Persik Grooming</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Стили для формы (те же, что были) */
        .form-box { background: white; padding: 2rem; border-radius: 24px; box-shadow: 0 8px 20px rgba(216, 92, 155, 0.15); max-width: 700px; margin: 0 auto; }
        .form-box .form-group { margin-bottom: 1.2rem; }
        .form-box label { font-weight: 600; color: #7a2451; display: block; margin-bottom: 0.3rem; }
        .form-box .required { color: #d82c68; }
        .form-box input, .form-box select, .form-box textarea { width: 100%; padding: 0.75rem; border: 2px solid #f0d0e0; border-radius: 12px; transition: all 0.3s; }
        .form-box input:focus, .form-box select:focus, .form-box textarea:focus { outline: none; border-color: #d85a9b; box-shadow: 0 0 0 3px rgba(216, 92, 155, 0.2); }
        .form-box .radio-group { display: flex; gap: 1.5rem; flex-wrap: wrap; padding: 0.5rem 0; }
        .form-box .radio-group label { font-weight: normal; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; }
        .form-box .checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: normal !important; }
        .form-box .checkbox-label input { width: 1.2rem; height: 1.2rem; }
        .form-box select[multiple] { min-height: 150px; }
        .form-box .error-border { border: 2px solid #f44336 !important; background: #ffebee !important; }
        .form-box .field-error { color: #f44336; font-size: 0.8rem; display: block; margin-top: 0.25rem; }
        .form-box .error-summary { background: #ffebee; border-left: 5px solid #f44336; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; }
        .form-box .success-message { background: #e8f5e9; border-left: 5px solid #4caf50; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; color: #2e7d32; }
        .form-box .submit-btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #d85a9b, #c14a8f); color: white; border: none; border-radius: 40px; font-size: 1.1rem; font-weight: bold; transition: all 0.3s; }
        .form-box .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(216, 92, 155, 0.4); }
        .form-box .user-info { background: #e8f5e9; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .form-box .user-info .logout-btn { background: #f44336; color: white; padding: 0.4rem 1rem; border-radius: 20px; text-decoration: none; font-size: 0.9rem; }
        .form-box .user-info .logout-btn:hover { background: #c62828; }
        .form-box .credentials-box { background: #fff3e0; border: 2px dashed #ff9800; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; text-align: center; }
        .form-box .credentials-box .cred { font-weight: bold; color: #e65100; font-size: 1.1rem; }
        .form-box small { display: block; margin-top: 0.25rem; color: #9b4b6e; font-size: 0.75rem; }
        .form-box .action-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem; }
        .form-box .action-btn { background: linear-gradient(135deg, #d85a9b, #c14a8f); color: white; padding: 0.6rem 1.2rem; border-radius: 40px; text-decoration: none; font-weight: bold; transition: transform 0.2s; display: inline-block; }
        .form-box .action-btn:hover { transform: translateY(-2px); }
        .form-box .action-btn.secondary { background: linear-gradient(135deg, #b0bec5, #90a4ae); }
        .form-box .action-btn.admin { background: linear-gradient(135deg, #ff9800, #f57c00); }
        .hidden { display: none !important; }
        .section-title { color: #7a2451; font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 2rem; }
    </style>
</head>

<body>

    <!-- HEADER + NAVIGATION -->
    <header>
        <nav class="navbar navbar-expand-md navbar-dark bg-transparent">
            <div class="container d-flex justify-content-between align-items-center">
                <a href="#hero-video-block" class="logo-link">Persik</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="menu">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <li class="nav-item"><a class="nav-link" href="#hero-video-block">Главная</a></li>
                        <li class="nav-item"><a class="nav-link" href="#gallery">Галерея</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="aboutDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">О нас</a>
                            <ul class="dropdown-menu" aria-labelledby="aboutDropdown">
                                <li><a class="dropdown-item" href="#history">История открытия</a></li>
                                <li><a class="dropdown-item" href="#calculator">Наши услуги</a></li>
                                <li><a class="dropdown-item" href="#reviews">Отзывы</a></li>
                                <li><a class="dropdown-item" href="#application-form">Запись на груминг</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Связь с нами</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin.php">👑 Админ</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- HERO VIDEO -->
        <section id="hero-video-block" class="hero-video-block">
            <video autoplay muted loop playsinline class="bg-video">
                <source src="video.mp4" type="video/mp4">
            </video>
            <div class="overlay"></div>
            <div class="hero-content">
                <h2 class="fw-bold mb-3">Добро пожаловать в Persik Grooming</h2>
                <a href="#gallery" class="hero-btn">Узнать больше</a>
            </div>
        </section>
    </header>

    <!-- GALLERY -->
    <section id="gallery" class="gallery-section">
        <div class="container">
            <h2 class="h4 mb-4 text-center">Галерея наших любимых</h2>
            <div id="carouselExampleIndicators" class="carousel slide shadow rounded mx-auto" data-bs-ride="carousel"
                style="max-width:800px;">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0"
                        class="active"></button>
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="photo1.jpg" class="d-block w-100 gallery-img" alt="Фото 1">
                    </div>
                    <div class="carousel-item">
                        <img src="photo2.jpg" class="d-block w-100 gallery-img" alt="Фото 2">
                    </div>
                    <div class="carousel-item">
                        <img src="photo3.jpg" class="d-block w-100 gallery-img" alt="Фото 3">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators"
                    data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators"
                    data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
        </div>
    </section>

    <!-- HISTORY -->
    <section id="history" class="story-section">
        <div class="container story-box">
            <h2 class="section-title">Наша история</h2>
            <p class="story-text">Груминг-салон <span class="accent">Persik</span> появился из большой любви к животным
                и желания дарить им заботу, комфорт и красоту.</p>
            <p class="story-text">Мы верим, что груминг — это не просто стрижка, а особый ритуал доверия, где каждый
                питомец чувствует себя спокойно и в безопасности.</p>
            <p class="story-text">С первого дня нашей работы мы ставим на первое место здоровье, аккуратность и
                индивидуальный подход к каждому пушистику.</p>
        </div>
    </section>

    <!-- CALCULATOR -->
    <section id="calculator" class="calculator-section">
        <div class="container">
            <h2 class="calculator-title text-center mb-4">Калькулятор стоимости груминг-услуг</h2>
            <div class="calculator-box card p-4">
                <div class="mb-3">
                    <label for="quantity" class="form-label">Количество собачек:</label>
                    <input type="number" id="quantity" class="form-control" min="1" value="1">
                </div>
                <fieldset class="mb-3">
                    <legend class="fs-6">Выберите услугу:</legend>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="service" id="service1" value="type1" checked>
                        <label class="form-check-label" for="service1">Груминг шпица (1000 ₽)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="service" id="service2" value="type2">
                        <label class="form-check-label" for="service2">Стрижка когтей (1600 ₽)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="service" id="service3" value="type3">
                        <label class="form-check-label" for="service3">Мытьё и сушка (2000 ₽)</label>
                    </div>
                </fieldset>
                <div id="optionsBox" class="mb-3 hidden">
                    <label for="optionSelect" class="form-label">Дополнительные опции:</label>
                    <select id="optionSelect" class="form-select">
                        <option value="100">Успокаивающий лосьон (+100 ₽)</option>
                        <option value="200">Гигиеническая обработка (+200 ₽)</option>
                        <option value="300">Антисептический спрей (+300 ₽)</option>
                    </select>
                </div>
                <div id="propertyBox" class="form-check mb-3 hidden">
                    <input class="form-check-input" type="checkbox" id="propertyCheck" value="300">
                    <label class="form-check-label" for="propertyCheck">Дополнительная обработка шерсти (+300 ₽)</label>
                </div>
                <h3 id="result" class="calculator-result">Стоимость: 0 ₽</h3>
            </div>
        </div>
    </section>

    <!-- APPLICATION FORM -->
    <section id="application-form" class="calculator-section" style="background-color: #fff0f6;">
        <div class="container">
            <h2 class="section-title">🐾 Запись на груминг</h2>
            <p class="text-center mb-4" style="color: #7a2451;">Заполните анкету для записи вашего питомца</p>

            <div class="form-box">
                <!-- Информация о пользователе -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    <span>👤 Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
                    <a href="logout.php" class="logout-btn">🚪 Выйти</a>
                </div>
                <?php endif; ?>

                <!-- Показываем логин/пароль при первой отправке -->
                <?php if (isset($_GET['new_login']) && isset($_GET['new_password']) && isset($_GET['shown']) && $_GET['shown'] == '0'): ?>
                <div class="credentials-box">
                    <p><strong>✅ Ваши данные для входа!</strong></p>
                    <p class="cred">🔑 Логин: <?php echo htmlspecialchars($_GET['new_login']); ?></p>
                    <p class="cred">🔒 Пароль: <?php echo htmlspecialchars($_GET['new_password']); ?></p>
                    <p style="font-size:0.8rem; color:#666;">* Сохраните пароль — он показывается один раз</p>
                </div>
                <?php endif; ?>

                <!-- Ошибки -->
                <?php
                $errors = [];
                foreach (['full_name','phone','email','birth_date','gender','breeds','contract_accepted'] as $f) {
                    $e = getError($f);
                    if ($e) $errors[] = $e;
                }
                if ($errors): ?>
                <div class="error-summary">
                    <strong>❌ Ошибки:</strong>
                    <ul style="margin:0.5rem 0 0 1.5rem;">
                        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Сообщение об успехе -->
                <?php if (isset($_GET['updated'])): ?>
                <div class="success-message">✅ Данные успешно обновлены!</div>
                <?php endif; ?>

                <form action="process.php" method="GET">
                    <input type="hidden" name="edit_id" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

                    <div class="form-group">
                        <label>ФИО владельца <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?php echo $full_name; ?>"
                               class="<?php echo hasError('full_name') ? 'error-border' : ''; ?>"
                               placeholder="Иванов Иван Иванович">
                        <?php if (hasError('full_name')): ?>
                            <span class="field-error">⚠️ <?php echo getError('full_name'); ?></span>
                        <?php endif; ?>
                        <small>Только буквы, пробелы и дефис</small>
                    </div>

                    <div class="form-group">
                        <label>Телефон <span class="required">*</span></label>
                        <input type="text" name="phone" value="<?php echo $phone; ?>"
                               class="<?php echo hasError('phone') ? 'error-border' : ''; ?>"
                               placeholder="+7 (123) 456-78-90">
                        <?php if (hasError('phone')): ?>
                            <span class="field-error">⚠️ <?php echo getError('phone'); ?></span>
                        <?php endif; ?>
                        <small>Формат: +7XXXXXXXXXX или 8XXXXXXXXXX</small>
                    </div>

                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo $email; ?>"
                               class="<?php echo hasError('email') ? 'error-border' : ''; ?>"
                               placeholder="example@domain.ru">
                        <?php if (hasError('email')): ?>
                            <span class="field-error">⚠️ <?php echo getError('email'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Дата рождения питомца <span class="required">*</span></label>
                        <input type="date" name="birth_date" value="<?php echo $birth_date; ?>"
                               class="<?php echo hasError('birth_date') ? 'error-border' : ''; ?>">
                        <?php if (hasError('birth_date')): ?>
                            <span class="field-error">⚠️ <?php echo getError('birth_date'); ?></span>
                        <?php endif; ?>
                        <small>Формат: ГГГГ-ММ-ДД</small>
                    </div>

                    <div class="form-group">
                        <label>Пол питомца <span class="required">*</span></label>
                        <div class="radio-group">
                            <label><input type="radio" name="gender" value="male" <?php echo $gender == 'male' ? 'checked' : ''; ?>> Мальчик</label>
                            <label><input type="radio" name="gender" value="female" <?php echo $gender == 'female' ? 'checked' : ''; ?>> Девочка</label>
                        </div>
                        <?php if (hasError('gender')): ?>
                            <span class="field-error">⚠️ <?php echo getError('gender'); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Порода питомца <span class="required">*</span></label>
                        <select name="breeds[]" multiple size="6" class="<?php echo hasError('breeds') ? 'error-border' : ''; ?>">
                            <?php foreach ($breeds as $breed): ?>
                                <option value="<?php echo $breed['id']; ?>" 
                                    <?php echo in_array($breed['id'], $selectedBreeds) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($breed['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (hasError('breeds')): ?>
                            <span class="field-error">⚠️ <?php echo getError('breeds'); ?></span>
                        <?php endif; ?>
                        <small>Зажмите Ctrl для выбора нескольких пород</small>
                    </div>

                    <div class="form-group">
                        <label>Особые пожелания</label>
                        <textarea name="biography" rows="4" placeholder="Опишите особенности вашего питомца..."><?php echo $biography; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="contract_accepted" value="1" <?php echo $contract_accepted ? 'checked' : ''; ?>>
                            Я согласен(на) с условиями записи <span class="required">*</span>
                        </label>
                        <?php if (hasError('contract_accepted')): ?>
                            <span class="field-error">⚠️ <?php echo getError('contract_accepted'); ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="submit-btn">✅ Записаться</button>
                </form>

                <div class="action-buttons">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="action-btn">🔐 Войти</a>
                    <?php endif; ?>
                    <a href="list.php" class="action-btn">📋 Мои записи</a>
                    <a href="admin.php" class="action-btn admin">👑 Админ-панель</a>
                </div>
            </div>
        </div>
    </section>

    <!-- REVIEWS -->
    <section id="reviews" class="reviews-section">
        <div class="container">
            <h2 class="section-title text-center mb-4">Отзывы наших клиентов</h2>
            <div class="reviews-grid">
                <div class="review-card">
                    <p class="review-text">«Очень аккуратный и внимательный подход! Наш шпиц вышел счастливый и красивый 💕»</p>
                    <span class="review-author">— Анна</span>
                </div>
                <div class="review-card">
                    <p class="review-text">«Лучший груминг, где мы были. Спокойная атмосфера и профессионализм!»</p>
                    <span class="review-author">— Мария</span>
                </div>
                <div class="review-card">
                    <p class="review-text">«Спасибо Persik за заботу и любовь к животным 🐶»</p>
                    <span class="review-author">— Екатерина</span>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT FORM -->
    <section id="contact" class="contact-section">
        <div class="container">
            <h2 class="h4 mb-4 text-center">Связь с нами</h2>
            <div class="card shadow p-4 mx-auto" style="max-width: 500px; background-color: #ffe4f0;">
                <form id="contactForm" action="https://formcarry.com/s/0BCnKmvLBYe" method="POST">
                    <div class="form-group">
                        <label for="name" class="required">Имя</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email" class="required">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="message" class="required">Сообщение</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Отправить</button>
                    <div class="message" id="formMessage" style="display:none;"></div>
                </form>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <p class="m-0">© Доценко Маргарита | Persik Grooming</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js" defer></script>
</body>

</html>