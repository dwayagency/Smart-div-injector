# Smart Div Injector

**Version:** 2.3.1  
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
- Injecting mid-article ads after specific paragraphs
- Inserting CTAs before or after images
- Injecting tracking scripts on targeted pages
- Displaying special banners or notices conditionally
- Inserting custom widgets or components dynamically
- A/B testing different content variations
- Creating multiple injection rules for different scenarios
- Newsletter signup boxes in strategic positions

## Features

✅ **Unlimited Rules** - Create as many injection rules as you need  
✅ **Multiple Code Variants** - Create different versions of code for each rule and switch between them  
✅ **Quick Edit** - Modify rules instantly from the list without opening the full editor  
✅ **Flexible Targeting** - Target all posts, category archives, posts by category, or specific pages  
✅ **Device Targeting** - Choose desktop-only, mobile-only, or both devices for each rule  
✅ **Content Alignment** - Position content with float left, float right, or centered alignment  
✅ **Advanced Search & Filters** - Find rules quickly with search and multiple filters  
✅ **Smart Pagination** - Navigate through rules with customizable items per page (10, 20, 50, 100)  
✅ **Smart Injection Positions** - Standard positions (append, prepend, before, after, replace) + Article-specific positions  
✅ **Article-Specific Positions** - Before/after content, paragraphs, images - no CSS selector needed!  
✅ **Paragraph Targeting** - Insert before or after any specific paragraph number  
✅ **Image Targeting** - Insert before or after the first image in posts  
✅ **CSS Selector Support** - Use any valid CSS selector for standard positions  
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

### Search, Filters & Pagination

When you have many rules, the plugin provides powerful tools to find and manage them efficiently:

#### Search by Name
- Use the search box to find rules by their name
- Search is case-insensitive and matches partial text
- Example: Searching "banner" will find "Homepage Banner", "Mobile Banner", etc.

#### Filter by Status
- **Tutte** (All) - Show all rules regardless of status
- **Solo attive** (Active Only) - Show only active rules that are running on the frontend
- **Solo non attive** (Inactive Only) - Show only inactive/disabled rules

#### Filter by Content Type
- **Tutti i tipi** (All Types) - Show all content types
- **📄 Tutti gli articoli** (All Posts) - Show rules targeting all post pages
- **📁 Archivio categoria** (Category Archive) - Show rules for category archive pages
- **🏷️ Articoli per categoria** (Posts by Category) - Show rules for posts in specific categories
- **📃 Pagina specifica** (Specific Page) - Show rules targeting specific pages

#### Filter by Device
- **Tutti i dispositivi** (All Devices) - Show all device targets
- **📱💻 Entrambi** (Both) - Show rules targeting both desktop and mobile
- **💻 Desktop** - Show desktop-only rules
- **📱 Mobile** - Show mobile-only rules

#### Items Per Page
Choose how many rules to display per page:
- 10 items per page (good for detailed review)
- 20 items per page (default, balanced view)
- 50 items per page (more items at once)
- 100 items per page (maximum items for quick scanning)

#### Using Filters
1. Select your desired filters from the dropdowns
2. Click "Applica Filtri" (Apply Filters) to update the view
3. Click "Reset" to clear all filters and return to the full list
4. Filters persist as you navigate between pages

#### Results Information
When filters are active, you'll see:
- Number of matching rules found
- Total number of rules available
- A message if no rules match the current filters

### Rule Settings

#### 1. Rule Name
Give your rule a descriptive name (e.g., "Banner on News Articles", "Tracking on Homepage")

#### 2. Active Status
Enable or disable the rule without deleting it. Inactive rules won't run on the frontend.

#### 2b. Code Variants (Multiple Versions)
Create and manage multiple versions of your code within a single rule:

**Features:**
- **Add Multiple Variants**: Click "Aggiungi Nuova Variante" to create a new code version
- **Name Each Variant**: Give descriptive names like "Summer Banner", "Christmas Banner", "Black Friday Ad"
- **Switch Active Variant**: Only one variant is active at a time - choose which one to display
- **View in List**: The rules list shows which variant is currently active and how many variants exist
- **Independent Code**: Each variant has its own complete HTML/JS/CSS code

