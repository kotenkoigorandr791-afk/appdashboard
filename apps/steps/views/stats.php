<?php
require_once __DIR__ . '/../../../project/core.php';
require_once __DIR__ . '/../../../lib/app_db.php';
require_once __DIR__ . '/../models/StepsModel.php';

// Проверка авторизации
if (!isUserLoggedIn()) {
    header('Location: /');
    exit;
}

// Загрузка переводов
$translations = loadTranslations($language, 'steps');

// Загрузка данных пользователя
$userDb = getUserDatabase($currentUser);
$userSettings = getUserSettings($currentUser);

// Получение статистики
$yearlyDistribution = getYearlyStepDistribution($userDb);
$monthlyIntensity = getMonthlyStepIntensityCurrentYear($userDb);

// Если нет данных, редирект на основную страницу
if (empty($yearlyDistribution)) {
    header('Location: /steps');
    exit;
}
?>

<div class="stats-container">
    <div class="stats-header">
        <h2><?php echo htmlspecialchars($translations['statistics_title']); ?></h2>
    </div>
    
    <div class="stats-charts">
        <div class="chart-card">
            <div class="chart-title"><?php echo htmlspecialchars($translations['statistics_yearly_distribution']); ?></div>
            <div class="chart-wrapper">
                <canvas id="yearly-distribution-chart"></canvas>
            </div>
        </div>
        
        <div class="chart-card">
            <div class="chart-title"><?php echo htmlspecialchars($translations['statistics_monthly_intensity']); ?></div>
            <div class="chart-wrapper">
                <canvas id="monthly-intensity-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // Передача данных для графиков
    const statsData = {
        yearlyDistribution: <?php echo json_encode($yearlyDistribution); ?>,
        monthlyIntensity: <?php echo json_encode($monthlyIntensity); ?>,
        translations: <?php echo json_encode($translations); ?>
    };
</script>
    </div>
</div>
