<?php
/**
 * Generic admin template for managing items
 * The refactored code includes:
A base handler class for common CRUD operations
Specific handlers (Questions, Passages) inheriting from base handler
Generic PHP template class for admin pages
Configurable fields and validation
 */

if (!defined('WPINC')) {
    die;
}

class LUS_Admin_Template {
    protected $db;
    protected $item_type;
    protected $fields;
    protected $table_columns;

    public function __construct($db, $config) {
        $this->db = $db;
        $this->item_type = $config['item_type'];
        $this->fields = $config['fields'];
        $this->table_columns = $config['table_columns'];
    }

    public function render() {
        $this->handle_form_submission();
        $this->render_page();
    }

    protected function handle_form_submission() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["lus_{$this->item_type}_nonce"])) {
            if (!wp_verify_nonce($_POST["lus_{$this->item_type}_nonce"], "lus_{$this->item_type}_action")) {
                wp_die(__('Security check failed', 'lus'));
            }

            $item_data = [];
            foreach ($this->fields as $field => $config) {
                $item_data[$field] = $this->sanitize_field($_POST[$field], $config['type']);
            }

            if (isset($_POST["{$this->item_type}_id"]) && !empty($_POST["{$this->item_type}_id"])) {
                $result = $this->db->{"update_{$this->item_type}"}(intval($_POST["{$this->item_type}_id"]), $item_data);
            } else {
                $result = $this->db->{"create_{$this->item_type}"}($item_data);
            }

            if (is_wp_error($result)) {
                $this->error_message = $result->get_error_message();
            } else {
                $this->success_message = __('Item saved successfully.', 'lus');
            }
        }
    }

    protected function sanitize_field($value, $type) {
        switch ($type) {
            case 'text':
                return sanitize_text_field($value);
            case 'html':
                return wp_kses_post($value);
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            default:
                return sanitize_text_field($value);
        }
    }

    protected function render_page() {
        $items = $this->db->{"get_all_{$this->item_type}s"}();
        ?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php $this->render_messages(); ?>
    <?php $this->render_list($items); ?>
    <?php $this->render_form(); ?>
</div>
<?php
    }

    protected function render_messages() {
        if (isset($this->error_message)): ?>
<div class="notice notice-error">
    <p><?php echo esc_html($this->error_message); ?></p>
</div>
<?php endif;

        if (isset($this->success_message)): ?>
<div class="notice notice-success">
    <p><?php echo esc_html($this->success_message); ?></p>
</div>
<?php endif;
    }

    protected function render_list($items) {
        ?>
<div class="lus-<?php echo esc_attr($this->item_type); ?>s-list">
    <h2><?php echo esc_html($this->get_list_title()); ?></h2>

    <?php if ($items): ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <?php foreach ($this->table_columns as $key => $label): ?>
                <th><?php echo esc_html($label); ?></th>
                <?php endforeach; ?>
                <th><?php _e('Actions', 'lus'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <?php foreach ($this->table_columns as $key => $label): ?>
                <td><?php echo esc_html($item->$key); ?></td>
                <?php endforeach; ?>
                <td>
                    <div class="button-group">
                        <button class="button lus-edit-item" data-id="<?php echo esc_attr($item->id); ?>"
                            <?php foreach ($this->fields as $field => $config): ?>
                            data-<?php echo esc_attr($field); ?>="<?php echo esc_attr($item->$field); ?>"
                            <?php endforeach; ?>>
                            <?php _e('Edit', 'lus'); ?>
                        </button>
                        <button class="button lus-delete-item" data-id="<?php echo esc_attr($item->id); ?>">
                            <?php _e('Delete', 'lus'); ?>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p><?php echo esc_html($this->get_empty_message()); ?></p>
    <?php endif; ?>
</div>
<?php
    }

    protected function render_form() {
        ?>
<div class="lus-form-container">
    <h2 id="lus-form-title"><?php echo esc_html($this->get_form_title()); ?></h2>
    <form id="lus-<?php echo esc_attr($this->item_type); ?>-form" class="lus-form" method="post">
        <?php wp_nonce_field("lus_{$this->item_type}_action", "lus_{$this->item_type}_nonce"); ?>
        <input type="hidden" name="<?php echo esc_attr($this->item_type); ?>_id"
            id="<?php echo esc_attr($this->item_type); ?>_id" value="">

        <table class="form-table">
            <?php foreach ($this->fields as $field => $config): ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($field); ?>">
                        <?php echo esc_html($config['label']); ?>
                    </label>
                </th>
                <td>
                    <?php $this->render_field($field, $config); ?>
                    <?php if (isset($config['description'])): ?>
                    <p class="description">
                        <?php echo esc_html($config['description']); ?>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary"
                value="<?php echo esc_attr($this->get_submit_text()); ?>">
            <button type="button" class="button lus-cancel-edit" style="display:none;">
                <?php _e('Cancel', 'lus'); ?>
            </button>
        </p>
    </form>
</div>
<?php
    }

    protected function render_field($field, $config) {
        switch ($config['input_type']) {
            case 'text':
                ?>
<input type="text" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" class="regular-text"
    <?php echo isset($config['required']) && $config['required'] ? 'required' : ''; ?>>
<?php
                break;

            case 'number':
                ?>
<input type="number" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>"
    value="<?php echo esc_attr($config['default'] ?? ''); ?>" min="<?php echo esc_attr($config['min'] ?? ''); ?>"
    max="<?php echo esc_attr($config['max'] ?? ''); ?>" step="<?php echo esc_attr($config['step'] ?? '1'); ?>"
    <?php echo isset($config['required']) && $config['required'] ? 'required' : ''; ?>>
<?php
                break;

            case 'textarea':
                if (isset($config['wysiwyg']) && $config['wysiwyg']) {
                    wp_editor('', $field, [
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
                } else {
                    ?>
<textarea id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" class="large-text"
    rows="<?php echo esc_attr($config['rows'] ?? '4'); ?>"
    <?php echo isset($config['required']) && $config['required'] ? 'required' : ''; ?>></textarea>
<?php
                }
                break;

            case 'select':
                ?>
<select id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>"
    <?php echo isset($config['required']) && $config['required'] ? 'required' : ''; ?>>
    <?php foreach ($config['options'] as $value => $label): ?>
    <option value="<?php echo esc_attr($value); ?>">
        <?php echo esc_html($label); ?>
    </option>
    <?php endforeach; ?>
</select>
<?php
                break;

            case 'file':
                ?>
<input type="file" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>"
    accept="<?php echo esc_attr($config['accept'] ?? ''); ?>"
    <?php echo isset($config['required']) && $config['required'] ? 'required' : ''; ?>>
<?php
                break;
        }
    }

    protected function get_list_title() {
        return sprintf(__('List of %s', 'lus'), $this->item_type);
    }

    protected function get_empty_message() {
        return sprintf(__('No %s found.', 'lus'), $this->item_type);
    }

    protected function get_form_title() {
        return sprintf(__('Add New %s', 'lus'), $this->item_type);
    }

    protected function get_submit_text() {
        return sprintf(__('Save %s', 'lus'), $this->item_type);
    }
}