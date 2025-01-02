<?php
/**
 * @var
 * This is a generic admin how-to
 * To implement:
 * Use template for each admin page:
 */
$config = [
    'item_type' => 'question',
    'fields' => [
        'question_text' => [
            'type' => 'text',
            'input_type' => 'text',
            'label' => 'Question',
            'required' => true
        ],
        // ... other fields
    ],
    'table_columns' => [
        'question_text' => 'Question',
        // ... other columns
    ]
];

$template = new LUS_Admin_Template($db, $config);
$template->render();