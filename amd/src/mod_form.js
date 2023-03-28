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

define(["jquery"], function($) {
    return mod_form = {
        id_videourl           : null,
        fitem_id_videosize    : null,
        fitem_id_showrel      : null,
        fitem_id_showcontrols : null,
        fitem_id_showshowinfo : null,
        fitem_id_autoplay     : null,

        init : function(engine) {
            // console.log("aaa");

            mod_form.id_videourl = $("#id_videourl");
            mod_form.fitem_id_videosize = $("#fitem_id_videosize");
            mod_form.fitem_id_showrel = $("#fitem_id_showrel");
            mod_form.fitem_id_showcontrols = $("#fitem_id_showcontrols");
            mod_form.fitem_id_showshowinfo = $("#fitem_id_showshowinfo");
            mod_form.fitem_id_autoplay = $("#fitem_id_autoplay");

            mod_form.id_videourl.change(mod_form.id_videourl_change);
            mod_form.id_videourl_change();

            mod_form.upload_file(engine);
        },

        upload_file : function(engine) {
            $("#fitem_id_videofile").show();

            $("#mform1").attr("enctype", "multipart/form-data");

            $("#videofile_file").change(function() {
                var filePartes = $(this).val().split("\\");
                var filename = filePartes[filePartes.length - 1];

                $('#videofile_file-name').html(filename);

                mod_form.id_videourl.val("[resource-file:" + filename + "]");
                mod_form.id_videourl.prop("readonly", true);

                mod_form.id_videourl_change();
            });
        },

        id_videourl_change : function() {
            var url = mod_form.id_videourl.val();

            if (mod_form.testUrlResource(url)) {
                // console.log("testUrlYouTube");
                mod_form.fitem_id_videosize.hide();
                mod_form.fitem_id_showrel.hide();
                mod_form.fitem_id_showcontrols.show();
                mod_form.fitem_id_showshowinfo.hide();
                mod_form.fitem_id_autoplay.show();

                mod_form.id_videourl.prop("readonly", true);

            } else if (mod_form.testUrlYouTube(url)) {
                // console.log("testUrlYouTube");
                mod_form.fitem_id_videosize.show();
                mod_form.fitem_id_videosize.find("option").hide();
                mod_form.fitem_id_videosize.find("[value=0]").show();
                mod_form.fitem_id_videosize.find("[value=1]").show();
                mod_form.fitem_id_videosize.val(1);

                mod_form.fitem_id_showrel.show();
                mod_form.fitem_id_showcontrols.show();
                mod_form.fitem_id_showshowinfo.show();
                mod_form.fitem_id_autoplay.show();

            } else if (mod_form.testUrlVimeo(url)) {
                // console.log("testUrlVimeo");
                mod_form.fitem_id_videosize.hide();
                mod_form.fitem_id_showrel.hide();
                mod_form.fitem_id_showcontrols.show();
                mod_form.fitem_id_showshowinfo.show();
                mod_form.fitem_id_autoplay.show();

            } else if (mod_form.testUrlDrive(url)) {
                // console.log("testUrlDrive");
                mod_form.fitem_id_videosize.show();
                mod_form.fitem_id_videosize.find("option").hide();
                mod_form.fitem_id_videosize.find("[value=5]").show();
                mod_form.fitem_id_videosize.find("[value=6]").show();
                mod_form.fitem_id_videosize.find("[value=7]").show();
                mod_form.fitem_id_videosize.val(7);

                mod_form.fitem_id_showrel.hide();
                mod_form.fitem_id_showcontrols.hide();
                mod_form.fitem_id_showshowinfo.hide();
                mod_form.fitem_id_autoplay.hide();
            } else {
                // console.log("else");
                mod_form.fitem_id_videosize.hide();
                mod_form.fitem_id_showrel.hide();
                mod_form.fitem_id_showcontrols.hide();
                mod_form.fitem_id_showshowinfo.hide();
                mod_form.fitem_id_autoplay.hide();
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
        }
    };
});
