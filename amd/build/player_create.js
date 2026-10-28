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

define(["jquery", "core/ajax", "core/notification", "mod_supervideo/player_render", "jqueryui"], function ($, Ajax, Notification, PlayerRender) {
    var youtubeApiCallbacks = [];
    var youtubeApiHooked = false;
    var youtubeApiRequested = false;

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
        _markers_create: function(elementId, markerConfig, seekTo, preferredHost) {
            var points = markerConfig && Array.isArray(markerConfig.points) ? markerConfig.points : [];
            var emptyController = {update: function() {}};

            if (points.length < 2 || typeof seekTo !== "function") {
                return emptyController;
            }

            var playerElement = document.getElementById(elementId);
            var host = preferredHost || (playerElement ? playerElement.parentElement : null);
            if (!host) {
                return emptyController;
            }

            var button = document.createElement("button");
            button.type = "button";
            button.className = "supervideo-marker-jump";
            button.hidden = true;
            host.appendChild(button);

            var nextMarker = null;
            var update = function(currentTime) {
                currentTime = Number(currentTime);
                if (!Number.isFinite(currentTime)) {
                    button.hidden = true;
                    nextMarker = null;
                    return;
                }

                var currentIndex = -1;
                points.forEach(function(point, index) {
                    if (Number(point.time) <= currentTime) {
                        currentIndex = index;
                    }
                });

                var currentMarker = points[currentIndex];
                var candidate = points[currentIndex + 1];
                var show = currentMarker && currentMarker.skip && candidate &&
                    currentTime < Number(candidate.time);

                if (!show) {
                    button.hidden = true;
                    nextMarker = null;
                    return;
                }

                nextMarker = candidate;
                button.textContent = markerConfig.buttonlabel || "Jump to next marker";
                if (candidate.label) {
                    button.textContent += ": " + candidate.label;
                    button.title = candidate.label;
                } else {
                    button.removeAttribute("title");
                }
                button.hidden = false;
            };

            button.addEventListener("click", function(event) {
                event.preventDefault();
                event.stopPropagation();
                if (nextMarker) {
                    seekTo(Number(nextMarker.time));
                    update(Number(nextMarker.time));
                }
            });

            return {update: update};
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

                youtube: function (view_id, start_currenttime, elementId, videoid, playersize, showcontrols, autoplay, markerConfig, protection, labels) {
            player_create._internal_view_id = view_id;

            labels = Object.assign({
                play: "Play", pause: "Pause", mute: "Mute", unmute: "Unmute",
                volume: "Volume", fullscreen: "Fullscreen", exitfullscreen: "Exit fullscreen",
                progress: "Progress", error: "Error loading the YouTube iframe API."
            }, labels || {});

            // Mode switch: if protection is explicitly disabled (false, 0, or "0"), run standard YouTube player
            if (protection === false || protection === 0 || protection === "0") {
                var stdPlayerVars = {
                    rel: 0,
                    controls: showcontrols,
                    autoplay: autoplay,
                    playsinline: 1,
                    start: start_currenttime ? start_currenttime : 0,
                };

                var stdPlayer = null;
                var stdProgressInterval = null;
                var onStdYoutubeSeek = function(event) {
                    if (stdPlayer && stdPlayer.seekTo) {
                        stdPlayer.seekTo(event.detail.goCurrentTime);
                    }
                };
                var stdMarkerController = player_create._markers_create(elementId, markerConfig, function(time) {
                    if (stdPlayer && stdPlayer.seekTo) {
                        stdPlayer.seekTo(time, true);
                    }
                });

                var showStdApiError = function() {
                    var errorElement = document.createElement("div");
                    errorElement.className = "alert alert-danger";
                    errorElement.textContent = labels.error;
                    $("#supervideo_area_embed").empty().append(errorElement);
                };

                var initStdPlayer = function() {
                    if (!(window.YT && window.YT.Player)) {
                        showStdApiError();
                        return;
                    }

                    stdPlayer = new window.YT.Player(elementId, {
                        suggestedQuality: "large",
                        videoId: videoid,
                        width: "100%",
                        playerVars: stdPlayerVars,
                        events: {
                            "onReady": function () {
                                var sizes = playersize ? playersize.split("x") : [];
                                if (sizes && sizes[1]) {
                                    player_create._internal_resize(sizes[0], sizes[1]);
                                } else {
                                    player_create._internal_resize(16, 9);
                                }
                                document.addEventListener("setCurrentTime", onStdYoutubeSeek);
                            },
                            "onStateChange": function(event) {
                                var playing = window.YT && event.data === window.YT.PlayerState.PLAYING;
                                if (playing && !stdProgressInterval) {
                                    stdProgressInterval = window.setInterval(function() {
                                        stdMarkerController.update(stdPlayer.getCurrentTime());
                                        player_create._internal_saveprogress(stdPlayer.getCurrentTime(), stdPlayer.getDuration());
                                    }, 1000);
                                } else if (!playing && stdProgressInterval) {
                                    window.clearInterval(stdProgressInterval);
                                    stdProgressInterval = null;
                                    player_create._internal_saveprogress(stdPlayer.getCurrentTime(), stdPlayer.getDuration());
                                }
                                if (window.YT && event.data === window.YT.PlayerState.ENDED) {
                                    player_create._internal_saveprogress(stdPlayer.getDuration(), stdPlayer.getDuration());
                                }
                            }
                        }
                    });
                    player_create._cleanup_when_removed(elementId, function() {
                        if (stdProgressInterval) {
                            window.clearInterval(stdProgressInterval);
                            stdProgressInterval = null;
                        }
                        document.removeEventListener("setCurrentTime", onStdYoutubeSeek);
                        if (stdPlayer && stdPlayer.destroy) {
                            stdPlayer.destroy();
                        }
                    });
                };

                player_create._youtube_when_ready(initStdPlayer);
                window.setTimeout(function() {
                    if (!stdPlayer && !(window.YT && window.YT.Player)) {
                        showStdApiError();
                    }
                }, 8000);
                return;
            }

            // --- Protected YouTube Player ---
            var container = document.getElementById(elementId);
            if (!container) {
                console.error("Container not found:", elementId);
                return;
            }

            var resizeW = 16;
            var resizeH = 9;
            if (playersize) {
                var sizes = playersize.split("x");
                if (sizes && sizes[0] && sizes[1]) {
                    var parsedW = parseInt(sizes[0], 10);
                    var parsedH = parseInt(sizes[1], 10);
                    if (parsedW > 0 && parsedH > 0) {
                        resizeW = parsedW;
                        resizeH = parsedH;
                    }
                }
            }
            player_create._internal_resize(resizeW, resizeH);

            var wrapper = document.createElement("div");
            wrapper.className = "supervideo-player-wrapper paused";
            wrapper.setAttribute("dir", "ltr");
            wrapper.style.direction = "ltr";
            wrapper.id = elementId + "-wrapper";

            var loading = document.createElement("div");
            loading.className = "supervideo-loading";
            wrapper.appendChild(loading);

            var playerDiv = document.createElement("div");
            playerDiv.id = elementId + "-player";
            wrapper.appendChild(playerDiv);

            var posterOverlay = document.createElement("div");
            posterOverlay.className = "supervideo-poster-overlay";
            posterOverlay.style.backgroundImage = "url(https://img.youtube.com/vi/" + videoid + "/maxresdefault.jpg)";
            wrapper.appendChild(posterOverlay);

            var protectionOverlay = document.createElement("div");
            protectionOverlay.className = "supervideo-protection-overlay";
            wrapper.appendChild(protectionOverlay);

            var bigPlay = document.createElement("div");
            bigPlay.className = "supervideo-big-play";
            bigPlay.innerHTML = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>';
            wrapper.appendChild(bigPlay);

            var controls = document.createElement("div");
            controls.className = "supervideo-controls";
            controls.style.direction = "ltr";
            controls.innerHTML = `
                <button class="supervideo-btn supervideo-play-btn" type="button" title="Play">
                    <svg class="play-icon" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    <svg class="pause-icon" style="display:none" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                </button>
                <div class="supervideo-progress-container">
                    <div class="supervideo-progress-bar"></div>
                </div>
                <span class="supervideo-time">0:00 / 0:00</span>
                <div class="supervideo-volume-container">
                    <button class="supervideo-btn supervideo-volume-btn" type="button" title="Volume">
                        <svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                    </button>
                    <div class="supervideo-volume-slider">
                        <div class="supervideo-volume-bar"></div>
                    </div>
                </div>
                <button class="supervideo-btn supervideo-fullscreen-btn" type="button" title="Fullscreen">
                    <svg class="fs-enter" viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                    <svg class="fs-exit" style="display:none" viewBox="0 0 24 24"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>
                </button>
            `;
            controls.style.display = showcontrols ? "flex" : "none";
            wrapper.appendChild(controls);

            container.innerHTML = "";
            container.appendChild(wrapper);
            player_create._internal_resize(resizeW, resizeH);

            wrapper.addEventListener("contextmenu", function (e) {
                e.preventDefault();
                return false;
            });

            var player = null;
            var progressInterval = null;
            var isDraggingProgress = false;
            var isMuted = false;

            var onYoutubeSeek = function(event) {
                if (player && player.seekTo) {
                    player.seekTo(event.detail.goCurrentTime, true);
                }
            };

            var markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                if (player && player.seekTo) {
                    player.seekTo(time, true);
                }
            }, wrapper);

            var showApiError = function() {
                var errorElement = document.createElement("div");
                    errorElement.className = "alert alert-danger";
                    errorElement.textContent = labels.error;
                    $("#supervideo_area_embed").empty().append(errorElement);
            };

            var initProtectedPlayer = function() {
                if (!(window.YT && window.YT.Player)) {
                    showApiError();
                    return;
                }

                var playerVars = {
                    rel: 0,
                    controls: 0,
                    autoplay: autoplay ? 1 : 0,
                    playsinline: 1,
                    start: start_currenttime ? parseInt(start_currenttime, 10) : 0,
                    modestbranding: 1,
                    iv_load_policy: 3,
                    showinfo: 0,
                    disablekb: 1,
                    fs: 0
                };

                player = new window.YT.Player(elementId + "-player", {
                    suggestedQuality: "large",
                    videoId: videoid,
                    width: "100%",
                    height: "100%",
                    playerVars: playerVars,
                    events: {
                        "onReady": function () {
                            wrapper.classList.add("ready");
                            player_create._internal_resize(resizeW, resizeH);
                            if (autoplay) {
                                wrapper.classList.remove("paused");
                                wrapper.classList.add("playing");
                            }
                            document.addEventListener("setCurrentTime", onYoutubeSeek);
                            setPlayAccessibility(player.getPlayerState && window.YT &&
                                player.getPlayerState() === window.YT.PlayerState.PLAYING);
                            updateVolumeAccessibility();
                            updateProgress(false);
                        },
                        "onStateChange": function (event) {
                            var playIcon = controls.querySelector(".play-icon");
                            var pauseIcon = controls.querySelector(".pause-icon");

                            if (window.YT && event.data === window.YT.PlayerState.PLAYING) {
                                wrapper.classList.remove("paused");
                                wrapper.classList.add("playing");
                                if (playIcon) playIcon.style.display = "none";
                                if (pauseIcon) pauseIcon.style.display = "block";
                                setPlayAccessibility(true);
                                startProgressInterval();
                                posterOverlay.style.display = "none";
                            } else if (window.YT && (event.data === window.YT.PlayerState.PAUSED || event.data === window.YT.PlayerState.ENDED)) {
                                wrapper.classList.remove("playing");
                                wrapper.classList.add("paused");
                                if (playIcon) playIcon.style.display = "block";
                                if (pauseIcon) pauseIcon.style.display = "none";
                                setPlayAccessibility(false);
                                stopProgressInterval();
                                updateProgress(false);
                                if (player && player.getCurrentTime && player.getDuration) {
                                    player_create._internal_saveprogress(player.getCurrentTime(), player.getDuration());
                                }
                            }
                            if (window.YT && event.data === window.YT.PlayerState.ENDED) {
                                if (player && player.getDuration) {
                                    player_create._internal_saveprogress(player.getDuration(), player.getDuration());
                                }
                            }
                        }
                    }
                });
            };

            player_create._youtube_when_ready(initProtectedPlayer);

            window.setTimeout(function() {
                if (!player && !(window.YT && window.YT.Player)) {
                    showApiError();
                }
            }, 8000);

            var playBtn = controls.querySelector(".supervideo-play-btn");
            var progressContainer = controls.querySelector(".supervideo-progress-container");
            var progressBar = controls.querySelector(".supervideo-progress-bar");
            var timeDisplay = controls.querySelector(".supervideo-time");
            var volumeBtn = controls.querySelector(".supervideo-volume-btn");
            var volumeSlider = controls.querySelector(".supervideo-volume-slider");
            var volumeBar = controls.querySelector(".supervideo-volume-bar");
            var fullscreenBtn = controls.querySelector(".supervideo-fullscreen-btn");

            function setPlayAccessibility(isPlaying) {
                var action = isPlaying ? labels.pause : labels.play;
                playBtn.title = action;
                playBtn.setAttribute("aria-label", action);
                playBtn.setAttribute("aria-pressed", isPlaying ? "true" : "false");
                bigPlay.setAttribute("aria-label", action);
                bigPlay.tabIndex = isPlaying ? -1 : 0;
            }

            function updateVolumeAccessibility() {
                if (player && player.isMuted) {
                    isMuted = player.isMuted();
                }
                var action = isMuted ? labels.unmute : labels.mute;
                volumeBtn.title = action;
                volumeBtn.setAttribute("aria-label", action);
                volumeBtn.setAttribute("aria-pressed", isMuted ? "true" : "false");
                var level = isMuted ? 0 : player && player.getVolume ? player.getVolume() : 100;
                volumeSlider.setAttribute("aria-valuenow", String(Math.round(level)));
            }

            function renderProgress(currentTime, duration) {
                if (!Number.isFinite(duration) || duration <= 0) {
                    return;
                }
                currentTime = Math.max(0, Math.min(duration, currentTime));
                progressBar.style.width = (currentTime / duration * 100) + "%";
                timeDisplay.textContent = formatTime(currentTime) + " / " + formatTime(duration);
                progressContainer.setAttribute("aria-valuemax", String(Math.floor(duration)));
                progressContainer.setAttribute("aria-valuenow", String(Math.floor(currentTime)));
                progressContainer.setAttribute("aria-valuetext", timeDisplay.textContent);
            }

            bigPlay.setAttribute("role", "button");
            volumeSlider.setAttribute("role", "slider");
            volumeSlider.tabIndex = 0;
            volumeSlider.setAttribute("aria-label", labels.volume);
            volumeSlider.setAttribute("aria-valuemin", "0");
            volumeSlider.setAttribute("aria-valuemax", "100");
            volumeSlider.setAttribute("aria-valuenow", "100");
            progressContainer.setAttribute("role", "slider");
            progressContainer.tabIndex = 0;
            progressContainer.setAttribute("aria-label", labels.progress);
            progressContainer.setAttribute("aria-valuemin", "0");
            progressContainer.setAttribute("aria-valuemax", "0");
            progressContainer.setAttribute("aria-valuenow", "0");
            fullscreenBtn.title = labels.fullscreen;
            fullscreenBtn.setAttribute("aria-label", labels.fullscreen);
            setPlayAccessibility(false);
            updateVolumeAccessibility();

            function togglePlay() {
                if (player && player.getPlayerState) {
                    var state = player.getPlayerState();
                    if (window.YT && state === window.YT.PlayerState.PLAYING) {
                        player.pauseVideo();
                    } else {
                        player.playVideo();
                    }
                }
            }

            playBtn.addEventListener("click", togglePlay);
            bigPlay.addEventListener("click", togglePlay);
            posterOverlay.addEventListener("click", togglePlay);
            protectionOverlay.addEventListener("click", togglePlay);
            bigPlay.addEventListener("keydown", function(event) {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    togglePlay();
                }
            });

            function formatTime(seconds) {
                if (isNaN(seconds) || !isFinite(seconds)) return "0:00";
                var mins = Math.floor(seconds / 60);
                var secs = Math.floor(seconds % 60);
                return mins + ":" + (secs < 10 ? "0" : "") + secs;
            }

            function getClientX(e) {
                if (e.touches && e.touches.length > 0) {
                    return e.touches[0].clientX;
                } else if (e.changedTouches && e.changedTouches.length > 0) {
                    return e.changedTouches[0].clientX;
                }
                return e.clientX;
            }

            function updateProgressUI(e) {
                if (!player || !player.getDuration) return null;
                var rect = progressContainer.getBoundingClientRect();
                var percent = (getClientX(e) - rect.left) / rect.width;
                percent = Math.max(0, Math.min(1, percent));
                var duration = player.getDuration();
                if (!Number.isFinite(duration) || duration <= 0) return null;
                renderProgress(percent * duration, duration);
                return percent;
            }

            function startDrag(e) {
                isDraggingProgress = true;
                progressContainer.classList.add("dragging");
                updateProgressUI(e);
            }

            function moveDrag(e) {
                if (isDraggingProgress) {
                    if (e.cancelable) e.preventDefault();
                    updateProgressUI(e);
                }
            }

            function endDrag(e) {
                if (isDraggingProgress) {
                    isDraggingProgress = false;
                    progressContainer.classList.remove("dragging");
                    var percent = updateProgressUI(e);
                    if (percent !== null && player && player.seekTo) {
                        player.seekTo(percent * player.getDuration(), true);
                    }
                }
            }

            progressContainer.addEventListener("mousedown", startDrag);
            progressContainer.addEventListener("touchstart", startDrag, { passive: true });

            var onMouseMove = function(e) { if (isDraggingProgress) moveDrag(e); };
            var onMouseUp = function(e) { if (isDraggingProgress) endDrag(e); };

            window.addEventListener("mousemove", onMouseMove, { passive: false });
            window.addEventListener("mouseup", onMouseUp);

            progressContainer.addEventListener("touchmove", moveDrag, { passive: false });
            progressContainer.addEventListener("touchend", endDrag);
            progressContainer.addEventListener("touchcancel", endDrag);
            progressContainer.addEventListener("keydown", function(event) {
                if (!player || !player.getDuration || !player.seekTo) return;
                var duration = player.getDuration();
                if (!Number.isFinite(duration) || duration <= 0) return;
                var target = player.getCurrentTime();
                switch (event.key) {
                    case "ArrowLeft": target -= 5; break;
                    case "ArrowRight": target += 5; break;
                    case "PageDown": target -= 10; break;
                    case "PageUp": target += 10; break;
                    case "Home": target = 0; break;
                    case "End": target = duration; break;
                    default: return;
                }
                event.preventDefault();
                target = Math.max(0, Math.min(duration, target));
                player.seekTo(target, true);
                renderProgress(target, duration);
            });

            volumeBtn.addEventListener("click", function () {
                if (player) {
                    if (isMuted) {
                        if (player.unMute) player.unMute();
                        if (player.setVolume) player.setVolume(100);
                        volumeBar.style.width = "100%";
                    } else {
                        if (player.mute) player.mute();
                        volumeBar.style.width = "0%";
                    }
                    isMuted = !isMuted;
                    updateVolumeAccessibility();
                }
            });

            function handleVolumeSeek(e) {
                if (player) {
                    var rect = volumeSlider.getBoundingClientRect();
                    var clientX = getClientX(e);
                    var percent = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
                    if (player.setVolume) player.setVolume(percent * 100);
                    volumeBar.style.width = (percent * 100) + "%";
                    if (percent > 0 && isMuted) {
                        if (player.unMute) player.unMute();
                        isMuted = false;
                    }
                    updateVolumeAccessibility();
                }
            }
            volumeSlider.addEventListener("keydown", function(event) {
                if (!player || !player.setVolume) return;
                var value = player.getVolume ? player.getVolume() : 100;
                switch (event.key) {
                    case "ArrowLeft":
                    case "ArrowDown": value -= 5; break;
                    case "ArrowRight":
                    case "ArrowUp": value += 5; break;
                    case "Home": value = 0; break;
                    case "End": value = 100; break;
                    default: return;
                }
                event.preventDefault();
                value = Math.max(0, Math.min(100, value));
                player.setVolume(value);
                if (value > 0 && player.unMute) player.unMute();
                if (value === 0 && player.mute) player.mute();
                volumeBar.style.width = value + "%";
                updateVolumeAccessibility();
            });
            volumeSlider.addEventListener("click", handleVolumeSeek);
            volumeSlider.addEventListener("touchstart", handleVolumeSeek, { passive: true });
            volumeSlider.addEventListener("touchmove", handleVolumeSeek, { passive: true });

            var fsEnter = fullscreenBtn.querySelector(".fs-enter");
            var fsExit = fullscreenBtn.querySelector(".fs-exit");

            function syncFullscreenUI() {
                var isFs = document.fullscreenElement || document.webkitFullscreenElement || wrapper.classList.contains("fullscreen");
                var area = document.getElementById("supervideo_area_embed");
                if (isFs) {
                    if (area) area.classList.add("supervideo-has-fullscreen");
                    if (fsEnter) fsEnter.style.display = "none";
                    if (fsExit) fsExit.style.display = "block";
                    fullscreenBtn.title = labels.exitfullscreen;
                    fullscreenBtn.setAttribute("aria-label", labels.exitfullscreen);
                } else {
                    if (area) area.classList.remove("supervideo-has-fullscreen");
                    wrapper.classList.remove("fullscreen");
                    document.body.style.overflow = "";
                    if (fsEnter) fsEnter.style.display = "block";
                    if (fsExit) fsExit.style.display = "none";
                    fullscreenBtn.title = labels.fullscreen;
                    fullscreenBtn.setAttribute("aria-label", labels.fullscreen);
                }
            }

            function toggleFullscreen() {
                var isFs = document.fullscreenElement || document.webkitFullscreenElement || wrapper.classList.contains("fullscreen");
                if (!isFs) {
                    if (wrapper.requestFullscreen) {
                        wrapper.requestFullscreen().catch(function() {
                            wrapper.classList.add("fullscreen");
                            syncFullscreenUI();
                        });
                    } else if (wrapper.webkitRequestFullscreen) {
                        wrapper.webkitRequestFullscreen();
                    } else {
                        wrapper.classList.add("fullscreen");
                        document.body.style.overflow = "hidden";
                        syncFullscreenUI();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().catch(function() {
                            wrapper.classList.remove("fullscreen");
                            syncFullscreenUI();
                        });
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else {
                        wrapper.classList.remove("fullscreen");
                        document.body.style.overflow = "";
                        syncFullscreenUI();
                    }
                }
            }

            fullscreenBtn.addEventListener("click", toggleFullscreen);
            document.addEventListener("fullscreenchange", syncFullscreenUI);
            document.addEventListener("webkitfullscreenchange", syncFullscreenUI);

            var onKeyDown = function (e) {
                if (e.key === "Escape") {
                    var isFs = document.fullscreenElement || document.webkitFullscreenElement || wrapper.classList.contains("fullscreen");
                    if (isFs) {
                        wrapper.classList.remove("fullscreen");
                        syncFullscreenUI();
                    }
                }
            };
            document.addEventListener("keydown", onKeyDown);

            function updateProgress(save) {
                if (isDraggingProgress || !player || !player.getCurrentTime || !player.getDuration) {
                    return;
                }
                var currentTime = player.getCurrentTime();
                var duration = player.getDuration();
                if (duration > 0) {
                    renderProgress(currentTime, duration);
                    markerController.update(currentTime);
                    if (save) player_create._internal_saveprogress(currentTime, duration);
                }
            }

            function startProgressInterval() {
                if (!progressInterval) {
                    updateProgress(true);
                    progressInterval = window.setInterval(function() {
                        updateProgress(true);
                    }, 1000);
                }
            }

            function stopProgressInterval() {
                if (progressInterval) {
                    window.clearInterval(progressInterval);
                    progressInterval = null;
                }
            }

            player_create._cleanup_when_removed(elementId, function () {
                stopProgressInterval();
                window.removeEventListener("mousemove", onMouseMove);
                window.removeEventListener("mouseup", onMouseUp);
                document.removeEventListener("keydown", onKeyDown);
                document.removeEventListener("fullscreenchange", syncFullscreenUI);
                document.removeEventListener("webkitfullscreenchange", syncFullscreenUI);
                document.removeEventListener("setCurrentTime", onYoutubeSeek);
                var area = document.getElementById("supervideo_area_embed");
                if (area) area.classList.remove("supervideo-has-fullscreen");
                if (player && player.destroy) {
                    player.destroy();
                }
            });
        },

        resource_audio: function (view_id, start_currenttime, elementId, markerConfig) {
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
            var markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                player.currentTime = time;
            }, player.elements.container);
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
                markerController.update(player.currentTime);
                player_create._internal_saveprogress(player.currentTime, player.duration);
            }, 750);
            player.on("timeupdate", onAudioProgress);
            player.on("ended", function() {
                player_create._internal_saveprogress(player.duration, player.duration);
            });
            player_create._cleanup_when_removed(elementId, function() {
                document.removeEventListener("setCurrentTime", onAudioSeek);
                player.off("timeupdate", onAudioProgress);
            });
        },

        resource_video: function (view_id, start_currenttime, elementId, isHls, markerConfig) {
            player_create._internal_view_id = view_id;

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
                settings: ["speed", "loop"],
                storage: {enabled: true, key: "id-" + view_id},
                speed: {selected: speed.length > 3, options: speed.split(",")},
                seekTime: parseInt(start_currenttime) ? parseInt(start_currenttime) : 0,
                markers: {
                    enabled: player_create._markers_points(markerConfig).length > 0,
                    points: player_create._markers_points(markerConfig),
                },
            };

            function playerReady(player) {
                var markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                    player.currentTime = time;
                }, player.elements.container);
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
                    markerController.update(player.currentTime);
                    player_create._internal_saveprogress(player.currentTime, player.duration);
                }, 750);
                player.on("timeupdate", onVideoProgress);
                player.on("ended", function() {
                    player_create._internal_saveprogress(player.duration, player.duration);
                });
                player_create._cleanup_when_removed(elementId, function() {
                    document.removeEventListener("setCurrentTime", onVideoSeek);
                    player.off("timeupdate", onVideoProgress);
                });
            }

            const video = document.querySelector(`#${elementId} video`);

            if (isHls) {
                if (Hls.isSupported()) {

                    var source = $(`#${elementId}`).attr("data-videourl");

                    // For more Hls.js options, see https://github.com/dailymotion/hls.js
                    const hls = new Hls();
                    hls.loadSource(source);

                    // From the m3u8 playlist, hls parses the manifest and returns
                    // all available video qualities. This is important, in this approach,
                    // we will have one source on the Plyr player.
                    hls.on(Hls.Events.MANIFEST_PARSED, function (event, data) {

                        // Keep Hls.js adaptive bitrate as the default and expose
                        // the manifest levels as optional manual quality choices.
                        const availableQualities = [...new Set(
                            hls.levels.map((level) => level.height).filter(Boolean)
                        )].sort((a, b) => b - a);

                        if (!config.settings.includes("quality")) {
                            config.settings.unshift("quality");
                        }
                        config.i18n = config.i18n || {};
                        config.i18n.qualityLabel = Object.assign(
                            {0: "Auto"},
                            config.i18n.qualityLabel || {}
                        );
                        config.quality = {
                            default: 0,
                            options: [0].concat(availableQualities),
                            forced: true,
                            onChange: (quality) => updateQuality(quality),
                        };

                        function updateQuality(newQuality) {
                            newQuality = parseInt(newQuality);
                            if (newQuality === 0) {
                                window.hls.currentLevel = -1;
                                return;
                            }
                            window.hls.levels.forEach((level, levelIndex) => {
                                if (level.height === newQuality) {
                                    window.hls.currentLevel = levelIndex;
                                }
                            });
                        }

                        // Initialize here
                        const player = new PlayerRender(video, config);
                        playerReady(player)
                    });
                    hls.attachMedia(video);
                    window.hls = hls;
                } else {
                    const player = new PlayerRender(video, config);
                    playerReady(player);
                }
            } else {
                const player = new PlayerRender(video, config);
                playerReady(player);
            }

            video.addEventListener("loadedmetadata", function (event) {
                player_create._internal_resize(video.videoWidth, video.videoHeight);
            });
        },

        vimeo: function (view_id, start_currenttime, vimeoid, elementId, markerConfig) {
            player_create._internal_view_id = view_id;

            const iframe = document.getElementById(elementId);
            const player = new Vimeo.Player(iframe);
            const markerController = player_create._markers_create(elementId, markerConfig, function(time) {
                player.setCurrentTime(time);
            });

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
                markerController.update(data.seconds);
                player_create._internal_saveprogress(data.seconds, data.duration);
            }, 750);
            var onVimeoEnded = function(data) {
                player_create._internal_saveprogress(data.duration, data.duration);
            };
            player.on("timeupdate", onVimeoProgress);
            player.on("ended", onVimeoEnded);
            player_create._cleanup_when_removed(elementId, function() {
                document.removeEventListener("setCurrentTime", onVimeoSeek);
                player.off("timeupdate", onVimeoProgress);
                player.off("ended", onVimeoEnded);
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
                if (iframe && iframe.contentWindow) {
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
                            markerController.update(ct);
                            player_create._internal_saveprogress(ct, dur);
                        }
                    }
                }
            };
            window.addEventListener("message", receiveEmbedMessage);
            player_create._cleanup_when_removed(elementId, function() {
                document.removeEventListener("setCurrentTime", onEmbedSeek);
                window.removeEventListener("message", receiveEmbedMessage);
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
                        markerController.update(data.currentTime);
                        player_create._internal_saveprogress(data.currentTime, duration);
                    }
                }
            };
            window.addEventListener("message", receivePandaMessage, false);
            player_create._cleanup_when_removed(elementId, function() {
                window.removeEventListener("message", receivePandaMessage, false);
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

                        // Force LTR on the map container and its flex children for RTL languages
            var mapContainer = document.getElementById("map-visualization") || document.getElementById("mapa-visualizacao");
            if (mapContainer) {
                mapContainer.style.setProperty("direction", "ltr", "important");
                mapContainer.setAttribute("dir", "ltr");
                var flexChildren = mapContainer.querySelectorAll(".map, .mapa, .clique");
                for (var fc = 0; fc < flexChildren.length; fc++) {
                    flexChildren[fc].style.setProperty("direction", "ltr", "important");
                    flexChildren[fc].style.setProperty("left", "0", "important");
                    flexChildren[fc].style.setProperty("right", "0", "important");
                }
            }

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
                        .on("click touchstart", function (e) {
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
