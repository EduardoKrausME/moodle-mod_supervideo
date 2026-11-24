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
 * player.js
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var that = this;
var supervideoCleanup = [];
var supervideoSaveTimer = null;
var supervideoMap = [];
var supervideoLastBucket = -1;
var supervideoLastPercent = -1;
var supervideoDuration = 0;
var youtubeStop = null;
var youtubeDispatchTimer = null;
var youtubeResume = 0;

this.supervideoLoading = true;
this.supervideoError = '';
this.supervideoData = null;
this.supervideoPercent = null;
this.supervideoYoutubeError = "";
this.supervideoYoutubeBrowser = false;
this.supervideoYoutubeRetry = false;

function cleanup() {
    if (youtubeDispatchTimer !== null) {
        clearTimeout(youtubeDispatchTimer);
        youtubeDispatchTimer = null;
    }
    if (youtubeStop) {
        youtubeStop();
    }
    supervideoCleanup.forEach(function(fn) {
        try { fn(); } catch (e) { /* Ignore stale player cleanup. */ }
    });
    supervideoCleanup = [];
    if (supervideoSaveTimer) {
        clearTimeout(supervideoSaveTimer);
        supervideoSaveTimer = null;
    }
}

function currentSite() {
    var siteId = that.CoreSitesProvider.getCurrentSiteId();
    return that.CoreSitesProvider.getSite(siteId);
}

function decodeMap(encoded) {
    try {
        var parsed = JSON.parse(atob(encoded || ''));
        return Array.isArray(parsed) ? parsed : Object.assign([], parsed || {});
    } catch (e) {
        return [];
    }
}

function progressLength(duration) {
    return Math.max(1, Math.min(100, Math.floor(duration || 100)));
}

function saveProgress(currentTime, duration, force) {
    currentTime = Math.max(0, Math.floor(Number(currentTime) || 0));
    duration = Math.max(0, Math.floor(Number(duration) || 0));
    if (!duration || !that.supervideoData) {
        return;
    }
    if (duration - currentTime <= 1 && currentTime > 0) {
        currentTime = duration;
    }
    supervideoDuration = duration;
    var length = progressLength(duration);
    var bucket = length < 100 ? currentTime : Math.floor(currentTime / duration * length);
    bucket = Math.max(0, Math.min(length, bucket));
    if (bucket > 0) {
        supervideoMap[bucket] = 1;
    }

    var watched = 0;
    for (var i = 1; i <= length; i++) {
        watched += supervideoMap[i] ? 1 : 0;
    }
    var percent = length < 100 ? Math.floor(watched / length * 100) : watched;
    that.supervideoPercent = percent;

    if (!force && bucket === supervideoLastBucket && percent === supervideoLastPercent) {
        return;
    }
    supervideoLastBucket = bucket;
    supervideoLastPercent = percent;

    var send = function() {
        supervideoSaveTimer = null;
        // Snapshot the payload before asynchronous site resolution or navigation.
        var payload = {
            view_id: that.supervideoData.config.viewid,
            currenttime: currentTime,
            duration: duration,
            percent: percent,
            map: JSON.stringify(supervideoMap)
        };
        currentSite().then(function(site) {
            return site.write("mod_supervideo_progress_save_mobile", payload);
        }).catch(function(error) {
            console.warn('mod_supervideo progress could not be saved', error);
        });
    };

    if (force) {
        if (supervideoSaveTimer) {
            clearTimeout(supervideoSaveTimer);
            supervideoSaveTimer = null;
        }
        send();
    } else if (!supervideoSaveTimer) {
        supervideoSaveTimer = setTimeout(send, 1200);
    }
}

function waitFor(selector, callback, attempts) {
    var element = document.querySelector(selector);
    if (element) {
        callback(element);
        return;
    }
    if ((attempts || 0) >= 30) {
        return;
    }
    setTimeout(function() { waitFor(selector, callback, (attempts || 0) + 1); }, 100);
}

