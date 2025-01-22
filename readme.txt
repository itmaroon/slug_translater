=== SLUG TRANSLATER ===
Contributors: itmaroon
Tags: slug, translate, custom,permalink,post,category,tags,japanese, english
Requires at least: 6.3
Tested up to: 6.7.1
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 8.2.10

Translate the slug generated in Japanese into English and replace it with an appropriate format.

== Description ==

* At the moment, it has a function to translate Japanese into English and replace the sanitized one.
* The default setting is to replace it when the post is saved.
* If you want to replace saved posts, you can set it on the setting screen.
* Categories, terms, and tags can also be replaced.

* English translation function
 * This plugin uses the API of "Minna no Jido Honyaku".
 * Please register as a user of [Minna no Jido Honyaku](https://mt-auto-minhon-mlt.ucri.jgn-x.jp/) in advance and obtain the authentication information.
 * You can register the authentication information from the setting screen.
 * This plugin uses the API of "Google Cloud Translation API".
 * Get the project ID and API key obtained by creating a project and enabling the Cloud Translation API from the [Google Cloud Platform dashboard](https://console.cloud.google.com/home/dashboard).
 * You can register the authentication information from the setting screen.

== Related Links ==

* [Github](https://github.com/itmaroon/slug_translater)

== Installation ==

1. From the WP admin panel, click “Plugins” -> “Add new”.
2. In the browser input box, type “SLUG TRANSLATER”.
3. Select the “SLUG TRANSLATER” plugin and click “Install”.
4. Activate the plugin.

OR…

1. Download the plugin from this page.
2. Save the .zip file to a location on your computer.
3. Open the WP admin panel, and click “Plugins” -> “Add new”.
4. Click “upload”.. then browse to the .zip file downloaded from this page.
5. Click “Install”.. and then “Activate plugin”.


== Frequently asked questions ==

= What do I need to use this plugin? =

Must be in accordance with [Minna no Jidou Honyaku Terms of Use] (https://mt-auto-minhon-mlt.ucri.jgn-x.jp/content/policy/).
Must be in accordance with [Google Cloud Platform Terms of Service] (https://cloud.google.com/terms).

== Screenshots ==



== Changelog ==

= 1.2.2 =
- Fixed a bug where the translation start flag would unintentionally become false when posting a new post.

= 1.2.1 =
- Session management has been discontinued using $_SESSION and switched to management using user metadata.
- The condition for translating the slug when posting a new post was changed from when the post status changed from new to auto-draft to when it changed from new to publish.

= 1.2.0 =
Made it multilingual. Currently, English and Japanese can be displayed on the management screen.

= 1.1.4 =
Deleted the options recorded in the database when uninstalling the plugin.
Fixed a bug that an error was not displayed properly when an error was detected in the authentication check on the management screen of Google Cloud Translation API.

= 1.1.3 =
Added a function to select Google Cloud Translation API for translation API
Eliminated error caused by calling before session variable is initialized.

= 1.0.2 =
Changed when to translate and replace permalinks from first publish to first save

= 1.0.0 =
First public release

== Upgrade notice ==



== Arbitrary section 1 ==