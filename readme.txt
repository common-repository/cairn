=== Cairn ===
Contributors: braydonf
Tags: art, fine art, bitcoin, electrum, usps, gpg, pgp, privacy, cart, checkout, commerce, ecommerce, sales, sell, shipping, shop, shopping, store, creative commons, license, templates, gallery, javascript, exhibition
Requires at least: 3.8
Tested up to: 3.9
Stable tag: trunk

== Description ==

Sell fine art online. Cairn is built to be fast and secure to display artwork full screen and across a wide range of display sizes.

Cairn is fast because template rendering is handled on the client side. Cairn replaces the theme and permalink features of WordPress by hooking into the rewrite rules with url rules defined in static/urls.json. Templates are written using EJS Templating and are rendered on the client side. There is a jQuery plugin written that uses the same rules to speed up user interactions, and with enough data able to render URLS without a request to the remote server.

Cairn is secure because personally identifiable information is stored in the database encrypted with a GPG public key, and private keys stored optimistically offline. This information should be submitted over an TLS connection (HTTPS), and is compulsory when using this plugin. Additionally security features include payments being able to be handled using cryptocurrency Bitcoin, and thus no potentially personally identifiable information is nessasary to be shared with a third party and can be directly peer-to-peer.

Features

* Accept bitcoin and credit card payments
* Personal privacy and security
* Dynamic prices and currency preference 
* Designed for mobile
* URL navigation is client side and lightweight

Prerequisites:

* A computer running GNU/Linux, Apache, MySQL to do development and testing.
* The use of GPG and have a public/private for email
* Expert knowledge of JavaScript, jQuery and AJAX
* General knowledge of EJS JavaScript templating
* Experience with the Electrum Bitcoin Wallet and general concepts of peer-to-peer payments.
* Understand priciples of asymetric cryptography and public/private keys
* Intermediate knowledge of PHP 
* USPS Web Tools API account for shipping calculations
* A Stripe account to process credit cards
* General knowledge of RSS/Atom feeds
* Apache .htaccess and general priciples of rewriting URLs
* Experience with configuring cron schedules
* Knowledge of PHP APC for caching is a plus

== Installation ==

URI are defined in /static/urls.json which is parsed and used within WordPress and jQuery Cairn

It is recommended to disable the default cron in the wp-config.php

define(‘DISABLE_WP_CRON’,true);

And then enable the cron to be scheduled externally so that tasks do not 
interfere with page loading:

php5 -q /<website path>/wp-cron.php

Rewrite rules needed to be added for images to be scaled dynamically:

RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_URI} \.(gif|png|jpg|jpeg)
RewriteCond %{QUERY_STRING} (w|h)=(.*)$
RewriteRule (.*) <path_to_website>/wp-content/plugins/cairn/includes/timthumb/timthumb.php?src=$1&%1=%2&%3=%4 [L]

It is also recommended to use object caching such as APC.

To generate documentation:

JavaScript Documented with http://usejsdoc.org/
jsdoc static/callbacks.js static/jquery.cairn.js static/readme.md -d docs/js/

PHP Documented with http://phpdoc.org/
phpdoc -d . -t ./docs/php/

Full Documentation is available at:
http://bitgress.com/cairn/

To clone a git repository:
git clone https://git.gitorious.org/cairn/cairn.git
