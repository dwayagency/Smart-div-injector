=== Smart Div Injector ===
Contributors: dwaysrl
Tags: code injection, html injection, javascript, css, content injection
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 2.5.2
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Insert custom HTML, CSS, or JavaScript code into specific div elements based on content type. Multiple rules, variants, quick edit, search, filters.

== Description ==

Smart Div Injector is a powerful WordPress plugin that allows you to create **unlimited injection rules** to insert custom HTML, CSS, or JavaScript code into specific div elements on your website based on content type (posts, pages, categories).

Perfect for:

* Adding custom content to specific posts or categories
* Injecting mid-article ads after specific paragraphs
* Inserting CTAs before or after images
* Injecting tracking scripts on targeted pages
* Displaying special banners or notices conditionally
* Inserting custom widgets or components dynamically
* A/B testing different content variations
* Creating multiple injection rules for different scenarios
* Newsletter signup boxes in strategic positions

== Features ==

* **Unlimited Rules** - Create as many injection rules as you need
* **Multiple Code Variants** - Create different versions of code for each rule and switch between them
* **Quick Edit** - Modify rules instantly from the list without opening the full editor
* **Flexible Targeting** - Target the entire website, all posts, category archives, posts by category, or specific pages
* **Site-Wide Mode** - Apply rules globally across the entire website (perfect for cookie banners, global announcements)
* **Device Targeting** - Choose desktop-only, mobile-only, or both devices for each rule
* **Content Alignment** - Position content with float left, float right, or centered alignment
* **Advanced Search & Filters** - Find rules quickly with search and multiple filters
* **Smart Pagination** - Navigate through rules with customizable items per page (10, 20, 50, 100)
* **Smart Injection Positions** - Standard positions (append, prepend, before, after, replace) + Article-specific positions
* **Article-Specific Positions** - Before/after content, paragraphs, images - no CSS selector needed!
* **Paragraph Targeting** - Insert before or after any specific paragraph number
* **Image Targeting** - Insert before or after the first image in posts
* **CSS Selector Support** - Use any valid CSS selector for standard positions
* **Script Activation** - Automatically activates injected scripts
* **Rule Management** - Add, edit, duplicate, and delete rules with ease
* **Enable/Disable Rules** - Activate or deactivate rules without deleting them
* **Modern UI/UX** - Beautiful and intuitive admin interface with custom styling
* **Responsive Design** - Fully responsive admin panel that works on all devices
* **Security First** - Respects WordPress capabilities for unfiltered HTML
* **Developer Friendly** - Includes filters for customization
* **Memory Optimized** - Handles sites with thousands of posts efficiently
* **Multisite Ready** - Full support for WordPress Multisite with Network Admin panel

== Installation ==

1. Upload the `smart-div-injector` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Smart Div Injector' in your WordPress admin menu to configure rules

== Frequently Asked Questions ==

= How do I create a rule? =

Navigate to Smart Div Injector in your WordPress admin menu and click "Add New Rule". Configure the targeting options, CSS selector, position, and your HTML/CSS/JavaScript code.

= Can I create multiple rules? =

Yes! You can create unlimited rules. Each rule can target different content types and positions.

= What is the Site-Wide mode? =

Site-Wide mode (introduced in v2.5.0) allows you to apply a rule to ALL pages across your entire website. Perfect for cookie consent banners, global announcements, tracking scripts, or any content that should appear everywhere.

= What are Code Variants? =

Code Variants allow you to create multiple versions of code within a single rule. You can switch between them without editing. Perfect for A/B testing, seasonal content, or having backup versions ready.

= Can I use this for ads? =

Yes! You can inject ad code in specific positions like after paragraphs, before/after images, or in custom div elements.

= Does it work with JavaScript? =

Yes! The plugin properly handles JavaScript code, including external scripts. Scripts are automatically activated when injected.

= Is it compatible with page builders? =

Yes! As long as your page builder outputs standard HTML with CSS selectors or WordPress content filters, the plugin will work.

= Does it work on mobile devices? =

Yes! You can target desktop-only, mobile-only, or both devices for each rule.

