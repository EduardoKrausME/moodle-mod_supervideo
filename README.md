# Moodle mod_supervideo

A module designed to simplify embedding YouTube, Vimeo, and Google Drive videos in the Moodle environment.

To provide a more dynamic and customizable teaching experience, I present the Super Video Module, an innovative tool that extends Moodle's capabilities by allowing videos from YouTube, Google Drive, or Vimeo to be added easily.

One of Super Video's notable features is its ability to adapt to Moodle. The video player automatically adjusts its width to avoid display issues, ensuring that the content is shown optimally-neither too small nor excessively large.

In addition, Super Video prioritizes continuity in student progress. The system automatically saves the point where the student stopped, providing a continuous and personalized learning experience. When the student returns to the video, the player automatically positions them at the exact point where they left off, eliminating the need to search manually.

An additional innovation is the viewing map bar, located below the video. This bar provides a visual representation of the actual percentage of the video that the student has watched. This approach prevents the student from skipping to the end of the video without consuming all the content, promoting more careful viewing and a deeper understanding of the material.

## Reports

In addition, Super Video generates comprehensive reports, providing instructors with valuable insights into student views and viewing maps. This detailed analysis enables continuous adjustments to the content and teaching approach, ensuring effective delivery of the material.

## Global settings

For administrators, the global settings feature offers comprehensive customization. Default fields can be defined for all videos, ensuring consistency throughout the learning environment. However, flexibility is not compromised, as instructors are free to modify this data in specific videos without affecting the global settings.

To access these settings, simply navigate to the Site Administration section, select "Plugins", then "Activity Modules", and finally choose "Super Video." Here, the customization options are readily available, allowing refined adaptation according to the specific needs of each course.

## Moodle App: updating the plugin and clearing stale templates

Super Video uses a remote site plugin to provide the Moodle App with an Angular template and JavaScript. Moodle's server-side template cache, the app's cached site plugin content, and cached web service responses are separate.

If the app still displays "Template has some errors and cannot be displayed" or an old "Invalid Login Token" message after updating Super Video:

1. Install the updated plugin and complete the Moodle upgrade.
2. Purge Moodle caches in Site administration > Development > Purge caches.
3. Reopen the activity in the Moodle App and pull down to refresh it. If necessary, sign out and sign back in to refresh the site's plugin data.
4. If stale content persists, first synchronise any pending offline changes and check for downloaded content. Only then consider clearing the site's stored data in the app, as this can remove local downloads and unsynchronised changes. Reconnect to the site and reopen the activity.

