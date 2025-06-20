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

define(["jquery", "core/ajax", "mod_supervideo/player_render", "jqueryui"], function($, Ajax, PlayerRender) {
    let progress = {

        ottflix: function(view_id, start_currenttime, elementId, identifier) {
            // progress._internal_resize(16, 9);
            window.addEventListener("message", function receiveMessage(event) {
                if (event.data.origem == "OTTFLIX-player") {

                    // console.log(event.data);

                    if (event.data.identifier == identifier) {
                        if (event.data.name == "progress") {
                            progress._internal_saveprogress(event.data.currentTime, event.data.duration);
                        } else if (event.data.name == "loadeddata") {
                            // Tamanho vem do OTTFlix
                            // progress._internal_resize(event.data.width, event.data.height);
                        }
                    }
                }
            });

            let morewidth = $("#distraction-free-mode-header .more-nav").width();
            $("#ottflix-tabs")
                .show()
                .css({"right": morewidth + 11})
                .tabs({
                    activate: function(event, ui) {
                        let panel = ui.newPanel;
                        let iframe = panel.find("iframe");

                        if (iframe.length && !iframe.attr("src")) {
                            iframe.attr("src", iframe.data("src"));
                        }
                    }
                });
        },

        youtube: function(view_id, start_currenttime, elementId, videoid, playersize, showcontrols, autoplay) {
            progress._internal_view_id = view_id;

            let playerVars = {
                rel: 0,
                controls: showcontrols,
                autoplay: autoplay,
                playsinline: 1,
                start: start_currenttime ? start_currenttime : 0,
            };

            if (YT && YT.Player) {
                let player = new YT.Player(elementId, {
                    suggestedQuality: "large",
                    videoId: videoid,
                    width: "100%",
                    playerVars: playerVars,
                    events: {
                        "onReady": function(event) {

                            let sizes = playersize.split("x");
                            console.log(sizes);
                            if (sizes && sizes[1]) {
                                console.log(sizes);
                                progress._internal_resize(sizes[0], sizes[1]);
                            } else {
                                progress._internal_resize(16, 9);
                            }

                            document.addEventListener("setCurrentTime", function(event) {
                                player.seekTo(event.detail.goCurrentTime);
                            });
                        },
                        "onStateChange": function(event) {
                            console.log(event);
                        }
                    }
                });
            } else {
                let html =
                        `<div class="alert alert-danger">
                             Error loading the JavaScript at https://www.youtube.com/iframe_api
                             Please check for any Security Policy restrictions.
                         </div>`;
                $("#supervideo_area_embed").html(html);
            }

            setInterval(function() {
                if (player && player.getCurrentTime != undefined) {
                    progress._internal_saveprogress(player.getCurrentTime(), player.getDuration() - 1);
                }
            }, 150);
        },

        resource_audio: function(view_id, start_currenttime, elementId) {
            $("body").removeClass("distraction-free-mode");

            progress._internal_view_id = view_id;

            let $element = $(`#${elementId}`);
            let fullurl = $element.attr("data-videourl");
            let autoplay = $element.attr("data-autoplay");
            let showcontrols = $element.attr("data-showcontrols");
            let controls = $element.attr("data-controls");
            let speed = $element.attr("data-speed");

            let embedparameters = "";
            if (showcontrols) {
                embedparameters += "controls ";
            }
            if (autoplay) {
                embedparameters += "autoplay ";
            }

            let embed = `<audio ${embedparameters} crossorigin playsinline id="${elementId}_audio"></audio>`;
            $element.html(embed);
            progress._error_load(`${elementId}_audio`);
            $(`#${elementId}_audio`).attr("src", fullurl);

            let config = {
                controls: controls.split(","),
                tooltips: {controls: showcontrols, seek: showcontrols},
                settings: ["speed", "loop"],
                autoplay: !!autoplay,
                storage: {enabled: true, key: "id-" + view_id},
                speed: {selected: speed.length > 3, options: speed.split(",")},
                seekTime: parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
            };
            let player = new PlayerRender(`#${elementId} audio`, config);
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

        resource_video: function(view_id, start_currenttime, elementId) {
            progress._internal_view_id = view_id;

            const $element = $(`#${elementId}`);
            const fullurl = $element.attr("data-videourl");
            const autoplay = $element.attr("data-autoplay");
            const showcontrols = $element.attr("data-showcontrols");
            const controls = $element.attr("data-controls");
            const speed = $element.attr("data-speed");

            let embedparameters = "";
            if (showcontrols) {
                embedparameters += "controls ";
            }
            if (autoplay == "true") {
                embedparameters += "autoplay ";
            }

            $element.html(`<video ${embedparameters} crossorigin playsinline id="${elementId}_video"></video>`);
            progress._error_load(`${elementId}_video`);
            $(`#${elementId}_video`).attr("src", fullurl);

            const config = {
                controls: controls.split(","),
                autoplay: !!autoplay,
                tooltips: {controls: showcontrols, seek: showcontrols},
                settings: ["speed", "loop"],
                storage: {enabled: true, key: "id-" + view_id},
                speed: {selected: speed.length > 3, options: speed.split(",")},
                seekTime: parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
            };
            const player = new PlayerRender(`#${elementId} video`, config);

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
                progress._internal_resize(16, 9);
            });

            const video = document.getElementById(elementId);
            video.addEventListener("loadedmetadata", function(event) {
                progress._internal_resize(video.videoWidth, video.videoHeight);
            });

            document.addEventListener("setCurrentTime", function(event) {
                player.currentTime = event.detail.goCurrentTime;
            });

            setInterval(function() {
                progress._internal_saveprogress(player.currentTime, player.duration);
            }, 200);
        },

        vimeo: function(view_id, start_currenttime, vimeoid, elementId) {
            progress._internal_view_id = view_id;

            const iframe = document.getElementById(elementId);
            const player = new Vimeo.Player(iframe);

            if (start_currenttime) {
                player.setCurrentTime(start_currenttime);
            }

            document.addEventListener("setCurrentTime", function(event) {
                player.setCurrentTime(event.detail.goCurrentTime);
            });

            Promise.all([player.getVideoWidth(), player.getVideoHeight()]).then(function(dimensions) {
                const width = dimensions[0];
                const height = dimensions[1];

                progress._internal_resize(width, height);
            });

            let duration = 0;
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

        drive: function(view_id, elementId, playersize) {
            $("#mapa-visualizacao").hide();

            progress._internal_view_id = view_id;
            progress._internal_saveprogress(1, 1);

            if (playersize == "4x3") {
                progress._internal_resize(4, 3);
            } else if (playersize == "16x9") {
                progress._internal_resize(16, 9);
            } else {
                $("body").removeClass("distraction-free-mode");

                progress._internal_resize(100, 640);
            }
        },

        _error_load: function(elementId) {
            function errorF(e) {
                $(`#${elementId}, #mapa-visualizacao`).hide();
                //$("body").removeClass("distraction-free-mode");

                switch (e.target.error.code) {
                    case e.target.error.MEDIA_ERR_ABORTED:
                        $("#error_media_err_aborted").show();
                        break;
                    case e.target.error.MEDIA_ERR_NETWORK:
                        $("#error_media_err_network").show();
                        break;
                    case e.target.error.MEDIA_ERR_DECODE:
                        $("#error_media_err_decode").show();
                        break;
                    case e.target.error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                        $("#error_media_err_src_not_supported").show();
                        break;
                    default:
                        $("#error_default").show();
                        break;
                }
            }

            let videoElem = document.getElementById(elementId);
            videoElem.addEventListener("error", errorF);
        },

        _internal_resize__width: 0,
        _internal_resize__height: 0,
        _internal_resize: function(width, height) {
            console.log([width, height]);
            progress._internal_resize__width = width;
            progress._internal_resize__height = height;

            $(window).resize(progress._internal_max_height__resizePage);
            progress._internal_max_height__resizePage();
        },

        _internal_max_height__resizePage: function() {

            let windowHeight = $(window).height();
            if ($("body").hasClass("distraction-free-mode")) {
                let $supervideoArea = $("#supervideo_area_embed video,#supervideo_area_embed iframe");

                $supervideoArea.css({
                    "max-height": "inherit",
                    "height": "inherit",
                });

                let removeHeight = 54 + 10; // $("#distraction-free-mode-header").height() + padding;
                let $activity = $(".activity-navigation");
                if ($activity.length && !$activity.is(":hidden")) {
                    removeHeight += $activity.height();
                }

                let $mapa = $("#mapa-visualizacao");
                if ($mapa.length && !$mapa.is(":hidden")) {
                    removeHeight += 12;
                }

                let playerMaxHeight = windowHeight - removeHeight;
                $("#supervideo_area_embed").css({
                    "max-height": playerMaxHeight,
                });
                $supervideoArea.css({
                    "max-height": playerMaxHeight,
                    "height": playerMaxHeight,
                });
            } else {
                if (document.querySelector("#supervideo_area_embed iframe")) {
                    let $supervideo_area_embed = $("#supervideo_area_embed");

                    let maxHeight = $(window).height() - $("#header").height();
                    let width = $supervideo_area_embed.width();
                    let height = (width * progress._internal_resize__height) / progress._internal_resize__width;

                    if (height < maxHeight) {
                        let ratio = (progress._internal_resize__height / progress._internal_resize__width) * 100;
                        if (ratio > 10) {
                            $supervideo_area_embed.css({
                                paddingBottom: `${ratio}%`,
                                width: "100%",
                            });
                        }
                    } else {
                        // let newWidth = (maxHeight * progress._internal_resize__width) / progress._internal_resize__height;
                        $supervideo_area_embed.css({
                            // width         : newWidth,
                            // margin        : "0 auto",
                            height: maxHeight,
                            maxHeight: maxHeight,
                            paddingBottom: "56.25%",
                        });
                    }
                }
            }
        },

        _internal_last_posicao_video: -1,
        _internal_last_percent: -1,
        _internal_assistido: [],
        _internal_view_id: 0,
        _internal_progress_length: 100,
        _internal_sizenum: -1,
        _internal_saveprogress: function(currenttime, duration) {

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
                let posicao_video = parseInt(currenttime / duration * progress._internal_progress_length);
            }

            if (progress._internal_last_posicao_video == posicao_video) return;
            progress._internal_last_posicao_video = posicao_video;

            if (posicao_video) {
                progress._internal_assistido[posicao_video] = 1;
            }

            let percent = 0;
            for (let j = 1; j <= progress._internal_progress_length; j++) {
                if (progress._internal_assistido[j]) {
                    percent++;
                    $("#mapa-visualizacao-" + j).css({opacity: 1});
                }
            }

            if (progress._internal_progress_length < 100) {
                percent = Math.floor(percent / progress._internal_progress_length * 100);
            }

            if (progress._internal_last_percent == percent) {
                return;
            }
            progress._internal_last_percent = percent;

            if ($("body").hasClass("distraction-free-mode")) {
                let $mapa = $("#mapa-visualizacao");
                if ($mapa.length && !$mapa.is(":hidden")) {
                    if (currenttime > (duration * .90)) {
                        if (progress._internal_sizenum != 1) {
                            $(".activity-navigation").hide();
                            $mapa.addClass("fixed-booton");
                            progress._internal_max_height__resizePage();
                            progress._internal_sizenum = 1;
                        }
                    } else {
                        if (progress._internal_sizenum != 2) {
                            $(".activity-navigation").show();
                            $mapa.removeClass("fixed-booton");
                            progress._internal_max_height__resizePage();
                            progress._internal_sizenum = 2;
                        }
                    }
                } else {
                    if (progress._internal_sizenum != 3) {
                        $(".activity-navigation").show();
                        $mapa.removeClass("fixed-booton");
                        progress._internal_max_height__resizePage();
                        progress._internal_sizenum = 3;
                    }
                }
            }

            if (currenttime) {
                Ajax.call([{
                    methodname: "mod_supervideo_progress_save",
                    args: {
                        view_id: progress._internal_view_id,
                        currenttime: parseInt(currenttime),
                        duration: parseInt(duration),
                        percent: parseInt(percent),
                        mapa: JSON.stringify(progress._internal_assistido)
                    }
                }]);
            }

            if (percent >= 0) {
                $("#seu-mapa-view span").html(percent + "%");
            }
        },

        _internal_progress_create: function(duration) {

            let $mapa = $("#mapa-visualizacao .mapa");
            if (!$mapa.length) {
                return;
            }

            let supervideo_view_mapa = [];
            try {
                let mapa_json_base64 = $mapa.attr("data-mapa");
                if (mapa_json_base64) {
                    supervideo_view_mapa = JSON.parse(atob(mapa_json_base64));
                }
            } catch (e) {
                supervideo_view_mapa = [];
            }

            if (Math.floor(duration) <= 100) {
                progress._internal_progress_length = Math.floor(duration);
            }
            for (let i = 1; i <= progress._internal_progress_length; i++) {
                if (typeof supervideo_view_mapa[i] != "undefined") {
                    progress._internal_assistido[i] = supervideo_view_mapa[i];
                } else {
                    progress._internal_assistido[i] = 0;
                }
                let $mapa_item = $("<div id='mapa-visualizacao-" + i + "'>");
                $mapa.append($mapa_item);

                // Mapa Clique
                let mapaTitle = Math.floor(duration / progress._internal_progress_length * i);

                let hours = Math.floor(mapaTitle / 3600);
                let minutes = (Math.floor(mapaTitle / 60)) % 60;
                let seconds = mapaTitle % 60;

                let tempo = minutes + ":" + seconds;
                if (hours) {
                    tempo = hours + ":" + minutes + ":" + seconds;
                }
                let $mapa_clique =
                        $("<div id='mapa-visualizacao-" + i + "'>")
                            .attr("title", "Ir para " + tempo)
                            .attr("data-currenttime", mapaTitle)
                            .click(function() {
                                let _setCurrentTime = $(this).attr("data-currenttime");
                                _setCurrentTime = parseInt(_setCurrentTime);

                                let event = document.createEvent("CustomEvent");
                                event.initCustomEvent("setCurrentTime", true, true, {goCurrentTime: _setCurrentTime});
                                document.dispatchEvent(event);
                            });
                $("#mapa-visualizacao .clique").append($mapa_clique);
            }
        },

        _internal_add: function(accumulator, a) {
            return accumulator + a;
        },

        error_idnotfound: function() {
            $("body").removeClass("distraction-free-mode");
        },

        secondary_navigation: function(course_id) {
            let newHeader = $(`<div id="distraction-free-mode-header"></div>`);
            $("#page-header").after(newHeader);

            let back = `<a href="${M.cfg.wwwroot}/course/view.php?id=${course_id}" class="back-icon"></a>`;
            newHeader.append(back);

            let $icon = $(".activityiconcontainer.content");
            $icon.addClass("activityiconcontainer-icon");
            newHeader.append($icon.clone());

            let $title = $(".page-header-headings h1");
            $title.addClass("page-header-free");
            newHeader.append($title.clone());

            let $navAdmin = $(".secondary-navigation .navigation .nav-tabs");
            $navAdmin.addClass("free-secondary-navigation");
            newHeader.append($navAdmin.clone());

            let $completionInfo = $("#id-activity-header .completion-info, .activity-header .completion-info");
            $completionInfo.addClass("completion-free");
            newHeader.append($completionInfo.clone());

            $("#ottflix-tabs-ul").css({
                "right": $("#distraction-free-mode-header ul").width()
            });
        },
    };
    return progress;
});
