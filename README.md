# Smart Div Injector

**Version:** 2.0.0  
**Author:** DWAY SRL  
**Author URI:** https://dway.agency  
**License:** GPL-2.0+  
**Requires at least:** WordPress 5.0  
**Requires PHP:** 7.2  
**Tested up to:** WordPress 6.4  
**Network:** Compatible with WordPress Multisite  

## Description

Smart Div Injector is a powerful WordPress plugin that allows you to create **unlimited injection rules** to insert custom HTML, CSS, or JavaScript code into specific div elements on your website based on content type (posts, pages, categories).

Perfect for:
- Adding custom content to specific posts or categories
- Injecting tracking scripts on targeted pages
- Displaying special banners or notices conditionally
- Inserting custom widgets or components dynamically
- A/B testing different content variations
- Creating multiple injection rules for different scenarios

## Features

✅ **Unlimited Rules** - Create as many injection rules as you need  
✅ **Flexible Targeting** - Target all posts, category archives, posts by category, or specific pages  
✅ **Multiple Injection Positions** - Append, prepend, before, after, or replace content  
✅ **CSS Selector Support** - Use any valid CSS selector to target elements  
✅ **Script Activation** - Automatically activates injected scripts  
✅ **Rule Management** - Add, edit, duplicate, and delete rules with ease  
✅ **Enable/Disable Rules** - Activate or deactivate rules without deleting them  
✅ **Modern UI/UX** - Beautiful and intuitive admin interface with custom styling  
✅ **Responsive Design** - Fully responsive admin panel that works on all devices  
✅ **Security First** - Respects WordPress capabilities for unfiltered HTML  
✅ **Developer Friendly** - Includes filters for customization  
✅ **User-Friendly Interface** - Clear admin panel with validation warnings  
✅ **Memory Optimized** - Handles sites with thousands of posts efficiently  
✅ **Multisite Ready** - Full support for WordPress Multisite with Network Admin panel  

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `smart-div-injector` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to `Settings > Smart Div Injector` to configure

### Via WordPress Admin

1. Go to `Plugins > Add New`
2. Search for "Smart Div Injector"
3. Click "Install Now" and then "Activate"
4. Go to `Settings > Smart Div Injector` to configure

## Configuration

Navigate to **Smart Div Injector** in your WordPress admin menu (main sidebar).

### How It Works

The plugin uses a **rules-based system**. You can create multiple injection rules, each with its own configuration:

1. **Create a Rule** - Click "Add New Rule" button
2. **Configure the Rule** - Set up targeting, selector, and code
3. **Save the Rule** - Click "Save Rule"
4. **Manage Rules** - View all rules in the main list, edit, duplicate, or delete as needed

### Rule Settings

#### 1. Rule Name
Give your rule a descriptive name (e.g., "Banner on News Articles", "Tracking on Homepage")

#### 2. Active Status
Enable or disable the rule without deleting it. Inactive rules won't run on the frontend.

#### 3. Content Type
Choose where to apply the injection:
- **All Posts** (Tutti gli articoli) - Inject on all single post pages
- **Category Archive Page** (Pagina archivio categoria) - Inject on the category archive page (e.g., example.com/category/news/)
- **Posts by Category** (Articoli di una categoria) - Inject only on single posts that belong to a specific category
- **Specific Page** (Pagina specifica) - Inject only on a selected page

#### 4. Category (when applicable)
If you selected "Category Archive Page" or "Posts by Category", choose which category to target.

#### 5. Page (when applicable)
If you selected "Specific Page", choose which page to target from the dropdown, or enter the Page ID manually.

**For sites with many pages:** The dropdown shows up to 500 pages. If your page isn't listed, use the manual ID input field.

#### 6. CSS Selector
Enter a valid CSS selector for the target element where the code will be injected.

**Examples:**
- `#main-content` - Target element with ID "main-content"
- `.post-content` - Target elements with class "post-content"
- `article > .entry-content` - Target with complex selectors
- `main .wrap` - Target descendant elements

