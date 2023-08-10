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
    return mod_form = {
        id_name               : null,
        id_videourl           : null,
        fitem_id_videourl     : null,
        fitem_id_videosize    : null,
        fitem_id_showcontrols : null,
        fitem_id_showinfo     : null,
        fitem_id_autoplay     : null,

        init : function(engine, lang) {

            mod_form.id_name = $("#id_name");
            mod_form.id_videourl = $("#id_videourl");

            mod_form.fitem_id_videourl = mod_form.find_fitem("videourl");
            mod_form.fitem_id_videofile = mod_form.find_fitem("videofile");
            mod_form.fitem_id_videosize = mod_form.find_fitem("videosize");
            mod_form.fitem_id_showcontrols = mod_form.find_fitem("showcontrols");
            mod_form.fitem_id_showinfo = mod_form.find_fitem("showinfo");
            mod_form.fitem_id_autoplay = mod_form.find_fitem("autoplay");

            mod_form.id_videourl.change(mod_form.id_videourl_change);
            mod_form.id_videourl_change();

            mod_form.upload_file(engine);

            mod_form.loadposter(lang);
        },

        upload_file : function(engine) {

            $("#id_videofile").change(function() {
                var filename = $('.filepicker-filename').text();

                if (mod_form.id_name.val() == "") {
                    mod_form.id_name.val(filename.slice(0, -4));
                }

                mod_form.id_videourl.val("[resource-file:" + filename + "]");
                mod_form.id_videourl.prop("readonly", true);

                mod_form.id_videourl_change();
            });
        },

        id_videourl_change : function() {
            var url = mod_form.id_videourl.val();

            mod_form.id_videourl.prop("readonly", false);

            var promise = (Ajax.call([{
                methodname : 'mod_supervideo_services_opengraph_getinfo',
                args       : {
                    url : url.replace("[link]:", "")
                }
            }]))[0];
            promise.done(function(info) {

                if (info.title && mod_form.id_name.val() == "") {
                    mod_form.id_name.val(info.title);
                }

                if (info.width && info.height) {
                    var val = info.width + "x" + info.height;
                    $("#id_videosize")
                        .append("<option value='" + val + "' selected>" + val + "</option>")
                        .val(val);
                    mod_form.validateUrl(url);
                    mod_form.fitem_id_videosize.hide();
                } else {
                    mod_form.validateUrl(url);
                    mod_form.fitem_id_videosize.show();
                }
            });

            mod_form.validateUrl(url);
        },

        validateUrl : function(url) {
            if (mod_form.testUrlResource(url)) {
                // console.log("testUrlYouTube");
                mod_form.fitem_id_videofile.show();
                mod_form.fitem_id_videosize.hide();

                mod_form.fitem_id_showcontrols && mod_form.fitem_id_showcontrols.show();
                mod_form.fitem_id_showinfo && mod_form.fitem_id_showinfo.hide();
                mod_form.fitem_id_autoplay && mod_form.fitem_id_autoplay.show();

                mod_form.id_videourl.prop("readonly", true);

            } else if (mod_form.testUrlYouTube(url)) {
                // console.log("testUrlYouTube");
                mod_form.fitem_id_videofile.hide();
                mod_form.fitem_id_videosize.show();
                mod_form.fitem_id_videosize.find("option").show();
                mod_form.fitem_id_videosize.find("[value=5]").hide();
                mod_form.fitem_id_videosize.find("[value=4x3]").hide();
                mod_form.fitem_id_videosize.find("[value=16x9]").hide();
                mod_form.fitem_id_videosize.val(1);

                mod_form.fitem_id_showcontrols && mod_form.fitem_id_showcontrols.show();
                mod_form.fitem_id_showinfo && mod_form.fitem_id_showinfo.show();
                mod_form.fitem_id_autoplay && mod_form.fitem_id_autoplay.show();

            } else if (mod_form.testUrlVimeo(url)) {
                // console.log("testUrlVimeo");
                mod_form.fitem_id_videofile.hide();
                mod_form.fitem_id_videosize.hide();

                mod_form.fitem_id_showcontrols && mod_form.fitem_id_showcontrols.show();
                mod_form.fitem_id_showinfo && mod_form.fitem_id_showinfo.show();
                mod_form.fitem_id_autoplay && mod_form.fitem_id_autoplay.show();

            } else if (mod_form.testUrlDrive(url)) {
                // console.log("testUrlDrive");
                mod_form.fitem_id_videofile.hide();
                mod_form.fitem_id_videosize.show();
                mod_form.fitem_id_videosize.find("option").hide();
                mod_form.fitem_id_videosize.find("[value=5]").show();
                mod_form.fitem_id_videosize.find("[value=4x3]").show();
                mod_form.fitem_id_videosize.find("[value=16x9]").show();
                mod_form.fitem_id_videosize.val(7);

                mod_form.fitem_id_showcontrols && mod_form.fitem_id_showcontrols.hide();
                mod_form.fitem_id_showinfo && mod_form.fitem_id_showinfo.hide();
                mod_form.fitem_id_autoplay && mod_form.fitem_id_autoplay.hide();
            } else if (mod_form.testUrlExternalFile(url)) {
                // console.log("testUrlExternalFile");
                mod_form.fitem_id_videofile.hide();
                mod_form.fitem_id_videosize.hide();

                mod_form.fitem_id_showcontrols && mod_form.fitem_id_showcontrols.hide();
                mod_form.fitem_id_showinfo && mod_form.fitem_id_showinfo.hide();
                mod_form.fitem_id_autoplay && mod_form.fitem_id_autoplay.show();

            } else {
                // console.log("else");
                mod_form.fitem_id_videofile.show();
                mod_form.fitem_id_videosize.hide();

                mod_form.fitem_id_showcontrols && mod_form.fitem_id_showcontrols.hide();
                mod_form.fitem_id_showinfo && mod_form.fitem_id_showinfo.hide();
                mod_form.fitem_id_autoplay && mod_form.fitem_id_autoplay.hide();

                isNotFound = true;
            }
        },

        testUrlResource : function(url) {
            var re = /(\[resource-file:).*/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },

        testUrlYouTube : function(url) {
            var re = /\/\/(?:www\.)?youtu(?:\.be|be\.com)\/(?:watch\?v=|embed\/)?([a-z0-9_\-]+)/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },
        testUrlVimeo   : function(url) {
            var re = /\/\/(?:www\.)?vimeo.com\/([0-9a-z\-_]+)/i;
            var matches = re.exec(url);
            return matches && matches[1];
        },
        testUrlDrive   : function(url) {
            var re = /https:\/\/(docs.google.com)\//i;
            var matches = re.exec(url);
            return matches && matches[1];
        },

        testUrlExternalFile : function(url) {
            var re = /^https?.*\.(mp3|mp4)/i;
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
            mod_form.fitem_id_videourl.addClass("videourl_form_item_supervideo");

            var playerRender = new PlayerRender();
            playerRender.loadposter($, lang);
        }

    };
});


