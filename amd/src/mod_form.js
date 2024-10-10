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

define(["jquery", "mod_supervideo/player_render"], function($, PlayerRender) {
    var modform = {

        init : function(engine, lang, courseinfo) {

            var id_origem = $("#id_origem");

            if (courseinfo) {

                id_origem.after(
                    `<a id="kapture-open" class="btn btn-primary ml-2"
                        style="padding:6px 18px;" 
                        href="${M.cfg.wwwroot}/mod/supervideo/vendor/kapture/?${courseinfo}">
                         ${M.util.get_string("record_kapture", "supervideo")}   
                     </a>`);

                id_origem.focus(function() {
                    console.log("id_origem focus");
                    var href = `${M.cfg.wwwroot}/mod/supervideo/vendor/kapture/?${courseinfo}&videotitle=${id_origem.val()}`;
                    $("#kapture-open").attr("href", href)
                });
            }

            var player = new PlayerRender();
            player.loadposter($, lang);
        }
    };
    return modform;
});
