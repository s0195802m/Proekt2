<?php
// admin.php - Панель администратора
require_once 'config.php';

// Проверка HTTP-авторизации
$adminLogin = authenticateAdmin();

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

// Получаем статистику по породам
$statsStmt = $pdo->prepare("
    SELECT db.name, COUNT(ab.application_id) as count
    FROM dog_breeds db
    LEFT JOIN application_breeds ab ON db.id = ab.breed_id
    GROUP BY db.id
    ORDER BY count DESC
");
$statsStmt->execute();
$breedStats = $statsStmt->fetchAll();

// Общее количество анкет
$totalApplications = count($applications);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — Persik Grooming</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; background: white; padding: 1rem 2rem; border-radius: 20px; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .admin-header .badge-admin { background: #4caf50; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1rem; border-radius: 16px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .stat-card .number { font-size: 2rem; font-weight: bold; color: #d85a9b; }
        .stat-card .label { color: #7a2451; font-size: 0.9rem; }
        .stat-card .lang-bar { margin-top: 0.5rem; height: 6px; background: #ffccd9; border-radius: 3px; overflow: hidden; }
        .stat-card .lang-bar .fill { height: 100%; background: linear-gradient(90deg, #d85a9b, #c14a8f); border-radius: 3px; transition: width 0.5s; }
        .table-wrapper { overflow-x: auto; background: white; border-radius: 20px; padding: 1rem; box-shadow: 0 8px 20px rgba(216,92,155,0.1); }
        .admin-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .admin-table th { background: linear-gradient(135deg, #f8b0c0, #f48fb1); color: white; padding: 0.75rem 1rem; text-align: left; position: sticky; top: 0; }
        .admin-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #ffccd9; vertical-align: middle; }
        .admin-table tr:hover { background: #fff5f7; }
        .badge { background: #d85a9b; color: white; padding: 0.15rem 0.6rem; border-radius: 20px; font-size: 0.7rem; display: inline-block; margin: 0.1rem; }
        .btn-admin-edit { background: #2196f3; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; text-decoration: none; font-size: 0.75rem; display: inline-block; }
        .btn-admin-edit:hover { background: #1976d2; color: white; }
        .btn-admin-delete { background: #f44336; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; text-decoration: none; font-size: 0.75rem; display: inline-block; border: none; cursor: pointer; }
        .btn-admin-delete:hover { background: #c62828; color: white; }
        .btn-back { background: linear-gradient(135deg, #b0bec5, #90a4ae); color: white; padding: 0.75rem 1.5rem; border-radius: 40px; text-decoration: none; font-weight: bold; display: inline-block; }
        .btn-back:hover { color: white; transform: translateY(-2px); }
        .empty-state { text-align: center; padding: 3rem; color: #7a2451; }
        .header-title { color: #7a2451; }
        @media (max-width: 768px) { .admin-header { flex-direction: column; gap: 1rem; text-align: center; } }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>🐾 Persik Grooming</h1>
            <p class="subtitle">Панель администратора</p>
        </div>
    </header>

    <main class="admin-container">
        <div class="admin-header">
            <div>
                <span style="font-size:1.5rem;">👑</span>
                <span><strong>Администратор:</strong> <?php echo htmlspecialchars($adminLogin); ?></span>
                <span class="badge-admin">✅ Авторизован</span>
            </div>
            <div>
                <a href="index.php" class="btn-back" style="margin-right: 0.5rem;">📝 Форма</a>
                <a href="list.php" class="btn-back">📋 Записи</a>
            </div>
        </div>

        <!-- Статистика -->
        <h3 style="color: #7a2451; margin-bottom: 1rem;">📊 Статистика по породам</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $totalApplications; ?></div>
                <div class="label">Всего записей</div>
            </div>
            <?php foreach ($breedStats as $stat): ?>
            <?php if ($stat['count'] > 0): ?>
            <div class="stat-card">
                <div class="number" style="font-size:1.5rem;"><?php echo $stat['count']; ?></div>
                <div class="label"><?php echo htmlspecialchars($stat['name']); ?></div>
                <div class="lang-bar">
                    <div class="fill" style="width: <?php echo $totalApplications > 0 ? ($stat['count'] / $totalApplications * 100) : 0; ?>%;"></div>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Таблица анкет -->
        <h3 style="color: #7a2451; margin-bottom: 1rem;">📋 Все записи</h3>
        <div class="table-wrapper">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <p>😕 Нет ни одной записи в базе данных.</p>
                </div>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Владелец</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Породы</th>
                        <th>Особые пожелания</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
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
                        </td>
                        <td style="max-width:150px; word-break:break-word;">
                            <?php 
                            $bio = htmlspecialchars($app['biography'] ?? '');
                            echo empty($bio) ? '<em style="color:#7a2451;">—</em>' : 
                                (strlen($bio) > 50 ? substr($bio, 0, 50) . '…' : $bio);
                            ?>
                        </td>
                        <td><?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?></td>
                        <td style="white-space:nowrap;">
                            <a href="admin_edit.php?id=<?php echo $app['id']; ?>" class="btn-admin-edit">✏️</a>
                            <a href="admin_delete.php?id=<?php echo $app['id']; ?>" 
                               class="btn-admin-delete" 
                               onclick="return confirm('Удалить запись №<?php echo $app['id']; ?>? Это действие нельзя отменить.');">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>© Доценко Маргарита | Persik Grooming</p>
        </div>
    </footer>
</body>
</html>