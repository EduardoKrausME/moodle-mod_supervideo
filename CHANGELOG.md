# Changelog

This changelog records verified changes between consecutive published GitHub release tags. Earlier releases remain available in the GitHub release history.

## Unreleased (3.4.1)

- Declared personal data sent to the configured OttFlix service during standard video playback in the Moodle Privacy API, and clarified the administrator-facing privacy notice and English, Portuguese and Spanish translations (#103).
- Added regression tests for the local viewing metadata and the external OttFlix metadata without changing playback or the existing data transfer.

- Fixed a Mustache comment that leaked closing braces into the Moodle App's Angular template and prevented activity rendering (#107).
- Made native video and audio players responsive in the Moodle App (#92), added regression tests for the rendered mobile template, and documented mobile cache troubleshooting.

- Added translated YouTube mobile playback errors, a retry action and a native Moodle App browser fallback to the same activity for error 153 and recoverable player failures. The fallback does not grant viewing credit or change completion rules (#92).
- Added regression coverage for the fallback and YouTube player lifecycle, including stale callbacks, load failures and observed progress.

- Added regression coverage for creating and editing Vimeo activities without a local upload, conditional file validation, and supported Vimeo URL formats (#49). Removed an obsolete filemanager comment without changing the form behaviour or upload limits.

- Stabilised HLS playback on web and mobile with isolated player instances, native playback detection, bounded recovery, embedded caption tracks and adaptive quality (#53). Only confirmed on-demand playlists with finite duration report percentage progress; live and unclassified streams do not.

- Added activity-owned WebVTT caption configuration, local draft-file validation, HTTPS URL tracks, transcript settings, and mapped backup/restore support (#53).

- Integrated configured WebVTT tracks and searchable, synchronized text transcripts in HTML5/HLS Web and Moodle App players. Added authenticated local-track reads, conditional export, seek guards and external caption privacy disclosure (#53).

## 3.4.0 - 2026-11-21

- Added an orphaned viewing-record detector and a CLI utility that reports invalid Super Video viewing records without deleting them by default, with an explicit batched deletion option.
- Added course reset integration for deleting Super Video viewing history and updated activity deletion to resolve its course module within the correct course.
- Corrected privacy-provider deletion of viewing records by course module ID, including when the original course module no longer exists.
- Added lifecycle regression tests and a generator for viewing records, a security reporting policy, and release workflow concurrency configuration.

**Comparison:** [3.3.10...3.4.0](https://github.com/EduardoKrausME/moodle-mod_supervideo/compare/3.3.10...3.4.0)
