<?php

$esg_obj = esg_module_admin::get_instance();
add_action('init', array($esg_obj, 'init'));

class esg_module_admin extends esg_base {
    private $page = 'esg_module_admin';
    
    /**
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'setup_menus' ] );
        add_action( 'admin_init', [ $this, 'register_admin_settings' ] );
        add_action( 'admin_footer-edit.php', [ $this, 'render_upload_form' ] );
        add_action( 'admin_footer-post.php', [ $this, 'render_upload_form' ] );
//        add_action( 'admin_footer-post-new.php', [ $this, 'render_upload_form' ] );
        add_filter( 'tiny_mce_before_init', [ $this, 'change_tinymce_settings' ] );
        add_filter( 'get_sample_permalink_html', [ $this, 'remove_gallery_permalink' ], 10, 2 );
        add_filter( 'post_row_actions', [ $this, 'change_gallery_row_actions' ], 10, 2 );
    }

    /**
     * Do some set up
     */
    function render_upload_form() {
        global $post;
        $esg_obj = esg_cpt::get_instance();
        
        if ( empty( $post->ID )
                || empty( $_REQUEST['post'] )
                || get_post_type() != $esg_obj->post_type ) {
            return;
        }
        
        $sc = $esg_obj->generate_upload_shortcode( $post->ID, [ 'ctx' => 'admin'] );
        echo do_shortcode($sc);
    }

    /**
     * Do some set up
     */
    function on_plugin_activate() {
    }

    /**
     * Do some nothing.
     */
    function on_plugin_deactivate() {
    }

    /**
     * Do some cleanup
     */
    function on_plugin_uninstall() {
    }
            
    function setup_menus() {
        $main_page_hook = add_menu_page(
            'Event Stream Gallery',
            'Event Stream Gallery',
            'manage_options',
            $this->page,
            null, //[ $this, 'settings_page_html' ],
            plugin_dir_url(ESG_CORE_BASE_PLUGIN) . '/assets/images/icon.png',
            
            // https://developer.wordpress.org/reference/functions/add_menu_page/
            81 // position the menu after Settings on WP single set up
        );
      
        add_submenu_page($this->page, 'Galleries', 'Galleries', 'manage_options', 'edit.php?post_type=esg_gallery');
        add_submenu_page($this->page, 'Add New Gallery', 'Add New Gallery', 'manage_options', 'post-new.php?post_type=esg_gallery');
        add_submenu_page($this->page, 'Categories', 'Categories', 'manage_options', 'edit-tags.php?taxonomy=esg_cat');
        add_submenu_page($this->page, 'Tags', 'Tags', 'manage_options', 'edit-tags.php?taxonomy=esg_tag' );
        add_submenu_page($this->page, 'Settings', 'Settings', 'manage_options', 'esg_admin_settings', [ $this, 'settings_page_html' ]);
        add_submenu_page($this->page, 'Pro Version', '<span style="color:yellow;">Pro Version</span>', 'manage_options', 'esg_admin_pro_ver', [ $this, 'pro_ver_page_html' ]);
        add_submenu_page($this->page, 'Help', 'Help', 'manage_options', 'esg_admin_help', [ $this, 'help_page_html' ]);
        remove_submenu_page( $this->page, $this->page); // The top menu is also duplicated as a submenu so we'll remove it.
        
        // when plugins are show add a settings link near my plugin for a quick access to the settings page.
        add_filter('plugin_action_links', array($this, 'add_plugin_settings_link'), 10, 2);
        
        // @tood see https://codex.wordpress.org/Function_Reference/get_current_screen
//        $main_page_hook
        
        add_filter( 'parent_file', [ $this, 'custom_tax_highlight_hack' ] );
    }

    /**
     * When adding a custom taxonomy to a custom menu it doesn't show up.
     * @return str
     * @see http://stackoverflow.com/questions/32984834/wordpress-show-taxonomy-under-custom-admin-menu
     */
    function custom_tax_highlight_hack( $parent_file ) {
        global $current_screen;

        $taxonomy = $current_screen->taxonomy;

        if ( $taxonomy == 'esg_tag' || $taxonomy == 'esg_cat' ) {
            $parent_file = 'esg_module_admin';
        }

        return $parent_file;
    }
    
