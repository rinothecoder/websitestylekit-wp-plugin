=== Website Stylekit ===
Contributors: rinothecoder
Tags: elementor, design tokens, import, export, variables
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and export Elementor V4 and Bricks variables and global classes as portable CSS.

== Description ==

Website Stylekit imports CSS design tokens and classes from websitestylekit.com into Elementor V4 or Bricks.

**Import**
* Import color, font family, and size variables from websitestylekit.com CSS
* Choose Elementor V4 or Bricks as the import target when available
* Import Elementor global classes from CSS framework exports
* Automatically detects supported CSS custom properties
* Preview changes before applying them
* Shows import summaries and warnings
* Two import modes: smart merge or replace existing imported style types

**Export**
* Export colors, sizes, fonts, and Elementor global classes as a CSS framework, CSS variables, clean JSON, or raw Elementor format
* Filter by variable type

**Backups**
* Automatically creates a backup before every import
* Create manual backups, upload backup files, restore, delete, and download CSS or backup files

**Utilities**
* Reorder variables — groups by type and assigns clean sequential order numbers

== Installation ==

1. Download the zip file
2. In WordPress, go to Plugins → Add New → Upload Plugin
3. Upload the zip and activate
4. Find Website Stylekit in the left menu

== Changelog ==

= 2.6 =
* Improved handling of Elementor default styles in backups and restores.

= 2.5 =
* Added Bricks global variable and class import with matching-name updates and automatic backups.

= 2.0 =
* Added CSS Framework export with variables and Elementor global classes.
* Added CSS Variables export for variables only.
* Added import preview before applying changes.
* Added font family variable import.
* Added Elementor global class import from CSS framework exports.
* Added clearer import summaries and warnings.
* Simplified import modes to merge or replace.
* Added automatic import backups, manual backups, backup upload, readable CSS previews, restore, CSS download, backup download, and delete actions.

= 1.0 =
* Initial release

== Upgrade Notice ==

= 2.6 =
Adds Bricks support and improves Elementor default style backup coverage.

= 2.0 =
Adds the CSS-first framework workflow with font family import, class export/import, import previews, summaries, warnings, and working backups.

= 1.0 =
First release.
