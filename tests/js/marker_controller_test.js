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
 * Offline marker controller regression tests.
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

function load(file, context = {}) {
    let api;
    const source = fs.readFileSync(path.join(__dirname, "../..", file), "utf8");
    vm.runInNewContext(source, Object.assign({define: (deps, factory) => { api = factory(); }}, context));
    return api;
}

const config = {allowed: true, buttonlabel: "Jump", errorlabel: "Failed", points: [
    {time: 30, label: "Question", skip: true},
    {time: 90, label: "Answer", skip: false},
    {time: 130, label: "Last", skip: true}
]};

function fixture(file = "amd/src/marker_controller.js") {
    const timers = new Map();
    let id = 0;
    const nodes = [];
    const create = () => {
        const handlers = new Map();
        return {
            hidden: true,
            addEventListener: (name, fn) => handlers.set(name, fn),
            removeEventListener: name => handlers.delete(name),
            setAttribute() {},
            remove() { this.removed = true; },
            click() { handlers.get("click")?.({preventDefault() {}, stopPropagation() {}}); }
        };
    };
    const api = load(file, {
        document: {createElement: create},
        setTimeout: fn => { timers.set(++id, fn); return id; },
        clearTimeout: key => timers.delete(key)
    });
    const host = {appendChild: node => nodes.push(node)};
    return {api, host, nodes, timers, expire: () => Array.from(timers.values()).forEach(fn => fn())};
}

test("interval boundaries and invalid destinations", () => {
    const {api} = fixture();
    for (const t of [0, 29, 90, 130, 149]) {
        assert.equal(api.target(config.points, t, 150), null);
    }
    assert.equal(api.target(config.points, 30, 150).time, 90);
    assert.equal(api.target(config.points, 89.9, 150).time, 90);
    for (const duration of [90, 89, Infinity, NaN]) {
        assert.equal(api.target(config.points, 40, duration), null);
    }
});

test("button tracks actual position, prevents double click and waits for playback", () => {
    const f = fixture();
    let requested = 0;
    const c = f.api.attach(f.host, config, {seek: time => { requested = time; }});
    const button = f.nodes[0];
    c.update(29, 150);
    assert.equal(button.hidden, true);
    c.update(30, 150);
    assert.equal(button.hidden, false);
    assert.equal(button.textContent, "Jump: Answer");
    button.click();
    button.click();
    assert.equal(requested, 90);
    assert.equal(button.disabled, true);
    assert.equal(c.mayReport(30, true), false);
    c.update(90, 150);
    assert.equal(button.hidden, true);
    assert.equal(c.mayReport(90, true), false);
    assert.equal(c.mayReport(91, false), false);
    assert.equal(c.mayReport(91, true), false);
    assert.equal(c.mayReport(92, true), true);
    c.update(40, 150);
    assert.equal(button.hidden, false);
    c.stop();
    assert.equal(button.removed, true);
    assert.equal(f.timers.size, 0);
});

test("timeout and rejected seek recover without fake credit", async() => {
    const f = fixture();
    const c = f.api.attach(f.host, config, {seek: () => Promise.reject(new Error("provider rejected"))});
    c.update(40, 150);
    f.nodes[0].click();
    await Promise.resolve();
    assert.equal(f.nodes[1].textContent, "Failed");
    assert.equal(f.nodes[0].disabled, false);
    assert.equal(c.mayReport(40, true), false);
    assert.equal(c.mayReport(41, true), true);
    f.nodes[0].click();
    f.expire();
    assert.equal(f.nodes[1].hidden, false);
    c.stop();
});

test("disabled controls and live mode do not expose jumps", () => {
    const f = fixture();
    f.api.attach(f.host, Object.assign({}, config, {allowed: false}), {seek() {}});
    assert.equal(f.nodes.length, 0);
    const c = f.api.attach(f.host, config, {seek() {}, available: false});
    c.update(40, 150);
    assert.equal(f.nodes[0].hidden, true);
    c.setAvailable(true);
    assert.equal(f.nodes[0].hidden, false);
    c.setAvailable(false);
    assert.equal(f.nodes[0].hidden, true);
    c.stop();
});

test("native media shares caption seek guard and removes listeners", () => {
    const f = fixture();
    const captions = load("amd/src/caption_controller.js");
    const events = new Map();
    const media = {currentTime: 40, duration: 150, paused: true, seeking: false, ended: false,
        seekable: {length: 1, start: () => 0, end: () => 150},
        addEventListener: (name, fn) => events.set(name, fn),
        removeEventListener: name => events.delete(name)};
    const c = f.api.native(media, f.host, config, {
        canSeek: true, seek: captions.seek, settle: captions.settleSeek
    });
    f.nodes[0].click();
    assert.equal(media.currentTime, 90);
    assert.equal(captions.mayReport(media), false);
    events.get("seeked")();
    assert.equal(f.nodes[0].hidden, true);
    media.paused = false;
    media.currentTime = 91;
    assert.equal(captions.mayReport(media), true);
    media.currentTime = 40;
    events.get("seeked")();
    assert.equal(f.nodes[0].hidden, false);
    c.stop();
    assert.equal(events.size, 0);
});

test("independent controllers and AMD build", () => {
    const f = fixture("amd/build/marker_controller.min.js");
    const one = f.api.attach(f.host, config, {seek() {}});
    const two = f.api.attach(f.host, config, {seek() {}});
    one.update(40, 150);
    two.update(0, 150);
    assert.equal(f.nodes[0].hidden, false);
    assert.equal(f.nodes[2].hidden, true);
    one.stop();
    two.update(50, 150);
    assert.equal(f.nodes[2].hidden, false);
    two.stop();
});
