# Simple Admin Notice

Simply add admin notices for all post saving hooks.
You can either set the notice using `set_notice_status` or wrap your hook with `with_error_notice`, then all the errors will be catched and send displayed as a notice on the admin UI.

## Features

 - [x] Gutenberg notices
 - [ ] Classic editor notices

## Installation

```
composer require koko-ng/admin-notice
```

## Examples

```php
require 'vendor/autoload.php';

add_action('acf/save_post', \Admin_Notice\with_error_notice( 'my_acf_save_post') );
function my_acf_save_post( $post_id ) {

  // You can use `\Admin_Notice\Exception` to set an notice level.
  throw new \Admin_Notice\Exception("Error message", 0, \Admin_Notice\NoticeLevels::Warning);
}
```
