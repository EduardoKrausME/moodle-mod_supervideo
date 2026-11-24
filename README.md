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

These steps help eliminate outdated site plugin templates; they do not resolve every playback or authentication error. YouTube playback error 153 on iOS is tracked separately in [issue #92](https://github.com/EduardoKrausME/moodle-mod_supervideo/issues/92).
