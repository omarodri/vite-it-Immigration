<?php

return [
    'defaults' => [
        ['key' => 'admission',     'category' => 'admission',     'i18n_key' => 'folder_templates.admission',     'enabled_by_default' => true],
        ['key' => 'archive',       'category' => 'archive',       'i18n_key' => 'folder_templates.archive',       'enabled_by_default' => true],
        ['key' => 'hearing',       'category' => 'hearing',       'i18n_key' => 'folder_templates.hearing',       'enabled_by_default' => true],
        ['key' => 'letters',       'category' => 'letters',       'i18n_key' => 'folder_templates.letters',       'enabled_by_default' => true],
        ['key' => 'communication', 'category' => 'communication', 'i18n_key' => 'folder_templates.communication', 'enabled_by_default' => true],
        ['key' => 'accounting',    'category' => 'accounting',    'i18n_key' => 'folder_templates.accounting',    'enabled_by_default' => true],
        ['key' => 'contract',      'category' => 'contract',      'i18n_key' => 'folder_templates.contract',      'enabled_by_default' => true],
        ['key' => 'documents',     'category' => 'documents',     'i18n_key' => 'folder_templates.documents',     'enabled_by_default' => true],
        ['key' => 'links',         'category' => 'links',         'i18n_key' => 'folder_templates.links',         'enabled_by_default' => true],
        ['key' => 'evidence',      'category' => 'evidence',      'i18n_key' => 'folder_templates.evidence',      'enabled_by_default' => true],
        ['key' => 'forms',         'category' => 'forms',         'i18n_key' => 'folder_templates.forms',         'enabled_by_default' => true],
        ['key' => 'history',       'category' => 'history',       'i18n_key' => 'folder_templates.history',       'enabled_by_default' => true],
        ['key' => 'other',         'category' => 'other',         'i18n_key' => 'folder_templates.other',         'enabled_by_default' => true],
        ['key' => 'questionary',   'category' => 'questionary',   'i18n_key' => 'folder_templates.questionary',   'enabled_by_default' => true],
    ],

    'validation' => [
        'name_max_length'       => 100,
        'name_min_length'       => 1,
        'forbidden_chars'       => ['<', '>', ':', '"', '/', '\\', '|', '?', '*'],
        'forbidden_chars_regex' => '/[<>:"\/\\\\|?*]/u',
        'leading_dot_regex'     => '/^\.+\s*$/u',
        'reserved_names'        => [
            'CON', 'PRN', 'AUX', 'NUL',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
            'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
        ],
        'max_custom_per_case'   => 10,
        'max_total_per_case'    => 30,
    ],
];
