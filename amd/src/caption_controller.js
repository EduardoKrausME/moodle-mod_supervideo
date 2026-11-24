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
 * WebVTT track lifecycle, accessible transcript and guarded navigation.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    const MAX_BYTES = 5242880;
    const MAX_CUES = 50000;
    const PAGE = 100;
    const suppressed = new WeakMap();

    /**
     * Keep a transcript-initiated seek out of viewing progress until media actually advances.
     *
     * @param {HTMLMediaElement} media Media.
     * @returns {boolean}
     */
    function mayReport(media) {
        const state = suppressed.get(media);
        if (!state) {
            return true;
        }
        if (media.seeking || media.paused || media.ended ||
                !Number.isFinite(media.currentTime) || media.currentTime < state.target + 0.5) {
            return false;
        }
        suppressed.delete(media);
        return true;
    }

    /**
     * Seek without simulating watched segments.
     *
     * @param {HTMLMediaElement} media Media.
     * @param {number} target Seconds.
     * @returns {boolean}
     */
    function seek(media, target) {
        if (!Number.isFinite(target) || !Number.isFinite(media.duration) || target < 0 ||
                target >= media.duration || !media.seekable || !media.seekable.length) {
            return false;
        }
        let allowed = false;
        for (let i = 0; i < media.seekable.length; i++) {
            if (target >= media.seekable.start(i) && target < media.seekable.end(i)) {
                allowed = true;
                break;
            }
        }
        if (!allowed) {
            return false;
        }
        suppressed.set(media, {target: target});
        try {
            media.currentTime = target;
            return true;
        } catch (error) {
            suppressed.delete(media);
            return false;
        }
    }

    /**
     * Fetch only the selected track with no cross-origin credentials or referrer.
     *
     * @param {object} track Track.
     * @param {AbortSignal} signal Cancellation.
     * @returns {Promise<string>}
     */
    async function fetchText(track, signal) {
        const url = new URL(track.url, document.baseURI);
        if (track.source === "url" && (url.protocol !== "https:" || url.username || url.password)) {
            throw new Error("Invalid caption URL");
        }
        if (track.source === "file" && url.origin !== window.location.origin) {
            throw new Error("Invalid local caption URL");
        }
        const response = await fetch(url.href, {
            credentials: track.source === "file" ? "same-origin" : "omit",
            referrerPolicy: "no-referrer",
            redirect: "error",
            signal: signal
        });
        if (!response.ok || Number(response.headers.get("content-length")) > MAX_BYTES) {
            throw new Error("Caption response");
        }
        if (!response.body || !response.body.getReader) {
            const text = await response.text();
            validateText(text);
            return text;
        }
        const reader = response.body.getReader();
        const decoder = new TextDecoder("utf-8", {fatal: true});
        let total = 0;
        let text = "";
        try {
            while (true) {
                const chunk = await reader.read();
                if (chunk.done) {
                    break;
                }
                total += chunk.value.byteLength;
                if (total > MAX_BYTES) {
                    throw new Error("Caption size");
                }
                text += decoder.decode(chunk.value, {stream: true});
            }
            return text + decoder.decode();
        } finally {
            await reader.cancel().catch(function() {});
        }
    }

    /**
     * Validate the input before giving it to the browser WebVTT parser.
     *
     * @param {string} text WebVTT.
     */
    function validateText(text) {
        if (typeof text !== "string" || text.length > MAX_BYTES ||
                new TextEncoder().encode(text).byteLength > MAX_BYTES ||
                !/^\uFEFF?WEBVTT(?:[ \t][^\r\n]*)?(?:\r\n|\n|\r|$)/.test(text) || text.includes("\u0000")) {
            throw new Error("Invalid WebVTT");
        }
    }

    function normalize(text) {
        return text.toLocaleLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    }

    function formatTime(seconds) {
        const total = Math.floor(seconds);
        const hours = Math.floor(total / 3600);
        return (hours ? hours + ":" : "") +
            String(Math.floor(total / 60) % 60).padStart(2, "0") + ":" + String(total % 60).padStart(2, "0");
    }

    /**
     * Attach configured tracks only to the supplied media element.
     *
     * @param {HTMLMediaElement} media Media.
     * @param {HTMLElement} panel Mustache-rendered controls.
     * @param {object} config Caption configuration.
     * @param {object} options Player and authenticated file adapter.
     * @returns {object} Lifecycle controller.
     */
    function attach(media, panel, config, options) {
        const noop = {stop: function() {}, setAvailable: function() {}};
        if (!panel || !config || !Array.isArray(config.tracks) || !config.tracks.length) {
            return noop;
        }
        const field = name => panel.querySelector("[data-caption-" + name + "]");
        const select = field("select");
        const visible = field("visible");
        const transcript = field("transcript");
        const search = field("search");
        const follow = field("follow");
        const list = field("list");
        const more = field("more");
        const download = field("download");
        const status = field("status");
        const audio = field("audio");
        const listeners = [];
        let available = false;
        let stopped = false;
        let revision = 0;
        let abort = null;
        let trackNode = null;
        let objectUrl = null;
        let timeout = null;
        let cues = [];
        let matches = [];
        let limit = PAGE;
        let active = [];
        let lastUpdate = 0;
        const records = config.tracks.slice(0, 20);
        const defaultTrack = records.find(record => record.isdefault);
        const preferred = defaultTrack || records[0];

        function listen(node, event, callback) {
            node.addEventListener(event, callback);
            listeners.push(() => node.removeEventListener(event, callback));
        }

        function clearTrack() {
            revision++;
            if (timeout !== null) {
                clearTimeout(timeout);
                timeout = null;
            }
            if (abort) {
                abort.abort();
                abort = null;
            }
            if (trackNode) {
                const player = options.player;
                if (player && player.isVideo && player.captions &&
                        player.captions.currentTrackNode === trackNode.track) {
                    player.currentTrack = -1;
                }
                trackNode.track.mode = "disabled";
                trackNode.remove();
                trackNode = null;
            }
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            cues = [];
            matches = [];
            active = [];
            list.replaceChildren();
            audio.textContent = "";
            more.hidden = true;
            download.disabled = true;
        }

        function updateVisibility() {
            if (!trackNode) {
                return;
            }
            const track = trackNode.track;
            const player = options.player;
            if (player && player.isVideo && player.supported && player.supported.ui && player.captions) {
                const tracks = Array.from(media.textTracks).filter(item =>
                    ["captions", "subtitles"].includes(item.kind) && player.captions.meta.has(item));
                const index = tracks.indexOf(track);
                if (visible.checked && index >= 0) {
                    player.currentTrack = index;
                } else if (!visible.checked && player.captions.currentTrackNode === track) {
                    player.currentTrack = -1;
                }
                track.mode = "hidden";
            } else {
                if (visible.checked) {
                    Array.from(media.textTracks).forEach(item => {
                        if (item !== track && ["captions", "subtitles"].includes(item.kind) && item.mode === "showing") {
                            item.mode = "disabled";
                        }
                    });
                }
                track.mode = visible.checked && media.tagName === "VIDEO" ? "showing" : "hidden";
            }
            updateActive(true);
        }

        function updateActive(force) {
            if (!available || !trackNode) {
                return;
            }
            const now = Date.now();
            if (!force && now - lastUpdate < 200) {
                return;
            }
            lastUpdate = now;
            const time = media.currentTime;
            const next = cues.filter(cue => cue.start <= time && time < cue.end).map(cue => cue.index);
            const changed = next.join(",") !== active.join(",");
            active = next;
            if (media.tagName === "AUDIO") {
                audio.textContent = visible.checked ? cues.filter(cue => active.includes(cue.index)).map(cue => cue.text).join("\n") : "";
            }
            if (!changed && !force) {
                return;
            }
            let first = null;
            list.querySelectorAll("[data-cue-index]").forEach(row => {
                const current = active.includes(Number(row.dataset.cueIndex));
                if (current) {
                    row.setAttribute("aria-current", "true");
                    row.classList.add("font-weight-bold");
                    row.style.borderInlineStart = "3px solid currentColor";
                    first = first || row;
                } else {
                    row.removeAttribute("aria-current");
                    row.classList.remove("font-weight-bold");
                    row.style.borderInlineStart = "";
                }
            });
            if (follow.checked && first && !transcript.hidden && transcript.open &&
                    !list.contains(document.activeElement) && document.activeElement !== search) {
                const top = first.offsetTop - list.offsetTop;
                if (top < list.scrollTop || top + first.offsetHeight > list.scrollTop + list.clientHeight) {
                    list.scrollTop = Math.max(0, top - list.clientHeight / 3);
                }
            }
        }

        function render() {
            const query = normalize(search.value.trim());
            matches = cues.filter(cue => !query || cue.normalized.includes(query));
            list.replaceChildren();
            const fragment = document.createDocumentFragment();
            matches.slice(0, limit).forEach(cue => {
                const item = document.createElement("li");
                item.dataset.cueIndex = String(cue.index);
                const button = document.createElement("button");
                button.type = "button";
                button.className = "btn btn-link p-1";
                button.textContent = formatTime(cue.start);
                button.disabled = !options.canSeek;
                button.dataset.cueSeek = String(cue.start);
                const text = document.createElement("span");
                text.textContent = cue.text;
                item.append(button, text);
                fragment.appendChild(item);
            });
            list.appendChild(fragment);
            more.hidden = matches.length <= limit;
            download.disabled = !cues.length;
            status.textContent = cues.length && !matches.length ? panel.dataset.empty : "";
            updateActive(true);
        }

        async function load() {
            clearTrack();
            if (!available || stopped) {
                return;
            }
            const record = records.find(item => String(item.id) === select.value);
            if (!record) {
                return;
            }
            const generation = revision;
            const fail = function() {
                if (!stopped && generation === revision) {
                    clearTrack();
                    status.textContent = panel.dataset.error;
                }
            };
            status.textContent = panel.dataset.loading;
            abort = new AbortController();
            timeout = setTimeout(fail, 20000);
            try {
                const text = options.read && record.source === "file" ?
                    await options.read(record, abort.signal) : await fetchText(record, abort.signal);
                if (stopped || generation !== revision) {
                    return;
                }
                validateText(text);
                objectUrl = URL.createObjectURL(new Blob([text], {type: "text/vtt"}));
                const node = document.createElement("track");
                node.kind = record.kind;
                node.label = record.label;
                node.srclang = record.language;
                node.src = objectUrl;
                trackNode = node;
                node.addEventListener("error", fail, {once: true});
                node.addEventListener("load", function() {
                    if (stopped || generation !== revision) {
                        return;
                    }
                    clearTimeout(timeout);
                    timeout = null;
                    try {
                        const parsed = Array.from(node.track.cues || []);
                        if (!parsed.length || parsed.length > MAX_CUES) {
                            throw new Error("Invalid cues");
                        }
                        cues = parsed.filter(cue => Number.isFinite(cue.startTime) && Number.isFinite(cue.endTime) &&
                            cue.startTime >= 0 && cue.endTime > cue.startTime).map((cue, index) => {
                            const text = cue.getCueAsHTML().textContent || "";
                            return {index: index, start: cue.startTime, end: cue.endTime, text: text, normalized: normalize(text)};
                        });
                        limit = PAGE;
                        render();
                        updateVisibility();
                    } catch (error) {
                        fail();
                    }
                }, {once: true});
                media.appendChild(node);
                node.track.mode = "hidden";
            } catch (error) {
                fail();
            }
        }

        function setAvailable(value) {
            value = Boolean(value);
            if (stopped || available === value) {
                return;
            }
            available = value;
            panel.hidden = !value;
            if (value) {
                load();
            } else {
                clearTrack();
            }
        }

        records.forEach(record => {
            const option = document.createElement("option");
            option.value = String(record.id);
            option.textContent = record.label + " (" + record.language + ")";
            select.appendChild(option);
        });
        select.value = String(preferred.id);
        visible.checked = Boolean(defaultTrack);
        transcript.hidden = !config.enabled;
        download.hidden = !config.download;
        listen(select, "change", load);
        listen(visible, "change", updateVisibility);
        listen(search, "input", function() {
            limit = PAGE;
            render();
        });
        listen(more, "click", function() {
            limit += PAGE;
            render();
        });
        listen(list, "click", function(event) {
            const button = event.target.closest("[data-cue-seek]");
            if (button && available && options.canSeek && !button.disabled) {
                seek(media, Number(button.dataset.cueSeek));
            }
        });
        listen(media, "timeupdate", () => updateActive(false));
        listen(media, "seeked", () => updateActive(true));
        listen(download, "click", function() {
            if (!config.download || !cues.length) {
                return;
            }
            const blob = new Blob([cues.map(cue => formatTime(cue.start) + " " + cue.text).join("\n")], {type: "text/plain;charset=utf-8"});
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement("a");
            anchor.href = url;
            anchor.download = "transcript.txt";
            anchor.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        });
        setAvailable(options.available !== false);
        return {
            setAvailable: setAvailable,
            stop: function() {
                if (!stopped) {
                    stopped = true;
                    clearTrack();
                    listeners.forEach(remove => remove());
                    panel.hidden = true;
                    select.replaceChildren();
                }
            }
        };
    }

    return {attach: attach, mayReport: mayReport, seek: seek, validateText: validateText, normalize: normalize};
});
