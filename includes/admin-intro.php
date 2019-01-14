<?php

if (!defined('ABSPATH')) {
    die;
}

define('WPRSS_INTRO_STEP_OPTION', 'wprss_intro_step');
define('WPRSS_INTRO_NONCE_NAME', 'wprss_intro_nonce');
define('WPRSS_INTRO_STEP_POST_PARAM', 'wprss_intro_step');
define('WPRSS_INTRO_FEED_URL_PARAM', 'wprss_intro_feed_url');

/**
 * AJAX handler for setting the introduction step the user has reached.
 *
 * @since [*next-version*]
 */
add_action('wp_ajax_wprss_set_intro_step', function () {
    check_ajax_referer(WPRSS_INTRO_NONCE_NAME, 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('', '', [
            'response' => 403,
        ]);
    }

    $step = filter_input(INPUT_POST, WPRSS_INTRO_STEP_POST_PARAM, FILTER_VALIDATE_INT);

    if ($step === null) {
        wprss_ajax_error_response(
            sprintf(__('Missing intro step param "%s"', WPRSS_TEXT_DOMAIN), WPRSS_INTRO_STEP_POST_PARAM)
        );
    }

    wprss_set_intro_step($step);
    wprss_ajax_success_response([
        'wprss_intro_step' => $step,
    ]);
});

/**
 * AJAX handler for creating a feed source from the introduction and previewing its items.
 *
 * @since [*next-version*]
 */
add_action('wp_ajax_wprss_create_intro_feed', function () {
    check_ajax_referer(WPRSS_INTRO_NONCE_NAME, 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('', '', [
            'response' => 403,
        ]);
    }

    $url = filter_input(INPUT_POST, WPRSS_INTRO_FEED_URL_PARAM, FILTER_VALIDATE_URL);

    if ($url === null) {
        wprss_ajax_error_response(
            __('Missing feed URL parameter', WPRSS_TEXT_DOMAIN)
        );
    }
    if ($url === false) {
        wprss_ajax_error_response(
            __('The given feed URL is invalid', WPRSS_TEXT_DOMAIN)
        );
    }

    try {
        $id = wprss_create_feed_source_with_url($url);
        $items = wprss_preview_feed_items($url);
        $data = [
            'feed_source_id' => $id,
            'feed_items' => $items,
        ];
        wprss_ajax_success_response($data);
    } catch (Exception $e) {
        wprss_ajax_error_response($e->getMessage(), 500);
    }
});

/**
 * Previews a feed by fetching some feed items.
 *
 * @since [*next-version*]
 *
 * @param string $url The URL of the feed source.
 * @param int    $max The maximum number of items to fetch.
 *
 * @return array An array of feed items, as associative arrays containing the following keys:
 *               * title - The feed title
 *               * permalink - The URL of the original article
 *               * date - The published date of the original article
 *               * author - The name of the author
 *
 * @throws Exception If failed to fetch the feed items.
 */
function wprss_preview_feed_items($url, $max = 10)
{
    $items = wprss_get_feed_items($url, null);

    if ($items === null) {
        throw new Exception(__('Failed to retrieve items'));
    }

    $count = 0;
    $results = [];
    foreach ($items as $item) {
        /* @var $item SimplePie_Item */
        $results[] = [
            'title' => $item->get_title(),
            'permalink' => $item->get_permalink(),
            'date' => $item->get_date(get_option('date_format')),
            'author' => $item->get_author()->name,
        ];

        if ($count++ > $max) {
            break;
        }
    }

    return $results;
}

/**
 * Creates a feed source with a given URL.
 *
 * @since [*next-version*]
 *
 * @param string $url The URL of the RSS feed.
 *
 * @return int The ID of the created feed source.
 *
 * @throws Exception If an error occurred while creating the feed source.
 */
function wprss_create_feed_source_with_url($url)
{
    $name = parse_url($url, PHP_URL_HOST);
    $name = ($name === null) ? $url : $name;
    $result = wprss_import_feed_sources_array([$url => $name]);

    if (empty($result)) {
        throw new Exception(
            sprintf(__('Failed to import the feed source "%s" with URL "%s"', WPRSS_TEXT_DOMAIN), $name, $url)
        );
    }

    if ($result[0] instanceof Exception) {
        throw $result[0];
    }

    return $result[0];
}

/**
 * Imports feed sources from an associative array.
 *
 * @since [*next-version*]
 *
 * @param string[] $array An array of feed source URLs mapping to feed source names.
 *
 * @return array The import results. For each source representation (in order), the result will be one of:
 *               - Integer, representing the ID of the created resource;
 *               - An {@link Exception} if something went wrong during import.
 */
function wprss_import_feed_sources_array($array)
{
    /* @var $importer Aventura\Wprss\Core\Component\BulkSourceImport */
    $importer = wprss_wp_container()->get(\WPRSS_SERVICE_ID_PREFIX . 'array_source_importer');

    return $importer->import($array);
}

/**
 * Retrieves the ID of the page with the shortcode for the introduction, creating it if necessary.
 *
 * @since [*next-version*]
 *
 * @return int The ID of the page.
 *
 * @throws Exception If failed to create the page.
 */
function wprss_get_intro_shortcode_page()
{
    $id = get_option(WPRSS_INTRO_SHORTCODE_PAGE_OPTION, 0);
    $page = get_post($id);

    if (!$page) {
        $id = wprss_create_shortcode_page();
        update_option(WPRSS_INTRO_SHORTCODE_PAGE_OPTION, $id);
    }

    return $id;
}

/**
 * Creates a page that contains the WP RSS Aggregator shortcode.
 *
 * @since [*next-version*]
 *
 * @param string|null $title  Optional title for the page.
 * @param string      $status Optional status of the page.
 *
 * @return int The ID of the created page.
 *
 * @throws Exception If failed to create the page.
 */
function wprss_create_shortcode_page($title = null, $status = 'draft')
{
    $title = ($title === null)
        ? _x('Feeds', 'default name of shortcode page', WPRSS_TEXT_DOMAIN)
        : $title;

    $id = wp_insert_post([
        'post_type' => 'page',
        'post_title' => $title,
        'post_content' => '[wp-rss-aggregator]',
        'post_status' => $status
    ]);

    if (is_wp_error($id)) {
        throw new Exception($id->get_error_message(), $id->get_error_code());
    }

    return $id;
}

/**
 * Retrieves the step the user has reached in the introduction.
 *
 * @since [*next-version*]
 *
 * @return int
 */
function wprss_get_intro_step()
{
    return get_option(WPRSS_INTRO_STEP_OPTION, 0);
}

/**
 * Sets the step the user has reached in the introduction.
 *
 * @since [*next-version*]
 *
 * @param int $step A positive integer.
 */
function wprss_set_intro_step($step)
{
    update_option(WPRSS_INTRO_STEP_OPTION, max($step, 0));
}

/**
 * Sends an AJAX success response.
 *
 * @since [*next-version*]
 *
 * @param array $data   Optional data to send.
 * @param int   $status Optional HTTP status code of the response.
 */
function wprss_ajax_success_response($data = [], $status = 200)
{
    echo json_encode([
        'success' => true,
        'error' => '',
        'data' => $data,
        'status' => $status,
    ]);
    wp_die();
}

/**
 * Sends an AJAX success response.
 *
 * @since [*next-version*]
 *
 * @param string $message Optional error message.
 * @param int    $status  Optional HTTP status code of the response.
 */
function wprss_ajax_error_response($message, $status = 400)
{
    echo json_encode([
        'success' => false,
        'error' => $message,
        'data' => [],
        'status' => $status,
    ]);
    wp_die();
}