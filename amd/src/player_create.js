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
 * player_create.js
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/notification", "mod_supervideo/player_render", "jqueryui", "mod_supervideo/caption_controller", "mod_supervideo/marker_controller"], function ($, Ajax, Notification, PlayerRender, jqueryui, Captions, Markers) {
    var youtubeApiCallbacks = [];
    var youtubeApiHooked = false;
    var youtubeApiRequested = false;

    /**
     * Own one HLS playback lifecycle. Only confirmed VOD may report watched segments.
     * @param {HTMLVideoElement} media Video element.
     * @param {String} url Original playlist URL.
     * @param {Object} callbacks Player hooks.
     * @returns {Object} Controller.
     */
    function createHlsController(media, url, callbacks) {
        var active = true;
        var hls = null;
        var mode = "unknown";
        var timer = null;
        var poll = null;
        var abort = null;
        var inspectionTimer = null;
        var script = null;
        var ready = false;
        var restored = false;
        var networkRecovery = false;
        var mediaRecovery = false;
        var native = false;
        var scriptUrl = "https://cdn.jsdelivr.net/npm/hls.js@1.7.3/dist/hls.min.js";

        function setMode(value) {
            if (!active || mode === "live" || mode === value) {
                return;
            }
            mode = value;
            callbacks.mode(mode);
            restore();
        }

        function validPosition(time, duration) {
            return active && mode === "vod" && Number.isFinite(time) && Number.isFinite(duration) &&
                time >= 0 && duration >= 1 && time <= duration + 1 && duration < 4294967296;
        }

        function restore() {
            if (!restored && validPosition(0, media.duration) && media.readyState >= 1) {
                restored = true;
                var time = Number(callbacks.resume);
                if (Number.isFinite(time) && time > 0 && time < media.duration) {
                    try {
                        media.currentTime = time;
                    } catch (error) {
                        // Seeking is optional when the media does not expose a seekable range yet.
                    }
                }
            }
        }

        function onMetadata() {
            restore();
            if (native) {
                loaded();
            }
        }

        function clearLoading() {
            if (timer !== null) {
                clearTimeout(timer);
                timer = null;
            }
            if (poll !== null) {
                clearTimeout(poll);
                poll = null;
            }
        }

        function loaded() {
            if (!active || ready) {
                return;
            }
            ready = true;
            clearLoading();
            callbacks.ready(hls);
        }

        function stop() {
            if (!active) {
                return;
            }
            active = false;
            clearLoading();
            if (abort) {
                abort.abort();
            }
            if (inspectionTimer !== null) {
                clearTimeout(inspectionTimer);
            }
            if (script) {
                script.removeEventListener("error", libraryFailed);
            }
            media.removeEventListener("loadedmetadata", onMetadata);
            media.removeEventListener("durationchange", restore);
            media.removeEventListener("error", nativeError);
            if (hls) {
                hls.destroy();
                hls = null;
            }
            media.pause();
            media.removeAttribute("src");
            media.load();
        }

        function fail(key) {
            if (active) {
                stop();
                callbacks.error(key);
            }
        }

        function nativeError() {
            if (native) {
                fail("hls_error");
            }
        }

        function libraryFailed() {
            if (script && script.parentNode && !window.Hls) {
                script.parentNode.removeChild(script);
            }
            fail("hls_libraryerror");
        }

        // Native media APIs do not reliably distinguish a finite live DVR window from VOD.
        // Only an ended media playlist is sufficient evidence; failure to inspect it is not a playback error.
        async function inspectNativePlaylist(address, depth) {
            if (!active || !abort) {
                return;
            }
            var response = await fetch(address, {signal: abort.signal, credentials: "same-origin"});
            if (!response.ok) {
                return;
            }
            var text = await response.text();
            if (!active || text.length > 2097152 || !text.trimStart().startsWith("#EXTM3U")) {
                return;
            }
            if (/^#EXTINF:/m.test(text)) {
                setMode(/^#EXT-X-ENDLIST\s*$/m.test(text) ? "vod" : "live");
                return;
            }
            if (depth === 0) {
                var lines = text.split(/\r?\n/);
                for (var index = 0; index < lines.length - 1; index++) {
                    if (lines[index].startsWith("#EXT-X-STREAM-INF:")) {
                        var next = new URL(lines[index + 1].trim(), response.url || address);
                        if (next.protocol === "https:" || next.protocol === "http:") {
                            await inspectNativePlaylist(next.href, 1);
                        }
                        return;
                    }
                }
            }
        }

        function initialise() {
            if (!active) {
                return;
            }
            if (window.Hls && typeof window.Hls.isSupported === "function") {
                if (!window.Hls.isSupported()) {
                    fail("hls_unsupported");
                    return;
                }
                var Hls = window.Hls;
                hls = new Hls({enableWebVTT: true, renderTextTracksNatively: true});
                hls.on(Hls.Events.LEVEL_LOADED, function(event, data) {
                    if (data.details && typeof data.details.live === "boolean") {
                        setMode(data.details.live ? "live" : "vod");
                    }
                });
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    loaded();
                });
                hls.on(Hls.Events.ERROR, function(event, data) {
                    if (!active || !data.fatal) {
                        return;
                    }
                    if (data.type === Hls.ErrorTypes.MEDIA_ERROR && !mediaRecovery) {
                        mediaRecovery = true;
                        hls.recoverMediaError();
                    } else if (data.type === Hls.ErrorTypes.NETWORK_ERROR && !networkRecovery &&
                            !(data.response && [401, 403].includes(data.response.code))) {
                        networkRecovery = true;
                        hls.startLoad();
                    } else {
                        fail("hls_error");
                    }
                });
                hls.loadSource(url);
                hls.attachMedia(media);
            } else {
                poll = setTimeout(initialise, 100);
            }
        }

        function start() {
            callbacks.mode("unknown");
            media.addEventListener("loadedmetadata", onMetadata);
            media.addEventListener("durationchange", restore);
            media.addEventListener("error", nativeError);
            timer = setTimeout(function() {
                fail(native || hls ? "hls_error" : "hls_libraryerror");
            }, 20000);
            native = Boolean(media.canPlayType("application/vnd.apple.mpegurl") ||
                media.canPlayType("application/x-mpegURL"));
            if (native) {
                if (typeof fetch === "function" && typeof AbortController === "function") {
                    abort = new AbortController();
                    inspectionTimer = setTimeout(function() { abort.abort(); }, 10000);
                    inspectNativePlaylist(url, 0).catch(function() {
                        // Playback can succeed even when the manifest cannot be inspected through CORS.
                    }).finally(function() {
                        clearTimeout(inspectionTimer);
                        inspectionTimer = null;
                    });
                }
                media.src = url;
                return;
            }
            if (!window.Hls) {
                script = document.querySelector('script[src="' + scriptUrl + '"]');
                if (!script) {
                    script = document.createElement("script");
                    script.src = scriptUrl;
                    script.integrity = "sha256-oS5+4c1kpp3NsxQVfkXa/LpwW/sLFEC3k1yyZdN0Qj4=";
                    script.crossOrigin = "anonymous";
                    script.addEventListener("error", libraryFailed);
                    document.head.appendChild(script);
                } else {
                    script.addEventListener("error", libraryFailed);
                }
            }
            initialise();
        }

        return {start: start, stop: stop, validPosition: validPosition};
    }

    var player_create = {
        /**
         * Create the optional button that jumps from a marker to the next one.
         *
         * @param {String} elementId Player element ID.
         * @param {Object} markerConfig Normalised marker configuration.
         * @param {Function} seekTo Player-specific seek callback.
         * @param {HTMLElement} preferredHost Optional element which should own the button.
         * @returns {Object} Marker controller.
         */
        _markers_create: function(elementId, markerConfig, seekTo, preferredHost, position) {
            var element = document.getElementById(elementId);
            return Markers.attach(preferredHost || (element && element.parentElement), markerConfig, {
                seek: seekTo,
                position: position
            });
        },

        /**
         * Get marker points in the format expected by Plyr.
         *
         * @param {Object} markerConfig Normalised marker configuration.
         * @returns {Array} Marker points.
         */
        _markers_points: function(markerConfig) {
            if (!markerConfig || !Array.isArray(markerConfig.points)) {
                return [];
            }
            return markerConfig.points.map(function(point) {
                return {time: Number(point.time), label: point.label};
            });
        },

        _throttle: function(callback, wait) {
            var last = 0;
            var timer = null;
            return function() {
                var args = arguments;
                var context = this;
                var now = Date.now();
                var remaining = wait - (now - last);
                if (remaining <= 0) {
                    if (timer) {
                        clearTimeout(timer);
                        timer = null;
                    }
                    last = now;
                    callback.apply(context, args);
                } else if (!timer) {
                    timer = setTimeout(function() {
                        timer = null;
                        last = Date.now();
                        callback.apply(context, args);
                    }, remaining);
                }
            };
        },

        _cleanup_when_removed: function(elementId, callback) {
            var observer = new MutationObserver(function() {
                var element = document.getElementById(elementId);
                if (!element || !document.documentElement.contains(element)) {
                    observer.disconnect();
                    callback();
                }
            });
            observer.observe(document.documentElement, {childList: true, subtree: true});
            return observer;
        },

        _youtube_when_ready: function(callback) {
            if (window.YT && window.YT.Player) {
                callback();
                return;
            }

            youtubeApiCallbacks.push(callback);

            if (!youtubeApiHooked) {
                youtubeApiHooked = true;

                var previousReady = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = function() {
                    if (typeof previousReady === "function") {
                        previousReady();
                    }

                    var callbacks = youtubeApiCallbacks.slice(0);
                    youtubeApiCallbacks = [];

                    callbacks.forEach(function(cb) {
                        cb();
                    });
                };
            }

            if (!youtubeApiRequested &&
                !document.querySelector('script[src="https://www.youtube.com/iframe_api"]')) {
                youtubeApiRequested = true;

                var tag = document.createElement("script");
                tag.src = "https://www.youtube.com/iframe_api";
                document.head.appendChild(tag);
            }
        },

        ottflix: function (view_id, start_currenttime, elementId, identifier, allowedOrigins) {
            player_create._internal_view_id = view_id;

            let totalDuration = false;
            if (Array.isArray(identifier)) {
                totalDuration = identifier.reduce((sum, item) => sum + (item.duration || 0), 0);
            }

            const identifiers = new Set(
                (Array.isArray(identifier) ? identifier : [identifier])
                    .filter(Boolean)
                    .map(i => (typeof i === "string" ? i : i.identifier))
                    .filter(Boolean)
            );

            const wrapper = document.getElementById(elementId);
            const frames = wrapper ? Array.from(wrapper.querySelectorAll("iframe")) : [];
            const allowedSources = new Set(frames.map(frame => frame.contentWindow).filter(Boolean));
            const origins = new Set((Array.isArray(allowedOrigins) ? allowedOrigins : []).filter(Boolean));

            function receiveMessage(event) {
                if (!(event && event.data) || !allowedSources.has(event.source) ||
                        !origins.has(event.origin)) {
                    return;
                }

                if (event.data.origem === "OTTFLIX-player") {
                    if (identifiers.has(event.data.identifier)) {
                        if (event.data.name === "progress") {
                            if (totalDuration) {
                                player_create._internal_saveprogress(event.data.currentTime, totalDuration);
                            } else {
                                player_create._internal_saveprogress(event.data.currentTime, event.data.duration);
                            }
                        }
                    }
                }
            }

            window.addEventListener("message", receiveMessage);
            player_create._cleanup_when_removed(elementId, function() {
                window.removeEventListener("message", receiveMessage);
            });
        },

        youtube: function (view_id, start_currenttime, elementId, videoid, playersize, showcontrols, autoplay, markerConfig) {
            player_create._internal_view_id = view_id;

            var playerVars = {
                rel: 0,
                controls: showcontrols,
                autoplay: autoplay,
                playsinline: 1,
                start: start_currenttime ? start_currenttime : 0,
            };

            var player = null;
            var progressInterval = null;
            var markerInterval = null;
            var onYoutubeSeek = function(event) {
                if (player && player.seekTo) {
                    player.seekTo(event.detail.goCurrentTime);
                }
            };
            var markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                if (player && player.seekTo) {
                    player.seekTo(time, true);
                    return true;
                }
                return false;
            }, null, function() {
                return {time: player ? player.getCurrentTime() : NaN, duration: player ? player.getDuration() : NaN};
            });

            var showApiError = function() {
                var html =
                    `<div class="alert alert-danger">
                        Error loading the YouTube iframe API.
                     </div>`;
                $("#supervideo_area_embed").html(html);
            };

            var initPlayer = function() {
                if (!(window.YT && window.YT.Player)) {
                    showApiError();
                    return;
                }

                player = new window.YT.Player(elementId, {
                    suggestedQuality: "large",
                    videoId: videoid,
                    width: "100%",
                    playerVars: playerVars,
                    events: {
                        "onReady": function () {
                            var sizes = playersize.split("x");

                            if (sizes && sizes[1]) {
                                player_create._internal_resize(sizes[0], sizes[1]);
                            } else {
                                player_create._internal_resize(16, 9);
                            }

                            document.addEventListener("setCurrentTime", onYoutubeSeek);
                            if (markerConfig && markerConfig.allowed && markerConfig.points.length) {
                                markerInterval = window.setInterval(function() {
                                    markerController.update(player.getCurrentTime(), player.getDuration());
                                }, 250);
                            }
                        },
                        "onStateChange": function(event) {
                            var playing = window.YT && event.data === window.YT.PlayerState.PLAYING;
                            if (playing && !progressInterval) {
                                progressInterval = window.setInterval(function() {
                                    if (markerController.mayReport(player.getCurrentTime(), true)) {
                                        player_create._internal_saveprogress(player.getCurrentTime(), player.getDuration());
                                    }
                                }, 1000);
                            } else if (!playing && progressInterval) {
                                window.clearInterval(progressInterval);
                                progressInterval = null;
                                if (markerController.mayReport(player.getCurrentTime(), false)) {
                                    player_create._internal_saveprogress(player.getCurrentTime(), player.getDuration());
                                }
                            }
                            if (window.YT && event.data === window.YT.PlayerState.ENDED &&
                                    markerController.mayReport(player.getDuration(), false)) {
                                player_create._internal_saveprogress(player.getDuration(), player.getDuration());
                            }
                        }
                    }
                });
                player_create._cleanup_when_removed(elementId, function() {
                    if (progressInterval) {
                        window.clearInterval(progressInterval);
                        progressInterval = null;
                    }
                    document.removeEventListener("setCurrentTime", onYoutubeSeek);
                    if (markerInterval !== null) {
                        window.clearInterval(markerInterval);
                    }
                    markerController.stop();
                    if (player && player.destroy) {
                        player.destroy();
                    }
                });
            };

            player_create._youtube_when_ready(initPlayer);

            window.setTimeout(function() {
                if (!player && !(window.YT && window.YT.Player)) {
                    showApiError();
                }
            }, 8000);

        },

        resource_audio: function (view_id, start_currenttime, elementId, markerConfig, captionConfig) {
            player_create._internal_view_id = view_id;

            var $element = $(`#${elementId}`);
            var fullurl = $element.attr("data-videourl");
            var autoplay = $element.attr("data-autoplay");
            var showcontrols = $element.attr("data-showcontrols");
            var controls = $element.attr("data-controls");
            var speed = $element.attr("data-speed");

            var embedparameters = "";
            if (showcontrols) {
                embedparameters += "controls ";
            }
            if (autoplay) {
                embedparameters += "autoplay ";
            }

            var embed = `<audio ${embedparameters} crossorigin playsinline id="${elementId}_audio"></audio>`;
            $element.html(embed);
            player_create._error_load(`${elementId}_audio`);
            $(`#${elementId}_audio`).attr("src", fullurl);

            var config = {
                controls: controls.split(","),
                tooltips: {controls: showcontrols, seek: showcontrols},
                settings: ["speed", "loop"],
                autoplay: !!autoplay,
                storage: {enabled: true, key: "id-" + view_id},
                speed: {selected: speed.length > 3, options: speed.split(",")},
                seekTime: parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
                markers: {
                    enabled: player_create._markers_points(markerConfig).length > 0,
                    points: player_create._markers_points(markerConfig),
                },
            };
            var player = new PlayerRender(`#${elementId} audio`, config);
            const media = document.getElementById(elementId + "_audio");
            const captionController = Captions.attach(media, document.getElementById(elementId + "-captions"), captionConfig, {
                player: player, canSeek: showcontrols === "1" && config.controls.includes("progress")
            });
            var markerController = Markers.native(media, player.elements.container, markerConfig, {
                canSeek: showcontrols === "1" && config.controls.includes("progress"),
                seek: Captions.seek, settle: Captions.settleSeek
            });
            player.on("ready", function () {
                if (start_currenttime) {
                    player.currentTime = parseInt(start_currenttime);
                    setTimeout(function () {
                        player.currentTime = parseInt(start_currenttime);
                    }, 1000);

                    if (!autoplay) {
                        player.pause();
                    }
                }
            });

            var onAudioSeek = function(event) {
                player.currentTime = event.detail.goCurrentTime;
            };
            document.addEventListener("setCurrentTime", onAudioSeek);

            var onAudioProgress = player_create._throttle(function() {
                if (!Captions.mayReport(media)) {
                    return;
                }
                player_create._internal_saveprogress(player.currentTime, player.duration);
            }, 750);
            player.on("timeupdate", onAudioProgress);
            player.on("ended", function() {
                if (Captions.mayReport(media)) {
                    player_create._internal_saveprogress(player.duration, player.duration);
                }
            });
            player_create._cleanup_when_removed(elementId, function() {
                document.removeEventListener("setCurrentTime", onAudioSeek);
                player.off("timeupdate", onAudioProgress);
                captionController.stop();
                markerController.stop();
            });
        },

        resource_hls: function(viewId, resume, elementId, markerConfig, captionConfig) {
            const host = document.getElementById(elementId);
            if (!host) {
                return;
            }
            const video = document.createElement("video");
            video.crossOrigin = "anonymous";
            video.playsInline = true;
            video.controls = host.dataset.showcontrols === "1";
            video.autoplay = host.dataset.autoplay === "true";
            host.prepend(video);
            const status = host.querySelector("[data-hls-status]");
            let player = null;
            let mode = "unknown";
            let disposed = false;
            let played = false;
            let marker = null;
            let captionController = null;
            const map = document.getElementById("map-visualization");
            const initialMapHidden = map ? map.hidden : false;

            const updateMode = function(value) {
                mode = value;
                if (marker) {
                    marker.setAvailable(value === "vod");
                }
                if (captionController) {
                    captionController.setAvailable(value === "vod");
                }
                if (map && player_create._internal_view_id === viewId) {
                    map.hidden = value !== "vod" || initialMapHidden;
                }
                if (status) {
                    status.textContent = value === "vod" ? "" : host.dataset[value === "live" ? "hlsLive" : "hlsUnknown"];
                    status.hidden = value === "vod";
                }
            };

            const controller = createHlsController(video, host.dataset.videourl, {
                resume: resume,
                mode: updateMode,
                error: function(key) {
                    if (marker) {
                        marker.stop();
                        marker = null;
                    }
                    if (captionController) {
                        captionController.stop();
                    }
                    if (status) {
                        status.textContent = host.dataset[key === "hls_unsupported" ? "hlsUnsupported" :
                            key === "hls_libraryerror" ? "hlsLibraryerror" : "hlsError"];
                        status.hidden = false;
                    }
                },
                ready: function(hls) {
                    if (disposed || player) {
                        return;
                    }
                    const config = {
                        controls: host.dataset.controls.split(","),
                        autoplay: video.autoplay,
                        settings: ["captions", "speed", "loop"],
                        captions: {update: true},
                        storage: {enabled: true, key: "id-" + viewId},
                        speed: {selected: host.dataset.speed.length > 3, options: host.dataset.speed.split(",")},
                        markers: {
                            enabled: player_create._markers_points(markerConfig).length > 0,
                            points: player_create._markers_points(markerConfig)
                        }
                    };
                    if (hls) {
                        const qualities = [...new Set(hls.levels.map(level => level.height).filter(Boolean))].sort((a, b) => b - a);
                        if (qualities.length) {
                            config.settings.unshift("quality");
                            config.i18n = {qualityLabel: {0: host.dataset.hlsAuto}};
                            config.quality = {
                                default: 0,
                                options: [0].concat(qualities),
                                forced: true,
                                onChange: function(quality) {
                                    const level = Number(quality) === 0 ? -1 :
                                        hls.levels.findIndex(item => item.height === Number(quality));
                                    if (Number(quality) === 0 || level >= 0) {
                                        hls.currentLevel = level;
                                    }
                                }
                            };
                        }
                    }
                    player = new PlayerRender(video, config);
                    captionController = Captions.attach(video, document.getElementById(elementId + "-captions"), captionConfig, {
                        player: player, available: mode === "vod",
                        canSeek: host.dataset.showcontrols === "1" && config.controls.includes("progress")
                    });
                    marker = Markers.native(video, player.elements.container, markerConfig, {
                        available: mode === "vod",
                        canSeek: host.dataset.showcontrols === "1" && config.controls.includes("progress"),
                        seek: Captions.seek, settle: Captions.settleSeek
                    });
                }
            });

            const onPlaying = function() {
                played = true;
            };
            const progress = function(event) {
                if (!disposed && played && !video.seeking &&
                        (event.type === "ended" || !video.paused) &&
                        player_create._internal_view_id === viewId &&
                        controller.validPosition(video.currentTime, video.duration) && Captions.mayReport(video)) {
                    player_create._internal_saveprogress(video.currentTime, video.duration);
                }
            };
            const seek = function(event) {
                if (mode === "vod" && player_create._internal_view_id === viewId &&
                        Number.isFinite(event.detail.goCurrentTime)) {
                    video.currentTime = event.detail.goCurrentTime;
                }
            };
            const resize = function() {
                if (video.videoWidth && video.videoHeight) {
                    player_create._internal_resize(video.videoWidth, video.videoHeight);
                }
            };
            video.addEventListener("playing", onPlaying);
            video.addEventListener("timeupdate", progress);
            video.addEventListener("ended", progress);
            video.addEventListener("loadedmetadata", resize);
            document.addEventListener("setCurrentTime", seek);
            player_create._cleanup_when_removed(elementId, function() {
                disposed = true;
                document.removeEventListener("setCurrentTime", seek);
                video.removeEventListener("playing", onPlaying);
                video.removeEventListener("timeupdate", progress);
                video.removeEventListener("ended", progress);
                video.removeEventListener("loadedmetadata", resize);
                if (captionController) {
                    captionController.stop();
                }
                if (marker) {
                    marker.stop();
                }
                controller.stop();
                if (player) {
                    player.destroy();
                }
            });
            controller.start();
        },

        resource_video: function (view_id, start_currenttime, elementId, isHls, markerConfig, captionConfig) {
            player_create._internal_view_id = view_id;
            if (isHls) {
                player_create.resource_hls(view_id, start_currenttime, elementId, markerConfig, captionConfig);
                return;
            }

            const $element = $(`#${elementId}`);
            const fullurl = $element.attr("data-videourl");
            const autoplay = $element.attr("data-autoplay");
            const showcontrols = $element.attr("data-showcontrols");
            const controls = $element.attr("data-controls");
            const speed = $element.attr("data-speed");

            var embedparameters = "";
            if (showcontrols) {
                embedparameters += "controls ";
            }
            if (autoplay == "true") {
                embedparameters += "autoplay ";
            }

            $element.html(`<video ${embedparameters} crossorigin playsinline id="${elementId}_video"></video>`);
            player_create._error_load(`${elementId}_video`);
            $(`#${elementId}_video`).attr("src", fullurl);

            const config = {
                controls: controls.split(","),
                autoplay: !!autoplay,
                tooltips: {controls: showcontrols, seek: showcontrols},
                settings: ["captions", "speed", "loop"],
                captions: {update: true},
                storage: {enabled: true, key: "id-" + view_id},
                speed: {selected: speed.length > 3, options: speed.split(",")},
                seekTime: parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
                markers: {
                    enabled: player_create._markers_points(markerConfig).length > 0,
                    points: player_create._markers_points(markerConfig),
                },
            };

            let captionController = null;
            function playerReady(player) {
                var markerController = Markers.native(video, player.elements.container, markerConfig, {
                    canSeek: showcontrols === "1" && config.controls.includes("progress"),
                    seek: Captions.seek, settle: Captions.settleSeek
                });
                player.on("ready", function () {
                    if (start_currenttime) {
                        player.currentTime = parseInt(start_currenttime);
                        setTimeout(function () {
                            player.currentTime = parseInt(start_currenttime);
                        }, 1000);

                        if (!autoplay) {
                            player.pause();
                        }
                    }
                    player_create._internal_resize(16, 9);
                });

                var onVideoSeek = function(event) {
                    player.currentTime = event.detail.goCurrentTime;
                };
                document.addEventListener("setCurrentTime", onVideoSeek);

                var onVideoProgress = player_create._throttle(function() {
                    if (!Captions.mayReport(video)) {
                        return;
                    }
                    player_create._internal_saveprogress(player.currentTime, player.duration);
                }, 750);
                player.on("timeupdate", onVideoProgress);
                player.on("ended", function() {
                    if (Captions.mayReport(video)) {
                        player_create._internal_saveprogress(player.duration, player.duration);
                    }
                });
                player_create._cleanup_when_removed(elementId, function() {
                    document.removeEventListener("setCurrentTime", onVideoSeek);
                    player.off("timeupdate", onVideoProgress);
                    markerController.stop();
                    if (captionController) {
                        captionController.stop();
                    }
                });
            }

            const video = document.querySelector(`#${elementId} video`);

            const player = new PlayerRender(video, config);
            captionController = Captions.attach(video, document.getElementById(elementId + "-captions"), captionConfig, {
                player: player, canSeek: showcontrols === "1" && config.controls.includes("progress")
            });
            playerReady(player);

            video.addEventListener("loadedmetadata", function (event) {
                player_create._internal_resize(video.videoWidth, video.videoHeight);
            });
        },

        vimeo: function (view_id, start_currenttime, vimeoid, elementId, markerConfig) {
            player_create._internal_view_id = view_id;

            const iframe = document.getElementById(elementId);
            const player = new Vimeo.Player(iframe);
            const markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                return player.setCurrentTime(time);
            });
            let markerPlaying = false;
            const markerPlay = () => { markerPlaying = true; };
            const markerPause = () => { markerPlaying = false; };
            const markerUpdate = data => markerController.update(data.seconds, data.duration);
            player.on("play", markerPlay);
            player.on("pause", markerPause);
            player.on("seeked", markerUpdate);
            player.on("timeupdate", markerUpdate);
            Promise.all([player.getCurrentTime(), player.getDuration()]).then(values => {
                markerController.update(values[0], values[1]);
            }).catch(function() {});

            if (start_currenttime) {
                player.setCurrentTime(start_currenttime);
            }

            var onVimeoSeek = function(event) {
                player.setCurrentTime(event.detail.goCurrentTime);
            };
            document.addEventListener("setCurrentTime", onVimeoSeek);

            Promise.all([player.getVideoWidth(), player.getVideoHeight()]).then(function (dimensions) {
                const width = dimensions[0];
                const height = dimensions[1];

                player_create._internal_resize(width, height);
            });

            var onVimeoProgress = player_create._throttle(function(data) {
                if (markerController.mayReport(data.seconds, markerPlaying)) {
                    player_create._internal_saveprogress(data.seconds, data.duration);
                }
            }, 750);
            var onVimeoEnded = function(data) {
                markerPlaying = false;
                if (markerController.mayReport(data.duration, false)) {
                    player_create._internal_saveprogress(data.duration, data.duration);
                }
            };
            player.on("timeupdate", onVimeoProgress);
            player.on("ended", onVimeoEnded);
            player_create._cleanup_when_removed(elementId, function() {
                document.removeEventListener("setCurrentTime", onVimeoSeek);
                player.off("timeupdate", onVimeoProgress);
                player.off("ended", onVimeoEnded);
                player.off("play", markerPlay);
                player.off("pause", markerPause);
                player.off("seeked", markerUpdate);
                player.off("timeupdate", markerUpdate);
                markerController.stop();
            });
        },

        drive: function (view_id, elementId, playersize) {
            $("#map-visualization").hide();

            player_create._internal_view_id = view_id;

            if (playersize == "4x3") {
                player_create._internal_resize(4, 3);
            } else if (playersize == "16x9") {
                player_create._internal_resize(16, 9);
            } else {
                $("body").removeClass("distraction-free-mode");

                player_create._internal_resize(100, 640);
            }
        },

        embed: function (view_id, start_currenttime, elementId, playersize, markerConfig, trustedOrigin) {
            player_create._internal_view_id = view_id;

            if (playersize == "4x3") {
                player_create._internal_resize(4, 3);
            } else if (playersize == "16x9") {
                player_create._internal_resize(16, 9);
            } else {
                player_create._internal_resize(16, 9);
            }

            var iframe = document.getElementById(elementId);
            var markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                if (iframe && iframe.contentWindow && trustedOrigin) {
                    iframe.contentWindow.postMessage({
                        type: "player:seekTo",
                        currentTime: time
                    }, trustedOrigin);
                }
            });

            // Allow the progress map to seek inside the embedded player.
            var onEmbedSeek = function(event) {
                if (iframe && iframe.contentWindow && trustedOrigin) {
                    iframe.contentWindow.postMessage({
                        type: "player:seekTo",
                        currentTime: event.detail.goCurrentTime
                    }, trustedOrigin);
                }
            };
            document.addEventListener("setCurrentTime", onEmbedSeek);

            // Listen for progress and dimension messages from embedded player iframe.
            var receiveEmbedMessage = function(event) {
                if (!trustedOrigin || event.source !== iframe.contentWindow || event.origin !== trustedOrigin) {
                    return;
                }
                if (event.data && event.data.origem === "supervideo-embed") {
                    if (event.data.name === "dimensions" && event.data.width && event.data.height) {
                        player_create._internal_resize(event.data.width, event.data.height);
                    }
                    if (event.data.name === "progress") {
                        var ct = event.data.currentTime;
                        var dur = event.data.duration;

                        if (dur > 0) {
                            markerController.update(ct, dur);
                            if (markerController.mayReport(Number(ct), true)) {
                                player_create._internal_saveprogress(ct, dur);
                            }
                        }
                    }
                }
            };
            window.addEventListener("message", receiveEmbedMessage);
            player_create._cleanup_when_removed(elementId, function() {
                document.removeEventListener("setCurrentTime", onEmbedSeek);
                window.removeEventListener("message", receiveEmbedMessage);
                markerController.stop();
            });
        },

        pandavideo: function (view_id, currenttime, elementId, size, markerConfig, trustedOrigin) {
            player_create._internal_resize(size.width, size.height);

            player_create._internal_view_id = view_id;

            var duration = false;
            const iframe = document.getElementById(elementId).contentWindow;
            const markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                iframe.postMessage({type: 'currentTime', parameter: time}, trustedOrigin);
            });
            const receivePandaMessage = (event) => {
                if (!trustedOrigin || event.source !== iframe || event.origin !== trustedOrigin) {
                    return;
                }
                const {data} = event;

                if (data.message === 'panda_allData') {
                    duration = data.playerData.duration
                } else if (data.message === 'panda_timeupdate') {
                    if (duration) {
                        markerController.update(data.currentTime, duration);
                        if (markerController.mayReport(Number(data.currentTime), true)) {
                            player_create._internal_saveprogress(data.currentTime, duration);
                        }
                    }
                }
            };
            window.addEventListener("message", receivePandaMessage, false);
            player_create._cleanup_when_removed(elementId, function() {
                window.removeEventListener("message", receivePandaMessage, false);
                markerController.stop();
            });

            iframe.postMessage({type: 'currentTime', parameter: currenttime}, trustedOrigin);
        },

        _error_load: function (elementId) {
            function errorF(e) {
                $(`#${elementId}, #map-visualization`).hide();
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

            var videoElem = document.getElementById(elementId);
            videoElem.addEventListener("error", errorF);
        },

        _internal_resize__width: 0,
        _internal_resize__height: 0,
        _internal_resize: function (width, height) {
            player_create._internal_resize__width = width;
            player_create._internal_resize__height = height;

            $(window).resize(player_create._internal_max_height__resizePage);
            player_create._internal_max_height__resizePage();
        },

        _internal_max_height__resizePage: function () {
            var $supervideoArea = $("#supervideo_area_embed");
            if (!$supervideoArea.length) {
                return;
            }

            var mediaWidth = Number(player_create._internal_resize__width);
            var mediaHeight = Number(player_create._internal_resize__height);

            if (!mediaWidth || !mediaHeight || mediaWidth <= 0 || mediaHeight <= 0) {
                return;
            }

            // visualViewport is more accurate on mobile when browser bars are visible.
            var windowHeight = window.visualViewport ?
                window.visualViewport.height :
                (window.innerHeight || $(window).height());

            var maxHeight;

            if ($("body").hasClass("distraction-free-mode")) {
                var style = window.getComputedStyle($supervideoArea[0]);

                var marginTop = parseFloat(style.marginTop) || 0;
                var marginBottom = parseFloat(style.marginBottom) || 0;

                maxHeight = windowHeight - marginTop - marginBottom -90; // 90 of the map.
            } else {
                // Moodle Boost and most Boost-based themes.
                var headerHeight = $("nav.navbar.fixed-top:visible").outerHeight() || 0;

                // Fallback for themes that use another header structure.
                if (!headerHeight) {
                    headerHeight = $("#header:visible").outerHeight() || 0;
                }

                maxHeight = windowHeight - headerHeight;
            }
            maxHeight = Math.max(1, maxHeight);

            var $parent = $supervideoArea.parent();
            var availableWidth = $parent.width() || $supervideoArea.width();

            if (!availableWidth || availableWidth <= 0) {
                return;
            }

            var finalWidth = availableWidth;
            var finalHeight;

            // Used by Drive when the requested size is a fixed 640px height.
            var fixedHeightMode = mediaWidth === 100 && mediaHeight === 640;

            if (fixedHeightMode) {
                finalHeight = Math.min(mediaHeight, maxHeight);
            } else {
                finalHeight = finalWidth * mediaHeight / mediaWidth;

                // The player can NEVER be taller than the visible area.
                if (finalHeight > maxHeight) {
                    finalHeight = maxHeight;
                    finalWidth = finalHeight * mediaWidth / mediaHeight;
                }
            }

            $supervideoArea.css({
                "width": finalWidth,
                "height": finalHeight,
                "max-height": maxHeight,
                "padding-bottom": 0,
                "margin-left": "auto",
                "margin-right": "auto",
                "aspect-ratio": fixedHeightMode ? "auto" : `${mediaWidth} / ${mediaHeight}`,
            });

            $supervideoArea.find("video, iframe").css({
                "max-height": maxHeight,
            });
        },

        _internal_last_position_video: -1,
        _internal_last_percent: -1,
        _internal_assisted: [],
        _internal_view_id: 0,
        _internal_progress_length: 100,
        _internal_sizenum: -1,
        _internal_saveprogress: function (currenttime, duration) {

            currenttime = Math.floor(currenttime);
            duration = Math.floor(duration);

            if (!duration) {
                return 0;
            }

            // When near the end (within 1 second), treat as complete
            if (duration - currenttime <= 1 && currenttime > 0) {
                currenttime = duration;
            }

            if (duration && player_create._internal_assisted.length == 0) {
                player_create._internal_progress_create(duration);
            }

            var video_position;
            if (player_create._internal_progress_length < 100) {
                video_position = currenttime;
            } else {
                video_position = parseInt(currenttime / duration * player_create._internal_progress_length);
            }

            if (video_position > player_create._internal_progress_length) {
                video_position = player_create._internal_progress_length;
            }
            if (video_position < 0) {
                video_position = 0;
            }

            if (player_create._internal_last_position_video == video_position) {
                return;
            }

            // Do not fill skipped segments. A jump in currentTime can happen after seeking,
            // so only the segment reached by this progress event is marked as watched.
            if (video_position > 0 && video_position <= player_create._internal_progress_length) {
                player_create._internal_assisted[video_position] = 1;
            }
            player_create._internal_last_position_video = video_position;

            var percent = 0;
            for (let j = 1; j <= player_create._internal_progress_length; j++) {
                if (player_create._internal_assisted[j]) {
                    percent++;
                    $(`#map-visualization-${j}`).css({opacity: 1});
                }
            }

            if (player_create._internal_progress_length < 100) {
                percent = Math.floor(percent / player_create._internal_progress_length * 100);
            }

            if (player_create._internal_last_percent == percent) {
                return;
            }
            player_create._internal_last_percent = percent;

            if ($("body").hasClass("distraction-free-mode")) {
                var $map = $("#map-visualization");
                if ($map.length && !$map.is(":hidden")) {
                    if (currenttime > (duration * .90)) {
                        if (player_create._internal_sizenum != 1) {
                            $(".activity-navigation").hide();
                            $map.addClass("fixed-booton");
                            player_create._internal_max_height__resizePage();
                            player_create._internal_sizenum = 1;
                        }
                    } else {
                        if (player_create._internal_sizenum != 2) {
                            $(".activity-navigation").show();
                            $map.removeClass("fixed-booton");
                            player_create._internal_max_height__resizePage();
                            player_create._internal_sizenum = 2;
                        }
                    }
                } else {
                    if (player_create._internal_sizenum != 3) {
                        $(".activity-navigation").show();
                        $map.removeClass("fixed-booton");
                        player_create._internal_max_height__resizePage();
                        player_create._internal_sizenum = 3;
                    }
                }
            }

            if (currenttime) {
                player_create._internal_queue_save({
                    view_id: player_create._internal_view_id,
                    currenttime: parseInt(currenttime),
                    duration: parseInt(duration),
                    percent: parseInt(percent),
                    map: JSON.stringify(player_create._internal_assisted)
                });
            }

            if (percent >= 0) {
                $("#your-map-view span").html(`${percent}%`);
            }
        },

        _internal_save_timer: null,
        _internal_pending_payload: null,
        _internal_last_saved_at: 0,
        _internal_queue_save: function(payload) {
            player_create._internal_pending_payload = payload;
            var elapsed = Date.now() - player_create._internal_last_saved_at;
            var delay = Math.max(0, 1500 - elapsed);
            if (player_create._internal_save_timer) {
                return;
            }
            player_create._internal_save_timer = window.setTimeout(function() {
                player_create._internal_save_timer = null;
                var latest = player_create._internal_pending_payload;
                player_create._internal_pending_payload = null;
                if (!latest) {
                    return;
                }
                player_create._internal_last_saved_at = Date.now();
                Ajax.call([{
                    methodname: "mod_supervideo_progress_save",
                    args: latest
                }])[0].fail(Notification.exception);
            }, delay);
        },

        _internal_progress_create: function (duration) {

            var $map = $("#map-visualization .map");
            if (!$map.length) {
                return;
            }

            var supervideo_view_map = [];
            try {
                var map_json_base64 = $map.attr("data-map");
                if (map_json_base64) {
                    supervideo_view_map = JSON.parse(atob(map_json_base64));
                }
            } catch (e) {
                supervideo_view_map = [];
            }

            if (Math.floor(duration) <= 100) {
                player_create._internal_progress_length = Math.floor(duration);
            }
            for (let i = 1; i <= player_create._internal_progress_length; i++) {
                if (typeof supervideo_view_map[i] != "undefined") {
                    player_create._internal_assisted[i] = supervideo_view_map[i];
                } else {
                    player_create._internal_assisted[i] = 0;
                }
                var $map_item = $(`<div id="map-visualization-${i}">`);
                $map.append($map_item);

                // Map Clik
                var mapTitle = Math.floor(duration / player_create._internal_progress_length * i);

                var hours = Math.floor(mapTitle / 3600);
                var minutes = (Math.floor(mapTitle / 60)) % 60;
                var seconds = mapTitle % 60;

                var tempo = minutes + ":" + seconds;
                if (hours) {
                    tempo = hours + ":" + minutes + ":" + seconds;
                }
                var $map_clique =
                    $("<div></div>")
                        .attr("title", tempo)
                        .attr("data-currenttime", mapTitle)
                        .click(function () {
                            var _setCurrentTime = $(this).attr("data-currenttime");
                            _setCurrentTime = parseInt(_setCurrentTime);

                            var event = document.createEvent("CustomEvent");
                            event.initCustomEvent("setCurrentTime", true, true, {goCurrentTime: _setCurrentTime});
                            document.dispatchEvent(event);
                        });
                $("#map-visualization .clique").append($map_clique);
            }
        }
    };
    return player_create;
});