#### 7. Injection Position
Choose where to inject your code relative to the target element:
- **Append** - Insert at the end inside the element
- **Prepend** - Insert at the beginning inside the element
- **Before** - Insert before the element
- **After** - Insert after the element
- **Replace** - Replace the entire content of the element

#### 8. Code to Inject
Enter the HTML/CSS/JavaScript code you want to inject. This field accepts any valid HTML markup.

**Security Note:** Only users with `unfiltered_html` capability (typically administrators) can save scripts without sanitization.

## Usage Examples

### Example 1: Add a Banner to All Posts

**Configuration:**
- Rule Name: `Banner on All Articles`
- Active: ✓
- Content Type: `All Posts`
- CSS Selector: `.entry-content`
- Position: `Prepend`
- Code:
```html
<div class="custom-banner" style="background: #f0f0f0; padding: 20px; margin-bottom: 20px;">
    <h3>Special Announcement!</h3>
    <p>This is a custom message for all articles.</p>
</div>
```

### Example 2: Add Banner on Category Archive Page

**Configuration:**
- Rule Name: `News Category Banner`
- Active: ✓
- Content Type: `Category Archive Page`
- Category: `News`
- CSS Selector: `.category-description`
- Position: `After`
- Code:
```html
<div class="category-banner">
    <h2>Latest News</h2>
    <p>Stay updated with our latest news articles!</p>
</div>
```

### Example 3: Add Tracking to Posts in a Category

**Configuration:**
- Rule Name: `Tracking for News Posts`
- Active: ✓
- Content Type: `Posts by Category`
- Category: `News`
- CSS Selector: `body`
- Position: `Append`
- Code:
```html
<script>
    console.log('Tracking code for News category posts');
    // Your tracking code here
</script>
```

### Example 4: Insert Custom Widget on Specific Page

**Configuration:**
- Rule Name: `Homepage Widget`
- Active: ✓
- Content Type: `Specific Page`
- Page: `Home` (ID: 2)
- CSS Selector: `.main-content`
- Position: `After`
- Code:
```html
<div class="featured-widget">
    <h4>Featured Content</h4>
    <!-- Your custom widget HTML -->
</div>
```

### Example 5: Add Custom Styling to Category Posts

**Configuration:**
- Rule Name: `Special Category Styling`
- Active: ✓
- Content Type: `Posts by Category`
- Category: `Featured`
- CSS Selector: `head`
- Position: `Append`
- Code:
```html
<style>
    .featured-post {
        background: linear-gradient(to right, #ff6b6b, #feca57);
        padding: 20px;
    }
</style>
```

## Developer Hooks

### Filter: `sdi_injection_payload`

Modify the injection payload before it's injected into the page.

**Parameters:**
- `$payload` (array) - Array containing `selector`, `position`, and `code`
- `$rule` (array) - The complete rule data
- `$rule_id` (string) - The unique ID of the rule

**Example:**
```php
add_filter( 'sdi_injection_payload', function( $payload, $rule, $rule_id ) {
    // Modify the code before injection (e.g., add dynamic content)
    $payload['code'] = str_replace( '{{site_name}}', get_bloginfo( 'name' ), $payload['code'] );
    $payload['code'] = str_replace( '{{rule_name}}', $rule['name'], $payload['code'] );
    
    return $payload;
}, 10, 3 );
```

**Advanced Example - Conditional Modification:**
```php
add_filter( 'sdi_injection_payload', function( $payload, $rule, $rule_id ) {
    // Only modify specific rules
    if ( $rule['name'] === 'Dynamic Banner' ) {
        // Add current user info
        $current_user = wp_get_current_user();
        if ( $current_user->ID ) {
            $payload['code'] = str_replace( 
                '{{username}}', 
                $current_user->display_name, 
                $payload['code'] 
            );
        }
    }
    
    return $payload;
}, 10, 3 );
```

## How It Works

