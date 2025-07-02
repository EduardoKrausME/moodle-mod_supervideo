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
            modform.panda();
            modform.panda_youtube_vimeo();

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
            let id_videourl_panda = $("#id_videourl_panda");
            id_videourl_panda.after(`<div id="id_videourl_panda-videos"></div>`);

            let id_origem = $("#id_origem");
            id_origem.change(function () {
                if ($(this).val() == "panda") {
                    Ajax.call([{
                        methodname: "mod_supervideo_panda_list_videos",
                        args: {

                        }
                    }])[0].done(function(data) {
                        Templates.render("mod_supervideo/panda_list_videos", data)
                            .then(function(templatehtml) {
                                $("#id_videourl_panda-videos").html(templatehtml);

                                $("#id_videourl_panda-videos .panda-item-video").click(function () {
                                    let videoid = $(this).attr("data-videoid");
                                    id_videourl_panda.val(videoid);
                                });
                            });
                    }).fail(Notification.exception);
                }
            });
        },

        panda_youtube_vimeo:function (){
            let id_origem = $("#id_origem");
            id_origem.change(function () {
                if ($(this).val() == "upload") {
                    $("#kapture-open").show();
                } else {
                    $("#kapture-open").hide();
                }
            });
        }
    };
    return modform;
});
