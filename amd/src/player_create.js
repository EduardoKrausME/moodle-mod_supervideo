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

define(["jquery", "core/ajax"], function($, Ajax) {
    return progress = {

        youtube : function(view_id, return_currenttime, elementId, videoid, videosize, showrel, showcontrols, showshowinfo, autoplay) {

            progress._internal_view_id = view_id;

            var player = new YT.Player(elementId, {
                suggestedQuality : 'large',
                videoId          : videoid,
                width            : '100%',
                playerVars       : {
                    showrel      : showrel,
                    showcontrols : showcontrols,
                    showshowinfo : showshowinfo,
                    autoplay     : autoplay
                },
                events           : {
                    'onReady'       : function(event) {

                        if (videosize == 1) {
                            progress._internal_resize(16, 9);
                        } else {
                            progress._internal_resize(4, 3);
                        }

                        if (return_currenttime) {
                            player.seekTo(return_currenttime);
                        }
                        document.addEventListener("setCurrentTime", function(event) {
                            player.seekTo(event.detail.goCurrentTime);
                        });
                    },
                    'onStateChange' : function(event) {
                        //console.log(event);
                    }
                }
            });

            setInterval(function() {
                if (player && player.getCurrentTime != undefined) {
                    var _currenttime = parseInt(player.getCurrentTime());
                    progress._internal_saveprogress(_currenttime, player.getDuration());
                }
            }, 200)
        },

        resource_audio : function(view_id, return_currenttime, elementId, autoplay) {

            progress._internal_view_id = view_id;

            var config = {
                controls : [
                    'play', 'progress', 'current-time', 'mute', 'volume', 'pip', 'airplay', 'duration'
                ],
                tooltips : {controls : true, seek : true},
                settings : ['speed', 'loop'],
                autoplay : autoplay,
                storage  : {enabled : true, key : "id-" + view_id},
                speed    : {selected : 1, options : [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 4]},
            };
            var player = new Plyr("#" + elementId, config);
            window.player = player;
            if (return_currenttime) {
                player.currentTime = return_currenttime;
            }
            document.addEventListener("setCurrentTime", function(event) {
                player.currentTime = event.detail.goCurrentTime;
            });

            setInterval(function() {
                progress._internal_saveprogress(player.currentTime, player.duration);
            }, 200);
        },
        resource_video : function(view_id, return_currenttime, elementId, videosize, autoplay) {

            progress._internal_view_id = view_id;

            var config = {
                controls : [
                    'play-large', 'play', 'current-time', 'progress', 'duration', 'mute', 'volume',
                    'settings', 'pip', 'airplay', 'fullscreen'
                ],
                tooltips : {controls : true, seek : true},
                settings : ['speed', 'loop'],
                autoplay : autoplay,
                storage  : {enabled : true, key : "id-" + view_id},
                speed    : {selected : 1, options : [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 4]},
            };
            var player = new Plyr("#" + elementId, config);
            window.player = player;
            if (return_currenttime) {
                player.currentTime = return_currenttime;
            }
            document.addEventListener("setCurrentTime", function(event) {
                player.currentTime = event.detail.goCurrentTime;
            });

            setInterval(function() {
                progress._internal_saveprogress(player.currentTime, player.duration);
            }, 200);
        },

        vimeo : function(view_id, return_currenttime, vimeoid, elementId) {

            progress._internal_view_id = view_id;

            var iframe = document.getElementById(elementId);
            var player = new Vimeo.Player(iframe);

            if (return_currenttime) {
                player.setCurrentTime(return_currenttime);
            }

            document.addEventListener("setCurrentTime", function(event) {
                player.setCurrentTime(event.detail.goCurrentTime);
            });

            player.on('ended', function() {
                // console.log("Ended");
            });

            Promise.all([player.getVideoWidth(), player.getVideoHeight()]).then(function(dimensions) {
                var width = dimensions[0];
                var height = dimensions[1];

                console.log([width, height]);

                progress._internal_resize(width, height);
            });

            var duration = 0;
            setInterval(function() {
                if (duration > 1) {
                    player.getCurrentTime().then(function(_currenttime) {
                        _currenttime = parseInt(_currenttime);
                        progress._internal_saveprogress(_currenttime, duration);
                    });
                } else {
                    player.getDuration().then(function(_duration) {
                        duration = _duration;
                    });
                }
            }, 200);
        },

        drive : function(view_id, elementId, videosize) {

            progress._internal_view_id = view_id;

            progress._internal_saveprogress(1, 1);

            if (videosize == 6) {
                progress._internal_resize(4, 3);
            } else if (videosize == 7) {
                progress._internal_resize(16, 9);
            } else {
                progress._internal_resize(480, 640);
            }
        },

        _internal_resize : function(width, height) {

            $(window).resize(_resizePage);
            _resizePage();

            function _resizePage() {
                var videoBoxWidth = $("#supervideo_area_embed").width();
                var videoBoxHeight = videoBoxWidth * height / width;

                $("#supervideo_area_embed iframe").css({
                    width  : videoBoxWidth,
                    height : videoBoxHeight,
                });
            }
        },

        _internal_last_currenttime : -1,
        _internal_last_percent     : -1,
        _internal_assistido        : [],
        _internal_view_id          : 0,
        _internal_saveprogress     : function(currenttime, duration) {

            if (!duration) {
                return 0;
            }

            if (currenttime > 100) {
                if (progress._internal_last_currenttime == currenttime) {
                    return;
                }
            }

            progress._internal_last_currenttime = currenttime;

            if (progress._internal_assistido.length == 0) {

                var mapa = $("#mapa-visualizacao");
                var supervideo_view_mapa = [];
                try {
                    var mapa_json_base64 = mapa.attr('data-mapa');
                    if (mapa_json_base64) {
                        supervideo_view_mapa = JSON.parse(atob(mapa_json_base64));
                    }
                } catch (e) {
                    supervideo_view_mapa = [];
                }

                for (var i = 0; i < 100; i++) {

                    if (typeof supervideo_view_mapa[i] != "undefined") {
                        progress._internal_assistido[i] = supervideo_view_mapa[i];
                    } else {
                        progress._internal_assistido[i] = 0;
                    }

                    var mapaTitle = Math.floor(duration / 100 * i);

                    var hours = Math.floor(mapaTitle / 3600);
                    var minutes = (Math.floor(mapaTitle / 60)) % 60;
                    var seconds = mapaTitle % 60;

                    var mapa_item = $("<div id='mapa-visualizacao-" + i + "'>");
                    if (hours)
                        mapa_item.attr("title", "ir para " + hours + ":" + minutes + ":" + seconds);
                    else
                        mapa_item.attr("title", "ir para " + minutes + ":" + seconds);
                    mapa_item.attr("data-currenttime", mapaTitle);
                    mapa_item.click(function() {
                        var _setCurrentTime = $(this).attr("data-currenttime");

                        var event = document.createEvent('CustomEvent');
                        event.initCustomEvent('setCurrentTime', true, true, {goCurrentTime : parseInt(_setCurrentTime)});
                        document.dispatchEvent(event);
                    });
                    mapa.append(mapa_item);
                }
            }

            var percent = 0;
            if (currenttime != 0) {
                var posicao_video = parseInt(currenttime / duration * 100);
                progress._internal_assistido[posicao_video] = 1;

                for (var j = 0; j < 100; j++) {
                    if (progress._internal_assistido[j]) {
                        percent++;
                        $("#mapa-visualizacao-" + j).css({opacity : 1});
                    }
                }
            }

            if (progress._internal_last_percent == percent) {
                return;
            }
            progress._internal_last_percent = percent;

            Ajax.call([{
                methodname : 'mod_supervideo_services_save_progress',
                args       : {
                    view_id     : progress._internal_view_id,
                    currenttime : parseInt(currenttime),
                    duration    : parseInt(duration),
                    percent     : parseInt(percent),
                    mapa        : JSON.stringify(progress._internal_assistido)
                }
            }]);

            if (percent >= 0) {
                $("#sua-view span").html(percent + "%");
            }
        },

        _internal_add : function(accumulator, a) {
            return accumulator + a;
        }
    };
});