1. **Rules Loading** - The plugin loads all active rules from the database
2. **Condition Check** - For each rule, the plugin checks if the current page matches the rule's conditions
3. **Payload Collection** - All matching rules are collected into an array
4. **Script Registration** - If there are matching rules, the plugin registers an inline script with all payloads
5. **DOM Ready** - The script waits for the DOM to be fully loaded
6. **Sequential Processing** - Each rule is processed in order:
   - The target element is selected using the CSS selector
   - The code is injected in the specified position
   - Any `<script>` tags are automatically activated
7. **Error Handling** - Each rule is executed independently; if one fails, others continue

## Validation and Warnings

The plugin includes validation at both form level and runtime level:

### Form Validation
When creating or editing a rule, the following fields are required:
- **Rule Name** - Must be provided
- **CSS Selector** - Must be provided
- **Code to Inject** - Must be provided

### Conditional Validation
Based on the Content Type selected:
- **Category Archive Page** - Requires a category to be selected
- **Posts by Category** - Requires a category to be selected
- **Specific Page** - Requires a page to be selected
- **All Posts** - No additional fields required

### Runtime Validation
On the frontend, rules are skipped if:
- The rule is marked as inactive
- Required fields (selector, code) are empty
- The page conditions don't match the rule's targeting

## Security Considerations

- Only users with `manage_options` capability can access the settings
- Only users with `unfiltered_html` capability can save unfiltered scripts
- All inputs are properly sanitized based on user capabilities
- CSS selectors are sanitized to prevent XSS attacks
- The plugin respects WordPress security best practices

## Browser Compatibility

The injected JavaScript uses modern web standards but is compatible with:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- All browsers supporting ES5+

## Multisite Support

This plugin is **fully compatible with WordPress Multisite** installations.

### How It Works in Multisite

- **Site-Specific Settings**: Each site in your network has its own independent configuration
- **Separate Database Tables**: Settings are stored in each site's options table
- **Network Admin Panel**: Super admins can view all sites and their configuration status
- **Flexible Activation**: Can be activated network-wide or per-site

### Network Admin Features

When activated on a multisite network, the plugin adds a **Network Admin** page accessible only to super administrators.

**Features of the Network Admin page:**
- View all sites in the network at a glance
- See which sites have the plugin configured
- Quick links to access each site's settings
- Visual indicators showing configuration status

### Configuration in Multisite

1. **Network Activation** (Optional):
   - Go to `Network Admin > Plugins`
   - Network activate the plugin to make it available to all sites

2. **Per-Site Configuration**:
   - Switch to the specific site you want to configure
   - Go to `Smart Div Injector` in the site's admin menu
   - Configure settings for that specific site

3. **Network Admin Overview**:
   - As a super admin, access `Network Admin > Smart Div Injector`
   - View the status of all sites
   - Click "Vai alle impostazioni" (Go to settings) to configure individual sites

### Multisite Use Cases

**Example 1: Different Injection Per Site**
- Site 1 (English): Inject tracking code for English users
- Site 2 (Spanish): Inject localized content for Spanish users
- Site 3 (French): Inject different banner for French market

**Example 2: Conditional Activation**
- Main site: No injection
- Blog subsites: Add custom widgets
- Store subsites: Add product recommendations

**Example 3: Network-Wide Campaign**
- Configure the same banner across all sites
- Each site maintains its own configuration
- Easy to update individually or in bulk

### Technical Details for Multisite

- Uses `switch_to_blog()` and `restore_current_blog()` for safe site switching
- Respects site-specific capabilities (`manage_options` per site)
- Network admins have `manage_network_options` capability
- No conflicts between sites
- Database queries are optimized for network operations

### Multisite Best Practices

1. **Test on One Site First**: Before rolling out to all sites, test on a single site
2. **Document Your Settings**: Keep track of what each site has configured
3. **Use Network Admin**: Regularly check the Network Admin page for overview
4. **Consistent Selectors**: If using across multiple sites, ensure CSS selectors exist on all sites
5. **Staging Environment**: Test multisite configurations in staging before production

## Troubleshooting

### Code is not being injected

**Check the following:**
1. Is the plugin activated?
2. Are all required settings filled in?
3. Are you viewing the correct post/page that matches your conditions?
4. Is the CSS selector correct? Open browser DevTools and try `document.querySelector('your-selector')`
5. Check browser console for any JavaScript errors