**Use Cases:**
- **A/B Testing**: Create two versions and test which performs better
- **Seasonal Content**: Have different versions for holidays, seasons, or special events ready to activate
- **Regional Variations**: Different content for different markets or languages
- **Quick Switching**: Change between versions without editing code - just activate a different variant
- **Backup Versions**: Keep old versions as backup while trying new designs

**How It Works:**
1. When creating/editing a rule, you'll see the "Varianti Codice" section
2. Start with one variant (Variante 1) by default
3. Add more variants with the "+ Aggiungi Nuova Variante" button
4. Each variant has:
   - A name field (required)
   - A code textarea (required)
   - An "Attiva questa" button to make it the active variant
   - A delete button (if there's more than one variant)
5. The active variant is marked with a green "✓ Attiva" badge
6. Only the active variant's code will be injected on the frontend

**Example Workflow:**
```
Rule: "Homepage Banner"
├── Variante 1: "Standard Banner" (Active) ← Currently shown
├── Variante 2: "Black Friday Banner" ← Ready to activate
└── Variante 3: "Christmas Banner" ← Ready to activate
```
When Black Friday arrives, simply click "Attiva questa" on Variante 2 - no code editing needed!

#### 3. Device Target
Choose which devices should display this rule:
- **Both** (📱💻) - Apply on both desktop and mobile devices (default)
- **Desktop Only** (💻) - Apply only on desktop/laptop browsers
- **Mobile Only** (📱) - Apply only on mobile devices and tablets

> The plugin uses WordPress's `wp_is_mobile()` function to detect mobile devices.

#### 3b. Content Alignment
Choose how to position the injected content:
- **Nessuno** (None) - Natural position without any special alignment (default)
- **Float a sinistra** (⬅️ Float Left) - Float the content to the left, allowing text to wrap around it on the right
- **Float a destra** (➡️ Float Right) - Float the content to the right, allowing text to wrap around it on the left
- **Centrato** (↔️ Centered) - Center the content horizontally on the page

**Use Cases:**
- **Float Left/Right**: Perfect for banners or ads that should have text flowing around them, like traditional magazine layouts
- **Centered**: Ideal for call-to-action boxes, announcements, or featured content that should be prominently displayed
- **None**: Best when you want the content to follow the natural document flow without affecting surrounding elements

> The alignment is applied by wrapping your injected code in a `<div>` with appropriate inline styles. Float alignments include automatic clearfix to prevent layout issues.

#### 4. Content Type
Choose where to apply the injection:
- **All Posts** (Tutti gli articoli) - Inject on all single post pages
- **Category Archive Page** (Pagina archivio categoria) - Inject on the category archive page (e.g., example.com/category/news/)
- **Posts by Category** (Articoli di una categoria) - Inject only on single posts that belong to a specific category
- **Specific Page** (Pagina specifica) - Inject only on a selected page

#### 5. Category (when applicable)
If you selected "Category Archive Page" or "Posts by Category", choose which category to target.

#### 6. Page (when applicable)
If you selected "Specific Page", choose which page to target from the dropdown, or enter the Page ID manually.

**For sites with many pages:** The dropdown shows up to 500 pages. If your page isn't listed, use the manual ID input field.

#### 7. CSS Selector
Enter a valid CSS selector for the target element where the code will be injected.

**Examples:**
- `#main-content` - Target element with ID "main-content"
- `.post-content` - Target elements with class "post-content"
- `article > .entry-content` - Target with complex selectors
- `main .wrap` - Target descendant elements

#### 8. Injection Position

**Standard Positions** (for CSS selectors):
- **Append** - Insert at the end inside the element
- **Prepend** - Insert at the beginning inside the element
- **Before** - Insert before the element
- **After** - Insert after the element
- **Replace** - Replace the entire content of the element

**Article-Specific Positions** (available for "All Posts" and "Posts by Category"):
- **Before Post** - Insert before the entire post content
- **Before Content** - Insert at the very beginning of the content
- **After Content** - Insert at the very end of the content
- **Before Paragraph N** - Insert before a specific paragraph (you specify the number)
- **After Paragraph N** - Insert after a specific paragraph (you specify the number)
- **Before First Image** - Insert before the first image in the content
- **After First Image** - Insert after the first image in the content

> **Note:** When using article-specific positions, the CSS selector field is not required and will be ignored.

#### 9. Code to Inject
Enter the HTML/CSS/JavaScript code you want to inject. This field accepts any valid HTML markup.

**Security Note:** Only users with `unfiltered_html` capability (typically administrators) can save scripts without sanitization.

## Usage Examples

### Example 1: Add a Banner Before Post Content

**Configuration:**
- Rule Name: `Banner on All Articles`
- Active: ✓
- Content Type: `All Posts`
- Position: `Before Content`
- Code:
```html
<div class="custom-banner" style="background: #f0f0f0; padding: 20px; margin-bottom: 20px;">
    <h3>Special Announcement!</h3>
    <p>This is a custom message shown before all article content.</p>
</div>
```

> This uses the article-specific "Before Content" position, so no CSS selector is needed.

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

### Example 6: Inject Ad After 3rd Paragraph

**Configuration:**
- Rule Name: `Mid-Article Ad`
- Active: ✓
- Content Type: `All Posts`
- Position: `After Paragraph N`
- Paragraph Number: `3`
- Code:
```html
<div class="mid-article-ad" style="margin: 30px 0; padding: 20px; background: #f9f9f9; text-align: center;">
    <p style="font-size: 12px; color: #999; margin-bottom: 10px;">Advertisement</p>
    <!-- Your ad code here -->
    <div class="ad-placeholder" style="background: #ddd; height: 250px; display: flex; align-items: center; justify-content: center;">
        Ad Space 300x250
    </div>
</div>
```

> This injects the ad after the 3rd paragraph of every post. No CSS selector needed!

### Example 7: Call-to-Action Before First Image

**Configuration:**
- Rule Name: `CTA Before Image`
- Active: ✓
- Content Type: `Posts by Category`
- Category: `Reviews`
- Position: `Before First Image`
- Code:
```html
<div class="cta-box" style="background: #2271b1; color: white; padding: 15px; margin: 20px 0; border-radius: 5px;">
    <strong>📸 Before you see the image...</strong>
    <p>Don't forget to subscribe to our newsletter for more reviews!</p>
    <button style="background: white; color: #2271b1; border: none; padding: 10px 20px; border-radius: 3px; cursor: pointer;">
        Subscribe Now
    </button>
</div>
```

> Automatically inserts a CTA box before the first image in review posts.

### Example 8: Mobile-Only Banner

**Configuration:**
- Rule Name: `Mobile Banner`
- Active: ✓
- Device Target: `📱 Mobile Only`
- Content Type: `All Posts`
- Position: `Before Content`
- Code:
```html
<div class="mobile-app-banner" style="background: #4CAF50; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center;">
    <strong>📱 Get Our Mobile App!</strong>
    <p style="margin: 10px 0;">Download our app for a better reading experience</p>
    <a href="#" style="background: white; color: #4CAF50; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block; font-weight: bold;">
        Download Now
    </a>
</div>
```

> This banner will only appear on mobile devices, promoting your mobile app.

### Example 9: Desktop-Only Sidebar Widget

**Configuration:**
- Rule Name: `Desktop Sidebar Widget`
- Active: ✓
- Device Target: `💻 Desktop Only`
- Content Type: `All Posts`
- CSS Selector: `.sidebar`
- Position: `Prepend`
- Code:
```html
<div class="desktop-widget" style="background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px;">
    <h3 style="margin-top: 0;">Subscribe to Newsletter</h3>
    <p>Get weekly updates delivered to your inbox</p>
    <input type="email" placeholder="Your email" style="width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 3px;">
    <button style="width: 100%; padding: 10px; background: #2271b1; color: white; border: none; border-radius: 3px; cursor: pointer;">
        Subscribe
    </button>
</div>
```

> This widget appears only on desktop, where there's more screen space for a sidebar.

### Example 10: Floating Ad Banner with Text Wrap

**Configuration:**
- Rule Name: `Floating Ad - Right Side`
- Active: ✓
- Device Target: `💻 Desktop Only`
- Content Type: `All Posts`
- Content Alignment: `➡️ Float Right`
- Position: `After Paragraph 2`
- Code:
```html
<div style="width: 300px; background: #fff; border: 2px solid #e0e0e0; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <p style="text-align: center; color: #999; font-size: 11px; margin: 0 0 10px 0;">ADVERTISEMENT</p>
    <img src="https://via.placeholder.com/270x200" alt="Ad" style="width: 100%; height: auto; border-radius: 4px;">
    <h4 style="margin: 10px 0 5px 0; font-size: 16px;">Featured Product</h4>
    <p style="margin: 0; font-size: 13px; color: #666;">Check out our latest offering!</p>
</div>
```

> This creates a floating ad that appears on the right side with text wrapping around it on the left, like traditional print media.

### Example 11: Centered Call-to-Action Box

**Configuration:**
- Rule Name: `Centered Newsletter CTA`
- Active: ✓
- Device Target: `📱💻 Both`
- Content Type: `Posts by Category`
- Category: `News`
- Content Alignment: `↔️ Centered`
- Position: `After Content`
- Code:
```html
<div style="max-width: 600px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
    <h2 style="color: white; text-align: center; margin: 0 0 15px 0; font-size: 28px;">Stay Updated!</h2>
    <p style="color: rgba(255,255,255,0.9); text-align: center; margin: 0 0 25px 0; font-size: 16px;">
        Get the latest news delivered directly to your inbox
    </p>
    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
        <input type="email" placeholder="your@email.com" style="flex: 1; min-width: 250px; padding: 12px 20px; border: none; border-radius: 6px; font-size: 15px;">
        <button style="padding: 12px 30px; background: white; color: #667eea; border: none; border-radius: 6px; font-weight: bold; font-size: 15px; cursor: pointer;">
            Subscribe
        </button>
    </div>
</div>
```

> This creates a prominent, centered call-to-action box that draws attention at the end of news articles.

### Example 12: Floating Info Box on the Left

**Configuration:**
- Rule Name: `Article Info Box - Left`
- Active: ✓
- Device Target: `💻 Desktop Only`
- Content Type: `All Posts`
- Content Alignment: `⬅️ Float Left`
- Position: `Before Content`
- Code:
```html
<div style="width: 250px; background: #f8f9fa; border-left: 4px solid #2271b1; padding: 20px; border-radius: 4px;">
    <h4 style="margin: 0 0 10px 0; color: #2271b1; font-size: 16px;">📌 Quick Info</h4>
    <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.6;">
        <li>Reading time: 5 min</li>
        <li>Last updated: Today</li>
        <li>Category: Tutorial</li>
    </ul>
</div>
```

> This info box floats on the left side of the article content, with text flowing around it on the right.

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

### 2.3.1
- **🐛 Bug Fix**: Fixed JavaScript conflict when multiple rules are present
  - Wrapped Quick Edit scripts in IIFE (Immediately Invoked Function Expression) for proper scope isolation
  - Added protection against duplicate event listener registration
  - Improved timing for scroll and focus operations to prevent race conditions
  - Fixed conflict with jQuery and other theme/plugin scripts (specifically resolved "cluentina" error)
- **🔧 Variant Handling**: Improved variant saving and index management
  - Now correctly saves all variants including empty ones to maintain proper indices
  - Fixed issue where active_variant index would become incorrect after saving
  - Ensured frontend only skips injection when code is empty, not during save
  - Simplified sanitization logic for better reliability
- **⚡ Stability**: Enhanced overall plugin stability
  - Better JavaScript isolation to prevent conflicts
  - Improved variant data integrity
  - Fixed edge cases in variant activation

### 2.3.0
- **⚡ Quick Edit (Modifica Rapida)**: Edit rules directly from the list without opening the full editor
  - Inline editing panel appears directly in the rules table
  - Quick access button with pencil icon
  - Edit key properties: name, active status, device target, alignment, active variant
  - Grid layout for form fields - responsive and organized
  - Save changes with a single click
  - Cancel button or press ESC to close
  - Smooth scroll and auto-focus on first field
  - Success message after quick update
  - Visual tip reminder that full edit is needed for code changes
  - Only one quick edit panel open at a time
  - Professional styling matching WordPress admin
- **🎨 Quick Edit UI**: Beautiful inline interface
  - Gray background with blue bottom border for clear separation
  - Header with rule name and close button
  - Grid-based responsive layout for fields
  - Proper spacing and typography
  - Mobile-friendly responsive design
  - Keyboard shortcuts (ESC to close)
- **⚡ Performance**: Efficient inline editing
  - No page reload required
  - Instant feedback with smooth animations
  - Saves only modified fields
  - Backend validation for all fields

### 2.2.0
- **🎭 Multiple Code Variants**: Revolutionary new feature for managing multiple versions of code
  - Create unlimited variants within each rule
  - Each variant has its own name and complete code
  - Switch between variants with a single click - no code editing needed
  - Perfect for A/B testing different ad designs or banners
  - Seasonal content management (Christmas, Summer, Black Friday versions ready to activate)
  - Regional or language variations in one place
  - Active variant clearly marked with green badge in form
  - Variant count and active variant shown in rules list table
  - Add new variants dynamically with JavaScript interface
  - Delete unused variants (must keep at least one)
  - Automatic migration for old rules with single code field
  - Retrocompatibility ensured - existing rules work seamlessly
- **🎨 Variants UI**: Beautiful interface for managing variants
  - Each variant displayed in a card with header and body
  - Collapsible variant items with hover effects
  - Color-coded active variant badge
  - Intuitive add/remove/activate buttons
  - Real-time variant number updates
  - Responsive design for mobile devices
  - Professional styling matching WordPress standards
- **⚡ Smart Variant Handling**: Efficient backend logic
  - `get_active_variant_code()` helper function
  - Only active variant code is injected on frontend
  - Variants stored as array in database
  - Empty variants automatically skipped
  - Active variant index validation
  - Seamless integration with existing alignment and injection logic

### 2.1.0
- **🔍 Search & Filters**: Advanced search and filtering system for managing multiple rules
  - Search rules by name with case-insensitive matching
  - Filter by status (active/inactive)
  - Filter by content type (posts, pages, categories)
  - Filter by device target (desktop/mobile/both)
  - Filters persist across page navigation
  - Results counter showing matches vs. total rules
- **📄 Pagination**: Smart pagination system for large rule lists
  - Navigate through rules with page numbers
  - Customizable items per page (10, 20, 50, 100)
  - "Previous" and "Next" navigation buttons
  - Total items counter
  - Pagination persists with active filters
- **🎯 Content Alignment**: New positioning options for injected content
  - Float Left (⬅️) - Content floats left with text wrapping on the right
  - Float Right (➡️) - Content floats right with text wrapping on the left
  - Centered (↔️) - Content centered horizontally on the page
  - None - Natural document flow (default)
  - Automatic clearfix for float alignments to prevent layout issues
  - Works with both JavaScript injection and WordPress filter injection
  - Perfect for ads, banners, info boxes, and call-to-action elements
- **🎨 Enhanced UI**: Improved styling for filters and pagination
  - Flexbox-based filter layout that adapts to screen size
  - Professional pagination controls with hover effects
  - Responsive design for mobile devices
  - Clean and modern interface matching WordPress standards
  - Empty state design when no results are found
- **⚡ Performance**: Efficient filtering and pagination
  - Client-side filtering for instant results
  - Array slicing for memory-efficient pagination
  - No additional database queries for filtering
  - Smooth page transitions

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
- **Device Targeting:**
  - Desktop Only (💻) - Apply rules only on desktop/laptop browsers
  - Mobile Only (📱) - Apply rules only on mobile devices and tablets
  - Both (📱💻) - Apply rules on all devices (default)
  - Uses WordPress `wp_is_mobile()` for device detection
  - Perfect for showing different content on mobile vs desktop
- **Article-Specific Injection Positions:**
  - Before Post - Insert before the entire post
  - Before Content - Insert at the start of the content
  - After Content - Insert at the end of the content
  - Before Paragraph N - Insert before a specific paragraph
  - After Paragraph N - Insert after a specific paragraph (great for mid-article ads!)
  - Before First Image - Insert before the first image
  - After First Image - Insert after the first image
  - Smart paragraph and image detection using WordPress filters
  - No CSS selector needed for article-specific positions
- **Modern UI/UX:**
  - Custom CSS styling with beautiful color palette
  - Responsive design for mobile, tablet, and desktop
  - Status badges with visual indicators
  - Improved button styles with hover effects
  - Toggle switch for enable/disable rules
  - Empty state design for better onboarding
  - Better spacing and typography
  - Dynamic form fields based on selection
  - Device indicator in rules table
- Memory optimization: Limits queries for sites with thousands of posts
- Manual ID input for posts/pages not in dropdown
- Added Author URI: https://dway.agency
- Complete UI/UX redesign for better usability
- Updated filter hook parameters to include rule data and rule ID
- Sequential rule processing with independent error handling
- Server-side content injection for better performance on article positions

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

