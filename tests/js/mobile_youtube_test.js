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
 * Offline regression tests for the mobile YouTube player.
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

const source = fs.readFileSync(path.join(__dirname, "../../js/mobileapp/player.js"), "utf8");

function setup(api = true) {
    const timers = new Map();
    const players = [];
    const writes = [];
    const scripts = [];
    let id = 0;
    const schedule = (callback, delay, repeat = false) => {
        timers.set(++id, {callback, delay, repeat});
        return id;
    };
    const run = delay => {
        for (const [key, timer] of [...timers]) {
            if (timer.delay === delay && timers.has(key)) {
                if (!timer.repeat) {
                    timers.delete(key);
                }
                timer.callback();
            }
        }
    };
    const host = {
        appendChild(node) { node.parentNode = this; },
        removeChild(node) { node.parentNode = null; }
    };
    function Player(node, config) {
        this.events = config.events;
        this.config = config;
        this.time = 20;
        this.getCurrentTime = () => this.time;
        this.getDuration = () => 100;
        this.destroy = () => { this.destroyed = true; };
        players.push(this);
    }
    const yt = {Player, PlayerState: {PLAYING: 1, PAUSED: 2, ENDED: 0}};
    const context = {
        window: api ? {YT: yt} : {},
        document: {
            getElementById: () => host,
            querySelector: () => scripts.find(s => s.parentNode) || null,
            createElement: () => {
                const listeners = new Map();
                return {
                    addEventListener: (name, callback) => listeners.set(name, callback),
                    removeEventListener: name => listeners.delete(name),
                    fire: name => listeners.get(name)?.()
                };
            },
            head: {
                appendChild(script) { script.parentNode = this; scripts.push(script); },
                removeChild(script) { script.parentNode = null; }
            }
        },
        setTimeout: schedule,
        clearTimeout: key => timers.delete(key),
        setInterval: (callback, delay) => schedule(callback, delay, true),
        clearInterval: key => timers.delete(key),
        atob,
        console,
        CONTENT_OTHERDATA: {cmid: 42},
        CoreSitesProvider: {
            getCurrentSiteId: () => "site",
            getSite: async() => ({write: async(name, payload) => writes.push({name, payload})})
        }
    };
    vm.createContext(context);
    vm.runInContext(source, context);
    context.supervideoPlaybackLoaded({
        content: {type: "youtube", providerid: "M7lc1UVf-VE"},
        config: {viewid: 8, currenttime: 10, showcontrols: 1, autoplay: 0, datamap: ""}
    });
    run(0);
    return {context, timers, players, writes, scripts, run, yt};
}

test("153 offers Moodle browser fallback without granting progress", async() => {
    const s = setup();
    s.players[0].events.onError({data: 153});
    assert.equal(s.context.supervideoYoutubeError, "plugin.mod_supervideo.mobile_youtubeerror153");
    assert.equal(s.context.supervideoYoutubeBrowser, true);
    assert.equal(s.players[0].destroyed, true);
    s.players[0].events.onStateChange({data: 0});
    await Promise.resolve();
    assert.equal(s.writes.length, 0);
    assert.equal(s.timers.size, 0);
});

test("terminal errors do not promise a browser workaround", () => {
    for (const [code, key] of [[2, "invalid"], [100, "unavailable"], [101, "embedding"], [150, "embedding"]]) {
        const s = setup();
        s.players[0].events.onError({data: code});
        assert.equal(s.context.supervideoYoutubeError, "plugin.mod_supervideo.mobile_youtube" + key);
        assert.equal(s.context.supervideoYoutubeBrowser, false);
        assert.equal(s.context.supervideoYoutubeRetry, false);
    }
});

test("observed progress is flushed before failure and retry resumes once", async() => {
    const s = setup();
    const p = s.players[0];
    p.events.onReady();
    p.events.onStateChange({data: 1});
    s.run(1000);
    p.events.onError({data: 153});
    await Promise.resolve();
    assert.equal(s.writes.length, 1);
    assert.equal(s.writes[0].payload.view_id, 8);
    assert.equal(s.writes[0].payload.currenttime, 20);
    s.context.supervideoRetryYoutube();
    s.context.supervideoRetryYoutube();
    assert.equal(s.players.length, 2);
    assert.equal(s.players[1].config.playerVars.start, 20);
    p.events.onError({data: 100});
    assert.equal(s.context.supervideoYoutubeError, "");
    s.context.ionViewWillLeave();
    assert.equal(s.timers.size, 0);
});

test("network error permits retry and late callbacks cannot revive an inactive player", () => {
    const s = setup(false);
    s.scripts[0].fire("error");
    assert.equal(s.context.supervideoYoutubeError, "plugin.mod_supervideo.mobile_youtubeloaderror");
    assert.equal(s.context.supervideoYoutubeRetry, true);
    s.context.supervideoRetryYoutube();
    assert.equal(s.scripts.length, 2);
    s.context.ionViewWillLeave();
    s.context.window.YT = s.yt;
    s.run(100);
    assert.equal(s.players.length, 0);
    assert.equal(s.timers.size, 0);
});

test("API readiness creates one player and timeout is recoverable", () => {
    const s = setup(false);
    s.context.window.YT = s.yt;
    s.run(100);
    s.run(100);
    assert.equal(s.players.length, 1);
    s.run(15000);
    assert.equal(s.context.supervideoYoutubeError, "plugin.mod_supervideo.mobile_youtubeloaderror");
    assert.equal(s.players[0].destroyed, true);
});

test("normal playback saves progress and stops timers on navigation", async() => {
    const s = setup();
    const p = s.players[0];
    p.events.onReady();
    p.events.onStateChange({data: 1});
    s.run(1000);
    s.run(1200);
    await Promise.resolve();
    assert.equal(s.writes[0].name, "mod_supervideo_progress_save_mobile");
    p.time = 30;
    p.events.onStateChange({data: 2});
    await Promise.resolve();
    assert.equal(s.writes.at(-1).payload.currenttime, 30);
    s.context.ionViewWillLeave();
    assert.equal(s.timers.size, 0);
});

test("HTML5 provider remains on its existing dispatch path", () => {
    const s = setup();
    s.context.ionViewWillLeave();
    s.context.supervideoPlaybackLoaded({content: {type: "file"}, config: {datamap: ""}});
    s.run(0);
    assert.equal(s.players.length, 1);
    assert.equal(s.context.supervideoYoutubeError, "");
});