= Can I use it on WordPress Multisite? =

Yes! The plugin is fully compatible with WordPress Multisite and includes a Network Admin panel.

== Screenshots ==

1. Rules list with search, filters, and pagination
2. Add/Edit rule form with all options
3. Code Variants management interface
4. Quick Edit inline form
5. Site-Wide targeting option

== Changelog ==

= 2.5.2 =
* Security: Fixed all WordPress Coding Standards violations
* Security: Added wp_unslash() to all $_POST and $_GET variables
* Security: Added proper escaping to all output variables
* Security: Replaced wp_redirect() with wp_safe_redirect() for all redirects
* Fix: Removed .DS_Store hidden files
* Fix: Renamed hook 'sdi_injection_payload' to 'smart_div_injector_injection_payload' for proper naming convention
* Enhancement: Added phpcs:ignore comments for intentional exceptions (paginate_links, debug comments)
* Code Quality: All Plugin Check errors and warnings resolved
* Code Quality: Full WordPress.org coding standards compliance

= 2.5.1 =
* Fix: Removed heredoc/nowdoc syntax for WordPress.org compliance
* Fix: Added Stable tag field to readme
* Fix: Updated Tested up to WordPress 6.9
* Compatibility: Plugin now passes WordPress.org automated checks
* Note: No functional changes - internal code formatting only

= 2.5.0 =
* New: Site-Wide targeting option - apply rules to entire website
* New: Perfect for cookie banners, global announcements, tracking scripts
* Enhancement: Added to content type dropdown with 🌐 icon
* Enhancement: Integrated into search/filter system
* Enhancement: Clear visual distinction in rules table

= 2.4.0 =
* Fix: Critical bug - removed obsolete code check blocking all variant rules
* Fix: JavaScript nodeType check before accessing tagName
* Fix: UTF-8 encoding with try-catch and fallback for special characters
* Fix: Network Admin method call to non-existent function
* Enhancement: Comprehensive variant index validation
* Enhancement: Robust quick_edit validation for edge cases
* Security: Added isset() checks on all array accesses
* Security: Defensive programming throughout variant system
* Security: Try-catch blocks on critical operations

= 2.3.3 =
* Fix: Removed obsolete $rule['code'] validation that broke variant system

= 2.3.2 =
* Enhancement: Visual warning indicators for empty active variants
* Enhancement: Debug comments in footer when WP_DEBUG is active
* Enhancement: Orange border highlight for empty active variant textareas

= 2.3.1 =
* Fix: JavaScript scope isolation with IIFE wrappers
* Fix: Prevented duplicate event listener registration
* Fix: Adjusted setTimeout timings for UI interactions

= 2.3.0 =
* New: Quick Edit functionality for inline rule modifications
* Enhancement: Edit name, status, device, alignment, active variant inline
* UI: Quick edit form with smooth animations and keyboard support (ESC to close)

= 2.2.0 =
* New: Content Alignment feature (float left, float right, centered)
* Enhancement: Wraps injected code with alignment styles
* UI: Alignment dropdown in rule form and quick edit

= 2.1.0 =
* New: Advanced search functionality by rule name
* New: Multiple filters (status, content type, device)
* New: Smart pagination with customizable items per page
* UI: Modern filters interface with responsive design
* Enhancement: Results counter and empty state messages

= 2.0.0 =
* New: Multiple Code Variants per rule
* New: A/B testing support with variant switching
* New: Variant management UI with add/delete/activate
* Enhancement: Backward compatibility with old single-code rules
* UI: Variant badges in rules list showing active variant

= 1.0.0 =
* Initial release
* Multiple injection rules support
* Flexible targeting options
* Device targeting
* Article-specific positions

== Upgrade Notice ==

= 2.5.1 =
WordPress.org compatibility update. No functional changes. Safe to upgrade.

= 2.5.0 =
Major new feature: Site-Wide targeting for global banners and announcements. Fully backward compatible.

= 2.4.0 =
Critical bug fixes for variant system. Upgrade immediately if using code variants.

= 2.0.0 =
Major update with Code Variants feature. Fully backward compatible with existing rules.
