<?php

/*
  Plugin Name: Test KWA Plugin
  Description: Testing Dynamic Gutenberg blocks.
  Version: 1.0
  Author: UCSC
  Author URI: https://www.ucsc.edu/
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class TestKWAPlugin
{
  function __construct()
  {
    add_action('init', array($this, 'adminAssets'));
    add_action('admin_menu', array($this, 'settingsLink'));
    add_action('admin_init', array($this, 'settings'));
    add_action("network_admin_menu", array($this, 'networkSettingsLink'));
    add_action('network_admin_edit_ucscplugin', array($this,'networkSaveSettings'));
    add_action('network_admin_notices', array($this, 'networkSettingsNotifications'));
  }

  function networkSettingsNotifications() {
    if (isset($_GET['page']) && $_GET['page'] == 'test-kwa-network-plugin' && isset($_GET['updated'])) {
      echo '<div id="message" class="updated notice is-dismissible"><p>Settings updated.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
    }
  }

  function networkSaveSettings() {
    check_admin_referer('ucscplugin-validate'); // Nonce security check

    update_site_option('tkp_omdb_api_key', $_POST['tkp_omdb_api_key']);

    wp_redirect(add_query_arg(
      array(
        'page' => 'test-kwa-network-plugin',
        'updated' => true
      ),
      network_admin_url('settings.php')
    ));

    exit;
  }

  function networkSettingsLink()
  {
    add_submenu_page(
      'settings.php', // Parent element
      'Test KWA Network Plugin', // Text in browser title bar
      'Test KWA Network Plugin', // Text to be displayed in the menu.
      'manage_options', // Capability
      'test-kwa-network-plugin', // Page slug, will be displayed in URL
      array($this, 'networkSettingsPage') // Callback function which displays the page
    );
  }

  function networkSettingsPage()
  {
    echo '<div class="wrap">
		<h1>Test KWA Network Plugin Settings</h1>
		<form method="post" action="edit.php?action=ucscplugin">';
    wp_nonce_field('ucscplugin-validate');
    echo '
			<table class="form-table">
				<tr>
					<th scope="row"><label for="tkp_omdb_api_key">OMDb API Key</label></th>
					<td>
						<input name="tkp_omdb_api_key" class="regular-text" type="text" id="tkp_omdb_api_key" value="' . esc_attr(get_site_option('tkp_omdb_api_key')) . '" />
					</td>
				</tr>
			</table>';
    submit_button();
    echo '</form></div>';
  }

  function settings()
  {
    add_settings_section('tkp_first_section', null, null, 'test-kwa-plugin-settings-page');
    add_settings_field('tkp_omdb_api_key', 'OMDb API Key', array($this, 'apikeyHTML'), 'test-kwa-plugin-settings-page', 'tkp_first_section');
    register_setting('testkwaplugin', 'tkp_omdb_api_key', array('sanitize_callback' => 'sanitize_text_field', 'default' => ''));
  }

  function apikeyHTML()
  { ?>
    <input type="text" name="tkp_omdb_api_key" value="<?php echo esc_attr(get_option('tkp_omdb_api_key')) ?>" />
  <?php }

  function settingsLink()
  {
    add_options_page('Test KWA Plugin Settings', 'Test KWA Plugin', 'manage_options', 'test-kwa-plugin-settings-page', array($this, 'settingsPageHTML'));
  }

  function settingsPageHTML()
  { ?>
    <div class="wrap">
      <h1>Test KWA Plugin Settings</h1>
      <form action="options.php" method="POST">
        <?php
        settings_fields('testkwaplugin');
        do_settings_sections('test-kwa-plugin-settings-page');
        submit_button();
        ?>
      </form>
    </div>
    <?php }

  function adminAssets()
  {
    wp_register_script('testkwaplugin', plugin_dir_url(__FILE__) . 'build/index.js', array('wp-blocks', 'wp-element', 'wp-components'));
    register_block_type('testkwaplugin/campus-press-test', array(
      'editor_script' => 'testkwaplugin',
      'render_callback' => array($this, 'theHTML')
    ));
  }

  function getCachedMovie($title) {
    $lowerTitle = strtolower($title);
    if (is_multisite()){
      $objBody = get_site_transient("omdb_" . $lowerTitle);
    } else {
      $objBody = get_transient("omdb_" . $lowerTitle);
    }
    if (!$objBody) {
      // Look for api key at network level settings
      $apikey = get_site_option('tkp_omdb_api_key');
      if (!strlen($apikey)) {
        // if no key is found at network level, get key from site level
        $apikey = get_option('tkp_omdb_api_key');
      }
      $response = wp_remote_get("https://www.omdbapi.com/?t={$lowerTitle}&apikey={$apikey}");
      $body = wp_remote_retrieve_body($response);
      $objBody = json_decode($body, true);
      if (is_multisite()) {
        set_site_transient("omdb_" . $lowerTitle, $objBody, WEEK_IN_SECONDS);
      } else {
        set_transient("omdb_" . $lowerTitle, $objBody, WEEK_IN_SECONDS);
      }
    }

    return $objBody;
  }

  function theHTML($attributes)
  {
    $objBody = $this->getCachedMovie($attributes['movieTitleSearch']);

    if ($objBody['Response'] === 'True') {
      ob_start(); ?>
      <div class="wrap">
        <h2><?php echo $objBody["Title"] ?></h2>
        <img alt="Movie Poster" src="<?php echo $objBody["Poster"] ?>" />
        <h3>Directed by: <?php echo $objBody["Director"] ?></h3>
        <h3>Staring: <?php echo $objBody["Actors"] ?></h3>
        <br>
        <h4><?php echo $objBody["Released"] ?> - <?php echo $objBody["Runtime"] ?></h4>
        <h5><?php echo $objBody["Rated"] ?> - <?php echo $objBody["Genre"] ?></h5>
        <br>
        <h6><?php
            foreach ($objBody['Ratings'] as $key => $rating) {
              echo "{$rating['Source']} {$rating['Value']} - ";
              end($objBody['Ratings']);
              if ($key === key($objBody['Ratings'])) {
                echo "{$rating['Source']} {$rating['Value']} ";
              }
            }
            ?></h6>
        <br>
        <p>Plot: <?php echo $objBody["Plot"] ?></p>
      </div>
      <?php return ob_get_clean();
    } else {
      ob_start(); ?>
      <h2>Movie NOT found "<?php echo $attributes['movieTitleSearch']; ?>"</h2>
      <?php return ob_get_clean();
    }
  }
}

$testKWAPlugin = new TestKWAPlugin();
