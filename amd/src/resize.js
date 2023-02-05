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
    return {
        start : function() {
            var videoBoxWidth = 0;

            var videoBox = document.getElementById('supervideoworkaround');
            if (videoBox.offsetWidth) {
                videoBoxWidth = videoBox.offsetWidth;
            }
            if (videoBox.clientWidth) {
                videoBoxWidth = videoBox.clientWidth;
            }

            var videoed0 = document.getElementById('videoed0');
            if (videoed0) {
                var videoBoxHeight1 = videoBoxWidth * 3 / 4;

                videoed0.style.width = videoBoxWidth + "px";
                videoed0.style.height = videoBoxHeight1 + "px";
            }

            var videohd1 = document.getElementById('videohd1');
            if (videohd1) {
                var videoBoxHeight2 = videoBoxWidth * 9 / 16;

                videohd1.style.width = videoBoxWidth + "px";
                videohd1.style.height = videoBoxHeight2 + "px";
            }

            var videohd2 = document.getElementById('videohd2');
            if (videohd2) {
                var videoBoxHeight3 = videoBoxWidth * 9 / 16;

                videohd2.style.width = videoBoxWidth + "px";
                videohd2.style.height = videoBoxHeight3 + "px";
            }

        }
    };
});
