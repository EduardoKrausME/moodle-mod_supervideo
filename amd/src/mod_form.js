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

define(["jquery", "core/ajax", "core/notification", "core/templates", "mod_supervideo/player_render"],
function ($, Ajax, Notification, Templates, PlayerRender) {
    var modform = {

        init: function (lang, courseinfo, has_panda_token) {

            modform.origem(courseinfo);

            modform.has_panda_token = has_panda_token;
            modform.panda();
            modform.panda_youtube_vimeo(lang);

            var player = new PlayerRender();
            player.loadposter($, lang);
        },

        origem:function (courseinfo){
            let id_origem = $("#id_origem");
            if (courseinfo) {
                id_origem.after(
                    `<a id="kapture-open" class="btn btn-primary ml-2" style="padding:6px 18px;" 
                        href="${M.cfg.wwwroot}/mod/supervideo/vendor/kapture/?${courseinfo}">
                         ${M.util.get_string("record_kapture", "supervideo")}   
                     </a>`);

                id_origem.focus(function () {
                    var href = `${M.cfg.wwwroot}/mod/supervideo/vendor/kapture/?${courseinfo}&videotitle=${id_origem.val()}`;
                    $("#kapture-open").attr("href", href)
                });

                id_origem.change(function () {
                    if ($(this).val() == "upload") {
                        $("#kapture-open").show();
                    } else {
                        $("#kapture-open").hide();
                    }
                });
            }
        },

        panda:function (){
            if (!modform.has_panda_token) {
                return;
            }

            let id_videourl_panda = $("#id_videourl_panda");
            id_videourl_panda.after(`
                <div style="background: #00000075;padding: 10px;margin-top: 5px;border-radius: 7px;width: 100%;">
                    <div class="simplesearchform" style="display:inline-block;">
                        <div class="input-group">
                            <input type="search" id="find-panda-videos" placeholder="Buscar no Panda Video">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-submit" data-action="submit">
                                    <i class="icon fa fa-magnifying-glass fa-fw"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="id_videourl_panda-videos"></div>
                </div>`);

            let id_origem = $("#id_origem");
            id_origem.change(function () {
                if (id_origem.val() == "panda") {
                    loadVideosPanda();
                }
            });

            var typingTimer;
            $("#find-panda-videos").on("input", function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function() {
                    loadVideosPanda();
                }, 1000);
            });

            function markActive (){
                let videoid = id_videourl_panda.val();
                $(`.panda-item-video:not(.videoid-${videoid})`).css("background", "");
                $(`.panda-item-video.videoid-${videoid}`).css("background", "#D2DAE1");
            }

            function loadVideosPanda(){
                console.log("calll");
                Ajax.call([{
                    methodname: "mod_supervideo_panda_list_videos",
                    args: {
                        title: $("#find-panda-videos").val(),
                    }
                }])[0].done(function(data) {
                    console.log("done...");
                    Templates.render("mod_supervideo/panda_list_videos", data)
                        .then(function(templatehtml) {
                            $("#id_videourl_panda-videos").html(templatehtml);

                            $("#id_videourl_panda-videos .panda-item-video").click(function () {
                                let videoid = $(this).attr("data-videoid");
                                id_videourl_panda.val(`https://dashboard.pandavideo.com.br/#/videos/${videoid}`);

                                markActive ();
                            });

                            markActive ();
                        });
                }).fail(Notification.exception);
            }

            id_videourl_panda.on("paste", function(e) {
                e.preventDefault();
                let clipboardData = (e.originalEvent || e).clipboardData.getData("text");
                let match = clipboardData.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
                if (match) {
                    id_videourl_panda.val(`https://dashboard.pandavideo.com.br/#/videos/${match[0]}`);
                    markActive ();
                } else {
                    id_videourl_panda.val("");
                    markActive ();
                }
            });
            id_videourl_panda.on("input", function(e) {
                markActive ();
            });
        },

        panda_youtube_vimeo:function (lang){
            let id_origem = $("#id_origem");
            id_origem.after(`<div id="banner_panda-videos" style="display:none;width:100%;"></div>`);

            id_origem.change(function () {
                if ($(this).val() == "youtube" || $(this).val() == "vimeo") {
                    $("#banner_panda-videos").show();
                }else{
                    $("#banner_panda-videos").hide();
                }
            });

            // Load Banner Panda Videos.
            $.ajax({
                url: `https://www.eduardokraus.com/logos/mod_supervideo/banneryoutube.json?lang=${lang}`,
                dataType: "json",
                success: function(data) {
                    if (lang.toLowerCase().startsWith("pt") && data.pt_br) {
                        $("#banner_panda-videos").html(data.pt_br);
                    } else if (lang.toLowerCase().startsWith("en") && data.en) {
                        $("#banner_panda-videos").html(data.en);
                    } else {
                        $("#banner_panda-videos").html(data.default);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("Erro ao baixar o JSON:", textStatus, errorThrown);
                }
            });
        }
    };
    return modform;
});
