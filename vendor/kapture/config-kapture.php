<?php

$strings = [
    "destination" => "ottflix", // "ottflix"   "supervideo"
    "app_title" => 'OTTFlix Kapture',
    "logo_title" => 'Logo',
    "select_slide" => 'Selecionar Slide',
    "layout_cam" => 'Layout Cam',
    "layout_presentation" => 'Layout Presentation',
    "layout_1" => 'Layout 1',
    "layout_2" => 'Layout 2',
    "layout_5" => 'Layout 5',
    "layout_4" => 'Layout 4',
    "layout_6" => 'Layout 6',
    "layout_3" => 'Layout 3',
    "finish_recording" => 'Finalizar a gravação',
    "this_tab" => 'Esta aba',
    "fullscreen" => 'Tela Inteira',
    "switched_off" => 'desligado',
    "inverter_camera" => 'Inverter Câmera',
    "round_camera" => 'Câmera Redonda',
    "camera_size" => 'Tamanho da câmera',
    "share_audio_system" => 'Compartilhar áudio do sistema',
    "countdown" => 'Contagem regressiva',
    "start_recording" => 'Iniciar gravação',
    "start_recording_fullscreen" => 'Iniciar gravação em FullScreen',
    "requires_camera" => 'O Kapture precisa acessar seu microfone e câmera.',
    "approve_permission" => 'Selecione <b><i>Permitir</i></b> quando seu navegador solicitar permissões.',
    "erro_camera_microfone." => 'Ocorreu um erro ao solicitar a Câmera e Microfone.',
    "contact_support_error" => 'Entre em contato com suporte e informe o erro ',
    "camera" => 'Câmera',
    "none_camera" => 'Não usar a câmera',
    "microfone" => 'Microfone',
    "error" => 'Erro:',
    "not_supported_mobile" => 'Não suportado em Celular',
    "finish" => 'Finalizar',
    "save_recording_ottflix" => 'Salve sua gravação no OttFlix',
    "video_title" => 'Título da sua gravação',
    "video_title_desc" => 'Escolha um nome fácil para encontrar depois.',
    "video_default_title" => 'Captura ',
    "save_ottflix" => 'Enviar para o OttFlix',
    "save_computer" => 'Salvar no Computador',
    "select_presentation" => 'Selecione a apresentação',
    "search_files" => 'Buscar arquivos',
    "send_new" => 'Enviar novo',
    "or" => 'ou',
    "loading_documents" => 'Carregando documentos...',
    "processing" => 'Processando...',
    "title_too_short" => 'Título muito curto!',
    "upload_completed" => 'Upload concluído. Aguardando processamento!',
    "ottflix" => 'OttFlix',
    "ottflix_upload_failure" => 'Falha no Upload para a OttFlix',
    "aborted_upload_ottflix" => 'Upload para a OttFlix foi abortado!',
    "error_accessing_camera" => 'Error accessing camera or capturing screen',
];

$logo = "img/logo.svg";
$rootPath = "";

if (file_exists(__DIR__ . "/../../repository_ajax.php") && file_exists(__DIR__ . "/../../../../config.php")) {
    require_once(__DIR__ . "/../../../../config.php");

    $strings["destination"] = "supervideo";

    foreach ($strings as $key => $value) {
        if ($key != "destination") {
            $string = get_string("kapture_{$key}", "mod_supervideo");
            if ($string) {
                $strings[$key] = $string;
            }
        }
    }
}

$text = "";
/**
 * Function kapture_get_string
 *
 * @param $str
 * @return string
 */
function kapture_get_string($str)
{
    global $strings, $text;

    if (!isset($strings[$str])) {
        $strings[$str] = "[[{$str}]]";
        $text .= "'{$str}' => '',\n";
    }

    return $strings[$str];
}

/**
 * Function kapture_get_string_js
 *
 * @return void
 */
function kapture_get_string_js()
{
    global $strings;

    echo "<script>langs = ";
    echo json_encode($strings);
    echo "</script>";
}

/**
 * Function get_and_htmlspecialchars
 *
 * @param $key
 * @return string
 */
function get_and_htmlspecialchars($key)
{
    if (isset($_GET[$key])) {
        return htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    }
    return "";
}
