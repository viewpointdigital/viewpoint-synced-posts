# Viewpoint Synced Posts

A WordPress library for synchronizing external API data with WordPress custom post types.

## Overview

Viewpoint Synced Posts is a developer library that automates the synchronization of data from external APIs into WordPress as custom post types. It handles the complexity of tracking changes, mapping fields, and performing create/update/delete operations asynchronously.

**Important:** This is a Composer library designed to be included in your WordPress plugin, not a standalone plugin.

### When to Use This Library

- Syncing product inventory from external systems to WordPress
- Aggregating content from multiple APIs
- Integrating WordPress with CRM, ERP, or other business systems
- Publishing real-time data from live data sources
- Automating content syndication workflows

## Requirements

- **PHP:** 8.0 or higher
- **WordPress:** 5.0 or higher
- **Advanced Custom Fields (ACF) Pro:** Required for admin settings interface
- **Composer:** For dependency management

### Dependencies

The library automatically installs:
- `viewpoint/viewpoint-utilities` - Utility classes (Singleton, Logger, Settings)
- `viewpoint/viewpoint-api` - API abstraction layer (Endpoint, JoinEndpoint, Formatter)
- `woocommerce/action-scheduler` ^3.7 - Background task processing

## Installation

Add the library to your plugin's `composer.json`:

```json
{
  "require": {
    "viewpoint/viewpoint-synced-posts": "dev-main"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://bitbucket.org/viewpointdigital/viewpoint-synced-posts.git"
    }
  ]
}
```

Then run:

```bash
composer install
```

## Quick Start

Here's a basic example to sync articles from an external API:

```php
<?php
use Viewpoint\ViewpointSyncedPosts\App;
use Viewpoint\ViewpointSyncedPosts\CustomPostType;
use Viewpoint\ViewpointSyncedPosts\Map;
use Viewpoint\ViewpointApi\Endpoint;

// 1. Register custom post type and taxonomies
$cpt = new CustomPostType(
    post_type: 'article',
    taxonomies: ['article_category'],
    args: [
        'label' => 'Articles',
        'supports' => ['title', 'editor', 'thumbnail']
    ],
    debug: false
);

// 2. Configure API endpoint
$endpoint = new Endpoint(
    url: 'https://api.example.com/articles',
    data_path: 'data.articles', // Path to array of items in response
    transient: 3600 // Cache API response for 1 hour
);

// 3. Map API fields to WordPress post fields
$map = new Map(
    src_id: 'id',                          // Unique identifier in API
    post_type: 'article',
    post_title: 'title',
    post_content: 'body',
    post_date: 'published_at',
    post_modified: 'updated_at',
    post_meta: [
        'author_name' => 'author.name',    // Supports nested fields
        'view_count' => 'stats.views'
    ],
    taxonomies: [
        'article_category' => 'category'
    ]
);

// 4. Initialize the sync application
$app = new App(
    cpt: $cpt,
    endpoint: $endpoint,
    map: $map,
    debug: false
);

// The sync will now run automatically based on the schedule configured in WordPress admin
// Navigate to Settings > Articles Sync to configure scheduling and trigger manual syncs
```

## Core Concepts

### App

The main orchestrator that initializes the sync system and admin interface. Creates instances of `Sync` and `Admin` classes.

**Constructor Parameters:**
- `$cpt` (CustomPostType) - Post type configuration
- `$endpoint` (Endpoint|JoinEndpoint) - API endpoint configuration
- `$map` (Map) - Field mapping configuration
- `$debug` (bool) - Enable debug mode (default: false)

**Public Properties:**
- `$sync` (Sync) - Access the sync interface
- `$cpt` (CustomPostType) - Access the post type configuration
- `$endpoint` (Endpoint|JoinEndpoint) - Access the endpoint configuration
- `$map` (Map) - Access the field mapping

### CustomPostType

Registers WordPress custom post types and taxonomies with sensible defaults.

**Constructor Parameters:**
- `$post_type` (string) - Post type slug
- `$taxonomies` (array) - Array of taxonomy slugs or slug => args pairs
- `$args` (array) - register_post_type() arguments (optional)
- `$debug` (bool) - Make post type public when debugging (default: false)

**Default Behavior:**
- Post types are private by default (public in debug mode)
- Supports: title, editor, custom-fields, thumbnail
- REST API enabled
- Taxonomies inherit debug mode settings

### Map

Defines how external API fields map to WordPress post properties, metadata, and taxonomies. Supports nested field paths using dot notation (e.g., `author.name`).

**Constructor Parameters:**
- `$src_id` (int|string) - API field name for unique identifier
- `$post_type` (string) - WordPress post type slug
- `$post_title` (string) - API field name for post title
- `$post_content` (string|array) - API field name(s) for post content
- `$post_date` (string|false) - API field name for publish date (optional)
- `$post_modified` (string|false) - API field name for modified date (optional)
- `$post_meta` (array) - Associative array: `meta_key => api_field_name`
- `$taxonomies` (array) - Associative array: `taxonomy_slug => api_field_name`

