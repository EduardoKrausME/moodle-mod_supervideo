<?php
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * CLI utility to audit or delete orphaned Super Video viewing records.
 *
 * @package   mod_supervideo
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_supervideo\local\orphaned_views;

$help = <<<HELP
Find orphaned Super Video viewing records.

By default the command only counts orphaned records. No data is changed.

Options:
  -h, --help             Show this help.
  --delete               Delete orphaned records (irreversible).
  --batch-size=N         Records per deletion batch (1-5000, default 500).
  --limit=N              Maximum records to delete (0 means unlimited).

Examples:
  php mod/supervideo/cli/cleanup_orphan_views.php
  php mod/supervideo/cli/cleanup_orphan_views.php --delete --limit=1000
  php mod/supervideo/cli/cleanup_orphan_views.php --delete

HELP;

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'delete' => false,
    'batch-size' => 500,
    'limit' => 0,
], [
    'h' => 'help',
]);

if ($options['help']) {
    echo $help;
    exit(0);
}

if ($unrecognized) {
    cli_error('Unrecognized options: ' . implode(', ', $unrecognized) . "\n" . $help);
}

if (!ctype_digit((string)$options['batch-size']) ||
        (int)$options['batch-size'] < 1 || (int)$options['batch-size'] > 5000) {
    cli_error('--batch-size must be an integer between 1 and 5000.');
}
if (!ctype_digit((string)$options['limit'])) {
    cli_error('--limit must be a non-negative integer.');
}
$batchsize = (int)$options['batch-size'];
$limit = (int)$options['limit'];

$total = orphaned_views::count();
mtrace("Orphaned Super Video viewing records: {$total}");

if (!$options['delete']) {
    mtrace('Dry run: no data was deleted. Use --delete to remove orphaned records.');
    exit(0);
}

$removed = 0;
$lastid = 0;

while ($limit === 0 || $removed < $limit) {
    $size = $limit === 0 ? $batchsize : min($batchsize, $limit - $removed);
    $ids = orphaned_views::get_batch($lastid, $size);
    if (!$ids) {
        break;
    }

    $lastid = end($ids);
    $DB->delete_records_list('supervideo_view', 'id', $ids);
    $removed += count($ids);
    mtrace("Deleted {$removed} orphaned records...");
}

mtrace("Finished. Deleted {$removed} records. Remaining orphaned records: " . orphaned_views::count());
