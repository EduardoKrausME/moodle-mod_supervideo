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

$string['videourl'] = 'Youtube, Vimeo, Google Drive, Link externo com extensão MP4/MP3/M3U8/WebM ou um arquivo MP4/MP3/WebM';
$string['videourl_help'] = '<h4>Youtube</h4>
<div>Adicione uma URL do Youtube que voc&ecirc; deseja adicionar ao curso:</div>
<div><strong>Ex:</strong> https://www.youtube.com/watch?v=SNhUMChfolc</div>
<div><strong>Ex:</strong> https://youtu.be/kwjhXQUpyvA</div>
<h4>Google Drive</h4>
<div>No Google Drive, clique em compartilhar v&iacute;deo e defina as permiss&otilde;es e cole o link aqui.</div>
<h4>Vimeo</h4>
<div>Adicione uma URL do Vimeo que voc&ecirc; deseja adicionar ao curso:</div>
<div><strong>Ex:</strong> https://vimeo.com/300138942</div>
<h4>Vídeo ou áudio externo</h4>
<div>Adicione uma URL de um vídeo que você tem hospedado em seu próprio servidor, com extensão MP3 ou MP4:</div>
<div><strong>Ex:</strong> https://host.com.br/file/video.mp4</div>
<div><strong>Ex:</strong> https://host.com.br/file/video.mp3</div>';
$string['videourl_error'] = 'URL do Super Vídeo';
$string['videofile'] = 'Ou selecione um arquivo MP3, MP4 ou WebM';
$string['videofile_help'] = 'Você pode fazer upload de um arquivo MP3 ou MP4, hospeda-lo na MoodleData e mostrar no player do Super Vídeo';
$string['pluginadministration'] = 'Super Vídeo';
$string['modulename_help'] = 'Este módulo adiciona um Vídeos Youtube, Google Drive ou Vimeo dentro do Moodle.';
$string['showmapa'] = 'Mostrar Mapa';
$string['showmapa_desc'] = 'Se marcado, mostra o mapa após o player do vídeo!';
$string['showcontrols'] = 'Controles';
$string['showcontrols_desc'] = 'Mostrar controles do player';
$string['autoplay'] = 'Reproduzir automaticamente';
$string['autoplay_desc'] = 'Reproduzir automaticamente ao carregar o player';
$string['playersize'] = 'Tamanho do Player';
$string['record_kapture'] = 'Grave seu vídeo com Kapture';

$string['idnotfound'] = 'Link não reconhecido como Youtube, Google Drive ou Vimeo';
$string['seu_mapa_view'] = 'Seu mapa de Visualização:';
$string['seu_mapa_ir_para'] = 'Ir para {$a}';

$string['report_title'] = 'Relatório';
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

$string['app_title'] = 'Módulo Super Video Kapture';
$string['logo_title'] = 'Logo';
$string['selecionar_slide'] = 'Selecionar Slide';
$string['layout_cam'] = 'Layout Cam';
$string['layout_presentation'] = 'Layout Presentation';
$string['layout_1'] = 'Layout 1';
$string['layout_2'] = 'Layout 2';
$string['layout_5'] = 'Layout 5';
$string['layout_4'] = 'Layout 4';
$string['layout_6'] = 'Layout 6';
$string['layout_3'] = 'Layout 3';
$string['finalizar_gravacao'] = 'Finalizar a gravação';
$string['esta_aba'] = 'Esta aba';
$string['tela_inteira'] = 'Tela Inteira';
$string['desligado'] = 'desligado';
$string['inverter_camera'] = 'Inverter Câmera';
$string['camera_redonda'] = 'Câmera Redonda';
$string['tamanho_camera'] = 'Tamanho da câmera';
$string['compartilhar_audio_sistema'] = 'Compartilhar áudio do sistema';
$string['contagem_regressiva'] = 'Contagem regressiva';
$string['iniciar_gravacao'] = 'Iniciar gravação';
$string['iniciar_gravacao_fullscreen'] = 'Iniciar gravação em FullScreen';
$string['kapture_precisa_camera'] = 'O Kapture precisa acessar seu microfone e câmera.';
$string['aprovar_permissao'] = 'Selecione <b><i>Permitir</i></b> quando seu navegador solicitar permissões.';
$string['erro_camera_microfone.'] = 'Ocorreu um erro ao solicitar a Câmera e Microfone.';
$string['entre_contato_suporte_erro'] = 'Entre em contato com suporte e informe o erro ';
$string['camera'] = 'Câmera';
$string['microfone'] = 'Microfone';
$string['erro'] = 'Erro:';
$string['nao_suportado_celular'] = 'Não suportado em Celular';
$string['finalizar'] = 'Finalizar';
$string['salvar_gravacao_ottflix'] = 'Salve sua gravação no Módulo Super Video';
$string['title_captura'] = 'Captura ';
$string['salvar_ottflix'] = 'Salvar no Módulo Super Video';
$string['salvar_computador'] = 'Salvar no Computador';
$string['selecione_apresentacao'] = 'Selecione a apresentação';
$string['buscar_arquivos'] = 'Buscar arquivos';
$string['enviar_novo'] = 'Enviar novo';
$string['ou'] = 'ou';
$string['carregando_documentos'] = 'Carregando documentos...';
$string['processando'] = 'Processando...';
$string['titulo_muito_curto'] = 'Título muito curto!';
$string['upload_concluido'] = 'Upload concluído. Aguardando processamento!';
$string['ottflix'] = 'Módulo Super Video';
$string['falha_upload_ottflix'] = 'Falha no Upload para o Módulo Super Video';
$string['abortado_upload_ottflix'] = 'Upload para o Módulo Super Video foi abortado!';
$string['error_accessing_camera'] = 'Error accessing camera or capturing screen';
