<?php
require_once "config-kapture.php";
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo kapture_get_string("app_title"); ?></title>

    <?php kapture_get_string_js() ?>
    <link rel="stylesheet" href="css/style.css"><script type="text/javascript" src="all.min.js?build=52"></script>

    <script src="ffmpeg/ffmpeg.js"></script>
</head>
<body>

<input id="Config-rootPath" type="hidden" value="<?php echo $rootPath ?>">

<div id="app" class="record-parado">
    <input type="hidden" id="pasta" value="<?php echo get_and_htmlspecialchars('pasta') ?>">
    <input type="hidden" id="videotitle" value="<?php echo get_and_htmlspecialchars('videotitle') ?>">
    <input type="hidden" id="description" value="<?php echo get_and_htmlspecialchars('description') ?>">
    <input type="hidden" id="identifier" value="<?php echo get_and_htmlspecialchars('identifier') ?>">
    <input type="hidden" id="course" value="<?php echo get_and_htmlspecialchars('course') ?>">
    <input type="hidden" id="section" value="<?php echo get_and_htmlspecialchars('section') ?>">
    <input type="hidden" id="token" value="<?php echo get_and_htmlspecialchars('token') ?>">
    <input type="hidden" id="webhooks" value="<?php echo get_and_htmlspecialchars('webhooks') ?>">

    <img class="logo" src="<?php echo $logo ?>" alt="<?php echo kapture_get_string("logo_title"); ?>"
         title="<?php echo kapture_get_string("logo_title"); ?>">

    <div id="device-control" class="layout-dual-1">
        <div class="controles-layout">
            <div id="ppt-select-icon">
                <img src="img/icons/presentation/presentation.svg"
                     alt="<?php echo kapture_get_string("select_slide"); ?>"
                     title="<?php echo kapture_get_string("select_slide"); ?>">
            </div>
            <img src="img/layout/layout-only-cam.jpg" data-layout="layout-only-cam"
                 alt="<?php echo kapture_get_string("layout_cam"); ?>"
                 title="<?php echo kapture_get_string("layout_cam"); ?>"
                 class="layout layout-only layout-only-cam">
            <img src="img/layout/layout-only-ppt.jpg" data-layout="layout-only-ppt"
                 alt="<?php echo kapture_get_string("layout_presentation"); ?>"
                 title="<?php echo kapture_get_string("layout_presentation"); ?>"
                 class="layout layout-only layout-only-ppt">
            <img src="img/layout/layout-dual-1.jpg" data-layout="layout-dual-1"
                 alt="<?php echo kapture_get_string("layout_1"); ?>"
                 title="<?php echo kapture_get_string("layout_1"); ?>"
                 class="layout layout-dual-1">
            <!--img src="img/layout/layout-dual-2.jpg" data-layout="layout-dual-2"
                    alt="<?php echo kapture_get_string("layout_2"); ?>"
                    title="<?php echo kapture_get_string("layout_2"); ?>"
                 class="layout layout-dual-2"-->

            <img src="img/layout/layout-pip-3.jpg" data-layout="layout-pip-3"
                 alt="<?php echo kapture_get_string("layout_3"); ?>"
                 title="<?php echo kapture_get_string("layout_3"); ?>"
                 class="layout layout-pip-3">
            <img src="img/layout/layout-pip-2.jpg" data-layout="layout-pip-2"
                 alt="<?php echo kapture_get_string("layout_2"); ?>"
                 title="<?php echo kapture_get_string("layout_2"); ?>"
                 class="layout layout-pip-2">
            <img src="img/layout/layout-pip-4.jpg" data-layout="layout-pip-4"
                 alt="<?php echo kapture_get_string("layout_4"); ?>"
                 title="<?php echo kapture_get_string("layout_4"); ?>"
                 class="layout layout-pip-4">
            <img src="img/layout/layout-pip-1.jpg" data-layout="layout-pip-1"
                 alt="<?php echo kapture_get_string("layout_1"); ?>"
                 title="<?php echo kapture_get_string("layout_1"); ?>"
                 class="layout layout-pip-1">

            <div id="record-time">00:00</div>

            <div class="controle-device btn-control btn-concluir"
                 title="<?php echo kapture_get_string("finish_recording"); ?>"
                 style="display:none">
                <div id="btn-stop" class="btn">
                    <?php echo kapture_get_string("finish"); ?>
                </div>
            </div>
        </div>
    </div>

    <div id="record-area" class="layout-dual-1">
        <div id="area-video" class="area-video">
            <div class="height-item"></div>
            <video id="camera-video" playsinline autoplay muted></video>
            <div id="cam-controls">
                <img src="img/icons/controle/control-rectangle.svg" class="control-rectangle"
                     onclick="$('#cam-round').trigger('click');">
                <img src="img/icons/controle/control-rounded.svg" class="control-rounded"
                     onclick="$('#cam-round').trigger('click');">
                <img src="img/icons/controle/control-invert.svg" class="control-invert"
                     onclick="$('#cam-invert').trigger('click');">
                <span></span>
                <img src="img/icons/controle/control-rounded.svg" style="height: 15px"
                     onclick="$('#cam-size').val(200).trigger('input');">
                <img src="img/icons/controle/control-rounded.svg" style="height: 24px"
                     onclick="$('#cam-size').val(275).trigger('input');">
                <img src="img/icons/controle/control-rounded.svg" style="height: 33px"
                     onclick="$('#cam-size').val(350).trigger('input');">
            </div>
        </div>
        <div id="area-presentation">
            <iframe id="slide-atual"
                    frameborder="0" scrolling="no" allowfullscreen webkitallowfullscreen=""></iframe>
        </div>
    </div>

    <div id="configurations">

        <div id="screen-shared-option">
            <div class="item selected" data-share="tab">
                <?php echo file_get_contents("img/screen/tabs.svg") ?>
                <div><?php echo kapture_get_string("this_tab"); ?></div>
            </div>
            <div class="item item-window" data-share="window">
                <?php echo file_get_contents("img/screen/monitor.svg") ?>
                <div><?php echo kapture_get_string("fullscreen"); ?></div>
            </div>
        </div>

        <div class="controles-device">
            <div id="select-video-area" class="controle-device background">
                <?php echo file_get_contents("img/icons/controle/cam.svg") ?>
                <select name="camera" id="select-video"></select>
                <div class="off"><?php echo kapture_get_string("switched_off"); ?></div>
            </div>
            <div id="select-video-alert" class="device-not-found-alert" style="display:none;">
                <?php echo kapture_get_string("camera_not_found"); ?>
            </div>

            <div id="cam-invert-area" class="controle-device cam-config-area">
                <?php echo kapture_get_string("inverter_camera"); ?>
                <label class='el-switch small'>
                    <input type="checkbox" id="cam-invert">
                    <span class='el-switch-style'></span>
                </label>
            </div>
            <div id="select-audio-alert" class="device-not-found-alert" style="display:none;">
                <?php echo kapture_get_string("microphone_not_found"); ?>
            </div>

            <div id="cam-round-area" class="controle-device cam-config-area">
                <?php echo kapture_get_string("round_camera"); ?>
                <label class='el-switch small'>
                    <input type="checkbox" id="cam-round">
                    <span class='el-switch-style'></span>
                </label>
            </div>

            <div id="cam-size-area" class="controle-device cam-config-area"
                 style="gap:16px;">
                <?php echo kapture_get_string("camera_size"); ?>
                <input type="range" id="cam-size" min="200" max="600" value="300" step="10"
                       style="width:100%;"/>
            </div>

            <hr/>

            <div id="select-audio-area" class="controle-device background"
                 style="margin-top:10px;">
                <?php echo file_get_contents("img/icons/controle/mic.svg") ?>
                <select name="microfone" id="select-audio"></select>
                <div class="off"><?php echo kapture_get_string("switched_off"); ?></div>
            </div>
            <div id="systemAudio-area" class="controle-device mic-config-area">
                <?php echo kapture_get_string("share_audio_system"); ?>
                <label class='el-switch small'>
                    <input type="checkbox" id="systemAudio">
                    <span class='el-switch-style'></span>
                </label>
            </div>

            <hr/>

            <div id="countdown-area" class="controle-device"
                 style="margin-bottom:10px;margin-top:10px;">
                <?php echo kapture_get_string("countdown"); ?>
                <label class='el-switch small'>
                    <input type="checkbox" id="countdown">
                    <span class='el-switch-style'></span>
                </label>
            </div>

            <div class="controle-device btn-control"
                 style="margin-bottom:10px;">
                <div id="btn-record" class="btn" title="<?php echo kapture_get_string("start_recording"); ?>">
                    <img src="img/icons/controle/record.svg">
                    <?php echo kapture_get_string("start_recording"); ?>
                </div>
            </div>
            <div class="controle-device btn-control"
                 style="margin-bottom:10px;">
                <div id="btn-record-fullscreen" class="btn"
                     title="<?php echo kapture_get_string("start_recording_fullscreen"); ?>">
                    <img src="img/icons/controle/fullscreen.svg">
                    <?php echo kapture_get_string("start_recording_fullscreen"); ?>
                </div>
            </div>
            <div class="controle-device btn-control btn-save"
                 style="margin-bottom:10px;display:none">
                <div id="btn-save" class="btn" title="<?php echo kapture_get_string("save_ottflix"); ?>">
                    <?php echo kapture_get_string("save_ottflix"); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="sleep-allow-video" class="app-status">
    <div class="info">
        <span>
            <img src="img/icons/controle/mic.svg" alt="<?php echo kapture_get_string("microfone"); ?>"
                 title="<?php echo kapture_get_string("microfone"); ?>"></span>
        <span>
            <img src="img/icons/controle/cam.svg" alt="<?php echo kapture_get_string("camera"); ?>"
                 title="<?php echo kapture_get_string("camera"); ?>"></span>
        <h3 class="info-title"><?php echo kapture_get_string("requires_camera"); ?></h3>
        <span class="info-text"><?php echo kapture_get_string("approve_permission"); ?></span>
    </div>
