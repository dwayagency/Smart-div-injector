# Smart Div Injector

**Version:** 1.0.1  
**Author:** DWAY SRL  
**License:** GPL-2.0+  
**Requires at least:** WordPress 5.0  
**Tested up to:** WordPress 6.4  

## Description

Smart Div Injector is a powerful WordPress plugin that allows you to inject custom HTML, CSS, or JavaScript code into specific div elements on your website based on post ID and/or category conditions.

Perfect for:
- Adding custom content to specific posts or categories
- Injecting tracking scripts on targeted pages
- Displaying special banners or notices conditionally
- Inserting custom widgets or components dynamically
- A/B testing different content variations

## Features

✅ **Flexible Targeting** - Target by post ID, category, or both  
✅ **Multiple Injection Positions** - Append, prepend, before, after, or replace content  
✅ **CSS Selector Support** - Use any valid CSS selector to target elements  
✅ **Script Activation** - Automatically activates injected scripts  
✅ **Security First** - Respects WordPress capabilities for unfiltered HTML  
✅ **Developer Friendly** - Includes filters for customization  
✅ **User-Friendly Interface** - Clear admin panel with validation warnings  

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

Navigate to **Settings > Smart Div Injector** in your WordPress admin panel.

### Settings Overview

#### 1. Activation Condition
Choose when the code injection should be triggered:
- **Post ID Only** - Inject only on a specific post/page
- **Category Only** - Inject on all posts in a specific category
- **Post ID AND Category** - Inject only when both conditions are met

#### 2. Post ID
Enter the ID of the post or page where you want to inject the code. Leave empty if not using this condition.

**Tip:** You can find the post ID in the URL when editing a post (e.g., `post.php?post=123`)

#### 3. Category
Select the category where you want to inject the code. Choose "None" if not using this condition.

#### 4. CSS Selector
Enter a valid CSS selector for the target element where the code will be injected.

**Examples:**
- `#main-content` - Target element with ID "main-content"
- `.post-content` - Target elements with class "post-content"
- `article > .entry-content` - Target with complex selectors
- `main .wrap` - Target descendant elements

#### 5. Injection Position
Choose where to inject your code relative to the target element:
- **Append** - Insert at the end inside the element
- **Prepend** - Insert at the beginning inside the element
- **Before** - Insert before the element
- **After** - Insert after the element
- **Replace** - Replace the entire content of the element

#### 6. Code to Inject
Enter the HTML/CSS/JavaScript code you want to inject. This field accepts any valid HTML markup.

**Security Note:** Only users with `unfiltered_html` capability (typically administrators) can save scripts without sanitization.

## Usage Examples

### Example 1: Add a Banner to a Specific Post

**Configuration:**
- Activation Condition: `Post ID Only`
- Post ID: `123`
- CSS Selector: `.entry-content`
- Position: `Prepend`
- Code:
```html
<div class="custom-banner" style="background: #f0f0f0; padding: 20px; margin-bottom: 20px;">
    <h3>Special Announcement!</h3>
    <p>This is a custom message for this specific post.</p>
</div>
```

### Example 2: Add Tracking to All Posts in a Category

**Configuration:**
- Activation Condition: `Category Only`
- Category: `News`
- CSS Selector: `body`
- Position: `Append`
- Code:
```html
<script>
    console.log('Tracking code for News category');
    // Your tracking code here
</script>
```

### Example 3: Insert Custom Widget After Content

**Configuration:**
- Activation Condition: `Post ID AND Category`
- Post ID: `456`
- Category: `Featured`
- CSS Selector: `.post-content`
- Position: `After`
- Code:
```html
<div class="related-posts-widget">
    <h4>You might also like:</h4>
    <!-- Your custom widget HTML -->
</div>
```

### Example 4: Add Custom Styling

**Configuration:**
- Activation Condition: `Category Only`
- Category: `Special`
- CSS Selector: `head`
- Position: `Append`
- Code:
```html
<style>
    .special-category-post {
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
- `$opts` (array) - All plugin options

**Example:**
```php
add_filter( 'sdi_injection_payload', function( $payload, $opts ) {
    // Modify the code before injection
    $payload['code'] = str_replace( '{{site_name}}', get_bloginfo( 'name' ), $payload['code'] );
    
    return $payload;
}, 10, 2 );
```

## How It Works

1. **Condition Check** - The plugin checks if the current page matches your configured conditions
2. **Script Registration** - If conditions are met, the plugin registers an inline script
3. **DOM Ready** - The script waits for the DOM to be fully loaded
4. **Element Selection** - The target element is selected using your CSS selector
5. **Code Injection** - Your code is injected in the specified position
6. **Script Activation** - Any `<script>` tags in your code are automatically activated

## Validation and Warnings

The plugin includes a smart validation system that warns you if:
- CSS selector is not set
- Code to inject is not set
- Post ID is required but not set (based on activation condition)
- Category is required but not set (based on activation condition)
- Both Post ID and Category are required but not set (for AND condition)

These warnings appear at the top of the settings page to help you complete the configuration correctly.

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

## Performance

- **Minimal footprint** - Only loads on pages that match your conditions
- **Efficient script injection** - Uses WordPress's built-in script system
- **DOM-ready detection** - Only executes when the page is fully loaded
- **No external dependencies** - Pure vanilla JavaScript

## Changelog

### 1.0.1
- Fixed HTML validation issue in category select
- Improved post_id field handling (empty vs 0)
- Reformatted inline JavaScript for better readability
- Added configuration validation warnings in admin
- Added developer filter hook `sdi_injection_payload`
- Enhanced documentation and code comments
- Fixed wp_register_script handle parameter

### 1.0.0
- Initial release
- Basic injection functionality
- Multiple position support
- Category and post ID targeting
- CSS selector support

## Support

For support, feature requests, or bug reports, please contact DWAY SRL.

## License

This plugin is licensed under GPL-2.0+. You are free to use, modify, and distribute this plugin under the terms of the GPL license.

---

**Made with ❤️ by DWAY SRL**

