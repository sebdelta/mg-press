<?php

class MgPageTemplates
{

    /**
     * A Unique Identifier
     */
    protected $plugin_slug;

    /**
     * A reference to an instance of this class.
     */
    private static $instance;

    /**
     * The array of templates that this plugin tracks.
     */
    protected $templates;


    /**
     * Returns an instance of this class.
     */
    public static function get_instance()
    {

        if (null == self::$instance) {
            self::$instance = new MgPageTemplates();
        }

        return self::$instance;

    }

    /**
     * Initializes the plugin by setting filters and administration functions.
     */
    private function __construct()
    {

        $this->templates = array();

        if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

            // 4.6 and older
            add_filter(
                'page_attributes_dropdown_pages_args',
                array($this, 'register_project_templates')
            );

        } else {

            // Add a filter to the wp 4.7 version attributes metabox
            add_filter(
                'theme_page_templates', array( $this, 'add_new_template' )
            );

        }

        // Add a filter to the attributes metabox to inject template into the cache.
        add_filter(
            'default_page_template_title',
            array($this, 'register_project_templates')
        );

        /** ACF Integration */
        add_action('acf/include_field_types', array($this, 'register_project_templates'));


        // Add a filter to the save post to inject out template into the page cache
        add_filter(
            'wp_insert_post_data',
            array($this, 'register_project_templates')
        );

        // add a filter to fix rewrite rules for base category
        add_filter('category_rewrite_rules', array($this, 'category_rewrite_filter'));

        // add rewrite rule for search page
        add_action('template_redirect', array($this, 'search_url_rewrite'));


        // Add your templates to this array.
        $this->templates = $this->loadCustomTemplates();
    }

    public function loadCustomTemplates(){
        // Add your templates to this array.
        $templates = array(
            'template/home'                => 'Home Template',
        );
        return $templates;
    }

    /*
     * Add custom templates for post/page/custom post types for WP 4.7+
     */

    public function add_new_template( $posts_templates ) {

        $posts_templates = array_merge( $posts_templates, $this->loadCustomTemplates() );
        return $posts_templates;
    }

    /**
     * Adds our template to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doesn't really exist.
     *
     */
    public function register_project_templates($atts)
    {

        // Create the key used for the themes cache
        $cache_key = 'page_templates-'.md5(get_theme_root().'/'.get_stylesheet());

        // Retrieve the cache list.
        // If it doesn't exist, or it's empty prepare an array
        $templates = wp_get_theme()->get_page_templates();
        if (empty($templates)) {
            $templates = $this->loadCustomTemplates();
        }

        // New cache, therefore remove the old one
        wp_cache_delete($cache_key, 'themes');

        // Now add our template to the list of templates by merging our templates
        // with the existing templates array from the cache.
        $templates = array_merge($templates, $this->templates);

        // Add the modified cache to allow WordPress to pick it up for listing
        // available templates
        wp_cache_add($cache_key, $templates, 'themes', 1800);

        return $atts;
    }

    /**
     * Category Rewrite Filter
     *
     * @param array $rules
     * @return array
     */
    public function category_rewrite_filter($rules)
    {
        if (count($rules)) {
            reset($rules);
            $firstRuleKey = key($rules);
            if (preg_match('/^([^\/]+)/', $firstRuleKey, $matches)) {
                $rules =
                    array($matches[1].'/page/?([0-9]{1,})/?$' => 'index.php?post_type=post&paged=$matches[1]')
                    + $rules;
            }
        }

        return $rules;
    }


    /**
     * Search URL Rewrite
     */
    public function search_url_rewrite()
    {
        if (is_search() && isset($_GET['s'])) {
            wp_redirect(home_url("/search/").urlencode(get_query_var('s')));
            exit();
        }
    }
}

MgPageTemplates::get_instance();
