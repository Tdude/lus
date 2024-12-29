<?php
/**
 * File: admin/partials/lus-questions.php
 * Manages questions for reading passages
 */

if (!defined('WPINC')) {
    die;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_question_nonce'])) {
    if (!wp_verify_nonce($_POST['lus_question_nonce'], 'lus_question_action')) {
        wp_die(__('Security check failed', 'lus'));
    }

    $question_data = [
        'passage_id' => intval($_POST['passage_id']),
        'question_text' => sanitize_text_field($_POST['question_text']),
        'correct_answer' => sanitize_text_field($_POST['correct_answer']),
        'weight' => floatval($_POST['weight'])
    ];

    if (isset($_POST['question_id']) && !empty($_POST['question_id'])) {
        $result = $this->db->update_question(intval($_POST['question_id']), $question_data);
    } else {
        $result = $this->db->create_question($question_data);
    }

    if (is_wp_error($result)) {
        $error_message = $result->get_error_message();
    } else {
        $success_message = __('Frågan är sparad.', 'lus');
    }
}

// Get selected passage ID from query string or first passage
$passages = $this->db->get_all_passages();
$selected_passage_id = isset($_GET['passage_id']) ? intval($_GET['passage_id']) :
                      ($passages ? $passages[0]->id : 0);

// Get questions for selected passage
$questions = $selected_passage_id ? $this->db->get_questions_for_passage($selected_passage_id) : [];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (isset($error_message)): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($error_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
    <div class="notice notice-success">
        <p><?php echo esc_html($success_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Passage selection -->
    <div class="lus-passage-selector">
        <form method="get">
            <input type="hidden" name="page" value="lus-questions">
            <select name="passage_id" id="passage_id" onchange="this.form.submit()">
                <?php foreach ($passages as $passage): ?>
                <option value="<?php echo esc_attr($passage->id); ?>"
                    <?php selected($selected_passage_id, $passage->id); ?>>
                    <?php echo esc_html($passage->title); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Questions list -->
    <div class="lus-questions-list">
        <h2><?php _e('Frågor för vald text', 'lus'); ?></h2>

        <?php if ($questions): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Fråga', 'lus'); ?></th>
                    <th><?php _e('Korrekt svar', 'lus'); ?></th>
                    <th><?php _e('Svårighetsgrad', 'lus'); ?></th>
                    <th><?php _e('Statistik', 'lus'); ?></th>
                    <th><?php _e('Aktivitet', 'lus'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                    if (!class_exists('LUS_Statistics')) {
                        include LUS_Constants::PLUGIN_DIR . 'admin/class-lus-statistics.php';
                    }
                ?>
                <?php foreach ($questions as $question):
                        $lus_statistics = new LUS_Statistics($this->db);
                        $stats = $lus_statistics->get_question_statistics($question->id);
                    ?>
                <tr>
                    <td><?php echo esc_html($question->question_text); ?></td>
                    <td><?php echo esc_html($question->correct_answer); ?></td>
                    <td><?php echo esc_html($question->weight); ?></td>
                    <td>
                        <?php if ($stats && $stats->total_responses > 0): ?>
                        <?php printf(
                                        __('Antal svar: %1$d<br>Rätt svar: %2$d%%<br>Snitt likhet: %3$d%%', 'lus'),
                                        $stats->total_responses,
                                        round(($stats->correct_responses / $stats->total_responses) * 100),
                                        round($stats->average_score)
                                    ); ?>
                        <?php else: ?>
                        <?php _e('Inga svar än', 'lus'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="button-group">
                            <button class="button lus-edit-question" data-id="<?php echo esc_attr($question->id); ?>"
                                data-question="<?php echo esc_attr($question->question_text); ?>"
                                data-answer="<?php echo esc_attr($question->correct_answer); ?>"
                                data-weight="<?php echo esc_attr($question->weight); ?>">
                                <?php _e('Ändra', 'lus'); ?>
                            </button>
                            <button class="button lus-delete-question" data-id="<?php echo esc_attr($question->id); ?>">
                                <?php _e('Radera', 'lus'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Hittade inga frågor på denna text.', 'lus'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Add/Edit question form -->
    <div class="lus-question-form-container">
        <h2 id="lus-form-title"><?php _e('Lägg till ny fråga', 'lus'); ?></h2>
        <form id="lus-question-form" method="post">
            <?php wp_nonce_field('lus_question_action', 'lus_question_nonce'); ?>
            <input type="hidden" name="question_id" id="question_id" value="">
            <input type="hidden" name="passage_id" value="<?php echo esc_attr($selected_passage_id); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="question_text"><?php _e('Fråga', 'lus'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="question_text" name="question_text" class="large-text" required>
                        <p class="description">
                            <?php _e('Skriv frågan som eleven ska svara på.', 'lus'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="correct_answer"><?php _e('Korrekt svar', 'lus'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="correct_answer" name="correct_answer" class="large-text" required>
                        <p class="description">
                            <?php _e('Skriv det korrekta svaret som ska jämföras med.', 'lus'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="weight"><?php _e('Svårighetsgrad', 'lus'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="weight" name="weight" value="1" min="1" max="20" step="1" required>
                        <p class="description">
                            <?php _e('Svårighetsgrad 1-20 där 20 är svårast.', 'lus'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php _e('Spara fråga', 'lus'); ?>">
                <button type="button" id="lus-cancel-edit" class="button" style="display:none;">
                    <?php _e('Avbryt', 'lus'); ?>
                </button>
            </p>
        </form>
    </div>
</div>