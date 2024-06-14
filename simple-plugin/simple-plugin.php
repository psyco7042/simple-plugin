<?php
/**
 * Plugin Name: Simple Plugin
 * Description: A simple contact form that creates an alert for the admin in the enquiry panel
 * Author: Priyam
 * Author Uri: #
 * Version: 1.0.0
 * Text Domain: Simple-Plugin  
 */

if(!defined('ABSPATH')){
    echo 'get out this page is not for you';
    exit;
}

class simplePlugin{

    public function __construct(){

        // Create custom post type
        add_action('init', array($this, 'create_custom_post_type'));

        // Load assets (CSS, JS, etc.)
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));

        // Add Shortcode
        add_shortcode('contact-form', array($this, 'load_shortcode'));

        // Load JavaScript in footer
        add_action('wp_footer', array($this, 'load_scripts'));

        // Register REST API endpoint
        add_action('rest_api_init', array($this, 'register_rest_api'));
    }

    public function create_custom_post_type(){
        $args = array(
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'custom-fields'), // Enable custom fields
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'labels' => array(
                'name' => 'Contact Form',
                'singular_name' => 'Contact Form Entry',
            ),
            'menu_icon' => 'dashicons-text-page',
        );

        register_post_type('simple-plugin', $args);
    }

    public function load_assets(){
        wp_enqueue_style(
            'simple-contact',
            plugin_dir_url(__FILE__) . 'assets/css/simple-plugin.css',
            array(),
            1,
            'all'
        );

        wp_enqueue_script(
            'simple-contact',
            plugin_dir_url(__FILE__) . 'assets/js/simple-plugin.js',
            array('jquery'),
            1,
            true
        );
    }

    public function load_shortcode(){
        ?>
        <div class="simple-plugin">
            <h1 class="form-head">Send us an email</h1>
            <p>Please fill out the below form</p>
            <form id="simple-plugin_form" method='POST' action='simple-plugin-form/v1/send-email'>
                <div class="form-group">
                    <input name="name" type="text" placeholder="Name" class="form-control">
                </div>
                <div class="form-group">
                    <input name="email" type="email" placeholder="abc@efg.com" class="form-control">
                </div>
                <div class="form-group">
                    <input name="phone" type="tel" placeholder="Phone No." class="form-control">
                </div>
                <div class="form-group">
                    <textarea name="message" placeholder="Write your message"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-success btn-block">Submit</button>
                </div>
            </form>
        </div>
        <?php
    }

    public function load_scripts(){
        ?>
        <script>
            var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
            (function($){
                $('#simple-plugin_form').submit(function(event){
                    event.preventDefault();
                    
                    var form = $(this).serialize();
                    console.log(form);

                    $.ajax({
                        method: 'POST',
                        url: '<?php echo get_rest_url(null, 'simple-plugin-form/v1/send-email'); ?>',
                        headers: { 'X-WP-Nonce': nonce },
                        data: form
                    }).done(function(response){
                        console.log(response);
                    }).fail(function(jqXHR, textStatus, errorThrown){
                        console.error('Error: ' + textStatus, errorThrown);
                    });

                });
            })(jQuery);
        </script>
        <?php
    }

    public function register_rest_api(){
        register_rest_route('simple-plugin-form/v1', '/send-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_simple_plugin')
        ));
    }

    public function handle_simple_plugin($data){
        $headers = $data->get_headers();
        $params = $data->get_params();

        $nonce = $headers['x_wp_nonce'][0];

        if(!wp_verify_nonce($nonce, 'wp_rest')){
            return new WP_REST_Response('Message not sent', 422);
        }

        $post_id = wp_insert_post([
            'post_type' => 'simple-plugin',
            'post_title' => 'Contact Enquiry',
            'post_status' => 'publish'
        ]);

        if($post_id){
            // Save form data as post meta
            update_post_meta($post_id, 'name', sanitize_text_field($params['name']));
            update_post_meta($post_id, 'email', sanitize_email($params['email']));
            update_post_meta($post_id, 'phone', sanitize_text_field($params['phone']));
            update_post_meta($post_id, 'message', sanitize_textarea_field($params['message']));
            
            return new WP_REST_Response('Thank you for your email', 200);
        } else {
            return new WP_REST_Response('Failed to create post', 500);
        }
    }
}

new simplePlugin();