These steps help eliminate outdated site plugin templates; they do not resolve every playback or authentication error. YouTube playback error 153 on iOS is tracked in [issue #92](https://github.com/EduardoKrausME/moodle-mod_supervideo/issues/92).

### YouTube playback in the Moodle App

Error 153 means that YouTube did not receive an acceptable Referer or equivalent client identity. Super Video keeps the existing IFrame API player and offers retry or opening the same Moodle activity in the system browser when the embedded player cannot load. The Moodle App handles auto-login when available; otherwise, sign in to the same Moodle account in the browser. If the web player can play the video, it records progress under the existing Moodle viewing and completion rules. Merely opening the browser does not award watched progress. Previously completed viewing sessions and resume positions follow the existing server rules.

Moodle LMS 5.2 and Moodle App 5.2 introduced [native site Referer support](https://moodledev.io/general/app/development/plugins-development-guide/troubleshooting#i-want-to-display-embedded-content-that-requires-the-moodle-site-as-referer) for core-iframe. This is not the same as supplying an origin or widget_referrer parameter to the YouTube API. The YouTube-specific integration is not switched to this route without real-device validation of playback and progress events. No proxy, custom authentication token or fabricated Referer is used.

Run the offline JavaScript regression tests with `node --test tests/js/mobile_youtube_test.js`. Real iOS playback and browser auto-login still require device testing.

## HLS streaming (.m3u8)

Select the external video link source and enter the complete HTTP(S) .m3u8 URL, including any signed query parameters. Native HLS is preferred when supported; otherwise the pinned HLS.js library is loaded with integrity protection. Web HLS.js playback retains adaptive and manual quality selection. Embedded subtitle tracks depend on the manifest and browser; external WebVTT configuration and transcription are not included.

All playlists, media segments, subtitles and encryption keys must be accessible to the client. For HLS.js configure CORS on the streaming origin for all required resources and use HTTPS. Signed parameters are not automatically copied to child URLs. Moodle permissions protect the activity, not the external CDN. No proxy or additional credentials are sent.

Percentage progress and resume are available only for confirmed VOD with finite media duration. HLS.js reports live status in level details; native playback checks the media playlist for ENDLIST (one master-to-media lookup, subject to CORS). If inspection is blocked or inconclusive, playback remains available but percentage progress is not recorded. A live session never becomes eligible for percentage tracking merely because a DVR window is finite or the event ends. Use a separate VOD activity for the recording. Existing viewing-based completion is unchanged; live attendance and server-side enforcement of a live-specific academic policy are outside this change. Do not configure percentage-based completion or grades for live-only streams.

The plugin does not upload HLS packages or bypass DRM, authentication, CORS or provider restrictions. Run the offline controller tests with `node --test tests/js/hls_player_test.js` and verify actual playback on your browsers and Moodle App devices.

## Caption configuration

For Upload or External link activities, the Captions and transcript section accepts up to 20 tracks. Add a row, choose a local WebVTT file or an absolute HTTPS URL, enter a label and language tag (e.g. en or pt-BR), and choose subtitles or closed captions. Select at most one default track. Mark a row for deletion and save to remove it. Local files are limited to 5 MiB and 50,000 cues, stored in the activity File API, and included in backups without user data. External URLs are stored without server-side requests; avoid credentials and ensure the remote server permits browser access. Signed URLs may be included in course backups. Tracks are retained but inactive when switching to a different provider.

### Captions and transcript playback

Choose a configured track and enable its display. The transcript can be searched without sending searches to Moodle; results are rendered as text, in pages of 100. Timestamp buttons are available only when normal player seek controls are enabled. HLS must be confirmed VOD before static tracks and transcripts become available; embedded live captions remain under the existing HLS/native player. Manifest subtitles remain independent, and a full transcript of segmented HLS subtitles is not generated. The browser parses the selected WebVTT using TextTrack, including cue styling; only plain cue text is used in the transcript.

Local tracks are read with Moodle session access on the web and the authenticated Moodle App web service on mobile. External tracks are fetched by the viewer without credentials or referrer, must permit CORS and cannot redirect. A provider sees ordinary connection metadata (such as IP address); no searches or viewing history are sent with caption requests. External captions and mobile blob playback/download require compatible browser/WebView support and network access. Download, when enabled, is a text export rather than a copying restriction. The feature does not provide live attendance, automatic captions, external URL proxying, or a new progress mechanism.

## Jump to next marker

In Upload, External link, YouTube or Vimeo activities, add timestamped markers in the visual editor. Check Allow jump to next marker on the start of an optional interval. The destination is the next marker in time order, not a fixed skip duration. Existing `time | label | skip` settings remain compatible. The button requires navigation controls and a reachable destination; native media requires a seekable range, and HLS requires confirmed VOD. The Moodle App shares the controller for native media, YouTube and Vimeo. Live and unclassified HLS do not offer the jump. A skipped interval is not watched credit: a 100% completion requirement still requires viewing the skipped content. Grades and completion rules are unchanged. The existing Panda/Embed message adapters remain, without exposing new configuration for those providers. Actual provider playback, fullscreen and mobile WebView behaviour require device testing.

Offline marker controller tests: `node --test tests/js/marker_controller_test.js`.

### URL handling and provider trust

Player URLs are kept as URLs, not pre-escaped HTML. HTML attributes are escaped once by Moodle Mustache, while mobile service responses carry raw URLs. For third-party iframe markup, only the iframe source is extracted and HTML entities are decoded once. Do not paste HTML into URL fields; use an absolute HTTP(S) URL with reserved characters percent-encoded where required. Query order and percent-encoded signatures are not rewritten. The existing generic embed resume parameter is unchanged; whether signed embed endpoints accept that extra parameter depends on their signing policy. OTTFlix player and H5P markup remain trusted administrator-configured integrations, not sanitized arbitrary third-party HTML. Configure only trusted OTTFlix services and restrict integration settings to authorized administrators.
