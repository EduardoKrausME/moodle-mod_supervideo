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

$string['modulename'] = 'Super Vídeo';
$string['pluginname'] = 'Super Vídeo';
$string['modulenameplural'] = 'Super Vídeo';

$string['dnduploadlabel-mp3'] = 'Adicionar Áudio com o Super Vídeo';
$string['dnduploadlabel-mp4'] = 'Adicionar Vídeo com o Super Vídeo';
$string['dnduploadlabeltext'] = 'Adicionar vídeo com o Super Vídeo';

$string['videourl'] = 'Youtube, Vímeo, Google Drive, link externo ou MP4/MP3';
$string['videourl_help'] = '' .
    '<h4>Youtube</h4>' .
    '<p>Adicione uma URL do Youtube que voc&ecirc; deseja adicionar ao curso:</p>' .
    '<address>Ex: https://www.youtube.com/watch?v=SNhUMChfolc <br>' .
    '             https://youtu.be/kwjhXQUpyvA</address>' .
    '<h4>Google Drive</h4>' .
    '<p>No Google Drive, clique em compartilhar v&iacute;deo e defina as permiss&otilde;es e cole o link aqui.</p>' .
    '<h4>Vimeo</h4>' .
    '<p>Adicione uma URL do Vimeo que voc&ecirc; deseja adicionar ao curso:</p>' .
    '<address>Ex: https://vimeo.com/300138942<br>' .
    '             https://vimeo.com/368389657</address>' .
    '<h4>Vídeo ou áudio externo</h4>' .
    '<p>Adicione uma URL de um vídeo que você tem hospedado em seu próprio servidor:</p>' .
    '<address>Ex: https://host.com.br/file/video.mp4<br>' .
    '             https://host.com.br/file/video.mp3</address>';
$string['videourl_error'] = 'URL do Super Vídeo';
$string['videofile'] = 'Ou selecione um arquivo MP3 ou MP4';
$string['videofile_help'] = 'Você pode fazer upload de um arquivo MP3 ou MP4, hospeda-lo na MoodleData e mostrar no player do Super Vídeo';
$string['pluginadministration'] = 'Super Vídeo';
$string['modulename_help'] = 'Este módulo adiciona um Vídeos Youtube, Google Drive ou Vimeo dentro do Moodle.';
$string['showmapa'] = 'Mostrar Mapa';
$string['showmapa_desc'] = 'Se marcado, mostra o mapa após o player do vídeo!';
$string['showrel'] = 'Sugestão de vídeos';
$string['showrel_desc'] = 'Mostrar vídeos sugeridos quando o vídeo terminar (somente Youtube)';
$string['showcontrols'] = 'Controles';
$string['showcontrols_desc'] = 'Mostrar controles do player';
$string['showinfo'] = 'Mostrar título';
$string['showinfo_desc'] = 'Mostrar título do vídeo e as ações do player';
$string['autoplay'] = 'Reproduzir automaticamente';
$string['autoplay_desc'] = 'Reproduzir automaticamente ao carregar o player';
$string['video_size'] = 'Tamanho do Player';

$string['idnotfound'] = 'Link não reconhecido como Youtube, Google Drive ou Vimeo';
$string['seu_mapa_view'] = 'Seu mapa de Visualização:';
$string['seu_mapa_ir_para'] = 'Ir para {$a}';

$string['report'] = 'Relatório de visualizações';
$string['report_userid'] = 'User ID';
$string['report_nome'] = 'Nome Completo';
$string['report_email'] = 'E-mail';
$string['report_tempo'] = 'Tempo assistido';
$string['report_duracao'] = 'Duração do Vídeo';
$string['report_porcentagem'] = 'Porcentagem visto';
$string['report_mapa'] = 'Mapa da Visualização';
$string['report_comecou'] = 'Começou a assistir quando';
$string['report_terminou'] = 'Terminou de assistir quando';
$string['report_visualizacoes'] = 'Visualizações';
$string['report_assistiu'] = 'Assistiu quando';
$string['report_all'] = 'Todos as visualizações deste aluno';
$string['report_filename'] = 'Visualização de vídeos do Plugin Super vídeo - {$a}';
$string['report_filename_geral'] = 'Geral';

$string['grade_approval'] = 'Definir nota para';
$string['grade_approval_0'] = 'Sem notas';
$string['grade_approval_1'] = 'Nota baseado na porcentagem da visuaização do vídeo';

$string['completionpercent'] = 'Requer porcentagem';
$string['completionpercent_help'] = 'Definir como concluído quando o aluno visualizar a porcentagem do vídeo definida. Aceito valores de de 1 à 100';
$string['completionpercent_error'] = 'Aceito valores de de 1 à 100';
$string['completionpercent_label'] = 'Habilitar:&nbsp;';
$string['completiondetail:completionpercent'] = 'Tem que assistir {$a}% do vídeo';

$string['no_data'] = 'Sem registros';

$string['settings_opcional_desmarcado'] = 'No FORM aparecerá desativado e o professor poderá ativar ou desativar';
$string['settings_opcional_marcado'] = 'No FORM aparecerá ativado e o professor poderá ativar ou desativar';
$string['settings_obrigatorio_desmarcado'] = 'Será desativado para todos e não há como editar no FORM';
$string['settings_obrigatorio_marcado'] = 'Será ativado para todos e não há como editar no FORM';

$string['supervideo:addinstance'] = 'Crie novas atividades com Vídeo';
$string['supervideo:view'] = 'Ver e interagir com o vídeo';

$string['privacy:metadata'] = 'O plug-in supervídeo não envia nenhum dado pessoal a terceiros.';

$string['privacy:metadata:supervideo_view'] = 'A record of the messages sent during a chat session';
$string['privacy:metadata:supervideo_view:cm_id'] = '';
$string['privacy:metadata:supervideo_view:user_id'] = '';
$string['privacy:metadata:supervideo_view:currenttime'] = '';
$string['privacy:metadata:supervideo_view:duration'] = '';
$string['privacy:metadata:supervideo_view:percent'] = '';
$string['privacy:metadata:supervideo_view:mapa'] = '';
$string['privacy:metadata:supervideo_view:timecreated'] = '';
$string['privacy:metadata:supervideo_view:timemodified'] = '';
