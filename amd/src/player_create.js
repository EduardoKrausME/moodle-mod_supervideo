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

        ottflix : function(view_id, start_currenttime, elementId, videoid) {
            window.addEventListener('message', function receiveMessage(event) {
                console.trace(event.data);

                if (event.data.origem == 'OTTFLIX-player' && event.data.name == "progress") {
                    progress._internal_saveprogress(event.data.currentTime, event.data.duration);
                }
            });
        },

        youtube : function(view_id, start_currenttime, elementId, videoid, playersize, showcontrols, autoplay) {

            progress._internal_view_id = view_id;

            var playerVars = {
                rel         : 0,
                controls    : showcontrols,
                autoplay    : autoplay,
                playsinline : 1,
            };

            if (start_currenttime) {
                playerVars.start = start_currenttime;
            }

            if (YT && YT.Player) {
                var player = new YT.Player(elementId, {
                    suggestedQuality : 'large',
                    videoId          : videoid,
                    width            : '100%',
                    playerVars       : playerVars,
                    events           : {
                        'onReady'       : function(event) {

                            if (playersize == 1) {
                                progress._internal_resize(16, 9);
                            } else if (playersize == 2) {
                                progress._internal_resize(4, 3);
                            } else if (playersize.indexOf("x")) {
                                var sizes = playersize.split("x");
                                progress._internal_resize(sizes[0], sizes[1]);
                            }

                            progress._internal_max_height();
                            document.addEventListener("setCurrentTime", function(event) {
                                player.seekTo(event.detail.goCurrentTime);
                            });
                        },
                        'onStateChange' : function(event) {
                        }
                    }
                });
            } else {
                var html =
                        '<div class="alert alert-danger">' +
                        'Error loading the JavaScript at https://www.youtube.com/iframe_api. ' +
                        'Please check for any Security Policy restrictions.' +
                        '</div>';
                $("#supervideo_area_embed").html(html);
            }

            setInterval(function() {
                if (player && player.getCurrentTime != undefined) {
                    progress._internal_saveprogress(player.getCurrentTime(), player.getDuration() - 1);
                }
            }, 150);
        },

        resource_audio : function(view_id, start_currenttime, elementId, fullurl, autoplay, showcontrols) {

            progress._internal_view_id = view_id;

            var embedparameters = "";
            if (showcontrols) embedparameters += "controls ";
            if (autoplay) embedparameters += "autoplay ";

            var embed =
                    "<audio " + embedparameters + " crossorigin playsinline >" +
                    "    <source src='" + fullurl + "' type='audio/mp3'>" +
                    "</audio>";
            $("#" + elementId).html(embed);

            var config = {
                controls :
                    showcontrols ? [
                        'play', 'progress', 'current-time', 'mute', 'volume', 'pip', 'airplay', 'duration'
                    ] : [
                        'play'
                    ],
                tooltips : {controls : showcontrols, seek : showcontrols},
                settings : ['speed', 'loop'],
                autoplay : autoplay ? true : false,
                storage  : {enabled : true, key : "id-" + view_id},
                speed    : {selected : 1, options : [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 4]},
                seekTime : parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
            };
            var player = new PlayerRender("#" + elementId + " audio", config);
            player.on("ready", function() {
                if (start_currenttime) {
                    player.currentTime = parseInt(start_currenttime);
                    setTimeout(function() {
                        player.currentTime = parseInt(start_currenttime);
                    }, 1000);

                    if (!autoplay) {
                        player.pause();
                    }
                }
            });

            document.addEventListener("setCurrentTime", function(event) {
                player.currentTime = event.detail.goCurrentTime;
            });

            setInterval(function() {
                progress._internal_saveprogress(player.currentTime, player.duration);
            }, 200);
        },

        resource_video : function(view_id, start_currenttime, elementId, fullurl, autoplay, showcontrols) {

            progress._internal_view_id = view_id;

            var embedparameters = "";
            if (showcontrols) embedparameters += "controls ";
            if (autoplay) embedparameters += "autoplay ";

            var embed =
                    "<video " + embedparameters + " crossorigin playsinline>" +
                    "    <source src='" + fullurl + "'>" +
                    "</video>";
            $("#" + elementId).html(embed);

            var config = {
                controls :
                    showcontrols ? [
                        'play-large', 'play', 'current-time', 'progress', 'duration', 'mute', 'volume',
                        'settings', 'pip', 'airplay', 'fullscreen'
                    ] : [
                        'play-large', 'play'
                    ],
                tooltips : {controls : showcontrols, seek : showcontrols},
                settings : ['speed', 'loop'],
                storage  : {enabled : true, key : "id-" + view_id},
                speed    : {selected : 1, options : [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 4]},
                // autoplay : autoplay ? 1 : 0,
                seekTime : parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
            };
            var player = new PlayerRender("#" + elementId + " video", config);

            player.on("ready", function() {
                if (start_currenttime) {
                    player.currentTime = parseInt(start_currenttime);
                    setTimeout(function() {
                        player.currentTime = parseInt(start_currenttime);
                    }, 1000);

                    if (!autoplay) {
                        player.pause();
                    }
                }
                progress._internal_max_height();
            });

            var video = document.getElementById(elementId);
            video.addEventListener("loadedmetadata", function(event) {
                progress._internal_max_height();
            });

            document.addEventListener("setCurrentTime", function(event) {
                player.currentTime = event.detail.goCurrentTime;
            });

            setInterval(function() {
                progress._internal_saveprogress(player.currentTime, player.duration);
            }, 200);
        },

        vimeo : function(view_id, start_currenttime, vimeoid, elementId) {

            progress._internal_view_id = view_id;

            var iframe = document.getElementById(elementId);
            var player = new Vimeo.Player(iframe);

            if (start_currenttime) {
                player.setCurrentTime(start_currenttime);
            }

            document.addEventListener("setCurrentTime", function(event) {
                player.setCurrentTime(event.detail.goCurrentTime);
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

        drive : function(view_id, elementId, playersize) {

            progress._internal_view_id = view_id;

            progress._internal_saveprogress(1, 1);

            if (playersize == 5) {
                progress._internal_resize(480, 640);
            } else if (playersize == 6) {
                progress._internal_resize(4, 3);
                progress._internal_max_height();
            } else if (playersize == 7) {
                progress._internal_resize(16, 9);
                progress._internal_max_height();
            } else if (playersize.indexOf("x")) {
                var sizes = playersize.split("x");
                progress._internal_resize(sizes[0], sizes[1]);
                progress._internal_max_height();
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

            currenttime = Math.floor(currenttime);
            duration = Math.floor(duration);

            if (!duration) {
                return 0;
            }

            if (duration && progress._internal_assistido.length == 0) {
                progress._internal_progress_create(duration);
            }

            if (progress._internal_progress_length < 100) {
                posicao_video = currenttime;
            } else {
                var posicao_video = parseInt(currenttime / duration * progress._internal_progress_length);
            }

            if (progress._internal_last_posicao_video == posicao_video) return;
            progress._internal_last_posicao_video = posicao_video;

            if (posicao_video) {
                progress._internal_assistido[posicao_video] = 1;
            }

            var percent = 0;
            for (var j = 1; j <= progress._internal_progress_length; j++) {
                if (progress._internal_assistido[j]) {
                    percent++;
                    $("#mapa-visualizacao-" + j).css({opacity : 1});
                }
            }

            if (progress._internal_progress_length < 100) {
                percent = Math.floor(percent / progress._internal_progress_length * 100);
            }

            if (progress._internal_last_percent == percent) return;
            progress._internal_last_percent = percent;

            if (currenttime) {
                Ajax.call([{
                    methodname : 'mod_supervideo_progress_save',
                    args       : {
                        view_id     : progress._internal_view_id,
                        currenttime : parseInt(currenttime),
                        duration    : parseInt(duration),
                        percent     : parseInt(percent),
                        mapa        : JSON.stringify(progress._internal_assistido)
                    }
                }]);
            }

            if (percent >= 0) {
                $("#seu-mapa-view span").html(percent + "%");
            }
        },

        _internal_progress_create : function(duration) {

            var $mapa = $("#mapa-visualizacao .mapa");
            if (!$mapa.length) {
                return;
            }

            var supervideo_view_mapa = [];
            try {
                var mapa_json_base64 = $mapa.attr('data-mapa');
                if (mapa_json_base64) {
                    supervideo_view_mapa = JSON.parse(atob(mapa_json_base64));
                }
            } catch (e) {
                supervideo_view_mapa = [];
            }

            if (Math.floor(duration) <= 100) {
                progress._internal_progress_length = Math.floor(duration);
            }
            for (var i = 1; i <= progress._internal_progress_length; i++) {
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
                var $mapa_clique =
                        $("<div id='mapa-visualizacao-" + i + "'>")
                            .attr("title", 'Ir para ' + tempo)
                            .attr("data-currenttime", mapaTitle)
                            .click(function() {
                                var _setCurrentTime = $(this).attr("data-currenttime");
                                _setCurrentTime = parseInt(_setCurrentTime);

                                var event = document.createEvent('CustomEvent');
                                event.initCustomEvent('setCurrentTime', true, true, {goCurrentTime : _setCurrentTime});
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