### Scripts are not executing

**Possible solutions:**
1. Make sure you have `unfiltered_html` capability (admin role)
2. Check that your script syntax is correct
3. Look for JavaScript errors in the browser console
4. Ensure there are no conflicts with other plugins

### Selector not found

**Tips:**
1. Inspect the page with DevTools to verify the element exists
2. Make sure the selector is valid CSS
3. Check if the element is dynamically loaded after page load
4. Try a more specific or less specific selector

### Multisite Issues

**Plugin not appearing in Network Admin:**
1. Make sure you're logged in as a super administrator
2. Verify the plugin is network activated or activated on at least one site
3. Clear browser cache and reload

**Configuration not saving on specific site:**
1. Switch to that specific site's admin panel
2. Ensure you have `manage_options` capability on that site
3. Check if the site's database is accessible

**Different behavior across sites:**
- This is expected! Each site has independent configuration
- Verify CSS selectors exist on all sites where you want injection
- Check that themes are compatible across sites

## Performance

- **Minimal footprint** - Only loads on pages that match your conditions
- **Efficient script injection** - Uses WordPress's built-in script system
- **DOM-ready detection** - Only executes when the page is fully loaded
- **No external dependencies** - Pure vanilla JavaScript

## Changelog

### 2.0.0
- **🎉 Major Update: Multiple Rules System**
- Create unlimited injection rules instead of single configuration
- Add, edit, duplicate, and delete rules individually
- Enable/disable rules without deleting them
- New rule management interface with list view
- Each rule has a name for easy identification
- **Four targeting options:**
  - All posts (all single post pages)
  - Category Archive Page (category archive pages like /category/news/)
  - Posts by Category (single posts in a specific category)
  - Specific Page (individual pages)
- **Modern UI/UX:**
  - Custom CSS styling with beautiful color palette
  - Responsive design for mobile, tablet, and desktop
  - Status badges with visual indicators
  - Improved button styles with hover effects
  - Toggle switch for enable/disable rules
  - Empty state design for better onboarding
  - Better spacing and typography
- Memory optimization: Limits queries for sites with thousands of posts
- Manual ID input for posts/pages not in dropdown
- Added Author URI: https://dway.agency
- Complete UI/UX redesign for better usability
- Updated filter hook parameters to include rule data and rule ID
- Sequential rule processing with independent error handling

### 1.1.1
- Fixed memory exhaustion on sites with many posts
- Limited post/page queries to 500 items max
- Added manual ID input field as alternative to dropdown
- Memory-efficient queries using 'fields' => 'ids'
- Warning messages when limits are reached

### 1.1.0
- Redesigned content type selection
- Separate fields for posts, pages, and categories
- Dropdown selection for posts and pages
- Dynamic field visibility based on selection
- Removed complex post_id handling

### 1.0.2
- **WordPress Multisite Support**: Full compatibility with WordPress Multisite installations
- Added Network Admin page for super administrators
- Site-specific configuration for each site in the network
- Network overview showing all sites and their configuration status
- Added multisite detection and warnings in admin interface
- Added `is_network_activated()` helper method
- Enhanced documentation with multisite section
- Added `Network: true` to plugin header
- Added PHP 7.2+ requirement in header

### 1.0.1
- Fixed HTML validation issue in category select
- Improved post_id field handling (empty vs 0)
- Reformatted inline JavaScript for better readability
- Added configuration validation warnings in admin
- Added developer filter hook `sdi_injection_payload`
- Enhanced documentation and code comments
- Fixed wp_register_script handle parameter
- Removed ID display from category dropdown (cleaner UI)

### 1.0.0
- Initial release
- Basic injection functionality
- Multiple position support
- Category and post ID targeting
- CSS selector support

## Support

For support, feature requests, or bug reports, please contact DWAY SRL.

**Website:** https://dway.agency

## License

This plugin is licensed under GPL-2.0+. You are free to use, modify, and distribute this plugin under the terms of the GPL license.

---

**Made with ❤️ by [DWAY SRL](https://dway.agency)**

