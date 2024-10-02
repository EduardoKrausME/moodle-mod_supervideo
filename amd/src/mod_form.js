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
        id_name     : null,
        id_videourl : null,

        init : function(engine, lang, courseSection) {

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
        }
    };
    return modform;
});
