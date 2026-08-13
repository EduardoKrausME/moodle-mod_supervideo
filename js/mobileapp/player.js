var that = this;
var supervideoCleanup = [];
var supervideoSaveTimer = null;
var supervideoMap = [];
var supervideoLastBucket = -1;
var supervideoLastPercent = -1;
var supervideoDuration = 0;

this.supervideoLoading = true;
this.supervideoError = '';
this.supervideoData = null;
this.supervideoPercent = null;

function cleanup() {
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
        currentSite().then(function(site) {
            return site.write('mod_supervideo_progress_save_mobile', {
                view_id: that.supervideoData.config.viewid,
                currenttime: currentTime,
                duration: duration,
                percent: percent,
                map: JSON.stringify(supervideoMap)
            });
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

function loadScript(src, test, callback) {
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
            loadScript('https://cdn.jsdelivr.net/npm/hls.js', function() { return !!window.Hls; }, function() {
                if (window.Hls && window.Hls.isSupported()) {
                    var hls = new window.Hls();
                    hls.loadSource(that.supervideoData.content.fileurl);
                    hls.attachMedia(media);
                    supervideoCleanup.push(function() { hls.destroy(); });
                }
            });
        }
    });
}

function bindYoutube() {
    var previous = window.onYouTubeIframeAPIReady;
    var initialise = function() {
        if (!window.YT || !window.YT.Player) { return; }
        var interval = null;
        var player = new window.YT.Player('mod-supervideo-api-player-' + that.supervideoCmid, {
            videoId: that.supervideoData.content.providerid,
            playerVars: {
                rel: 0,
                playsinline: 1,
                controls: that.supervideoData.config.showcontrols,
                autoplay: that.supervideoData.config.autoplay,
                start: that.supervideoData.config.currenttime || 0
            },
            events: {
                onStateChange: function(event) {
                    if (event.data === window.YT.PlayerState.PLAYING && !interval) {
                        interval = setInterval(function() {
                            saveProgress(player.getCurrentTime(), player.getDuration(), false);
                        }, 1000);
                    }
                    if (event.data !== window.YT.PlayerState.PLAYING && interval) {
                        clearInterval(interval);
                        interval = null;
                        saveProgress(player.getCurrentTime(), player.getDuration(), true);
                    }
                    if (event.data === window.YT.PlayerState.ENDED) {
                        saveProgress(player.getDuration(), player.getDuration(), true);
                    }
                }
            }
        });
        supervideoCleanup.push(function() {
            if (interval) { clearInterval(interval); }
            if (player && player.destroy) { player.destroy(); }
        });
    };
    window.onYouTubeIframeAPIReady = function() {
        if (typeof previous === 'function') { previous(); }
        initialise();
    };
    loadScript('https://www.youtube.com/iframe_api', function() { return !!(window.YT && window.YT.Player); }, initialise);
}

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

    // cmid is injected below by the PHP template before this code is evaluated.
    var type = result.content.type;
    setTimeout(function() {
        if (type === 'file' || type === 'link') {
            bindHtml5();
        } else if (type === 'youtube') {
            bindYoutube();
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
    this.supervideoError = error && error.message ? error.message : 'Unable to load Super Video.';
};

// The handler PHP sets this value in otherdata/template context via literal replacement.
this.supervideoCmid = Number((this.CONTENT_OTHERDATA && this.CONTENT_OTHERDATA.cmid) || 0);

this.ionViewWillLeave = function() {
    cleanup();
};
