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

/**
 * supervideo_filepicker.js
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.supervideo_filepicker = {};
M.supervideo_filepicker.Y = null;
M.supervideo_filepicker.instances = [];

M.supervideo_filepicker.callback = function (params) {
    console.log(params);

    let elementid = M.core_filepicker.instances[params['client_id']].options.elementid

    let element_elementid = document.getElementById(elementid);
    element_elementid.value = params.url;

    M.supervideo_filepicker.instances[elementid].fileadded = true;
    M.supervideo_filepicker.Y.one('#'+elementid).simulate('change');
};

/**
 * This fucntion is called for each file picker on page.
 */
M.supervideo_filepicker.init = function (Y, options) {
    console.log(options);
    //Keep reference of YUI, so that it can be used in callback.
    M.supervideo_filepicker.Y = Y;

    //For client side validation, initialize file status for this filepicker
    M.supervideo_filepicker.instances[options.elementid] = {};
    M.supervideo_filepicker.instances[options.elementid].fileadded = false;

    //Set filepicker callback
    options.formcallback = M.supervideo_filepicker.callback;

    if (!M.core_filepicker.instances[options.client_id]) {
        M.core_filepicker.init(Y, options);
    }
    Y.on('click', function (e, client_id) {
        e.preventDefault();
        if (this.ancestor('.fitem.disabled') == null) {
            M.core_filepicker.instances[client_id].show();
        }
    }, '#filepicker-button-' + options.elementid, null, options.client_id);

    var button = document.getElementById('filepicker-button-' + options.elementid);
    if (button) {
        button.style.display = '';
    }
};
