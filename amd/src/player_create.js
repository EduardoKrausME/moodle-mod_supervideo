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
    return progress = {

        youtube : function(view_id, return_currenttime, elementId, videoid, videosize, showrel, showcontrols, showinfo, autoplay) {

            progress._internal_view_id = view_id;

            var player = new YT.Player(elementId, {
                suggestedQuality : 'large',
                videoId          : videoid,
                width            : '100%',
                playerVars       : {
                    rel      : showrel,
                    controls : showcontrols,
                    showinfo : showinfo,
                    autoplay : autoplay,
                },
                events           : {
                    'onReady'       : function(event) {

                        if (videosize == 1) {
                            progress._internal_resize(16, 9);
                        } else {
                            progress._internal_resize(4, 3);
                        }
                        progress._internal_max_height();

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
            }, 150);
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
            var player = new PlayerRender("#" + elementId, config);
            window.player = player;
            if (return_currenttime) {
                var video = document.getElementById(elementId);
                video.addEventListener("loadedmetadata", function(event) {
                    player.currentTime = return_currenttime;
                });
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
            var player = new PlayerRender("#" + elementId, config);
            window.player = player;
            if (return_currenttime) {
                var video = document.getElementById(elementId);
                video.addEventListener("loadedmetadata", function(event) {
                    player.currentTime = return_currenttime;

                    progress._internal_max_height();
                });
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

                progress._internal_resize(width, height);
                progress._internal_max_height();
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
            }, 300);
        },

        drive : function(view_id, elementId, videosize) {

            progress._internal_view_id = view_id;

            progress._internal_saveprogress(1, 1);

            if (videosize == 6) {
                progress._internal_resize(4, 3);
                progress._internal_max_height();
            } else if (videosize == 7) {
                progress._internal_resize(16, 9);
                progress._internal_max_height();
            } else {
                progress._internal_resize(480, 640);
            }

            $("#mapa-visualizacao").hide();
        },

        _internal_resize : function(width, height) {

            function _resizePage() {
                var videoBoxWidth = $("#supervideo_area_embed").width();
                var videoBoxHeight = videoBoxWidth * height / width;

                $("#supervideo_area_embed iframe").css({
                    //width  : videoBoxWidth,
                    height : videoBoxHeight,
                });
            }

            $(window).resize(_resizePage);
            _resizePage();


            var element = $("#supervideo_area_embed");
            var lastWidth = element.width();
            setInterval(function() {
                if (lastWidth === element.width()) return;
                lastWidth = element.width();

                _resizePage();
            }, 500);


            return element;

        },

        _internal_max_height : function() {
            $(window).resize(_resizePage);
            _resizePage();

            function _resizePage() {

                var $supervideo_area_embed = $("#supervideo_area_embed");

                $supervideo_area_embed.css({
                    "max-height" : "inherit",
                    "height"     : "inherit",
                });

                var header_height = ($("#header") && $("#header").height()) || 60;
                var window_height = $(window).height();

                var player_max_height = window_height - header_height;

                if ($supervideo_area_embed.height() > player_max_height) {
                    $supervideo_area_embed.css({
                        "max-height" : player_max_height,
                        "height"     : player_max_height
                    });
                }
            }
        },

        _internal_last_posicao_video : -1,
        _internal_last_percent       : -1,
        _internal_assistido          : [],
        _internal_view_id            : 0,
        _internal_progress_length    : 100,
        _internal_saveprogress       : function(currenttime, duration) {

            if (duration && progress._internal_assistido.length == 0) {
                progress._internal_progress_create(duration);
            }

            if (!duration || !progress._internal_show_mapa) {
                return 0;
            }

            var percent = 0;
            //if (currenttime != 0) {
            var posicao_video = parseInt(currenttime / duration * progress._internal_progress_length);

            if (progress._internal_last_posicao_video == posicao_video) {
                return;
            }
            progress._internal_last_posicao_video = posicao_video;
            progress._internal_assistido[posicao_video] = 1;
            // console.log(progress._internal_assistido);

            for (var j = 0; j < progress._internal_progress_length; j++) {
                if (progress._internal_assistido[j]) {
                    percent++;
                    $("#mapa-visualizacao-" + j).css({opacity : 1});
                }
            }

            if (progress._internal_progress_length != 100) {
                percent = Math.floor(percent / progress._internal_progress_length * 100);
            }
            //}

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
                $("#seu-mapa-view span").html(percent + "%");
            }
        },

        _internal_show_mapa       : false,
        _internal_progress_create : function(duration) {
            var $mapa = $("#mapa-visualizacao .mapa");
            if (!$mapa.length) {
                return;
            }
            progress._internal_show_mapa = true;

            var supervideo_view_mapa = [];
            try {
                var mapa_json_base64 = $mapa.attr('data-mapa');
                if (mapa_json_base64) {
                    supervideo_view_mapa = JSON.parse(atob(mapa_json_base64));
                }
            } catch (e) {
                supervideo_view_mapa = [];
            }

            if (duration < 100) {
                progress._internal_progress_length = Math.floor(duration);
            }
            for (var i = 0; i < progress._internal_progress_length; i++) {
                if (typeof supervideo_view_mapa[i] != "undefined") {
                    progress._internal_assistido[i] = supervideo_view_mapa[i];
                } else {
                    progress._internal_assistido[i] = 0;
                }
                var $mapa_item = $("<div id='mapa-visualizacao-" + i + "'>");
                $mapa.append($mapa_item);

                // Mapa Clique
                var mapaTitle = Math.floor(duration / progress._internal_progress_length * i);

                var hours = Math.floor(mapaTitle / 3600);
                var minutes = (Math.floor(mapaTitle / 60)) % 60;
                var seconds = mapaTitle % 60;


                var tempo = minutes + ":" + seconds;
                if (hours) {
                    tempo = hours + ":" + minutes + ":" + seconds;
                }
                var $mapa_clique = $("<div id='mapa-visualizacao-" + i + "'>");
                // $mapa_clique.attr("title", M.util.get_string('seu_mapa_ir_para', 'mod_supervideo', tempo));
                $mapa_clique.attr("title", 'Ir para ' + tempo);

                $mapa_clique.attr("data-currenttime", mapaTitle);

                $mapa_clique.click(function() {
                    var _setCurrentTime = $(this).attr("data-currenttime");

                    var event = document.createEvent('CustomEvent');
                    event.initCustomEvent('setCurrentTime', true, true, {goCurrentTime : parseInt(_setCurrentTime)});
                    document.dispatchEvent(event);
                });
                $("#mapa-visualizacao .clique").append($mapa_clique);
            }
        },

        _internal_add : function(accumulator, a) {
            return accumulator + a;
        }
    };
});
