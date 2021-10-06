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

  function theHTML($attributes)
  {
    $apikey = get_option('tkp_omdb_api_key');
    $response = wp_remote_get("https://www.omdbapi.com/?t={$attributes['movieTitleSearch']}&apikey={$apikey}");
    $body = wp_remote_retrieve_body($response);
    $objBody = json_decode($body, true);

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