function loadScript(src, test, callback, integrity) {
    if (test()) {
        callback();
        return;
    }
    var existing = document.querySelector('script[src="' + src + '"]');
    if (existing) {
        existing.addEventListener('load', callback, {once: true});
        return;
    }
    var script = document.createElement('script');
    script.src = src;
    if (integrity) {
        script.integrity = integrity;
        script.crossOrigin = 'anonymous';
    }
    script.onload = callback;
    document.head.appendChild(script);
}

function bindHtml5() {
    waitFor('#mod-supervideo-media-' + that.supervideoCmid, function(media) {
        if (that.supervideoData.config.currenttime > 0) {
            media.currentTime = that.supervideoData.config.currenttime;
        }
        var onTime = function() { saveProgress(media.currentTime, media.duration, false); };
        var onPause = function() { saveProgress(media.currentTime, media.duration, true); };
        var onEnded = function() { saveProgress(media.duration, media.duration, true); };
        media.addEventListener('timeupdate', onTime);
        media.addEventListener('pause', onPause);
        media.addEventListener('ended', onEnded);
        supervideoCleanup.push(function() {
            media.removeEventListener('timeupdate', onTime);
            media.removeEventListener('pause', onPause);
            media.removeEventListener('ended', onEnded);
        });

        if (that.supervideoData.content.ishls && media.tagName === 'VIDEO' &&
                !media.canPlayType('application/vnd.apple.mpegurl')) {
            loadScript('https://cdn.jsdelivr.net/npm/hls.js@1.7.3/dist/hls.min.js', function() { return !!window.Hls; }, function() {
                if (window.Hls && window.Hls.isSupported()) {
                    var hls = new window.Hls();
                    hls.loadSource(that.supervideoData.content.fileurl);
                    hls.attachMedia(media);
                    supervideoCleanup.push(function() { hls.destroy(); });
                }
            }, 'sha256-oS5+4c1kpp3NsxQVfkXa/LpwW/sLFEC3k1yyZdN0Qj4=');
        }
    });
}

/**
 * Bind one YouTube player attempt. Never change the WebView identity or manufacture progress.
 */
