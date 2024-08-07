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

define(["jquery", "core/ajax", "mod_supervideo/player_render"], function($, Ajax, PlayerRender) {
    var modform = {
        id_name               : null,
        id_videourl           : null,
        fitem_id_videourl     : null,
        fitem_id_playersize   : null,
        fitem_id_showcontrols : null,
        fitem_id_autoplay     : null,

        init : function(engine, lang, courseSection) {

            modform.id_name = $("#id_name");
            modform.id_videourl = $("#id_videourl");

            modform.fitem_id_videourl = modform.find_fitem("videourl");
            modform.fitem_id_videofile = modform.find_fitem("videofile");
            modform.fitem_id_playersize = modform.find_fitem("playersize");
            modform.fitem_id_showcontrols = modform.find_fitem("showcontrols");
            modform.fitem_id_autoplay = modform.find_fitem("autoplay");

            modform.id_videourl.change(modform.id_videourl_change);
            modform.id_videourl_change();

            modform.upload_file(engine);

            modform.loadposter(lang);

            if (courseSection) {
                modform.id_videourl.after(`
                    <div style="width:100%;">
                        <a id="kapture-open" class='btn btn-primary' 
                           href='${M.cfg.wwwroot}/mod/supervideo/vendor/kapture/?${courseSection}'>
                            ${M.util.get_string('record_kapture', 'supervideo')}   
                        </a>
                    </div>`);
                modform.id_name.focus(function() {
                    var videotitle = modform.id_name.val();
                    $("#kapture-open").attr("href", `${M.cfg.wwwroot}/mod/supervideo/vendor/kapture/?${courseSection}&videotitle=${videotitle}`)
                })
            }
        },

        upload_file : function(engine) {

            var filemanager = modform.fitem_id_videofile.find(".filemanager");
            filemanager[0].addEventListener("core_form/uploadCompleted", function(event) {

                var interval = setInterval(function() {
                    var filename = filemanager.find(".fp-filename").text();
                    console.log("'" + filename + "'");
                    filename = filename.trim();
                    console.log("'" + filename + "'");
                    if (filename.length < 3) {
                        return;
                    }

                    modform.id_videourl.val("[resource-file:" + filename + "]");
                    modform.id_videourl.prop("readonly", true);

                    clearInterval(interval);
                }, 500);
            });
        },

        id_videourl_change : function() {
            var url = modform.id_videourl.val();

            modform.id_videourl.prop("readonly", false);

            var promise = (Ajax.call([{
                methodname : 'mod_supervideo_opengraph_getinfo',
                args       : {
                    url : url.replace("[link]:", "")
                }
            }]))[0];
            promise.done(function(info) {

                if (info.title && modform.id_name.val() == "") {
                    modform.id_name.val(info.title);
                }

                if (info.width && info.height) {
                    var val = info.width + "x" + info.height;
                    $("#id_playersize")
                        .append("<option value='" + val + "' selected>" + val + "</option>")
                        .val(val);
                    modform.validateUrl(url);
                    modform.fitem_id_playersize.hide();
                } else {
                    modform.validateUrl(url);
                }
            });

            modform.validateUrl(url);
        },

        validateUrl : function(url) {
            if (modform.testUrlResource(url)) {
                modform.fitem_id_videofile.show();
                modform.fitem_id_playersize.hide();

                modform.fitem_id_showcontrols && modform.fitem_id_showcontrols.show();
                modform.fitem_id_autoplay && modform.fitem_id_autoplay.show();

                modform.id_videourl.prop("readonly", true);

            } else if (modform.testUrlYouTube(url)) {
                modform.fitem_id_videofile.hide();
                modform.fitem_id_playersize.show();
                modform.fitem_id_playersize.find("option").show();
                modform.fitem_id_playersize.find("[value=5]").hide();
                modform.fitem_id_playersize.find("[value=4x3]").hide();
                modform.fitem_id_playersize.find("[value=16x9]").hide();
                modform.fitem_id_playersize.val(1);

                modform.fitem_id_showcontrols && modform.fitem_id_showcontrols.show();
                modform.fitem_id_autoplay && modform.fitem_id_autoplay.show();

            } else if (modform.testUrlVimeo(url)) {
                modform.fitem_id_videofile.hide();
                modform.fitem_id_playersize.hide();

                modform.fitem_id_showcontrols && modform.fitem_id_showcontrols.show();
                modform.fitem_id_autoplay && modform.fitem_id_autoplay.show();

            } else if (modform.testUrlDrive(url)) {
                modform.fitem_id_videofile.hide();
                modform.fitem_id_playersize.show();
                modform.fitem_id_playersize.find("option").hide();
                modform.fitem_id_playersize.find("[value=5]").show();
                modform.fitem_id_playersize.find("[value=4x3]").show();
                modform.fitem_id_playersize.find("[value=16x9]").show();
                modform.fitem_id_playersize.val(7);

                modform.fitem_id_showcontrols && modform.fitem_id_showcontrols.hide();
                modform.fitem_id_autoplay && modform.fitem_id_autoplay.hide();
            } else if (type = modform.testUrlExternalFile(url)) {
                modform.fitem_id_videofile.hide();
                modform.fitem_id_playersize.hide();

                modform.fitem_id_showcontrols && modform.fitem_id_showcontrols.hide();
                modform.fitem_id_autoplay && modform.fitem_id_autoplay.show();
            } else {
                modform.fitem_id_videofile.show();
                modform.fitem_id_playersize.hide();

                modform.fitem_id_showcontrols && modform.fitem_id_showcontrols.hide();
                modform.fitem_id_autoplay && modform.fitem_id_autoplay.hide();
            }
        },

        testUrlResource     : function(url) {
            var re = /(\[resource-file:).*/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },
        testUrlYouTube      : function(url) {
            var re = /\/\/(?:www\.)?youtu(?:\.be|be\.com)\/(?:watch\?v=|embed\/|live\/|shorts\/)?([a-z0-9_\-]+)/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },
        testUrlVimeo        : function(url) {
            var re = /\/\/(?:www\.)?vimeo.com\/([0-9a-z\-_]+)/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },
        testUrlDrive        : function(url) {
            var re = /https:\/\/(docs.google.com)\//i;
            var matches = re.exec(url);
            return matches && matches[1];
        },
        testUrlExternalFile : function(url) {
            var re = /^https?.*\.(mp3|mp4|m3u8|webm)/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },

        find_fitem : function(fitem_id) {
            var key = "fitem_id_" + fitem_id;
            if (document.getElementById(key)) {
                return $("#" + key);
            }

            var element = $("#id_" + fitem_id);

            element = element.parent();
            if (element.hasClass("fitem")) {
                return element;
            }
            element = element.parent();
            if (element.hasClass("fitem")) {
                return element;
            }
            element = element.parent();
            if (element.hasClass("fitem")) {
                return element;
            }
            element = element.parent();
            if (element.hasClass("fitem")) {
                return element;
            }
            element = element.parent();
            if (element.hasClass("fitem")) {
                return element;
            }

            return $("#id_" + fitem_id).parent();
        },

        loadposter : function(lang) {
            modform.fitem_id_videourl.addClass("videourl_form_item_supervideo");

            var playerRender = new PlayerRender();
            playerRender.loadposter($, lang);
        }
    };
    return modform;
});
