<?php
/**
 * File: admin/partials/lus-passages.php
 * Manages reading passages/texts in the admin area
 */

if (!defined('WPINC')) {
    die;
}

global $wpdb;

// Get database instance
$lus_db = $this->db;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lus_passage_nonce'])) {
    if (!wp_verify_nonce($_POST['lus_passage_nonce'], 'lus_passage_action')) {
        wp_die(__('Security check failed', 'lus'));
    }

    $passage_data = [
        'title' => sanitize_text_field($_POST['title']),
        'content' => wp_kses_post($_POST['content']),
        'time_limit' => intval($_POST['time_limit']),
        'difficulty_level' => intval($_POST['difficulty_level'])
    ];

    // Handle file upload
    if (!empty($_FILES['audio_file']['name'])) {
        // Create directory if it doesn't exist
        if (!file_exists( LUS_Constants::UPLOAD_DIR )) {
            if (!wp_mkdir_p( LUS_Constants::UPLOAD_DIR )) {
                $error_message = __('Failed to create upload directory', 'lus');
            }
        }

        if (!isset($error_message)) {
            $file_name = sanitize_file_name($_FILES['audio_file']['name']);
            $file_path = LUS_Constants::UPLOAD_DIR . '/' . $file_name;

            if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $file_path)) {
                $passage_data['audio_file'] = $file_name;
            } else {
                $error_message = __('Failed to upload audio file', 'lus');
            }
        }
    }

    if (!isset($error_message)) {
        if (isset($_POST['passage_id']) && !empty($_POST['passage_id'])) {
            $result = $lus_db->update_passage(intval($_POST['passage_id']), $passage_data);
        } else {
            $result = $lus_db->create_passage($passage_data);
        }

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } else {
            $success_message = __('Text saved successfully.', 'lus');
        }
    }
}
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

    <!-- List of existing passages -->
    <div class="lus-passages-list">
        <h2><?php _e('Sparade texter', 'lus'); ?></h2>
        <?php
        $passages = $lus_db->get_all_passages();
        ?>

        <?php if ($passages): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Titel', 'lus'); ?></th>
                    <th><?php _e('Antal inspelningar', 'lus'); ?></th>
                    <th><?php _e('Tidsgräns', 'lus'); ?></th>
                    <th><?php _e('Svårighetsgrad', 'lus'); ?></th>
                    <th><?php _e('Inläsningar', 'lus'); ?></th>
                    <th><?php _e('Skapad', 'lus'); ?></th>
                    <th><?php _e('Aktivitet', 'lus'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $stats = new LUS_Statistics($this->db); ?>
                <?php foreach ($passages as $passage):
                    $recording_count = $stats->get_passage_recording_count($passage->id);
                    $passage_stats = $stats->get_passage_statistics($passage->id);
                    ?>
                <tr>
                    <td>
                        <strong>
                            <a href="#" class="lus-edit-passage" data-id="<?php echo esc_attr($passage->id); ?>">
                                <?php echo esc_html($passage->title); ?>
                            </a>
                        </strong>
                    </td>
                    <td>
                        <?php if ($recording_count > 0): ?>
                        <a href="<?php echo esc_url(add_query_arg([
                                        'page' => 'lus',
                                        'passage_filter' => $passage->id
                                    ], admin_url('admin.php'))); ?>" class="recording-count-link">
                            <?php printf(
                                            _n('%d inspelning', '%d inspelningar', $recording_count, 'lus'),
                                            $recording_count
                                        ); ?>
                        </a>
                        <?php else: ?>
                        <span class="no-recordings">
                            <?php _e('Inga inspelningar', 'lus'); ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($passage->time_limit); ?>
                        <?php _e('sekunder', 'lus'); ?>
                    </td>
                    <td><?php echo esc_html($passage->difficulty_level); ?></td>
                    <td>
                        <?php if ($passage_stats && $passage_stats->total_attempts > 0): ?>
                        <?php printf(
                            __('Antal försök: %1$d<br>Medelresultat: %2$.1f', 'lus'),
                            $passage_stats->total_attempts,
                            $passage_stats->average_score
                        ); ?>
                        <?php else: ?>
                        <?php _e('Inga försök här än', 'lus'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html(
                                    date_i18n(get_option('date_format'), strtotime($passage->created_at))
                                ); ?>
                    </td>
                    <td>
                        <div class="button-group">
                            <button class="button lus-edit-passage" data-id="<?php echo esc_attr($passage->id); ?>"
                                data-title="<?php echo esc_attr($passage->title); ?>"
                                data-content="<?php echo esc_attr($passage->content); ?>"
                                data-time-limit="<?php echo esc_attr($passage->time_limit); ?>"
                                data-difficulty-level="<?php echo esc_attr($passage->difficulty_level); ?>">
                                <?php _e('Ändra', 'lus'); ?>
                            </button>
                            <button class="button lus-delete-passage" data-id="<?php echo esc_attr($passage->id); ?>"
                                data-title="<?php echo esc_attr($passage->title); ?>">
                                <?php _e('Radera', 'lus'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php _e('Inga texter hittade.', 'lus'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Add/Edit passage form -->
    <div class="lus-passage-form-container">
        <h2 id="lus-form-title"><?php _e('Lägg till ny text', 'lus'); ?></h2>
        <form id="lus-passage-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('lus_passage_action', 'lus_passage_nonce'); ?>
            <input type="hidden" name="passage_id" id="passage_id" value="">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="title"><?php _e('Titel', 'lus'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="title" name="title" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="content"><?php _e('Textinnehåll', 'lus'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor('', 'content', [
                            'media_buttons' => false,
                            'textarea_rows' => 10,
                            'teeny' => true,
                            'tinymce' => [
                                'forced_root_block' => 'p',
                                'remove_linebreaks' => false,
                                'convert_newlines_to_brs' => true,
                                'remove_redundant_brs' => false
                            ]
                        ]);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="time_limit"><?php _e('Time Limit (seconds)', 'lus'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="time_limit" name="time_limit" value="180" min="30" step="1">
                        <p class="description">
                            <?php _e('Tidsgräns för inspelning i sekunder.', 'lus'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="difficulty_level">
                            <?php _e('Svårighetsgrad', 'lus'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" id="difficulty_level" name="difficulty_level" value="1" min="1" max="20"
                            step="1">
                        <p class="description">
                            <?php _e('Svårighetsgrad 1-20 där 20 är svårast.', 'lus'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary"
                    value="<?php _e('Spara text', 'lus'); ?>">
                <button type="button" id="lus-cancel-edit" class="button" style="display:none;">
                    <?php _e('Avbryt', 'lus'); ?>
                </button>
            </p>
        </form>
    </div>
</div>