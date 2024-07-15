<?php
/**
 * Plugin Name: Todo Sync Plugin
 * Description: A plugin to sync todos from an external API and provide a shortcode to display the last 5 unfinished tasks.
 * Version: 1.0
 * Author: Nerso Tonoyan
 */

if (!defined('ABSPATH')) {
    error_log('ABSPATH not defined');
    exit;
}

class TodoSyncPlugin
{
    public function __construct(){
        error_log('TodoSyncPlugin constructor called');
        register_activation_hook(__FILE__, [$this, 'onActivation']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_shortcode('todo_list', [$this, 'displayTodoList']);
        add_action('admin_post_sync_todos', [$this, 'syncTodos']);
    }

    public function onActivation(){
        error_log('TodoSyncPlugin activation hook called');
        global $wpdb;
        $table_name = $wpdb->prefix . 'todos';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            completed tinyint(1) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function addAdminMenu(){
        error_log('TodoSyncPlugin admin menu added');
        add_menu_page(
            'Todo Sync',
            'Todo Sync',
            'manage_options',
            'todo_sync',
            [$this, 'adminPage'],
            'dashicons-list-view',
            6
        );
    }

    public function registerSettings(){
        error_log('TodoSyncPlugin settings registered');
        register_setting('todo_sync_options_group', 'todo_sync_api_url');
    }

    public function adminPage(){
        error_log('TodoSyncPlugin admin page displayed');
        ?>
        <div class="wrap">
            <h1>Todo Sync</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('todo_sync_options_group');
                do_settings_sections('todo_sync_options_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">API URL</th>
                        <td><input type="text" name="todo_sync_api_url" value="<?php echo esc_attr(get_option('todo_sync_api_url', 'https://jsonplaceholder.typicode.com/todos')); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="sync_todos">
                <?php submit_button('Sync Todos'); ?>
            </form>
        </div>
        <?php
    }

    public function syncTodos(){
        error_log('TodoSyncPlugin syncTodos called');
        $api_url = get_option('todo_sync_api_url', 'https://jsonplaceholder.typicode.com/todos');
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            error_log('Error fetching todos: ' . $response->get_error_message());
            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        }

        $todos = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($todos)) {
            error_log('Invalid response format');
            wp_redirect($_SERVER['HTTP_REFERER']);
            exit;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'todos';

        foreach ($todos as $todo) {
            $wpdb->replace(
                $table_name,
                [
                    'id' => $todo['id'],
                    'title' => $todo['title'],
                    'completed' => $todo['completed']
                ]
            );
        }

        error_log('Todos synced successfully');
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }

    public function displayTodoList()
    {
        error_log('TodoSyncPlugin displayTodoList called');
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}todos WHERE completed = 0 ORDER BY id DESC LIMIT 5");

        if ($results){
            echo '<ul class="todo-list">';
            foreach ($results as $todo){
                echo '<li>' . esc_html($todo->title) . '</li>';
            }
            echo '</ul>';
        } else {
            echo 'No todos found.';
        }
    }
}

new TodoSyncPlugin();