    // Add the ? settings link in Plugins page very good
    function add_plugin_settings_link($links, $file) {
        if ($file == plugin_basename(ESG_CORE_BASE_PLUGIN)) {
            //$prefix = 'options-general.php?page=' . dirname(plugin_basename(ESG_CORE_BASE_PLUGIN)) . '/';
            $prefix = $this->plugin_admin_url_prefix . '/';

            $help_link = admin_url( 'admin.php?page=esg_admin_help' );
            $help_link_html = "<a href=\"{$help_link}\">" . __("Help", 'esg') . '</a>';
            
            $settings_link = admin_url( 'admin.php?page=esg_admin_settings' );
            $settings_link_html = "<a href=\"{$settings_link}\">" . __("Settings", 'esg') . '</a>';
            
            $pro_link = admin_url( 'admin.php?page=esg_admin_pro_ver' );
            $pro_link_html = "<a href=\"{$pro_link}\">" . __("Pro Version", 'esg' ) . '</a>';

            array_unshift($links, $pro_link_html);
            array_unshift($links, $help_link_html);
//            array_unshift($links, $settings_link_html);
        }

        return $links;
    }
    
    public function validate_settings( $data ) {
        return $data;
    }
    
    public function get_options() {
        static $opts = null;

        if (!is_null($opts)) {
            return $opts;
        }
        
        $defaults = array(
            'gallery_thumbnail_src' => 'thumbnail',
        );

        $opts = get_option('esg_options');
        $opts = (array) $opts;
        $opts = array_merge($defaults, $opts);

        return $opts;
    }
    
    public function register_admin_settings() {
        register_setting('esg_settings', 'esg_options', [ $this, 'validate_settings' ] );
    }