function bindYoutube() {
    if (youtubeStop || !that.supervideoData || that.supervideoData.content.type !== "youtube") {
        return;
    }

    var data = that.supervideoData;
    var active = true;
    var player = null;
    var mount = null;
    var script = null;
    var ownScript = false;
    var poll = null;
    var deadline = null;
    var interval = null;
    var last = null;
    var played = false;

    function valid() {
        return active && that.supervideoData === data;
    }

    function stopPolling() {
        if (poll !== null) {
            clearTimeout(poll);
            poll = null;
        }
        if (deadline !== null) {
            clearTimeout(deadline);
            deadline = null;
        }
        if (script) {
            script.removeEventListener("error", loadFailed);
        }
    }

    function flushObserved() {
        if (last && that.supervideoData === data) {
            saveProgress(last.time, last.duration, true);
            last = null;
        }
    }

    function stop() {
        if (!active) {
            return;
        }
        active = false;
        stopPolling();
        if (interval !== null) {
            clearInterval(interval);
            interval = null;
        }
        flushObserved();
        if (player && player.destroy) {
            try {
                player.destroy();
            } catch (error) {
                // The iframe may already have been removed by Angular.
            }
        }
        if (mount && mount.parentNode) {
            mount.parentNode.removeChild(mount);
        }
        if (ownScript && script && script.parentNode && !(window.YT && window.YT.Player)) {
            script.parentNode.removeChild(script);
        }
        if (youtubeStop === stop) {
            youtubeStop = null;
        }
    }

    function fail(key, browser, retry) {
        if (!valid()) {
            return;
        }
        stop();
        that.supervideoYoutubeError = "plugin.mod_supervideo." + key;
        that.supervideoYoutubeBrowser = browser;
        that.supervideoYoutubeRetry = retry;
    }

    function loadFailed() {
        fail("mobile_youtubeloaderror", true, true);
    }

    function observe(force) {
        if (!valid() || !played || !player) {
            return;
        }
        var time = Number(player.getCurrentTime());
        var duration = Number(player.getDuration());
        if (!Number.isFinite(time) || !Number.isFinite(duration) || duration <= 0 || time <= 0) {
            return;
        }
        youtubeResume = Math.min(time, duration);
        last = {time: youtubeResume, duration: duration};
        saveProgress(last.time, last.duration, force);
        if (force) {
            last = null;
        }
    }

    function initialise(host) {
        stopPolling();
        // Keep Angular's host intact: the IFrame API replaces only this child.
        mount = document.createElement("div");
        host.appendChild(mount);
        deadline = setTimeout(loadFailed, 15000);
        try {
            player = new window.YT.Player(mount, {
                videoId: data.content.providerid,
                playerVars: {
                    rel: 0,
                    playsinline: 1,
                    controls: data.config.showcontrols,
                    autoplay: data.config.autoplay,
                    start: Math.floor(youtubeResume)
                },
                events: {
                    onReady: function() {
                        if (valid()) {
                            stopPolling();
                        }
                    },
                    onError: function(event) {
                        switch (Number(event.data)) {
                            case 153:
                                fail("mobile_youtubeerror153", true, true);
                                break;
                            case 2:
                                fail("mobile_youtubeinvalid", false, false);
                                break;
                            case 100:
                                fail("mobile_youtubeunavailable", false, false);
                                break;
                            case 101:
                            case 150:
                                fail("mobile_youtubeembedding", false, false);
                                break;
                            case 5:
                                fail("mobile_youtubeplaybackerror", true, true);
                                break;
                            default:
                                loadFailed();
                        }
                    },
                    onStateChange: function(event) {
                        if (!valid()) {
                            return;
                        }
                        if (event.data === window.YT.PlayerState.PLAYING) {
                            played = true;
                            if (interval === null) {
                                interval = setInterval(function() {
                                    observe(false);
                                }, 1000);
                            }
                        } else if (interval !== null) {
                            clearInterval(interval);
                            interval = null;
                            observe(true);
                        } else if (event.data === window.YT.PlayerState.ENDED && played) {
                            observe(true);
                        }
                    }
                }
            });
            // A synchronous error callback can dispose the attempt before construction returns.
            if (!valid() && player && player.destroy) {
                player.destroy();
            }
        } catch (error) {
            loadFailed();
        }
    }

    function waitForApi() {
        if (!valid()) {
            return;
        }
        var host = document.getElementById("mod-supervideo-api-player-" + that.supervideoCmid);
        if (host && window.YT && typeof window.YT.Player === "function") {
            initialise(host);
            return;
        }
        poll = setTimeout(waitForApi, 100);
    }

    youtubeStop = stop;
    that.supervideoYoutubeError = "";
    that.supervideoYoutubeBrowser = false;
    that.supervideoYoutubeRetry = false;
    if (!(window.YT && typeof window.YT.Player === "function")) {
        script = document.querySelector('script[src="https://www.youtube.com/iframe_api"]');
        if (!script) {
            script = document.createElement("script");
            script.src = "https://www.youtube.com/iframe_api";
            ownScript = true;
        }
        script.addEventListener("error", loadFailed);
        if (ownScript) {
            document.head.appendChild(script);
        }
    }
    deadline = setTimeout(loadFailed, 15000);
    waitForApi();
}

this.supervideoRetryYoutube = function() {
    if (this.supervideoYoutubeRetry && !youtubeStop && this.supervideoData &&
            this.supervideoData.content.type === "youtube") {
        bindYoutube();
    }
};

function getCoreIframe(callback) {
    waitFor('#mod-supervideo-frame-' + that.supervideoCmid, function(component) {
        var find = function(count) {
            var iframe = component.querySelector ? component.querySelector('iframe') : null;
            if (iframe) { callback(iframe); return; }
            if (count < 30) { setTimeout(function() { find(count + 1); }, 100); }
        };
        find(0);
    });
}

