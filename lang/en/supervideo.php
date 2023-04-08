<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * lang file
 *
 * @package    mod_supervideo
 * @copyright  2023 Eduardo kraus (http://eduardokraus.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'Super Video';
$string['pluginname'] = 'Super Video';
$string['modulenameplural'] = 'Super Videos';
$string['videourl'] = 'Video URL Super';
$string['videourl_help'] = '<h2>Youtube</h2><p>Add a Youtube URL you want to add to the course:</p><address>Ex: https://www.youtube.com/watch?v=SNhUMChfolc<br />https://www.youtube.com/watch?v=kwjhXQUpyvA</address><h2>Google Drive</h2><p>In Google Drive, click share video and set permissions and paste the link here.</p><h2>Vimeo</h2><p>Add a Vimeo URL you want to add to the course:</p><address>Ex: https://vimeo.com/300138942<br />https://vimeo.com/368389657</address>';
$string['videourl_error'] = 'Super Video URL';
$string['videofile'] = 'Ou selecione um arquivo MP3 ou MP4';
$string['videofile_help'] = 'You can upload an MP3 or MP4 file, host it on MoodleData and show it in the Super Video player';
$string['pluginadministration'] = 'Super Videos';
$string['modulename_help'] = 'This module adds a Super Video within Moodle.';
$string['showmapa'] = 'Show Map';
$string['showmapa_desc'] = 'If checked, show the map after the video player!';
$string['showrel'] = 'Suggested videos';
$string['showrel_desc'] = 'Show suggested videos when the video ends (Youtube only)';
$string['showcontrols'] = 'Controls';
$string['showcontrols_desc'] = 'Show player controls';
$string['showshowinfo'] = 'Show title';
$string['showshowinfo_desc'] = 'Show video title and player actions';
$string['autoplay'] = 'Play automatically';
$string['autoplay_desc'] = 'Automatically play the player load';
$string['video_size'] = 'Video size';

$string['idnotfound'] = 'Unrecognized link like Youtube, Google Drive or Vimeo';
$string['seu_view'] = 'Your View map:';

$string['report_download_title'] = 'Super Video Plugin Video Views';
$string['report'] = 'Views report';
$string['report_userid'] = 'User ID';
$string['report_nome'] = 'Full name';
$string['report_email'] = 'Email';
$string['report_tempo'] = 'Watched time';
$string['report_duracao'] = 'Video duration';
$string['report_porcentagem'] = 'Percentage seen';
$string['report_mapa'] = 'View Map';
$string['report_comecou'] = 'Started watching when';
$string['report_terminou'] = 'Finished watching when';
$string['report_visualizacoes'] = 'Visualizations';
$string['report_assistiu'] = 'Watched when';
$string['report_all'] = 'All views for this student';

$string['complet_percent'] = 'Requires percentage';
$string['complet_percent_help'] = 'Set complete when student views set percentage of video';

$string['no_data'] = 'No records';

$string['supervideo:addinstance'] = 'Create new activities with Super Video';
$string['supervideo:view'] = 'View and interact with Super Video';

$string['privacy:metadata:supervideo_view'] = 'Save View Map sessions';
$string['privacy:metadata:supervideo_view:cm_id'] = 'ID of module being saved';
$string['privacy:metadata:supervideo_view:user_id'] = 'User ID';
$string['privacy:metadata:supervideo_view:currenttime'] = 'Current player time saved for if student views video again';
$string['privacy:metadata:supervideo_view:duration'] = 'Total video duration';
$string['privacy:metadata:supervideo_view:percent'] = 'Percentage of the map watched. From 0 to 100';
$string['privacy:metadata:supervideo_view:map'] = 'Viewmap JSON';
$string['privacy:metadata:supervideo_view:timecreated'] = 'Record creation timestamp';
$string['privacy:metadata:supervideo_view:timemodified'] = 'Timestamp of last record modification';