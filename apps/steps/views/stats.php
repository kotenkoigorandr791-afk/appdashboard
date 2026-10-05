<?php
require_once __DIR__ . '/../../../project/core.php';
require_once __DIR__ . '/../../../lib/app_db.php';
require_once __DIR__ . '/../models/StepsModel.php';

// Auth check
if (!isUserLoggedIn()) {
    header('Location: /');
    exit;
}

// Load translations
$translations = loadTranslations($language, 'steps');

// Load user data
$userDb = getUserDatabase($currentUser);
$userSettings = getUserSettings($currentUser);

// Get statistics
$yearlyDistribution = getYearlyStepDistribution($userDb);
$monthlyIntensity = getMonthlyStepIntensityCurrentYear($userDb);

// If no data, redirect to main page
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
    // Pass data for charts
    const statsData = {
        yearlyDistribution: <?php echo json_encode($yearlyDistribution, JSON_HEX_TAG); ?>,
        monthlyIntensity: <?php echo json_encode($monthlyIntensity, JSON_HEX_TAG); ?>,
        translations: <?php echo json_encode($translations, JSON_HEX_TAG); ?>
    };
</script>
    </div>
</div>
