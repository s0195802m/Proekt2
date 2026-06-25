<?php
// list.php - Список анкет
session_start();
require_once 'config.php';

// Получаем все анкеты с породами
$stmt = $pdo->prepare("
    SELECT a.*, 
           GROUP_CONCAT(db.name ORDER BY db.name SEPARATOR ', ') as breeds
    FROM applications a
    LEFT JOIN application_breeds ab ON a.id = ab.application_id
    LEFT JOIN dog_breeds db ON ab.breed_id = db.id
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute();
$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список записей — Persik Grooming</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .table-wrapper { overflow-x: auto; background: white; border-radius: 20px; padding: 1rem; box-shadow: 0 8px 20px rgba(216,92,155,0.1); }
        .applications-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .applications-table th { background: linear-gradient(135deg, #f8b0c0, #f48fb1); color: white; padding: 0.75rem; text-align: left; }
        .applications-table td { padding: 0.75rem; border-bottom: 1px solid #ffccd9; }
        .badge { background: #d85a9b; color: white; padding: 0.15rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; margin: 0.1rem; }
        .btn-edit { background: #4caf50; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; text-decoration: none; font-size: 0.75rem; }
        .btn-edit:hover { background: #388e3c; color: white; }
        .action-buttons { margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .action-btn { background: linear-gradient(135deg, #d85a9b, #c14a8f); color: white; padding: 0.75rem 1.5rem; border-radius: 40px; text-decoration: none; font-weight: bold; }
        .action-btn:hover { color: white; transform: translateY(-2px); }
        .action-btn.secondary { background: linear-gradient(135deg, #b0bec5, #90a4ae); }
        .header-title { color: #7a2451; }
        .stats { background: white; padding: 1rem; border-radius: 16px; margin-bottom: 1.5rem; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>🐾 Persik Grooming</h1>
            <p class="subtitle">Список записей</p>
        </div>
    </header>

    <main class="container" style="padding: 2rem 0;">
        <div class="stats">
            📊 Всего записей: <strong><?php echo count($applications); ?></strong>
        </div>
        
        <div class="table-wrapper">
            <table class="applications-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Владелец</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Породы</th>
                        <th>Дата создания</th>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <th>Действия</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?php echo $app['id']; ?></td>
                        <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['phone']); ?></td>
                        <td><?php echo htmlspecialchars($app['email']); ?></td>
                        <td><?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></td>
                        <td><?php echo $app['gender'] == 'male' ? '♂ Мальчик' : '♀ Девочка'; ?></td>
                        <td>
                            <?php 
                            $breeds = explode(', ', $app['breeds'] ?? '');
                            foreach ($breeds as $breed):
                                if (trim($breed)):
                            ?>
                                <span class="badge"><?php echo htmlspecialchars(trim($breed)); ?></span>
                            <?php endif; endforeach; ?>
                            <?php if (empty($app['breeds'])): ?>
                                <span style="color: #9b4b6e;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?></td>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $app['id']): ?>
                            <td>
                                <a href="index.html?edit_id=<?php echo $app['id']; ?>" class="btn-edit">✏️ Редактировать</a>
                            </td>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                            <td><span style="color: #9b4b6e; font-size:0.8rem;">—</span></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="action-buttons">
            <a href="index.html" class="action-btn">📝 Записаться</a>
            <a href="login.php" class="action-btn secondary">🔐 Вход</a>
            <a href="admin.php" class="action-btn secondary">👑 Админ</a>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>© Доценко Маргарита | Persik Grooming</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>