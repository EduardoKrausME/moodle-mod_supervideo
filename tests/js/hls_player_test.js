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
 * Offline HLS lifecycle tests, using the actual web and mobile controllers.
 *
 * @package mod_supervideo
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const {test} = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

function source(file) {
    return fs.readFileSync(path.join(__dirname, "../..", file), "utf8");
}

function extract(file) {
    const content = source(file);
    const start = content.indexOf("function createHlsController(");
    const marker = "return {start: start, stop: stop, validPosition: validPosition};";
    const end = content.indexOf("}", content.indexOf(marker, start) + marker.length);
    return content.slice(start, end + 1).trim();
}

function setup(native = false, playlist = null) {
    const events = new Map();
    const timers = new Map();
    const instances = [];
    const modes = [];
    const errors = [];
    let next = 0;
    class Hls {
        static isSupported() { return true; }
        static Events = {LEVEL_LOADED: "level", MANIFEST_PARSED: "manifest", ERROR: "error"};
        static ErrorTypes = {MEDIA_ERROR: "media", NETWORK_ERROR: "network"};
        constructor() { this.events = new Map(); instances.push(this); }
        on(event, handler) { this.events.set(event, handler); }
        emit(event, data) { this.events.get(event)?.(event, data); }
        loadSource(url) { this.url = url; }
        attachMedia(media) { this.media = media; }
        destroy() { this.destroyed = true; }
        recoverMediaError() { this.recovered = true; }
        startLoad() { this.restarted = true; }
    }
    const media = {
        duration: 120, currentTime: 0, readyState: 1,
        canPlayType: () => native ? "probably" : "",
        addEventListener: (event, handler) => events.set(event, handler),
        removeEventListener: event => events.delete(event),
        pause() {},
        removeAttribute(name) { delete this[name]; },
        load() {}
    };
    const context = {
        window: {Hls}, URL,
        fetch: playlist === null ? undefined : async() => ({
            ok: true,
            text: async() => playlist
        }),
        AbortController: class {
            constructor() { this.signal = {}; }
            abort() {}
        },
        setTimeout: (fn, delay) => { timers.set(++next, {fn, delay}); return next; },
        clearTimeout: id => timers.delete(id)
    };
    const create = vm.runInNewContext(extract("js/mobileapp/player.js") + "\ncreateHlsController", context);
    const callbacks = {resume: 12, mode: m => modes.push(m), ready() {}, error: e => errors.push(e)};
    const controller = create(media, "https://example.com/movie.m3u8?token=abc&expires=123", callbacks);
    controller.start();
    return {create, callbacks, controller, media, instances, events, modes, errors, timers};
}

test("web and mobile use the same controller", () => {
    const normalize = s => s.split("\n").map(l => l.trim()).join("\n").trim();
    const web = extract("amd/src/player_create.js");
    const mobile = extract("js/mobileapp/player.js");
    assert.equal(normalize(web), normalize(mobile));
});

test("VOD permits finite progress and restores only after classification", () => {
    const s = setup();
    assert.equal(s.media.currentTime, 0);
    assert.equal(s.controller.validPosition(5, 120), false);
    s.instances[0].emit("level", {details: {live: false}});
    assert.equal(s.media.currentTime, 12);
    assert.equal(s.controller.validPosition(15, 120), true);
    for (const duration of [Infinity, NaN, 0, -1, 4294967296]) {
        assert.equal(s.controller.validPosition(15, duration), false);
    }
    assert.equal(s.controller.validPosition(NaN, 120), false);
    assert.equal(s.instances[0].url, "https://example.com/movie.m3u8?token=abc&expires=123");
    s.controller.stop();
    assert.equal(s.instances[0].destroyed, true);
    assert.equal(s.events.size, 0);
});

test("live classification remains conservative even when DVR duration is finite or event ends", () => {
    const s = setup();
    s.instances[0].emit("level", {details: {live: true}});
    s.instances[0].emit("level", {details: {live: false}});
    assert.equal(s.controller.validPosition(15, 120), false);
    assert.equal(s.media.currentTime, 0);
    assert.equal(s.modes.at(-1), "live");
});

test("native playback does not create HLS.js or award unconfirmed progress", () => {
    const s = setup(true);
    assert.equal(s.instances.length, 0);
    assert.equal(s.media.src, "https://example.com/movie.m3u8?token=abc&expires=123");
    assert.equal(s.controller.validPosition(15, 120), false);
    s.controller.stop();
    assert.equal(s.media.src, undefined);
});

test("fatal media recovery is bounded and stale callbacks are ignored", () => {
    const s = setup();
    const h = s.instances[0];
    h.emit("error", {fatal: true, type: "media"});
    assert.equal(h.recovered, true);
    h.emit("error", {fatal: true, type: "media"});
    assert.equal(h.destroyed, true);
    assert.equal(s.errors[0], "hls_error");
    h.emit("level", {details: {live: false}});
    assert.equal(s.controller.validPosition(15, 120), false);
});

test("access denied does not retry network requests", () => {
    const s = setup();
    s.instances[0].emit("error", {fatal: true, type: "network", response: {code: 403}});
    assert.equal(s.instances[0].restarted, undefined);
    assert.equal(s.errors[0], "hls_error");
});

test("native VOD with an ended playlist enables finite progress", async() => {
    const s = setup(true, "#EXTM3U\n#EXTINF:120,\nsegment.ts\n#EXT-X-ENDLIST\n");
    for (let index = 0; index < 5; index++) {
        await Promise.resolve();
    }
    assert.equal(s.controller.validPosition(15, 120), true);
    assert.equal(s.media.currentTime, 12);
    s.controller.stop();
});

test("two simultaneous controllers keep HLS instances independent", () => {
    const s = setup();
    const secondMedia = Object.assign({}, s.media);
    const second = s.create(secondMedia, "https://example.com/other.m3u8", s.callbacks);
    second.start();
    assert.equal(s.instances.length, 2);
    s.controller.stop();
    assert.equal(s.instances[0].destroyed, true);
    assert.equal(s.instances[1].destroyed, undefined);
    second.stop();
});

test("compiled AMD includes exactly the same HLS controller and player methods", () => {
    const web = source("amd/src/player_create.js");
    const build = source("amd/build/player_create.min.js");
    const helper = web.slice(web.indexOf("    /**\n     * Own one HLS"), web.indexOf("    var player_create = {")).trim();
    const methods = web.slice(web.indexOf("        resource_hls:"), web.indexOf("        vimeo:")).trim().replace(/,\s*$/, "");
    assert.equal(build.includes(helper), true);
    assert.equal(build.includes(methods), true);
});