    function settings_page_html() {
        $opts = $this->get_options();
        ?>

        <div class="wrap orbisius_limit_logins_admin_wrapper orbisius_limit_logins_container">

            <div id="icon-options-general" class="icon32"></div>
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (!empty($_REQUEST['settings-updated'])) : ?>
                <div class="updated"><p>
                       <strong><?php _e( 'Settings saved.', 'esg' ); ?></strong>
                </p></div>
            <?php endif; ?>

            <div id="poststuff">

                <div id="post-body" class="metabox-holder columns-2">

                    <!-- main content -->
                    <div id="post-body-content">

                        <div class="meta-box-sortables ui-sortable">

                            <div class="postbox">
                                <div class="inside">
                                    <form method="post" action="options.php">
                                        <?php settings_fields('esg_settings'); ?>
                                        <table class="form-table">
                                            <tr valign="top">
                                                <th scope="row">Gallery Thumbnail Image</th>
                                                <td>
                                                    <?php
                                                    $gallery_api = esg_gallery::get_instance();
                                                    $th_opts = $gallery_api->get_thumbnail_options();
                                                    echo esg_html_util::html_select( 'esg_options[gallery_thumbnail_src]', $opts['gallery_thumbnail_src'], $th_opts );
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>

                                        <p class="submit">
                                            <input type="submit" class="button-primary" 
                                                   value="<?php _e('Save Changes') ?>" />
                                        </p>
                                    </form>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->

                            <div class="postbox">
                                <h3><span>Share</span></h3>
                                <div class="inside">
                                    <?php
                                        $plugin_data = get_plugin_data(ESG_CORE_BASE_PLUGIN);

                                        $app_link = urlencode($plugin_data['PluginURI']);
                                        $app_title = urlencode($plugin_data['Name']);
                                        $app_descr = urlencode($plugin_data['Description']);
                                    ?>
                                    <p>
                                        <!-- AddThis Button BEGIN -->
                                        <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                                            <a class="addthis_button_facebook" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_twitter" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_email" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <!--<a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>-->
                                            <!--<a class="addthis_button_myspace" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_google" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_digg" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_delicious" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                            <a class="addthis_button_favorites" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>-->
                                            <a class="addthis_button_compact"></a>
                                        </div>
                                        <!-- The JS code is in the footer -->

                                        <script type="text/javascript">
                                        var addthis_config = {"data_track_clickback":true};
                                        var addthis_share = {
                                          templates: { twitter: 'Check out {{title}} @ {{lurl}} #WordPress #plugin' }
                                        }
                                        </script>
                                        <!-- AddThis Button START part2 -->
                                        <script type="text/javascript" src="//s7.addthis.com/js/250/addthis_widget.js#pubid=lordspace"></script>
                                        <!-- AddThis Button END part2 -->
                                    </p>
                                </div> <!-- .inside -->

                            </div> <!-- .postbox -->
                          
                        </div> <!-- .meta-box-sortables .ui-sortable -->

                    </div> <!-- post-body-content -->

                    <!-- sidebar -->
                    <div id="postbox-container-1" class="postbox-container">

                        <div class="meta-box-sortables">
                            <div class="postbox"> <!-- quick-contact -->
                                <?php
                                $current_user = wp_get_current_user();
                                $email = empty($current_user->user_email) ? '' : $current_user->user_email;
                                $quick_form_action = is_ssl()
                                        ? 'https://ssl.orbisius.com/apps/quick-contact/'
                                        : 'http://apps.orbisius.com/quick-contact/';

                                if (!empty($_SERVER['DEV_ENV'])) {
                                    $quick_form_action = 'http://localhost/projects/quick-contact/';
                                }
                                ?>
                                <h3><span>Quick Question or Suggestion</span></h3>
                                <div class="inside">
                                    <div>
                                        <form method="post" action="<?php echo $quick_form_action; ?>" target="_blank">
                                            <?php
                                                global $wp_version;
                                                $plugin_data = get_plugin_data(ESG_CORE_BASE_PLUGIN);

                                                $hidden_data = array(
                                                    'site_url' => site_url(),
                                                    'wp_ver' => $wp_version,
                                                    'first_name' => $current_user->first_name,
                                                    'last_name' => $current_user->last_name,
                                                    'product_name' => $plugin_data['Name'],
                                                    'product_ver' => $plugin_data['Version'],
                                                    'woocommerce_ver' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : 'n/a',
                                                );
                                                $hid_data = http_build_query($hidden_data);
                                                echo "<input type='hidden' name='data[sys_info]' value='$hid_data' />\n";
                                            ?>
                                            <textarea class="widefat" id='orbisius_limit_logins_msg' name='data[msg]' required></textarea>
                                            <br/>Your Email: <input type="text" class=""
                                                   name='data[sender_email]' placeholder="Email" required="required"
                                                   value="<?php echo esc_attr($email); ?>"
                                                   />
                                            <br/><input type="submit" class="button-primary" value="<?php _e('Send') ?>"
                                                        onclick="try { if (jQuery('#orbisius_limit_logins_msg').val().trim() == '') { alert('Enter your message.'); jQuery('#orbisius_limit_logins_msg').focus(); return false; } } catch(e) {};" />
                                            <br/>
                                            What data will be sent
                                            <a href='javascript:void(0);'
                                                onclick='jQuery(".orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent").toggle();'>(show/hide)</a>
                                            <div class="hide hide-if-js orbisius-price-changer-woocommerce-quick-contact-data-to-be-sent">
                                                <textarea class="widefat" rows="4" readonly disabled="disabled"><?php
                                                foreach ($hidden_data as $key => $val) {
                                                    if (is_array($val)) {
                                                        $val = var_export($val, 1);
                                                    }

                                                    echo "$key: $val\n";
                                                }
                                                ?></textarea>
                                            </div>
                                        </form>
                                    </div>
                                </div> <!-- .inside -->

                            </div> <!-- .postbox --> <!-- /quick-contact -->
                            
                            <?php if (0) : ?>
                                <!-- Newsletter-->
                                <div class="postbox">
                                    <h3><span>Newsletter</span></h3>
                                    <div class="inside">
                                        <!-- Begin MailChimp Signup Form -->
                                        <div id="mc_embed_signup">
                                            <?php
                                                $current_user = wp_get_current_user();
                                                $email = empty($current_user->user_email) ? '' : $current_user->user_email;
                                            ?>

                                            <form action="//WebWeb.us2.list-manage.com/subscribe/post?u=005070a78d0e52a7b567e96df&amp;id=1b83cd2093" method="post"
                                                  id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank">
                                                <input type="hidden" value="settings" name="SRC2" />
                                                <input type="hidden" value="<?php echo str_replace('.php', '', basename(ESG_CORE_BASE_PLUGIN));?>" name="SRC" />

                                                <span>Get notified about cool plugins we release</span>
                                                <!--<div class="indicates-required"><span class="app_asterisk">*</span> indicates required
                                                </div>-->
                                                <div class="mc-field-group">
                                                    <label for="mce-EMAIL">Email</label>
                                                    <input type="email" value="<?php echo esc_attr($email); ?>" name="EMAIL" class="required email" id="mce-EMAIL">
                                                </div>
                                                <div id="mce-responses" class="clear">
                                                    <div class="response" id="mce-error-response" style="display:none"></div>
                                                    <div class="response" id="mce-success-response" style="display:none"></div>
                                                </div>	<div class="clear"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button-primary"></div>
                                            </form>
                                        </div>
                                        <!--End mc_embed_signup-->
                                    </div> <!-- .inside -->
                                </div> <!-- .postbox -->
                                <!-- /Newsletter-->
                            <?php endif; ?>

                            <!-- Support options -->
                            <div class="postbox">
                                <h3><span>Support</span></h3>
                                <h3>
                                    <?php
                                        $plugin_data = get_plugin_data(ESG_CORE_BASE_PLUGIN);
                                        $product_name = trim($plugin_data['Name']);
                                        $product_page = trim($plugin_data['PluginURI']);
                                        $product_descr = trim($plugin_data['Description']);
                                        $product_descr_short = substr($product_descr, 0, 50) . '...';
                                        $product_descr_short .= ' #WordPress #plugin';

                                        $base_name_slug = basename(ESG_CORE_BASE_PLUGIN);
                                        $base_name_slug = str_replace('.php', '', $base_name_slug);
                                        $product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
                                        $product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

                                        $product_page_tweet_link = $product_page;
                                        $product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
                                    ?>
                                    <!-- Twitter: code -->
                                    <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
                                    <!-- /Twitter: code -->

                                    <!-- Twitter: Orbisius_Follow:js -->
                                        <a href="https://twitter.com/orbisius" class="twitter-follow-button"
                                           data-align="right" data-show-count="false">Follow @orbisius</a>
                                    <!-- /Twitter: Orbisius_Follow:js -->

                                    &nbsp;

                                    <!-- Twitter: Tweet:js -->
                                    <a href="https://twitter.com/share" class="twitter-share-button"
                                       data-lang="en" data-text="Check out <?php echo $product_name;?> #WordPress #plugin.<?php echo $product_descr_short; ?>"
                                       data-count="none" data-via="orbisius" data-related="orbisius"
                                       data-url="<?php echo $product_page_tweet_link;?>">Tweet</a>
                                    <!-- /Twitter: Tweet:js -->

                                    <br/>
                                    <span>
                                        <a href="<?php echo $product_page; ?>" target="_blank" title="[new window]">Product Page</a>
                                        |
                                        <a href="http://eventstreamgallery.com/contact-us/"
                                        target="_blank" title="[new window]">Contact Us</a>
                                    </span>
                                </h3>
                            </div> <!-- .postbox -->
                            <!-- /Support options -->
                            
                            <!-- Hire Us -->
                            <div class="postbox">
                                <h3><span>Hire Us</span></h3>
                                <div class="inside">
                                    Hire us to create a plugin/web/mobile app
                                    <br/><a href="//orbisius.com/page/free-quote/?utm_source=<?php echo str_replace('.php', '', basename(ESG_CORE_BASE_PLUGIN));?>&utm_medium=plugin-settings&utm_campaign=product"
                                       title="If you want a custom web/mobile app/plugin developed contact us. This opens in a new window/tab"
                                        class="button-primary" target="_blank">Get a Free Quote</a>
                                </div> <!-- .inside -->
                            </div> <!-- .postbox -->
                            <!-- /Hire Us -->

                        </div> <!-- .meta-box-sortables -->

                    </div> <!-- #postbox-container-1 .postbox-container -->

                </div> <!-- #post-body .metabox-holder .columns-2 -->

                <br class="clear">
            </div> <!-- #poststuff -->

        </div> <!-- .wrap -->
        <?php
    }
    
