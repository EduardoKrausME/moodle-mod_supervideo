<?php

defined('MOODLE_INTERNAL') || die();

$addons = [
    "mod_supervideo" => [
        'handlers' => [
            'coursesupervideo' => [
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/supervideo/pix/icon.svg',
                    'class' => '',
                ],
            ]
        ]
    ]
];