</div>
<div id="error-allow-video" class="app-status">
    <div class="info">
        <span>
            <img src="img/icons/controle/mic.svg" alt="<?php echo kapture_get_string("microfone"); ?>"
                 title="<?php echo kapture_get_string("microfone"); ?>"></span>
        <span>
            <img src="img/icons/controle/cam.svg" alt="<?php echo kapture_get_string("camera"); ?>"
                 title="<?php echo kapture_get_string("camera"); ?>"></span>
        <h3 class="info-title"><?php echo kapture_get_string("erro_camera_microfone."); ?></h3>
        <h4 class="info-text"><?php echo kapture_get_string("contact_support_error"); ?></h4>
        <div class="error-message"></div>
    </div>
</div>
<div id="error-video" class="app-status">
    <div class="info">
        <h3 class="info-title"><?php echo kapture_get_string("error"); ?></h3>
        <span class="info-text"></span>
    </div>
</div>

<div id="popup-geral" aria-hidden="true">
    <div class="popup" role="dialog" aria-modal="true" aria-labelledby="popup-title">
        <div class="popup-header">
            <h2 id="popup-title">&nbsp;</h2>
            <button type="button" class="close" aria-label="Fechar popup"></button>
        </div>
        <div class="content">&nbsp;</div>
    </div>
</div>

<div class="mobile-view-only">
    <h1 class="headText"><?php echo kapture_get_string("not_supported_mobile"); ?></h1>
    <img class="mobile-view-img" src="img/mobileView.png"
         alt="<?php echo kapture_get_string("not_supported_mobile"); ?>"
         title="<?php echo kapture_get_string("not_supported_mobile"); ?>">
</div>

<?php echo $text; ?>

</body>
</html>