    function help_page_html() {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div>
                <h3>Getting started</h3>
                <pre>
<strong>Gallery Set up</strong>
The first step is to create a gallery by going to Event Stream Gallery > Add New Gallery.
Then you will need to create a page that will hold the gallery.
On the Galleries page you will see the exact shortcode that you need to use.
It will look like this [esg_gallery id="123"] where 123 is the gallery id.
Then you need to paste it on the page you want.

<strong>Uploads</strong>
In order to allow people to upload images to the gallery create a new page and use this shortcode:
[esg_upload id="123"]

Make sure you set a gallery upload password because anybody who can access that page will be able to upload photos.
Our Pro version will have many features and one of them will be to set a password.
                </pre>
            </div>
            
            <div>
                If you have any issues feel or questions free to reach us at: 
                <a href='mailto:support@site123.ca?subject=<?php echo urlencode( 'Event Stream Gallery Support' ); ?>'
                   target="_blank">support@site123.ca</a>
            </div>
            
            <h3>Options</h3>
            <textarea class="widefat" readonly rows="20"><?php 
                echo file_get_contents( ESG_CORE_BASE_DIR . '/data/shortcodes.txt' ); 
                ?></textarea>

        </div>
        <?php
    }
    
    function pro_ver_page_html() {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
   
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="uk-text-bold uk-text-primary">Need more functionality in your galleries?</h2>
                    <p class="uk-text-strong uk-text-large uk-text-success">Event Stream Gallery Pro will be available soon with cool features like:</p>
                    <div class="uk-promo-container uk-flex uk-flex-middle uk-flex-wrap">
                     <div class="uk-width-1-1 uk-width-medium-1-2 uk-width-large-1-3">
                    <ul class="uk-list uk-list-space">
                        <li><i class="uk-icon-check uk-text-success"></i> More gallery styles including dynamic/mosaic grids</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Custom folders</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Custom Image Sizes</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Additional grid columns (1-6 columns)</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Pagination, Infinate Scroll and Load More</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Assign a Unique Password for each gallery</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Add text or caption to images</li>
                        <li><i class="uk-icon-check uk-text-success"></i> Short/Tiny URLs for gallery upload links</li>
                    </ul>
                </div>
                <div class="uk-width-1-1 uk-width-medium-1-2 uk-width-large-2-3 uk-padding-15 uk-border-thin">
                <h3>Be the first to know!</h3>
                <p>Sign up below and we'll notify you when <strong>Event Stream Gallery Pro Addon</strong> is released!<br />
                We'll also send you a coupon code for <u>50% off!</u></p>
                <!-- ESG PRO VERSION NOTIFICATION FORM -->
                  <form id="ema_signup_form" target="_blank" action="https://madmimi.com/signups/subscribe/390976" accept-charset="UTF-8" method="post">
                      <div class="uk-flex">
                         <input name="utf8" type="hidden" value="✓"/>
                         <div class="mimi_field required">
                            <input id="signup_email" name="signup[email]" type="text" data-required-field="you@example.com" placeholder="you@example.com" class="uk-form-large uk-width-1-1"/>
                         </div>
                         <div class="mimi_field uk-text-bold">
                            <input type="submit" class="submit button-primary uk-button uk-button-large uk-button-success" value="Sign Up!" id="webform_submit_button" data-default-text="Sign Up!" data-submitting-text="Sending..." data-invalid-text="Oops! You forgot your Email address" data-choose-list="↑ Choose a list" data-thanks="Thank you!"/>
                         </div>
                      </div>
                  </form>
                
              <script type="text/javascript">
              (function(global) {
                function serialize(form){if(!form||form.nodeName!=="FORM"){return }var i,j,q=[];for(i=form.elements.length-1;i>=0;i=i-1){if(form.elements[i].name===""){continue}switch(form.elements[i].nodeName){case"INPUT":switch(form.elements[i].type){case"text":case"hidden":case"password":case"button":case"reset":case"submit":q.push(form.elements[i].name+"="+encodeURIComponent(form.elements[i].value));break;case"checkbox":case"radio":if(form.elements[i].checked){q.push(form.elements[i].name+"="+encodeURIComponent(form.elements[i].value))}break;case"file":break}break;case"TEXTAREA":q.push(form.elements[i].name+"="+encodeURIComponent(form.elements[i].value));break;case"SELECT":switch(form.elements[i].type){case"select-one":q.push(form.elements[i].name+"="+encodeURIComponent(form.elements[i].value));break;case"select-multiple":for(j=form.elements[i].options.length-1;j>=0;j=j-1){if(form.elements[i].options[j].selected){q.push(form.elements[i].name+"="+encodeURIComponent(form.elements[i].options[j].value))}}break}break;case"BUTTON":switch(form.elements[i].type){case"reset":case"submit":case"button":q.push(form.elements[i].name+"="+encodeURIComponent(form.elements[i].value));break}break}}return q.join("&")};


                function extend(destination, source) {
                  for (var prop in source) {
                    destination[prop] = source[prop];
                  }
                }

                if (!Mimi) var Mimi = {};
                if (!Mimi.Signups) Mimi.Signups = {};

                Mimi.Signups.EmbedValidation = function() {
                  this.initialize();

                  var _this = this;
                  if (document.addEventListener) {
                    this.form.addEventListener('submit', function(e){
                      _this.onFormSubmit(e);
                    });
                  } else {
                    this.form.attachEvent('onsubmit', function(e){
                      _this.onFormSubmit(e);
                    });
                  }
                };

                extend(Mimi.Signups.EmbedValidation.prototype, {
                  initialize: function() {
                    this.form         = document.getElementById('ema_signup_form');
                    this.submit       = document.getElementById('webform_submit_button');
                    this.callbackName = 'jsonp_callback_' + Math.round(100000 * Math.random());
                    this.validEmail   = /.+@.+\..+/
                  },

                  onFormSubmit: function(e) {
                    e.preventDefault();

                    this.validate();
                    if (this.isValid) {
                      this.submitForm();
                    } else {
                      this.revalidateOnChange();
                    }
                  },

                  validate: function() {
                    this.isValid = true;
                    this.emailValidation();
                    this.fieldAndListValidation();
                    this.updateFormAfterValidation();
                  },

                  emailValidation: function() {
                    var email = document.getElementById('signup_email');

                    if (this.validEmail.test(email.value)) {
                      this.removeTextFieldError(email);
                    } else {
                      this.textFieldError(email);
                      this.isValid = false;
                    }
                  },

                  fieldAndListValidation: function() {
                    var fields = this.form.querySelectorAll('.mimi_field.required');

                    for (var i = 0; i < fields.length; ++i) {
                      var field = fields[i],
                          type  = this.fieldType(field);
                      if (type === 'checkboxes' || type === 'radio_buttons') {
                        this.checkboxAndRadioValidation(field);
                      } else {
                        this.textAndDropdownValidation(field, type);
                      }
                    }
                  },

                  fieldType: function(field) {
                    var type = field.querySelectorAll('.field_type');

                    if (type.length) {
                      return type[0].getAttribute('data-field-type');
                    } else if (field.className.indexOf('checkgroup') >= 0) {
                      return 'checkboxes';
                    } else {
                      return 'text_field';
                    }
                  },

                  checkboxAndRadioValidation: function(field) {
                    var inputs   = field.getElementsByTagName('input'),
                        selected = false;

                    for (var i = 0; i < inputs.length; ++i) {
                      var input = inputs[i];
                      if((input.type === 'checkbox' || input.type === 'radio') && input.checked) {
                        selected = true;
                      }
                    }

                    if (selected) {
                      field.className = field.className.replace(/ invalid/g, '');
                    } else {
                      if (field.className.indexOf('invalid') === -1) {
                        field.className += ' invalid';
                      }

                      this.isValid = false;
                    }
                  },

                  textAndDropdownValidation: function(field, type) {
                    var inputs = field.getElementsByTagName('input');

                    for (var i = 0; i < inputs.length; ++i) {
                      var input = inputs[i];
                      if (input.name.indexOf('signup') >= 0) {
                        if (type === 'text_field') {
                          this.textValidation(input);
                        } else {
                          this.dropdownValidation(field, input);
                        }
                      }
                    }
                    this.htmlEmbedDropdownValidation(field);
                  },

                  textValidation: function(input) {
                    if (input.id === 'signup_email') return;

                    if (input.value) {
                      this.removeTextFieldError(input);
                    } else {
                      this.textFieldError(input);
                      this.isValid = false;
                    }
                  },

                  dropdownValidation: function(field, input) {
                    if (input.value) {
                      field.className = field.className.replace(/ invalid/g, '');
                    } else {
                      if (field.className.indexOf('invalid') === -1) field.className += ' invalid';
                      this.onSelectCallback(input);
                      this.isValid = false;
                    }
                  },

                  htmlEmbedDropdownValidation: function(field) {
                    var dropdowns = field.querySelectorAll('.mimi_html_dropdown');
                    var _this = this;

                    for (var i = 0; i < dropdowns.length; ++i) {
                      var dropdown = dropdowns[i];

                      if (dropdown.value) {
                        field.className = field.className.replace(/ invalid/g, '');
                      } else {
                        if (field.className.indexOf('invalid') === -1) field.className += ' invalid';
                        this.isValid = false;
                        dropdown.onchange = (function(){ _this.validate(); });
                      }
                    }
                  },

                  textFieldError: function(input) {
                    input.className   = 'required invalid uk-form-large uk-width-1-1';
                    input.placeholder = input.getAttribute('data-required-field');
                  },

                  removeTextFieldError: function(input) {
                    input.className   = 'required uk-form-large uk-width-1-1';
                    input.placeholder = '';
                  },

                  onSelectCallback: function(input) {
                    if (typeof Widget === 'undefined' || !Widget.BasicDropdown) return;

                    var dropdownEl = input.parentNode,
                        instances  = Widget.BasicDropdown.instances,
                        _this = this;

                    for (var i = 0; i < instances.length; ++i) {
                      var instance = instances[i];
                      if (instance.wrapperEl === dropdownEl) {
                        instance.onSelect = function(){ _this.validate() };
                      }
                    }
                  },

                  updateFormAfterValidation: function() {
                    this.form.className   = this.setFormClassName();
                    this.submit.value     = this.submitButtonText();
                    this.submit.disabled  = !this.isValid;
                    this.submit.className = this.isValid ? 'submit uk-button uk-button-large uk-button-primary' : 'disabled uk-button uk-button-large uk-button-primary';
                  },

                  setFormClassName: function() {
                    var name = this.form.className;

                    if (this.isValid) {
                      return name.replace(/\s?mimi_invalid/, '');
                    } else {
                      if (name.indexOf('mimi_invalid') === -1) {
                        return name += ' mimi_invalid';
                      } else {
                        return name;
                      }
                    }
                  },

                  submitButtonText: function() {
                    var invalidFields = document.querySelectorAll('.invalid'),
                        text;

                    if (this.isValid || !invalidFields) {
                      text = this.submit.getAttribute('data-default-text');
                    } else {
                      if (invalidFields.length || invalidFields[0].className.indexOf('checkgroup') === -1) {
                        text = this.submit.getAttribute('data-invalid-text');
                      } else {
                        text = this.submit.getAttribute('data-choose-list');
                      }
                    }
                    return text;
                  },

                  submitForm: function() {
                    this.formSubmitting();

                    var _this = this;
                    window[this.callbackName] = function(response) {
                      delete window[this.callbackName];
                      document.body.removeChild(script);
                      _this.onSubmitCallback(response);
                    };

                    var script = document.createElement('script');
                    script.src = this.formUrl('json');
                    document.body.appendChild(script);
                  },

                  formUrl: function(format) {
                    var action  = this.form.action;
                    if (format === 'json') action += '.json';
                    return action + '?callback=' + this.callbackName + '&' + serialize(this.form);
                  },

                  formSubmitting: function() {
                    this.form.className  += ' mimi_submitting';
                    this.submit.value     = this.submit.getAttribute('data-submitting-text');
                    this.submit.disabled  = true;
                    this.submit.className = 'disabled uk-button uk-button-large uk-button-primary';
                  },

                  onSubmitCallback: function(response) {
                    if (response.success) {
                      this.onSubmitSuccess(response.result);
                    } else {
                      top.location.href = this.formUrl('html');
                    }
                  },

                  onSubmitSuccess: function(result) {
                    if (result.has_redirect) {
                      top.location.href = result.redirect;
                    } else if(result.single_opt_in || !result.confirmation_html) {
                      this.disableForm();
                      this.updateSubmitButtonText(this.submit.getAttribute('data-thanks'));
                    } else {
                      this.showConfirmationText(result.confirmation_html);
                    }
                  },

                  showConfirmationText: function(html) {
                    var fields = this.form.querySelectorAll('.mimi_field');

                    for (var i = 0; i < fields.length; ++i) {
                      fields[i].style['display'] = 'none';
                    }

                    (this.form.querySelectorAll('fieldset')[0] || this.form).innerHTML = html;
                  },

                  disableForm: function() {
                    var elements = this.form.elements;
                    for (var i = 0; i < elements.length; ++i) {
                      elements[i].disabled = true;
                    }
                  },

                  updateSubmitButtonText: function(text) {
                    this.submit.value = text;
                  },

                  revalidateOnChange: function() {
                    var fields = this.form.querySelectorAll(".mimi_field.required"),
                        _this = this;

                    for (var i = 0; i < fields.length; ++i) {
                      var inputs = fields[i].getElementsByTagName('input');
                      for (var j = 0; j < inputs.length; ++j) {
                        if (this.fieldType(fields[i]) === 'text_field') {
                          inputs[j].onkeyup = function() {
                            var input = this;
                            if (input.getAttribute('name') === 'signup[email]') {
                              if (_this.validEmail.test(input.value)) _this.validate();
                            } else {
                              if (input.value.length === 1) _this.validate();
                            }
                          }
                        } else {
                          inputs[j].onchange = function(){ _this.validate() };
                        }
                      }
                    }
                  }
                });

                if (document.addEventListener) {
                  document.addEventListener("DOMContentLoaded", function() {
                    new Mimi.Signups.EmbedValidation();
                  });
                }
                else {
                  window.attachEvent('onload', function() {
                    new Mimi.Signups.EmbedValidation();
                  });
                }
              })(this);
              </script>
            </div>
        </div>
  <!-- /WPES PRO VERSION NOTIFICATION FORM -->
        </div>
        <?php
    }
    
