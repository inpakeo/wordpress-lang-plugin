# WordPress Hreflang Manager

A simple and powerful multilingual WordPress plugin with automatic hreflang tags generation. Perfect for SEO and international websites.

## Features

- **Simple Language Management**: Add, edit, and remove languages through an intuitive admin interface
- **Automatic Hreflang Tags**: Generates Google-compliant hreflang tags automatically
- **Multiple Display Styles**: Choose from dropdown, list, or flags-only language switchers
- **Widget Support**: Add language switcher to any widget area
- **Shortcode Support**: Use `[language_switcher]` anywhere in your content
- **Universal Theme Compatibility**: Works with any WordPress theme
- **Translation Linking**: Link pages/posts across languages easily
- **Cookie-based Language Memory**: Remembers user's language preference
- **SEO Optimized**: Follows Google's hreflang guidelines
- **Open Graph Support**: Automatic og:locale tags for social media

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `wordpress-hreflang-manager` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings → Hreflang Manager to configure

### From GitHub

```bash
cd wp-content/plugins
git clone https://github.com/inpakeo/wordpress-lang-plugin.git wordpress-hreflang-manager
```

## Configuration

### 1. Add Languages

Go to **Settings → Hreflang Manager** and add your languages:

- **Language Code**: 2-letter ISO 639-1 code (e.g., `en`, `es`, `fr`)
- **Language Name**: Full language name (e.g., "English", "Español")
- **Hreflang Code**: ISO 639-1 + optional ISO 3166-1 (e.g., `en-US`, `es-MX`)
- **Flag**: Emoji flag (e.g., 🇺🇸, 🇪🇸, 🇫🇷)

### 2. Set Default Language

Choose your website's primary language from the dropdown.

### 3. Configure Display Options

- **Switcher Style**: Dropdown, List, or Flags
- **Show Flags**: Display flag emojis
- **Show Language Names**: Display language names
- **Auto Redirect**: Automatically redirect based on browser language

### 4. Link Translations

When editing a post or page:

1. Set the language of the current content using the "Language Translations" metabox
2. Link it to translations in other languages
3. Save the post

The plugin will automatically create bidirectional links.

## Usage

### Widget

1. Go to **Appearance → Widgets**
2. Add "Language Switcher" widget to any widget area
3. Configure display options
4. Save

### Shortcode

Add the language switcher anywhere in your content:

```php
[language_switcher]
```

With custom attributes:

```php
[language_switcher style="list" show_flags="true" show_names="true"]
```

### PHP Code

Add to your theme template:

```php
<?php
if ( class_exists( 'WP_Hreflang_Language_Switcher' ) ) {
    WP_Hreflang_Language_Switcher::render( array(
        'style' => 'dropdown',
        'show_flags' => true,
        'show_names' => true,
        'echo' => true
    ) );
}
?>
```

## How It Works

### Automatic Hreflang Tags

The plugin automatically generates hreflang tags in your HTML `<head>`:

```html
<link rel="alternate" hreflang="en-US" href="https://example.com/page-english" />
<link rel="alternate" hreflang="es-ES" href="https://example.com/pagina-espanol" />
<link rel="alternate" hreflang="x-default" href="https://example.com/page-english" />
```

### Language Attribute

Updates the HTML `lang` attribute dynamically:

```html
<html lang="en-US">
```

### Open Graph Tags

Adds locale meta tags for social media:

```html
<meta property="og:locale" content="en_US" />
<meta property="og:locale:alternate" content="es_ES" />
```

## Supported Hreflang Codes

Common formats:

- Language only: `en`, `es`, `fr`, `de`, `ru`, `zh`
- Language + Region: `en-US`, `en-GB`, `es-ES`, `es-MX`, `fr-FR`, `fr-CA`

The plugin validates hreflang codes to ensure Google compliance.

## Frequently Asked Questions

### Does this plugin translate content?

No, this plugin manages hreflang tags and language switching. You create translations manually and link them together.

### Is it compatible with translation plugins?

Yes! This plugin can work alongside translation plugins or as a standalone solution.

### How do I add more languages?

Go to Settings → Hreflang Manager and use the "Add New Language" form.

### Can I use custom language codes?

Yes, but ensure they follow ISO 639-1 standards for proper SEO.

### Does it work with custom post types?

Yes! The plugin works with all public post types.

## Best Practices

1. **Use proper hreflang codes**: Follow ISO 639-1 (language) and ISO 3166-1 (region) standards
2. **Link all translations**: Every page should link to all its translations
3. **Set x-default**: Choose a default language for unmatched regions
4. **Test with Google**: Use Google Search Console to verify hreflang implementation
5. **Keep URLs consistent**: Use the same URL structure across languages when possible

## Technical Details

### Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

### File Structure

```
wordpress-hreflang-manager/
├── wordpress-hreflang-manager.php   # Main plugin file
├── includes/
│   ├── class-language-manager.php    # Language management
│   ├── class-hreflang-generator.php  # Hreflang tag generation
│   ├── class-admin-settings.php      # Admin interface
│   ├── class-language-widget.php     # Widget functionality
│   └── class-language-switcher.php   # Switcher rendering
├── admin/
│   ├── css/admin-style.css          # Admin styles
│   └── js/admin-script.js           # Admin JavaScript
├── public/
│   ├── css/public-style.css         # Frontend styles
│   └── js/public-script.js          # Frontend JavaScript
└── README.md
```

### Hooks & Filters

The plugin provides hooks for developers:

```php
// Modify language options
add_filter( 'wp_hreflang_options', 'my_custom_options' );

// Customize switcher output
add_filter( 'wp_hreflang_switcher_html', 'my_custom_switcher', 10, 2 );

// Modify hreflang tags before output
add_filter( 'wp_hreflang_tags', 'my_custom_tags' );
```

## Support & Contributing

- **Issues**: [GitHub Issues](https://github.com/inpakeo/wordpress-lang-plugin/issues)
- **Contribute**: Pull requests are welcome!
- **Documentation**: This README and inline code comments

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Alexander Fedin ([@o2alexanderfedin](https://github.com/o2alexanderfedin))

Based on Google's hreflang guidelines and WordPress best practices.

## Changelog

### 1.0.0 - Initial Release

- Simple language management interface
- Automatic hreflang tag generation
- Multiple switcher styles (dropdown, list, flags)
- Widget and shortcode support
- Translation linking metabox
- Cookie-based language memory
- Open Graph locale tags
- Full theme compatibility
- Google-compliant hreflang implementation
