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

        init: function (lang, courseinfo) {

            modform.origem(courseinfo);
            modform.pandavideo_youtube_vimeo(lang);

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

        pandavideo_youtube_vimeo:function (lang){
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
