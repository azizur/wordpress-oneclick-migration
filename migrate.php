<?php
/*
 * WordPress OneClick Migration
 * This script will update site information when moving WordPress sites from server/site to server/site.
 *
 * @version: 1.4
 * @author: Azizur Rahman
 * Twitter: @azizur
 * Website: http://azizur-rahman.co.uk
 *
 * Copyright (C) 2011 Azizur Rahman (ProDevStudio)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */

if ( !defined('ABSPATH') )
        define('ABSPATH', dirname(__FILE__) . '/');

// sanity checks
if (!is_readable(ABSPATH . 'wp-config.php') or !is_readable(ABSPATH . 'wp-includes/functions.php')) {
    echo('wp-config.php or WordPress functions files was not found.');
    echo('Are you sure you have uploaded all the core wordpress files to this server');
    exit();
} else {
    // Load WordPress config and functions
    require_once ABSPATH . 'wp-config.php';
    require_once ABSPATH . 'wp-includes/functions.php';
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
            update_site_options($option_value, $old_url, $new_url);
        }

        // attempt to unserialize option_value
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

    // ignore _site_transient_update_*
    if(is_object($data)){
        return $data;
    }

    foreach ($data as $key => $val) {
        if (is_array($val)) {
                $data[$key] = update_serialized_options($val, $old_url, $new_url);
        } else {
            if (!strstr($val, $old_url)) {
                continue;
            }
            $data[$key] = str_replace($old_url, $new_url, $val);
        }
    }
    return $data;
}

/**
 * self_destruct -- self destruct?
 *
 * @param boolean $runmeonce
 * @return type boolean or string
 */
function self_destruct($runmeonce) {
    $return = false;
    if($runmeonce) {
        if( is_writable(__FILE__) && unlink(__FILE__) ){
            $return = '<p id="selfdestruct" class="failed"><strong>Successfully deleted</strong> <br /><code>'.__FILE__.'</code><br />';
            $return .= 'For security reason please please check it manually.</p>';
        } else {
            $return = '<p id="selfdestruct" class="failed"><strong>Unable to delete</strong> <br /><code>'.__FILE__.'</code><br />';
            $return .= 'For security reason please delete it manually.</p>';
        }
    }
    return $return;
}

// Get posted data
$old_url = (isset($_POST['old_url'])) ? ($_POST['old_url']) : ( site_url() ); //('url');
$new_url = (isset($_POST['new_uri'])) ? ($_POST['new_uri']) : ( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] );

// have we already migrated?
$migrated = (0 == strcmp($old_url, $new_url))?true:false;

// manually force it?
$migrated = (isset($_REQUEST['forced']) && $_REQUEST['forced'])?false:$migrated;

// check if we need to migrate?
if ($migrated) {
    header('Location: '.$new_url);
    exit();
}

// allow multiple runs?
$runmeonce = false;

// Have we been pleased with a POST?
if (isset($_POST['submit']) and !$migrated) {

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
	
	// update user meta just incase we have database prefix changes
	$user_meta_keys = array('capabilities','user_level','user-settings','user-settings-time','dashboard_quick_press_last_post_id');
	foreach($user_meta_keys as $meta_key) {
		$usermeta = "UPDATE $wpdb->usermeta
						SET
							meta_key = '{$wpdb->prefix}$meta_key'
						WHERE
							meta_key LIKE '%".$meta_key."';";
		$result = $wpdb->query( $usermeta );
	}
	
	// update user roles options just incase we have database prefix changes
	$user_roles = "UPDATE $wpdb->options
					SET
						option_name = '{$wpdb->prefix}user_roles'
					WHERE
						option_name LIKE '%user_roles';";
	$result = $wpdb->query( $user_roles );
	
	// TODO: possbly check custom tables made by plugins

    // we have migrate all the core data
    $migrated = true;

    // only run me once
    if ( isset($_POST['runme']) ) {
        $runmeonce = true;
    }
}
?>
<!doctype html>
<html>
    <head>
        <title>WordPress OneClick Migration</title>
        <link rel='stylesheet' id='login-css'  href='/wp-admin/css/login.css' type='text/css' media='all' />
        <link rel='stylesheet' id='colors-fresh-css'  href='/wp-admin/css/colors-fresh.css' type='text/css' media='all' />
        <style>
            #migrate { margin: 7em auto; width: 480px; }
            h1 a { width: 486px; }
            #intro, #status, #selfdestruct { padding: 10px;
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
            #intro, #notice, #status, #selfdestruct { text-align: center; }
            #intro strong, #notice strong {color: #21759B;}
            #notice { margin: 0 0 0 8px; padding: 16px;text-shadow: 0 1px 0 #FFFFFF;}

            input[type="checkbox"] {
                background: none repeat scroll 0 0 #FBFBFB;
                border: 1px solid #E5E5E5;
                font-size: 24px;
                margin-bottom: 16px;
                margin-right: 6px;
                margin-top: 6px;
                margin-left: 6px;
                padding: 6px 4px;
                /*width: 10%;*/
            }
            form p {
                margin-bottom: 10px;
            }
            #status { background-color: #e7edf1; }
            #selfdestruct { background-color: #fee; }
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
                <p id="intro"><strong>WordPress OneClick Migration</strong><br />This script will update site information on your new site.</p>
                <?php if($migrated) { ?>
                <p id="status" class="migrated"><strong>Migration complete.</strong><br /><br /><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('Back to %s &rarr;'), get_bloginfo('title', 'display')); ?></a></p>
                <?php
                if($runmeonce) {
                    echo self_destruct($runmeonce);
                } ?>
                <?php } else { ?>
                <p>
                    <label>Old URL<br />
                        <input type="text" name="old_url" id="old_url" class="input" value="<?php echo $old_url; ?>" size="20" tabindex="10" /></label>
                </p>
                <p>

                    <label>New URL<br />
                        <input type="text" name="new_uri" id="new_uri" class="input" value="<?php echo $new_url; ?>" size="20" tabindex="20" /></label>
                </p>
                <p class="runmeonce">
                    <label>Delete after migration?<br />
                        <input name="runme" type="checkbox" id="runme" value="runonce" tabindex="90" /> Yes, Please</label>
                </p>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button-primary" value="Update Site Information" tabindex="100" />
                    <input type="hidden" name="forced" value="<?php echo (isset($_REQUEST['forced'])?'1':'0'); ?>" />
                </p>
                <?php } ?>
            </form>
                <p id="notice"><strong>WordPress OneClick Migration</strong> Copyright (C) 2011 <a href="http://azizur-rahman.co.uk/?utm_source=wordpress-oneclick-migration&utm_medium=github&utm_campaign=wordpress-oneclick-migration">Azizur Rahman</a><br /><br />This program comes with ABSOLUTELY NO WARRANTY; This is free software, and you are welcome to redistribute it under certain conditions;<br /><strong><a href="http://www.gnu.org/licenses/gpl-3.0.html">GPLv3</a></strong></p>

        </div>
        <p id="backtoblog"><a href="<?php bloginfo('url'); ?>/" title="<?php _e('Are you lost?') ?>"><?php printf(__('&larr; Back to %s'), get_bloginfo('title', 'display')); ?></a></p>

        <!-- javascripts -->
        <script type='text/javascript' src='/wp-includes/js/jquery/jquery.js?'></script>
        <!-- /javascripts -->
    </body>
</html>