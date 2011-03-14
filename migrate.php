<?php
/*
 * WordPress Migration
 *
 * version: 1.0
 *
 * Author: Azizur Rahman
 * Twitter: @azizur
 * Website: http://azizur-rahman.co.uk
 *
 *
 * Script usage:
 * 1) Upload this script to you WordPress root directory (where wp-config.php file is located)
 * 2) Browse to http://your-newly-migrated.com/migrate.php
 * 3) Follow the on screen instructions
 */


// sanity check
if (!is_readable('wp-config.php') or !is_readable('wp-includes/functions.php')) {
    echo('wp-config.php or WordPress functions files was not found.');
    echo('Are you sure you have uploaded all the core wordpress files to this server');
    die;exit;
} else {
    // Load WordPress config and functions
    require_once 'wp-config.php';
    require_once 'wp-includes/functions.php';
}

// set up error reporting
defined('WP_DEBUG') or define('WP_DEBUG', true);

/**
 * update_site_options - migrate an array of options
 *
 * @param array $options options to migrate
 * @param type $old_url migrate from url
 * @param type $new_url migrate to url
 */
function update_site_options(array $options, $old_url, $new_url) {
    foreach ($options as $option_name => $option_value) {

        if (FALSE === strpos($option_value, $old_url)) {
            continue;
        }

        if (is_array($option_value)) {
            update_site_options($option_value);
        }


        // attempt to unserialize option_value
        //$unserialized = @unserialize($option_value);

        if(!is_serialized($option_value)) {
            $newvalue = str_replace($old_url, $new_url, $option_value);
        } else {
            $newvalue = update_serialized_options(maybe_unserialize($option_value), $old_url, $new_url);
        }

        update_option($option_name, $newvalue);
    }
}

/**
 * update_serialized_options -- recursively update site options values (usally stored as serealised data)
 *
 * @param array $data
 * @param type $old_url url to search for
 * @param type $new_url url to replace with
 * @return type array
 */
function update_serialized_options($data, $old_url, $new_url) {
    foreach ($data as $key => $val) {

        if (is_array($val)) {
            $data[$key] = update_serialized_options($val, $old_url, $new_url);
        } else {
            $found = strstr($val, $old_url);
            if (!$found) {
                continue;
            }
            $data[$key] = str_replace($old_url, $new_url, $val);
        }
    }
    return $data;
}

// Get posted data
$old_url = (isset($_POST['old_url'])) ? ($_POST['old_url']) : ( site_url() ); //('url');
$new_url = (isset($_POST['new_uri'])) ? ($_POST['new_uri']) : ( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] );

// allow multiple runs
$runmeonce = false;

// Have we been pleased with a POST?
if (isset($_POST['submit'])) {

    // site options
    $siteopts = wp_load_alloptions();
    $result = update_site_options($siteopts, $old_url, $new_url);

    // Permalinks
    $permalinks = "UPDATE $wpdb->posts SET guid = replace(guid, '" . $old_url . "/','" . $new_url . "/')";
    $result     = $wpdb->query( $permalinks );

    // Post content
    $content = "UPDATE $wpdb->posts SET post_content = replace(post_content, '" . $old_url . "/','" . $new_url . "/')";
    $result = $wpdb->query( $content );

    // Post excerpts
    $excerpts = "UPDATE $wpdb->posts SET post_excerpt = replace(post_excerpt, '" . $old_url . "/','" . $new_url . "/')";
    $result = $wpdb->query( $excerpts );

    // Postmeta
    $postmeta = "UPDATE $wpdb->postmeta SET meta_value = replace(meta_value, '" . $old_url . "/','" . $new_url . "/')";
    $result = $wpdb->query( $postmeta );

    $message = '<br /><p class="path"><strong>Migration complete:</strong> please delete this script.</p>';

    // only run me once
    if (isset($_POST['runme']) and strcmp('once', $_POST['runme'])) {
        $runmeonce = true;
    }
}
?>
<!doctype html>
<html>
    <head>
        <title>WordPress OneClick DB Migration</title>

        <link rel='stylesheet' id='login-css'  href='/wp-admin/css/login.css' type='text/css' media='all' />
        <link rel='stylesheet' id='colors-fresh-css'  href='/wp-admin/css/colors-fresh.css' type='text/css' media='all' />
        <?php
//        wp_admin_css('login', true);
//        wp_admin_css('colors-fresh', true);
        ?>
        <style>
            #migrate { margin: 7em auto; width: 480px; }
            h1 a { width: 486px; }
            #intro { padding: 10px;
                     background: #FBFBFB;
                     border: 1px solid #E5E5E5;
                     color: #777777;
                     font-size: 13px;
                     margin-bottom: 16px;
                     margin-right: 6px;
                     margin-top: 0px;
                     padding: 10px 3px;
                     width: 97%;
                     text-shadow: 0 1px 0 #FFFFFF;
            }
            #intro, #notice { text-align: center; }
            #intro strong, #notice strong {color: #21759B;}
            #notice { margin: 0 0 0 8px; padding: 16px;text-shadow: 0 1px 0 #FFFFFF;}
        </style>
        <meta name='robots' content='noindex,nofollow' />
    </head>
    <body class="migrate">
        <div id="migrate">

            <?php if (!is_multisite()) { ?>
                <h1><a href="<?php echo apply_filters('login_headerurl', 'http://wordpress.org/'); ?>" title="<?php echo apply_filters('login_headertitle', __('Powered by WordPress')); ?>"><?php bloginfo('name'); ?></a></h1>
            <?php } else { ?>
                <h1><a href="<?php echo apply_filters('login_headerurl', network_home_url()); ?>" title="<?php echo apply_filters('login_headertitle', $current_site->site_name); ?>"><span class="hide"><?php bloginfo('name'); ?></span></a></h1>
            <?php } ?>


            <form name="migrateform" id="migrateform" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                <p id="intro"><strong>WordPress OneClick Migration</strong><br />This script will update site information on your new domain.</p>


                <p>
                    <label>Old URL<br />
                        <input type="text" name="old_url" id="old_url" class="input" value="<?php echo $old_url; ?>" size="20" tabindex="10" /></label>
                </p>
                <p>

                    <label>New URL<br />
                        <input type="text" name="new_uri" id="new_uri" class="input" value="<?php echo $new_url; ?>" size="20" tabindex="20" /></label>
                </p>
                <p class="runmeonce">
                    <label><input name="runme" type="checkbox" id="runme" value="once" tabindex="90" /> Delete Me</label>
                </p>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button-primary" value="Update Site Information" tabindex="100" />
                    <input type="hidden" name="redirect_to" value="<?php echo $new_url; ?>" />
                </p>
            </form>


            <p id="notice"><strong>WordPress OneClick Migration</strong> v1.0 by <a href="https://github.com/azizur">Azizur Rahman</a></p>

        </div>
        <p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&larr; Back to %s'), get_bloginfo('title', 'display')); ?></a></p>

        <!-- javascripts -->
        <script type='text/javascript' src='/wp-includes/js/jquery/jquery.js?'></script>
        <!-- /javascripts -->
    </body>
</html>
<?php
if ($runmeonce) {
    unlink(__FILE__);
    exit;
}
?>
