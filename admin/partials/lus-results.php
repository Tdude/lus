<?php
/**
 * File: admin/partials/lus-results.php
 * Displays assessment results and statistics
 */

if (!defined('WPINC')) {
    die;
}

// Get filter values
$passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) : 0;
$date_range = isset($_GET['date_range']) ? intval($_GET['date_range']) : 30;

// Calculate date range - this is where it's going wrong
$date_limit = '';
if ($date_range > 0) {
    $date_limit = date('Y-m-d', strtotime("-{$date_range} days"));
}




// Get statistics
$stats = new LUS_Statistics($this->db);
$overall_stats = $stats->get_overall_statistics($passage_id, $date_limit);
$passage_stats = $stats->get_passage_statistics($passage_id, $date_limit);
$question_stats = $stats->get_question_statistics($passage_id, $date_limit);


?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Filters -->
    <div class="lus-results-container">
        <div class="lus-results-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="lus-results">
                <select name="passage_id">
                    <option value=""><?php _e('Alla texter', 'lus'); ?></option>
                    <?php foreach ($stats->get_all_passages() as $passage): ?>
                    <option value="<?php echo esc_attr($passage->id); ?>" <?php selected($passage_id, $passage->id); ?>>
                        <?php echo esc_html($passage->title); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="date_range">
                    <option value="7" <?php selected($date_range, 7); ?>>
                        <?php _e('Senaste 7 dagarna', 'lus'); ?>
                    </option>
                    <option value="30" <?php selected($date_range, 30); ?>>
                        <?php _e('Senaste 30 dagarna', 'lus'); ?>
                    </option>
                    <option value="90" <?php selected($date_range, 90); ?>>
                        <?php _e('Senaste 90 dagarna', 'lus'); ?>
                    </option>
                    <option value="all" <?php selected($date_range, 'all'); ?>>
                        <?php _e('Alla tider', 'lus'); ?>
                    </option>
                </select>
                <?php submit_button(__('Filtrera', 'lus'), 'secondary', 'submit', false); ?>
            </form>
        </div>

        <!-- Overview Cards -->
        <div class="lus-stats-overview">
            <div class="lus-stat-card">
                <h3><?php _e('Antal inspelningar', 'lus'); ?></h3>
                <div class="stat-number"><?php echo esc_html($overall_stats['total_recordings']); ?></div>
            </div>
            <div class="lus-stat-card">
                <h3><?php _e('Antal elever', 'lus'); ?></h3>
                <div class="stat-number"><?php echo esc_html($overall_stats['unique_students']); ?></div>
            </div>
            <div class="lus-stat-card">
                <h3><?php _e('Medelresultat', 'lus'); ?></h3>
                <div class="stat-number">
                    <?php echo esc_html(number_format($overall_stats['avg_normalized_score'] ?? 0, 1)); ?>%
                </div>
            </div>
            <div class="lus-stat-card">
                <h3><?php _e('Besvarade frågor', 'lus'); ?></h3>
                <div class="stat-number">
                    <?php echo esc_html($overall_stats['total_questions_answered']); ?>
                </div>
            </div>
        </div>

        <!-- Passage Performance -->
        <div class="lus-stats-passages">
            <h2><?php _e('Resultat per text', 'lus'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Text', 'lus'); ?></th>
                        <th><?php _e('Inspelningar', 'lus'); ?></th>
                        <th><?php _e('Medelresultat', 'lus'); ?></th>
                        <th><?php _e('Korrekta svar', 'lus'); ?></th>
                        <th><?php _e('Medeltid', 'lus'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passage_stats as $passage): ?>
                    <tr>
                        <td><?php echo esc_html($passage->title ?? ''); ?></td>
                        <td><?php echo esc_html($passage->recording_count ?? 0); ?></td>
                        <td><?php echo esc_html(number_format($passage->avg_score ?? 0, 1)); ?>%</td>
                        <td><?php echo esc_html(number_format($passage->correct_answer_rate ?? 0, 1)); ?>%</td>
                        <td><?php echo esc_html(number_format((float)($passage->avg_duration ?? 0), 1)); ?>s</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Question Analysis -->
        <div class="lus-stats-questions">
            <h2><?php _e('Frågeanalys', 'lus'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Fråga', 'lus'); ?></th>
                        <th><?php _e('Text', 'lus'); ?></th>
                        <th><?php _e('Antal svar', 'lus'); ?></th>
                        <th><?php _e('Rätta svar', 'lus'); ?></th>
                        <th><?php _e('Medel likhet', 'lus'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($question_stats as $question): ?>
                    <tr>
                        <td><?php echo esc_html($question['question_text']); ?></td>
                        <td><?php echo esc_html($question['passage_title']); ?></td>
                        <td><?php echo esc_html($question['times_answered']); ?></td>
                        <td><?php echo esc_html(number_format($question['correct_rate'] ?? 0, 1)); ?>%</td>
                        <td><?php echo esc_html(number_format($question['avg_similarity'] ?? 0, 1)); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Charts containers -->
        <div class="lus-charts-section">
            <!-- Overall Distribution Chart -->
            <div class="lus-chart-container">
                <h3><?php _e('Övergripande statistik', 'lus'); ?></h3>
                <canvas id="overall-stats-chart"
                    data-recordings="<?php echo esc_attr($overall_stats['total_recordings']); ?>"
                    data-students="<?php echo esc_attr($overall_stats['unique_students']); ?>"
                    data-assessments="<?php echo esc_attr($overall_stats['total_assessments'] ?? 0); ?>">
                </canvas>
            </div>

            <!-- Score Distribution Chart -->
            <div class="lus-chart-container">
                <h3><?php _e('Resultatfördelning', 'lus'); ?></h3>
                <canvas id="score-distribution-chart" data-passage-id="<?php echo esc_attr($passage_id); ?>"></canvas>
            </div>

            <!-- Time Trend Chart -->
            <div class="lus-chart-container">
                <h3><?php _e('Utveckling över tid', 'lus'); ?></h3>
                <canvas id="trend-stats-chart" data-passage-id="<?php echo esc_attr($passage_id); ?>"
                    data-date-range="<?php echo esc_attr($date_range); ?>">
                </canvas>
            </div>

            <!-- Question Success Rate Chart -->
            <div class="lus-chart-container">
                <h3><?php _e('Frågornas svarsfrekvens', 'lus'); ?></h3>
                <canvas id="questions-success-chart"></canvas>
            </div>

            <!-- Time Per Question Chart -->
            <div class="lus-chart-container">
                <h3><?php _e('Tid per inläsning', 'lus'); ?></h3>
                <canvas id="time-per-recording-chart"></canvas>
            </div>

            <!-- Student Progress Chart -->
            <div class="lus-chart-container">
                <h3><?php _e('Elevutveckling', 'lus'); ?></h3>
                <canvas id="student-progress-chart"></canvas>
            </div>
        </div>
    </div>

    <!-- Export btns -->
    <div class="lus-export-buttons">
        <button id="export-csv" class="button" onclick="LUS.Handlers.Results.handleExport('csv')">
            <?php _e('Exportera CSV', 'lus'); ?>
        </button>
        <button id="export-json" class="button" onclick="LUS.Handlers.Results.handleExport('json')">
            <?php _e('Exportera JSON', 'lus'); ?>
        </button>
        <button id="export-pdf" class="button" onclick="LUS.Handlers.Results.handleExport('pdf')">
            <?php _e('Exportera PDF', 'lus'); ?>
        </button>
    </div>
</div>