function bindVimeo() {
    getCoreIframe(function(iframe) {
        loadScript('https://player.vimeo.com/api/player.js', function() { return !!(window.Vimeo && window.Vimeo.Player); }, function() {
            var player = new window.Vimeo.Player(iframe);
            if (that.supervideoData.config.currenttime > 0) {
                player.setCurrentTime(that.supervideoData.config.currenttime).catch(function() {});
            }
            var onTime = function(data) { saveProgress(data.seconds, data.duration, false); };
            var onEnded = function(data) { saveProgress(data.duration, data.duration, true); };
            player.on('timeupdate', onTime);
            player.on('ended', onEnded);
            supervideoCleanup.push(function() {
                player.off('timeupdate', onTime);
                player.off('ended', onEnded);
            });
        });
    });
}

function bindMessages(type) {
    getCoreIframe(function(iframe) {
        var trustedOrigin = that.supervideoData.content.trustedorigin;
        var providerData = {};
        try { providerData = JSON.parse(that.supervideoData.content.providerdata || '{}'); } catch (e) {}
        var identifiers = new Set((providerData.identifiers || []).map(function(item) {
            return typeof item === 'string' ? item : item.identifier;
        }).filter(Boolean));
        var duration = 0;

        var receive = function(event) {
            if (!event || !event.data || event.source !== iframe.contentWindow ||
                    !trustedOrigin || event.origin !== trustedOrigin) {
                return;
            }
            var data = event.data;
            if (type === 'pandavideo') {
                if (data.message === 'panda_allData' && data.playerData) {
                    duration = Number(data.playerData.duration) || 0;
                } else if (data.message === 'panda_timeupdate' && duration) {
                    saveProgress(data.currentTime, duration, false);
                }
            } else if (type === 'ottflix') {
                if (data.origem === 'OTTFLIX-player' && identifiers.has(data.identifier) && data.name === 'progress') {
                    saveProgress(data.currentTime, data.duration, false);
                }
            } else if (type === 'embed' && data.origem === 'supervideo-embed' && data.name === 'progress') {
                saveProgress(data.currentTime, data.duration, false);
            }
        };
        window.addEventListener('message', receive);
        supervideoCleanup.push(function() { window.removeEventListener('message', receive); });

        if (type === 'pandavideo' && trustedOrigin) {
            iframe.contentWindow.postMessage({type: 'currentTime', parameter: that.supervideoData.config.currenttime || 0}, trustedOrigin);
        }
    });
}

this.supervideoPlaybackLoaded = function(result) {
    cleanup();
    this.supervideoLoading = false;
    this.supervideoError = '';
    this.supervideoData = result;
    this.supervideoCmid = Number((this.CONTENT_OTHERDATA && this.CONTENT_OTHERDATA.cmid) || this.supervideoCmid || 0);
    supervideoMap = decodeMap(result.config.datamap);
    this.supervideoPercent = null;
    this.supervideoYoutubeError = "";
    this.supervideoYoutubeBrowser = false;
    this.supervideoYoutubeRetry = false;
    youtubeResume = Math.max(0, Number(result.config.currenttime) || 0);

    // cmid is injected below by the PHP template before this code is evaluated.
    var type = result.content.type;
    if (type === "youtube") {
        youtubeDispatchTimer = setTimeout(function() {
            youtubeDispatchTimer = null;
            if (that.supervideoData === result) {
                bindYoutube();
            }
        }, 0);
        return;
    }
    setTimeout(function() {
        if (type === 'file' || type === 'link') {
            bindHtml5();
        } else if (type === 'vimeo') {
            bindVimeo();
        } else if (type === 'drive') {
            saveProgress(1, 1, true);
        } else if (type === 'pandavideo' || type === 'ottflix' || type === 'embed') {
            bindMessages(type);
        }
    }, 0);
};

this.supervideoPlaybackError = function(error) {
    cleanup();
    this.supervideoLoading = false;
    this.supervideoData = null;
    this.supervideoYoutubeError = "";
    this.supervideoYoutubeBrowser = false;
    this.supervideoYoutubeRetry = false;
    this.supervideoError = error && error.message ? error.message : 'Unable to load Super Video.';
};

// The handler PHP sets this value in otherdata/template context via literal replacement.
this.supervideoCmid = Number((this.CONTENT_OTHERDATA && this.CONTENT_OTHERDATA.cmid) || 0);

this.ionViewWillLeave = function() {
    cleanup();
};
