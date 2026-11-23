# Changelog

This changelog records verified changes between consecutive published GitHub release tags. Earlier releases remain available in the GitHub release history.

## 3.4.0 - 2026-11-21

- Added an orphaned viewing-record detector and a CLI utility that reports invalid Super Video viewing records without deleting them by default, with an explicit batched deletion option.
- Added course reset integration for deleting Super Video viewing history and updated activity deletion to resolve its course module within the correct course.
- Corrected privacy-provider deletion of viewing records by course module ID, including when the original course module no longer exists.
- Added lifecycle regression tests and a generator for viewing records, a security reporting policy, and release workflow concurrency configuration.

**Comparison:** [3.3.10...3.4.0](https://github.com/EduardoKrausME/moodle-mod_supervideo/compare/3.3.10...3.4.0)
