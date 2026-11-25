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
 * Offline tests of the shared caption controller.
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

function load(file = "amd/src/caption_controller.js") {
    let exported;
    const source = fs.readFileSync(path.join(__dirname, "../..", file), "utf8");
    vm.runInNewContext(source, {define: (deps, factory) => { exported = factory(); }, TextEncoder});
    return exported;
}

function media() {
    return {
        duration: 120, currentTime: 0, paused: false, seeking: false, ended: false,
        seekable: {length: 1, start: () => 0, end: () => 120}
    };
}

test("transcript seek grants no immediate progress", () => {
    const api = load();
    const video = media();
    assert.equal(api.mayReport(video), true);
    assert.equal(api.seek(video, 90), true);
    assert.equal(video.currentTime, 90);
    assert.equal(api.mayReport(video), false);
    video.currentTime = 90.2;
    assert.equal(api.mayReport(video), false);
    video.currentTime = 91;
    video.paused = true;
    assert.equal(api.mayReport(video), false);
    video.paused = false;
    assert.equal(api.mayReport(video), true);
});

test("invalid, unseekable and live targets are rejected", () => {
    const api = load();
    const video = media();
    for (const target of [NaN, -1, Infinity, 120, 121]) {
        assert.equal(api.seek(video, target), false);
    }
    video.duration = Infinity;
    assert.equal(api.seek(video, 5), false);
    video.duration = 120;
    video.seekable.length = 0;
    assert.equal(api.seek(video, 5), false);
});

test("seek suppression is isolated per media element", () => {
    const api = load();
    const a = media();
    const b = media();
    api.seek(a, 60);
    assert.equal(api.mayReport(a), false);
    assert.equal(api.mayReport(b), true);
});

test("WebVTT header and byte limit", () => {
    const api = load();
    api.validateText("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nOlá");
    api.validateText("\uFEFFWEBVTT\r\n\r\ncue");
    for (const value of ["<html>not VTT</html>", "WEBVTT\n\u0000", "WEBVTT\n" + "é".repeat(3000000)]) {
        assert.throws(() => api.validateText(value));
    }
});

test("search is case and accent insensitive", () => {
    const api = load();
    assert.equal(api.normalize("CONFIGURAÇÃO"), "configuracao");
});

test("AMD build exports equivalent behavior", () => {
    const api = load("amd/build/caption_controller.min.js");
    const video = media();
    assert.equal(api.seek(video, 10), true);
    assert.equal(api.mayReport(video), false);
    assert.equal(api.normalize("Itália"), "italia");
});

test("no captions configuration is a no-op", () => {
    const api = load();
    const controller = api.attach(media(), null, {tracks: []}, {});
    controller.setAvailable(true);
    controller.stop();
});