    /**
     * We want to reduce the height so the extra gallery fields are shown.
     */
    function change_tinymce_settings( $settings ) {
        $esg_obj = esg_cpt::get_instance();

        if ( get_post_type() == $esg_obj->post_type ) {
            $settings['height'] = '80px';
        }
        
        return $settings;
    }
    
    /**
     * Get rid of the link and replace it with gallery's shortcode.
     */
    function remove_gallery_permalink( $link, $post_id = 0 ) {
        $esg_obj = esg_cpt::get_instance();
        $post_id = intval( $post_id );
        
        if ( $post_id > 0 && get_post_type() == $esg_obj->post_type ) {
            $upload_link = $esg_obj->generate_upload_url( $post_id );
            $preview_link = $esg_obj->generate_preview_url( $post_id );
            
            // Here we search if any of the pages/posts have used the shortcode and if yes we'll show them.
            // We explicitely search for a partial shortcode because each shortcode can have
            // different attributes
            global $wpdb;
            $limit = 25;
            $where = "WHERE (post_status != 'trash' AND (post_type = 'page' OR post_type = 'post')) ";
            $where .= ' AND ' . str_replace('{search}', esc_sql( '[esg_gallery' ),
                    " post_content LIKE '%{search}%' ");
            $where .= ' ORDER BY id DESC ';
            $where .= " LIMIT $limit";

            $sql = "SELECT id, post_content FROM {$wpdb->posts} $where";

            $items_raw = $wpdb->get_results( $sql, ARRAY_A );
            $items_raw = empty($items_raw) ? array() : $items_raw;

            // If we find a page that uses that gallery we'll use that link instead.
            foreach ( $items_raw as $rec ) {
                if ( preg_match( '#\[esg_gallery.*?id\s*=[\s*\'\"]*' . $post_id . '[\s\'\"\]]+#si', $rec['post_content'] ) ) {
                    $preview_link = get_permalink( $rec['id'] );
                    break;
                }
            }
            
            $link = '';
            $link .= "<div class='uk-margin-top'><a href='$preview_link' target='_blank' class='button-primary'><i class='uk-icon-eye'></i> Preview Gallery</a></div>";
            $link .= '<br/><div class="esg_shortcode_editor">Gallery Shortcode: ' . esg_html_util::generate_embed_code( $esg_obj->generate_shortcode( $post_id ) ) . '</div>';
            $link .= "<br/>Upload URL (<a href='$upload_link' target='_blank'>View</a>): ";
            $link .= esg_html_util::generate_embed_code( $upload_link );
        }
        
        return $link;
    }
    
    /**
     * Removed the unnecessary actions to reduce decisions the user needs to make.
     */
    function change_gallery_row_actions( $actions, $post = null ) {
        $esg_obj = esg_cpt::get_instance();
        
        if ( get_post_type() == $esg_obj->post_type ) {
            foreach ( $actions as $act_key => $html ) {
                if ( preg_match( '#view|inline#si', $act_key ) ) {
                    unset( $actions[ $act_key ] );
                }
            }
        }

        return $actions;
    }
}
