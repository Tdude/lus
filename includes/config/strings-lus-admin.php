<?php
/**
 * File: config/stings-lus-admin.php
 * Admin UI strings configuration
 *
 * @package    LUS
 * @subpackage LUS/includes/config
 */

return [
    // Common actions
    'save' => __('Spara', 'lus'),
    'edit' => __('Ändra', 'lus'),
    'delete' => __('Radera', 'lus'),
    'cancel' => __('Avbryt', 'lus'),

    // Form states
    'loading' => __('Laddar...', 'lus'),
    'saving' => __('Sparar...', 'lus'),
    'deleting' => __('Raderar...', 'lus'),

    // Messages
    'confirmDelete' => __('Är du säker på att du vill radera detta?', 'lus'),
    'deleteSuccess' => __('Raderat', 'lus'),
    'savingSuccess' => __('Sparat', 'lus'),
    'errorOccurred' => __('Ett fel uppstod', 'lus'),

    // Content type specific strings
    'passages' => [
        'addNew' => __('Lägg till ny text', 'lus'),
        'edit' => __('Ändra text', 'lus'),
        'confirmDelete' => __('Är du säker på att du vill radera denna text?', 'lus'),
    ],
    'questions' => [
        'addNew' => __('Lägg till ny fråga', 'lus'),
        'edit' => __('Ändra fråga', 'lus'),
        'confirmDelete' => __('Är du säker på att du vill radera denna fråga?', 'lus'),
    ],
    'recordings' => [
        'addNew' => __('Lägg till ny inspelning', 'lus'),
        'edit' => __('Ändra inspelning', 'lus'),
        'confirmDelete' => __('Är du säker på att du vill radera denna inspelning?', 'lus'),
    ]
];