**Public Methods:**
- `get($field, $post_data)` - Retrieve mapped value from API data
- `getInsertPostData($post_data)` - Format API data for wp_insert_post()

### Sync

Coordinates the synchronization process between the API and WordPress.

**Constructor Parameters:**
- `$post_type` (string) - WordPress post type slug
- `$endpoint` (Endpoint|JoinEndpoint) - API endpoint
- `$map` (Map) - Field mapping configuration

**Public Methods:**
- `start_sync_task()` - Execute synchronization and return SyncTask with status

**Public Properties:**
- `$task` (SyncTask) - Access sync task details
- `$map` (Map) - Access field mapping

### Admin

Provides WordPress admin interface with ACF settings page, manual sync trigger, and automatic scheduling. Automatically initialized by `App`.

**Admin Settings:**
- **Schedule** - Sync frequency in minutes (default: 60)
- **Debug** - Enable verbose logging and show hidden post types
- **Manual Sync Button** - Trigger on-demand sync

### SyncTask

Executes the actual synchronization logic. Compares source and destination data to determine which posts need to be created, updated, or deleted.

**Public Properties:**
- `$status` (array) - Sync statistics: `created`, `updated`, `deleted`, `skipped`
- `$src_posts` (array) - Source posts from API
- `$dest_posts` (array) - Destination post IDs in WordPress
- `$mapped_posts` (array) - Mapping of src_id => dest_id

## Hooks & Extensibility

### Filters

#### `viewpoint_sync_src_posts`

Modify or filter source posts retrieved from the API before processing.

```php
add_filter('viewpoint_sync_src_posts', function($src_posts, $post_type) {
    // Filter out posts that shouldn't be synced
    return array_filter($src_posts, function($post) {
        return $post->status === 'published';
    });
}, 10, 2);
```

**Parameters:**
- `$src_posts` (array) - Source posts from API
- `$post_type` (string) - Post type being synced

---

#### `viewpoint_sync_dest_posts`

Modify the list of destination post IDs before comparison.

```php
add_filter('viewpoint_sync_dest_posts', function($dest_posts) {
    // Exclude certain posts from sync consideration
    return array_diff($dest_posts, [123, 456]);
}, 10, 1);
```

**Parameters:**
- `$dest_posts` (array) - WordPress post IDs

---

#### `viewpoint_update_post_check`

Override the logic that determines whether a post should be updated.

```php
add_filter('viewpoint_update_post_check', function($should_update, $dest_id, $src_timestamp, $dest_timestamp) {
    // Always update posts regardless of modified date
    return true;
}, 10, 4);
```

**Parameters:**
- `$should_update` (bool) - Default update decision
- `$dest_id` (int) - WordPress post ID
- `$src_timestamp` (int) - Source modified timestamp
- `$dest_timestamp` (int) - Destination modified timestamp

---

### Actions

#### `viewpoint_post_updated`

Fires after a post is created or updated.

```php
add_action('viewpoint_post_updated', function($post_id, $post_data) {
    // Update custom fields, send notifications, etc.
    error_log("Post {$post_id} was synced");
}, 10, 2);
```

**Parameters:**
- `$post_id` (int) - WordPress post ID
- `$post_data` (array) - Post data array used for insert/update

---

#### `viewpoint_update_post_terms`

Fires after taxonomy terms are assigned to a post.

```php
add_action('viewpoint_update_post_terms', function($post_id, $terms, $post_data) {
    // Perform actions after terms are set
}, 10, 3);
```

**Parameters:**
- `$post_id` (int) - WordPress post ID
- `$terms` (array|WP_Error) - Result from wp_set_object_terms()
- `$post_data` (array) - Post data array

---

#### `viewpoint_sync_complete`

Fires after the sync process completes and all async actions are queued.

```php
add_action('viewpoint_sync_complete', function() {
    // Clear additional caches, send notifications, etc.
    do_action('custom_cache_flush');
}, 10, 0);
```

**Note:** This action fires before all individual create/update/delete operations complete since they run asynchronously.

---

#### `viewpoint_{post_type}_create_post`

Async action to create a specific post. Queued via Action Scheduler.

```php
add_action('viewpoint_article_create_post', function($post_data) {
    // Runs asynchronously for each post creation
    // Custom logic before/after creation
}, 5, 1); // Priority < 10 to run before default handler
```

**Parameters:**
- `$post_data` (array) - Post data for wp_insert_post()

---

#### `viewpoint_{post_type}_update_post`

Async action to update a specific post. Queued via Action Scheduler.

```php
add_action('viewpoint_article_update_post', function($post_id, $post_data) {
    // Runs asynchronously for each post update
}, 5, 2); // Priority < 10 to run before default handler
```

**Parameters:**
- `$post_id` (int) - WordPress post ID to update
- `$post_data` (array) - Post data for wp_insert_post()

