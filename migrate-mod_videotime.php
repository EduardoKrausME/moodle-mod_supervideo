<?php
/**
 * User: Eduardo Kraus
 * Date: 21/03/2023
 * Time: 14:18
 */


require_once ( '../../config.php' );


$module_videotime = $DB->get_record ( 'modules', [ 'name' => 'videotime' ] );
if(!$module_videotime){
    die("Você não tem o MOD_VIDEOTIME instalado");
}
$module_supervideo   = $DB->get_record ( 'modules', [ 'name' => 'supervideo' ] );

$videotimes = $DB->get_records ( "videotime" );

foreach ( $videotimes as $videotime ) {

    $supervideo = (object)[
        'course'       => $videotime->course,
        'name'         => $videotime->name,
        'intro'        => $videotime->intro,
        'introformat'  => $videotime->introformat,
        'videourl'     => $videotime->vimeo_url,
        'videosize'    => 1,
        'showrel'      => 0,
        'showcontrols' => 0,
        'showshowinfo' => 0,
        'autoplay'     => 0,
        'timemodified' => $videotime->timemodified,
    ];

    $supervideo->id = $DB->insert_record ( "supervideo", $supervideo );


    $course_modules = $DB->get_record ( "course_modules",
        [
            'module'   => $module_videotime->id,
            'instance' => $videotime->id
        ] );

    if ( $course_modules ) {
        $course_modules->module   = $module_supervideo->id;
        $course_modules->instance = $supervideo->id;

        echo '<pre>';
        print_r ( $course_modules );
        echo '</pre>';

        $DB->update_record ( 'course_modules', $course_modules );
    }
}