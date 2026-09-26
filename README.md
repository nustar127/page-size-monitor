# Page Size Monitor

A lightweight WordPress plugin that monitors and displays the size of your pages, helping you keep an eye on performance and page weight.

## Features

- Tracks the total size of rendered pages
- Helps identify heavy pages that may affect load times
- Simple, dependency-free implementation
- Clean uninstall (removes plugin data on deletion)

## Tech Stack

- PHP
- WordPress Plugin API

## Project Structure

```
assets/                  # CSS, JS, and image assets
includes/                # Core plugin logic and helper files
page-size-monitor.php    # Main plugin file (entry point)
uninstall.php            # Cleanup logic on plugin uninstall
```

## Installation

### Manual

1. Download or clone this repository:

   ```bash
   git clone https://github.com/nustar127/page-size-monitor.git
   ```

2. Copy the `page-size-monitor` folder into `wp-content/plugins/`.
3. Activate the plugin in **WordPress Admin → Plugins**.

### From Archive

1. Zip the plugin folder.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip, install, and activate.