---

#### `viewpoint_{post_type}_delete_post`

Async action to delete a specific post. Queued via Action Scheduler.

```php
add_action('viewpoint_article_delete_post', function($post_id) {
    // Runs asynchronously for each post deletion
    // Clean up related data before deletion
}, 5, 1); // Priority < 10 to run before default handler
```

**Parameters:**
- `$post_id` (int) - WordPress post ID to delete

---

#### `viewpoint_{sanitized_name}` (Dynamic Hook)

Manual sync trigger action. The `{sanitized_name}` is based on the label you provide to the Admin class.

```php
// Example: If your admin label is "Articles Sync", the hook is:
do_action('viewpoint_articles_sync');

// This is triggered automatically by the admin "Run Sync" button
```

## Configuration Reference

### Endpoint Configuration

Using the `Endpoint` class from `viewpoint/viewpoint-api`:

```php
use Viewpoint\ViewpointApi\Endpoint;

$endpoint = new Endpoint(
    url: 'https://api.example.com/data',
    data_path: 'items',           // Path to array in JSON response
    transient: 3600,               // Cache duration in seconds (0 to disable)
    headers: [                     // Optional HTTP headers
        'Authorization' => 'Bearer token123'
    ]
);
```

### JoinEndpoint Configuration

For combining data from multiple API endpoints:

```php
use Viewpoint\ViewpointApi\JoinEndpoint;

$endpoint = new JoinEndpoint(
    endpoints: [$endpoint1, $endpoint2],
    join_field: 'id'              // Field to join on
);
```

### Field Mapping Examples

**Basic mapping:**
```php
$map = new Map(
    src_id: 'id',
    post_type: 'product',
    post_title: 'name',
    post_content: 'description'
);
```

**Nested fields:**
```php
$map = new Map(
    src_id: 'id',
    post_type: 'product',
    post_title: 'name',
    post_content: 'details.description',
    post_meta: [
        'price' => 'pricing.amount',
        'sku' => 'inventory.sku',
        'manufacturer' => 'vendor.company.name'
    ]
);
```

**Array content:**
```php
$map = new Map(
    src_id: 'id',
    post_type: 'article',
    post_title: 'title',
    post_content: ['intro', 'body', 'conclusion'] // Multiple fields combined
);
```

**Taxonomies:**
```php
$map = new Map(
    src_id: 'id',
    post_type: 'product',
    post_title: 'name',
    post_content: 'description',
    taxonomies: [
        'product_cat' => 'category',      // Single category
        'product_tag' => 'tags'           // Array of tags
    ]
);
```

## Advanced Usage

### Programmatic Sync Trigger

Trigger a sync programmatically without using the admin interface:

```php
// Access the App instance
global $my_articles_app;

// Start sync and get results
$sync_task = $my_articles_app->sync->start_sync_task();

// Check results
echo "Created: {$sync_task->status['created']}\n";
echo "Updated: {$sync_task->status['updated']}\n";
echo "Deleted: {$sync_task->status['deleted']}\n";
echo "Skipped: {$sync_task->status['skipped']}\n";
```

### Custom Sync Scheduling

Override the default scheduling behavior:

```php
// Disable built-in scheduling by not using the Admin class
// Then implement your own schedule:

add_action('my_custom_cron_hook', function() use ($app) {
    $app->sync->start_sync_task();
});

if (!wp_next_scheduled('my_custom_cron_hook')) {
    wp_schedule_event(time(), 'hourly', 'my_custom_cron_hook');
}
```

### Multiple Syncs

Sync multiple post types from different APIs:

```php
// Articles sync
$articles_cpt = new CustomPostType('article', ['article_category']);
$articles_endpoint = new Endpoint('https://api.example.com/articles', 'data');
$articles_map = new Map('id', 'article', 'title', 'content');
$articles_app = new App($articles_cpt, $articles_endpoint, $articles_map);

// Products sync
$products_cpt = new CustomPostType('product', ['product_cat']);
$products_endpoint = new Endpoint('https://api.example.com/products', 'items');
$products_map = new Map('sku', 'product', 'name', 'description');
$products_app = new App($products_cpt, $products_endpoint, $products_map);

// Each sync runs independently with its own admin settings page
```

### Monitoring Sync Progress

Check Action Scheduler for detailed sync status:

```php
// View in WordPress admin: Tools > Scheduled Actions
// Or programmatically:

$actions = as_get_scheduled_actions([
    'hook' => 'viewpoint_article_create_post',
    'status' => 'pending'
]);

foreach ($actions as $action) {
    echo "Pending creation at: " . $action->get_schedule()->get_date()->format('Y-m-d H:i:s');
}
```

## License

This library is proprietary software developed by Viewpoint Digital.

## Credits

**Author:** Jeremy Lind (jeremy@viewpointdigital.ca)
**Maintainer:** Viewpoint Digital
**Repository:** https://bitbucket.org/viewpointdigital/viewpoint-synced-posts
