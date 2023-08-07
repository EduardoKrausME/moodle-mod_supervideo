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

$string['dnduploadlabel-mp3'] = 'Add Audio with Super Video';
$string['dnduploadlabel-mp4'] = 'Add Video with Super Video';
$string['dnduploadlabeltext'] = 'Add video with Super Video';

$string['videourl'] = 'Youtube, Vimeo, Google Drive, external URL or MP4/MP3 file.';
$string['videourl_help'] = '<h4>Youtube</h4>
<div>Add a Youtube URL that you used. want to add to the course:</div>
<div><strong>Ex:</strong> https://www.youtube.com/watch?v=SNhUMChfolc</div>
<div><strong>Ex:</strong> https://youtu.be/kwjhXQUpyvA</div>
<h4>Google Drive</h4>
<div>In Google Drive, click share video and set permissions and paste the link here.</div>
<h4>Vimeo</h4>
<div>Add a Vimeo URL that you used. want to add to the course:</div>
<div><strong>Ex:</strong> https://vimeo.com/300138942</div>
<h4>External video or audio</h4>
<div>Add a URL of a video you have hosted on your own server:</div>
<div><strong>Ex:</strong> https://host.com.br/file/video.mp4</div>
<div><strong>Ex:</strong> https://host.com.br/file/video.mp3</div>';
$string['videourl_error'] = 'Super Video URL';
$string['videofile'] = 'Or select an MP3 or MP4 file';
$string['videofile_help'] = 'You can upload an MP3 or MP4 file, host it on MoodleData and show it in the Super Video player';
$string['pluginadministration'] = 'Super Videos';
$string['modulename_help'] = 'This module adds a Super Video within Moodle.';
$string['showmapa'] = 'Show Map';
$string['showmapa_desc'] = 'If checked, show the map after the video player!';
$string['showcontrols'] = 'Controls';
$string['showcontrols_desc'] = 'Show player controls';
$string['showinfo'] = 'Show title';
$string['showinfo_desc'] = 'Show video title and player actions';
$string['autoplay'] = 'Play automatically';
$string['autoplay_desc'] = 'Automatically play the player load';
$string['video_size'] = 'Video size';

$string['idnotfound'] = 'Unrecognized link like Youtube, Google Drive or Vimeo';
$string['seu_mapa_view'] = 'Your View map:';
$string['seu_mapa_ir_para'] = 'Go to {$a}';

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
$string['report_filename'] = 'Super Video Plugin video preview - {$a}';
$string['report_filename_geral'] = 'General';

$string['grade_approval'] = 'Set grade for';
$string['grade_approval_0'] = 'No grades';
$string['grade_approval_1'] = 'Grade based on percentage of video views';

$string['completionpercent'] = 'Requires percentage';
$string['completionpercent_help'] = 'Set complete when student views set percentage of video. Accept values from 1 to 100.';
$string['completionpercent_error'] = 'Accept values from 1 to 100';
$string['completionpercent_label'] = 'Enable:&nbsp;';
$string['completiondetail:completionpercent'] = 'You have to watch {$a}% of the video';

$string['no_data'] = 'No records';

$string['settings_opcional_desmarcado'] = 'The FORM will appear deactivated and the teacher can activate or deactivate';
$string['settings_opcional_marcado'] = 'On the FORM it will appear activated and the teacher can activate or deactivate it';
$string['settings_obrigatorio_desmarcado'] = 'It will be disabled for everyone and there is no way to edit it on the FORM';
$string['settings_obrigatorio_marcado'] = 'It will be activated for everyone and there is no way to edit it on the FORM';

$string['supervideo:addinstance'] = 'Create new activities with Super Video';
$string['supervideo:view'] = 'View and interact with Super Video';

$string['privacy:metadata'] = 'The supervideo plugin does not send any personal data to third parties.';

$string['privacy:metadata:supervideo_view'] = 'A record of the messages sent during a chat session';
$string['privacy:metadata:supervideo_view:cm_id'] = '';
$string['privacy:metadata:supervideo_view:user_id'] = '';
$string['privacy:metadata:supervideo_view:currenttime'] = '';
$string['privacy:metadata:supervideo_view:duration'] = '';
$string['privacy:metadata:supervideo_view:percent'] = '';
$string['privacy:metadata:supervideo_view:mapa'] = '';
$string['privacy:metadata:supervideo_view:timecreated'] = '';
$string['privacy:metadata:supervideo_view:timemodified'] = '';
