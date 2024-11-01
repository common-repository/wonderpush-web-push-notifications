=== WonderPush - Web Push Notifications - WooCommerce Abandoned Cart - GDPR
Contributors: WonderPush
Donate link: https://www.wonderpush.com
Tags: push, notification, web, woocommerce, cart, AMP, android, GDPR, abandoned, reminder, basket
Requires at least: 5.0
Tested up to: 6.5.3
Stable tag: 1.11.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 5.3.3

Automatically send web push notifications to your subscribers desktop or mobile device every time you post. WooCommerce abandoned cart reminder. Full featured and GDPR compliant, starting €1 / month (+ €1 for every additional 1000 subscribers).

== Description ==

Web push notifications are the most effective way to retain your users and grow your WordPress site audience. [WonderPush](https://www.wonderpush.com) lets you send unlimited web push notifications.

Web push notifications are alert-style messages that can be sent to a user's desktop or mobile device even when the user is not on the website.

With [WonderPush](https://www.wonderpush.com), readers who subscribe to your web push notifications are alerted instantly each time you publish a post, even after they’ve left your website.

WonderPush provides ready-made user interfaces to let users subscribe, ranging from a browser prompt to a bell widget at the bottom of the page.

Installing WonderPush only takes a few minutes and requires absolutely no coding skills and is fully GDPR compliant.

WonderPush provides WooCommerce site owners with an “Abandoned cart reminder” to re-engage customers that left without buying the contents of their shopping cart by sending them a web push notification automatically.

With WonderPush, automating web push notifications based on user behavior is easy, just connect to your WonderPush dashboard and setup a campaign triggered when users meet certain segmentation criteria.

WonderPush lets AMP site owners add web push support to their AMP pages and have visitors subscribe to web push notifications from these pages.

WonderPush offers a 14-day free trial. After the trial period, you can send unlimited web push notifications for €1 per month plus €1 per 1000 subscribers. There are no limits to the number of web push notifications you can send. WonderPush supports rich formats, automation, real-time analytics, and a  powerful segmentation engine. All features are included without ever having to pay more.

WonderPush also lets you add [in-app messaging to your WordPress website](https://www.wonderpush.com/in-app-and-website-messaging/). Create and remotely trigger the display of banners, modals and alerts targeting users as they browse your site and use your app, letting you drive engagement and increase conversion.

Contact [contact@wonderpush.com](mailto:contact@wonderpush.com) if you have any questions.

= Company =
WonderPush is trusted by over 5,000 developers in Europe and Worldwide. WonderPush never shares your data with third parties. Your data is safe with us and we protect the privacy of your users as if they were ours. Data collected by our SDKs is fully documented and hosted in Europe. We provide ready-made tools to collect and manage user consent, and let users download or delete all their data.

= Features =
* **Supports Chrome** (Desktop & Android), **Microsoft Edge** (Desktop & Android), **Opera** (Desktop & Android) and **Firefox** (Desktop & Android) on both HTTP and HTTPS sites.

* **Automatic Notifications** - Send notifications to subscribers every time you publish a new post. Send them a notification if they haven’t visited for a few days.

* **Targeting Segments** - Send notifications to specific subscribers based on language, tags, or events .

* **WooCommerce support** - Abandoned cart: automatically send notifications to users that leave without buying with our easy to configure cart reminder.

* **Subscription prompt customization** - Choose when and how to ask your visitors to opt-in to browser notifications. Customize the prompt they first see.

* **Real Time Analytics** - See delivery metrics in real time, and watch them as they convert into visits.

* **Advanced dashboard** - Discover the power of push notification automation. Manage segments and campaigns. Invite staff to join your project.

* **AMP support** - Adds web push support to AMP sites. Let AMP visitors subscribe to web push notifications.

== Installation ==

1. Install WonderPush from the WordPress.org plugin directory or by uploading the WonderPush plugin folder to your wp-content/plugins directory.
2. Active the WonderPush plugin from your WordPress settings dashboard.
3. Follow the instructions on the Setup page.

= Subscription prompt =
Getting users to subscribe is an important aspect of setting up push notifications. WonderPush offers several out-of-the-box interfaces that you can fully customize directly from your dashboard.
* **Bell widget** that stays available at the bottom corner of the page,
* **Permission prompt** that appears under conditions determined by you,
* **HTML dialog** that can be customized and appears under conditions determined by you,
* **Cross-domain** popup window you can use to subscribe users from another domain or subdomain,
* **Switch** you can place anywhere on the page.

== Migrate from OneSignal plugin and others ==
If you need to be GDPR compliant and you already use a push notification plugin, migrating to WonderPush is easy: all you have to do is add the WonderPush plugin to your website and disable the other plugin.
Once you have the WonderPush plugin on your website, users who visit your site and have already granted push notification permission will automatically appear among your subscribers without having to subscribe again.

== Frequently Asked Questions ==

= Setting up push notifications =

[WordPress quickstart guide](https://docs.wonderpush.com/docs/web-push-notifications-wordpress)

== Screenshots ==

1. Example of a web push notification on an Android phone
2. The setup page of our WonderPush plugin for WordPress
3. Our online dashboard has detailed push analytics
4. The subscription bell on a WordPress example site
5. The subscription dialog on a WordPress example site
6. Out online dashboard provides advanced notification authoring tools

== Changelog ==
= 1.11.5 =
- Using WonderPush's curl client by default unless specified in settings
- Forcing ipv4 in WonderPush's curl client

= 1.11.4 =
- Add more logging when parsing JSON responses fails.

= 1.11.3 =
- Bugfix: fix segment list

= 1.11.2 =
- Bugfix: remove debug file published by mistake

= 1.11.1 =
- Bugfix

= 1.11.0 =
- Allow to use the user email as WonderPush user ID

= 1.10.3 =
- Fix bug where deselecting "Send push notification on post publish" did not persist after saving the post.

= 1.10.2 =
- Fix bug where selecting "Everybody" as audience did not select the right option after returning to the post editor
- Tested WordPress 6.4.2

= 1.10.1 =
- Old php compatibility

= 1.10.0 =
- Using full version of select2
- Fixing PHP warnings
- Adding extra checks before pushing notification on post publish

= 1.9.26 =
- Fix syntax error on PHP < 5.4

= 1.9.25 =
- Fix bug where default segment was not used

= 1.9.24 =
- Moved the WonderPush post settings in the main column of the editor
- Introduced multi-segments and multi-tags target audiences

= 1.9.23 =
- Removed PHP 8 warning
- Fix bug where target segments were not applied

= 1.9.22 =
- Removed PHP 8 warning

= 1.9.21 =
- Adding support for sending push automatically when publishing a post with a custom type

= 1.9.20 =
- Remove warning

= 1.9.19 =
- Tested with WordPress 6.0.1

= 1.9.18 =
- Allow notification delay up to 24h

= 1.9.17 =
- Bug fixes

= 1.9.16 =
- Introduce notification delay in the post editor
- Bug fixes

= 1.9.15 =
- WPRocket CDN support in push notification images

= 1.9.14 =
- Allowing up to 12 hours delay

= 1.9.13 =
- Bug fixes

= 1.9.12 =
- Bug fixes

= 1.9.11 =
- Tested with WordPress 6.0

= 1.9.10 =
- Giving precedence to the additional JSON options specified in the WordPress admin

= 1.9.9 =
- Supporting more segments in the segment selector.

= 1.9.8 =
- We are changing the way we include images in the notifications: you can now choose to use a medium or large image.

= 1.9.7 =
- WooCommerce: stripping HTML entities and newlines from product descriptions and names sent to WonderPush

= 1.9.6 =
- WooCommerce: stripping HTML from product descriptions and names sent to WonderPush

= 1.9.5 =
- WooCommerce: sending events to WonderPush when adding/removing from cart, purchasing and exiting so you can automate more re-engagement campaigns.

= 1.9.4 =
- Checked WordPress 5.9 compat

= 1.9.3 =
- Add option to introduce a delay for blog post notifications
- Using the default WordPress HTTP client

= 1.9.2 =
- Bug fixes

= 1.9.1 =
- Fix compatibility with PHP 7.4+

= 1.9.0 =
- Manage UTM parameters to automatically add to all your push notifications

= 1.8.0 =
- Allow selecting a segment from the WordPress editor, and a default segment from the WonderPush blogging settings

= 1.7.1 =
- Prevent direct access to php files

= 1.7.0 =
- Adding in-app messaging

= 1.6.6 =
- AMP: support for the standard and transitional modes of the official AMP plugin.

= 1.6.5 =
- WooCommerce: Send a WonderPush event when users place an order and visit the thank-you page.

= 1.6.4 =
- Improve PHP 7.4 support.

= 1.6.3 =
- Fix bug where Firefox users would stop seeing the plugin interface.

= 1.6.2 =
- Better compatibility with Microsoft Edge

= 1.6.1 =
- Better compatibility with Tera wallet

= 1.6.0 =
- Posts featured image is now sent as big picture to native iOS and Android apps.

= 1.5.4 =
- New "Advanced" tab allows you to add additional init options by providing a JSON payload.

= 1.5.3 =
- Allow debugging of network errors.

= 1.5.2 =
- More precise error display

= 1.5.1 =
- Added warning when cURL is missing
- Added standalone get_wonderpush_client() to allow other plugins to access WonderPush easily

= 1.5.0 =
- User interface redesign: we added a tabbed interface that makes it easier to find your favorite settings.
- Segmentation: WonderPush now automatically collects user information such as username, first and last names to help you target and personalize your messages
- WooCommerce: we've added order confirmation and shipping messages

= 1.4.3 =
- Tested with WordPress 5.3.2

= 1.4.2 =
- Add the ability to target users by ID when sending push notifications. This is active by default and the setting is entitled "Send user IDs of subscribers to WonderPush".

= 1.4.1 =
- Adds a support button that makes it easy to get in touch with us

= 1.4.0 =
- Added number of subscribers, push sent and clicked on the plugin page
- Added a Subscription Prompts section that sums up how you sign users up
- Showing AMP settings only to those who have AMP installed
- Added support and chat buttons so you can reach out to us easily

= 1.3.2 =
- Fix Google Search Console errors on AMP pages when subscription buttons are disabled.

= 1.3.1 =
- Fixing uncaught error on some WooCommerce installations

= 1.3.0 =
- Adding support for Google campaign parameters utm_source, utm_medium, etc.

= 1.2.2 =
- Using default sound for iOS notifications.

= 1.2.1 =
- Bug fixes

= 1.2.0 =
- You can now check the status of your 14-day free trial directly from the plugin.
- Added handy links to the WonderPush dashboard.
- Tested with WordPress 5.2.4

= 1.1.1 =
- Bug fixes.

= 1.1.0 =
- Adding AMP Web Push support. You can now subscribe AMP users to web push notifications!

= 1.0.8 =
- Better readme.txt description.

= 1.0.7 =
- Added WooCommerce cart reminder: automatically send push notifications to users that leave without buying.

= 1.0.6 =
- Added the ability to send push later

= 1.0.5 =
- Better compatibility with non-HTTPS sites.

= 1.0.4 =
- Better PHP 5.3 compatibility

= 1.0.3 =
- Better WordPress 5.0.x compatibility

= 1.0.2 =
- Better PHP 7.3 compatibility

= 1.0.1 =
- Added author URI

= 1.0.0 =
- Initial release of the plugin

