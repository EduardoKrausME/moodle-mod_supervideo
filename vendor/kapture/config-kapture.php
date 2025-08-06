<?php

$strings = [
    "destino" => "ottflix", // "ottflix"   "supervideo"
    "app_title" => 'OTTFlix Kapture',
    "logo_title" => 'Logo',
    "selecionar_slide" => 'Selecionar Slide',
    "layout_cam" => 'Layout Cam',
    "layout_presentation" => 'Layout Presentation',
    "layout_1" => 'Layout 1',
    "layout_2" => 'Layout 2',
    "layout_5" => 'Layout 5',
    "layout_4" => 'Layout 4',
    "layout_6" => 'Layout 6',
    "layout_3" => 'Layout 3',
    "finalizar_gravacao" => 'Finalizar a gravação',
    "esta_aba" => 'Esta aba',
    "tela_inteira" => 'Tela Inteira',
    "desligado" => 'desligado',
    "inverter_camera" => 'Inverter Câmera',
    "camera_redonda" => 'Câmera Redonda',
    "tamanho_camera" => 'Tamanho da câmera',
    "compartilhar_audio_sistema" => 'Compartilhar áudio do sistema',
    "contagem_regressiva" => 'Contagem regressiva',
    "iniciar_gravacao" => 'Iniciar gravação',
    "iniciar_gravacao_fullscreen" => 'Iniciar gravação em FullScreen',
    "kapture_precisa_camera" => 'O Kapture precisa acessar seu microfone e câmera.',
    "aprovar_permissao" => 'Selecione <b><i>Permitir</i></b> quando seu navegador solicitar permissões.',
    "erro_camera_microfone." => 'Ocorreu um erro ao solicitar a Câmera e Microfone.',
    "entre_contato_suporte_erro" => 'Entre em contato com suporte e informe o erro ',
    "camera" => 'Câmera',
    "none_camera" => 'Não usar a câmera',
    "microfone" => 'Microfone',
    "erro" => 'Erro:',
    "nao_suportado_celular" => 'Não suportado em Celular',
    "finalizar" => 'Finalizar',
    "salvar_gravacao_ottflix" => 'Salve sua gravação no OttFlix',
    "title_captura" => 'Captura ',
    "salvar_ottflix" => 'Salvar no OttFlix',
    "salvar_computador" => 'Salvar no Computador',
    "selecione_apresentacao" => 'Selecione a apresentação',
    "buscar_arquivos" => 'Buscar arquivos',
    "enviar_novo" => 'Enviar novo',
    "ou" => 'ou',
    "carregando_documentos" => 'Carregando documentos...',
    "processando" => 'Processando...',
    "titulo_muito_curto" => 'Título muito curto!',
    "upload_concluido" => 'Upload concluído. Aguardando processamento!',
    "ottflix" => 'OttFlix',
    "falha_upload_ottflix" => 'Falha no Upload para a OttFlix',
    "abortado_upload_ottflix" => 'Upload para a OttFlix foi abortado!',
    "error_accessing_camera" => 'Error accessing camera or capturing screen',
];

$logo = "img/logo.svg";
$rootPath = "";

if (file_exists(__DIR__ . "/../../repository_ajax.php") && file_exists(__DIR__ . "/../../../../config.php")) {
    require_once(__DIR__ . "/../../../../config.php");

    $strings["destino"] = "supervideo";

    foreach ($strings as $key => $value) {
        if ($key != "destino") {
            $string = get_string($key, "mod_supervideo");
            if ($string) {
                $strings[$key] = $string;
            }
        }
    }
}

$text = "";
function kapture_get_string($str) {
    global $strings, $text;

    if (!isset($strings[$str])) {
        $strings[$str] = "[[{$str}]]";
        $text .= "'{$str}' => '',\n";
    }

    return $strings[$str];
}

function kapture_get_string_js() {
    global $strings;

    echo "<script>langs = ";
    echo json_encode($strings);
    echo "</script>";
}
