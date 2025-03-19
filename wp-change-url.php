<?php
/**
 * WordPress URL Replacer
 *
 * This script replaces URLs in the WordPress database.
 * It can be uploaded to the root directory of your WordPress installation.
 */

// Prevent direct access to this file
if (!defined('ABSPATH') && !isset($_POST['manual_connect'])) {
    // Try to load WordPress config file
    if (file_exists('wp-config.php')) {
        require_once('wp-config.php');
    }
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define constants
define('SCRIPT_VERSION', '1.0');

/**
 * Class WP_URL_Replacer
 */
class WP_URL_Replacer {
    private $db_name;
    private $db_user;
    private $db_password;
    private $db_host;
    private $table_prefix;
    private $conn;
    private $is_connected = false;
    private $errors = [];
    private $messages = [];
    private $site_url = '';
    private $home_url = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
        $this->handle_requests();
    }

    /**
     * Initialize the script
     */
    private function init() {
        // Check if WordPress is loaded
        if (defined('ABSPATH') && function_exists('wp_load_alloptions')) {
            $this->db_name = DB_NAME;
            $this->db_user = DB_USER;
            $this->db_password = DB_PASSWORD;
            $this->db_host = DB_HOST;
            $this->table_prefix = $GLOBALS['table_prefix'];
        }
    }

    /**
     * Handle form submissions
     */
    private function handle_requests() {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'connect':
                    $this->handle_connect();
                    break;
                case 'manual_connect':
                    $this->handle_manual_connect();
                    break;
                case 'change_url':
                    $this->handle_change_url();
                    break;
                case 'delete_script':
                    $this->handle_delete_script();
                    break;
                case 'test_connection':
                    $this->handle_test_connection();
                    break;
                case 'get_current_url':
                    $this->handle_get_current_url();
                    break;
            }
        }
    }

    /**
     * Handle the connect action
     */
    private function handle_connect() {
        if (!$this->is_connected) {
            $this->connect_to_db();
            if ($this->is_connected) {
                $this->get_wordpress_urls_from_db();
            }
        }
    }

    /**
     * Handle manual connection
     */
    private function handle_manual_connect() {
        if (isset($_POST['db_name'], $_POST['db_user'], $_POST['db_host'], $_POST['table_prefix'])) {
            $this->db_name = $this->sanitize_input($_POST['db_name']);
            $this->db_user = $this->sanitize_input($_POST['db_user']);
            $this->db_password = isset($_POST['db_password']) ? $_POST['db_password'] : '';
            $this->db_host = $this->sanitize_input($_POST['db_host']);
            $this->table_prefix = $this->sanitize_input($_POST['table_prefix']);
            
            $this->connect_to_db();
            if ($this->is_connected) {
                $this->get_wordpress_urls_from_db();
            }
        }
    }

    /**
     * Handle test connection
     */
    private function handle_test_connection() {
        if (isset($_POST['db_name'], $_POST['db_user'], $_POST['db_host'], $_POST['table_prefix'])) {
            $this->db_name = $this->sanitize_input($_POST['db_name']);
            $this->db_user = $this->sanitize_input($_POST['db_user']);
            $this->db_password = isset($_POST['db_password']) ? $_POST['db_password'] : '';
            $this->db_host = $this->sanitize_input($_POST['db_host']);
            $this->table_prefix = $this->sanitize_input($_POST['table_prefix']);
            
            $this->connect_to_db(true);
        }
    }

    /**
     * Handle getting current URL from database
     */
    private function handle_get_current_url() {
        if (!$this->is_connected) {
            $this->connect_to_db();
        }
        
        if ($this->is_connected) {
            $this->get_wordpress_urls_from_db();
        }
    }

    /**
     * Get WordPress URLs from database
     */
    private function get_wordpress_urls_from_db() {
        try {
            // Query for getting siteurl
            $query = "SELECT option_value FROM {$this->table_prefix}options WHERE option_name = 'siteurl'";
            $result = $this->conn->query($query);
            
            if ($result && $row = $result->fetch_assoc()) {
                $this->site_url = $row['option_value'];
            }
            
            // Query for getting home
            $query = "SELECT option_value FROM {$this->table_prefix}options WHERE option_name = 'home'";
            $result = $this->conn->query($query);
            
            if ($result && $row = $result->fetch_assoc()) {
                $this->home_url = $row['option_value'];
            }
            
            
        } catch (Exception $e) {
            $this->errors[] = 'Error retrieving URLs: ' . $e->getMessage();
        }
    }

    /**
     * Handle URL change action
     */
    private function handle_change_url() {
        $old_url = isset($_POST['old_url']) ? $this->sanitize_url($_POST['old_url']) : '';
        $new_url = isset($_POST['new_url']) ? $this->sanitize_url($_POST['new_url']) : '';

        if (empty($old_url) || empty($new_url)) {
            $this->errors[] = 'Both Old URL and New URL are required.';
            return;
        }

        if (!$this->is_connected) {
            $this->connect_to_db();
        }

        if ($this->is_connected) {
            $this->replace_urls($old_url, $new_url);
        }
    }

    /**
     * Handle delete script action
     */
    private function handle_delete_script() {
        $current_file = $_SERVER['SCRIPT_FILENAME'];
        if (file_exists($current_file)) {
            if (unlink($current_file)) {
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit;
            } else {
                $this->errors[] = 'Failed to delete the script. Please delete it manually.';
            }
        }
    }

    /**
     * Connect to the database
     */
    private function connect_to_db($test_only = false) {
        try {
            $this->conn = new mysqli($this->db_host, $this->db_user, $this->db_password, $this->db_name);

            if ($this->conn->connect_error) {
                throw new Exception('Database connection failed: ' . $this->conn->connect_error);
            }

            $this->is_connected = true;

            if ($test_only) {
                $this->messages[] = '✓ Connected to the database successfully!';
            } else {
                $this->messages[] = '✓ Connected to the database successfully!';
            }
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->is_connected = false;
        }
    }

    /**
     * Replace URLs in the database
     */
    private function replace_urls($old_url, $new_url) {
        try {
            // Start transaction
            $this->conn->begin_transaction();

            // Update options table
            $options_query = "UPDATE {$this->table_prefix}options SET option_value = REPLACE(option_value, ?, ?) WHERE option_name = 'home' OR option_name = 'siteurl'";
            $stmt = $this->conn->prepare($options_query);
            $stmt->bind_param('ss', $old_url, $new_url);
            $stmt->execute();
            $options_affected = $stmt->affected_rows;
            $stmt->close();

            // Update posts table - guid
            $posts_guid_query = "UPDATE {$this->table_prefix}posts SET guid = REPLACE(guid, ?, ?)";
            $stmt = $this->conn->prepare($posts_guid_query);
            $stmt->bind_param('ss', $old_url, $new_url);
            $stmt->execute();
            $posts_guid_affected = $stmt->affected_rows;
            $stmt->close();

            // Update posts table - content
            $posts_content_query = "UPDATE {$this->table_prefix}posts SET post_content = REPLACE(post_content, ?, ?)";
            $stmt = $this->conn->prepare($posts_content_query);
            $stmt->bind_param('ss', $old_url, $new_url);
            $stmt->execute();
            $posts_content_affected = $stmt->affected_rows;
            $stmt->close();

            // Update postmeta table
            $postmeta_query = "UPDATE {$this->table_prefix}postmeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?";
            $stmt = $this->conn->prepare($postmeta_query);
            $like_pattern = '%' . $old_url . '%';
            $stmt->bind_param('sss', $old_url, $new_url, $like_pattern);
            $stmt->execute();
            $postmeta_affected = $stmt->affected_rows;
            $stmt->close();

            // Additional tables that might contain URLs
            
            // Update usermeta table (for user-specific URLs)
            $usermeta_query = "UPDATE {$this->table_prefix}usermeta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_value LIKE ?";
            $stmt = $this->conn->prepare($usermeta_query);
            $stmt->bind_param('sss', $old_url, $new_url, $like_pattern);
            $stmt->execute();
            $usermeta_affected = $stmt->affected_rows;
            $stmt->close();
            
            // Update comments table (for URLs in comments)
            $comments_query = "UPDATE {$this->table_prefix}comments SET comment_content = REPLACE(comment_content, ?, ?)";
            $stmt = $this->conn->prepare($comments_query);
            $stmt->bind_param('ss', $old_url, $new_url);
            $stmt->execute();
            $comments_affected = $stmt->affected_rows;
            $stmt->close();

            // Commit transaction
            $this->conn->commit();

            $total_affected = $options_affected + $posts_guid_affected + $posts_content_affected + $postmeta_affected + $usermeta_affected + $comments_affected;
            $this->messages[] = "<br><br><b>URL replacement completed:</b>";
			$this->messages[] = "<br>✓ Total records updated: $total_affected";
            $this->messages[] = "<br>✓ Options table: $options_affected records";
            $this->messages[] = "<br>✓ Posts table (guid): $posts_guid_affected records";
            $this->messages[] = "<br>✓ Posts table (content): $posts_content_affected records";
            $this->messages[] = "<br>✓ Postmeta table: $postmeta_affected records";
            $this->messages[] = "<br>✓ Usermeta table: $usermeta_affected records";
            $this->messages[] = "<br>✓ Comments table: $comments_affected records";

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->errors[] = 'Error replacing URLs: ' . $e->getMessage();
        }
    }

    /**
     * Sanitize URL input
     */
    private function sanitize_url($url) {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return rtrim($url, '/');
    }

    /**
     * Sanitize general input
     */
    private function sanitize_input($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render the form
     */
    public function render() {
        $this->render_header();
        $this->render_messages();
        $this->render_form();
        $this->render_footer();
    }

    /**
     * Render the header
     */
    private function render_header() {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>WordPress URL Replacer</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    margin: 0;
                    padding: 20px;
                    background-color: #EEEEEE;
                    color: #333;
                }
                .container {
                    max-width: 800px;
                    margin: 0 auto;
                    background-color: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #0073aa;
                    margin-top: 0;
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                input[type="text"], input[type="password"] {
                    width: 100%;
                    padding: 8px;
                    margin-bottom: 15px;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    box-sizing: border-box;
					font-size: 16px;
                }
                button {
                    background-color: #0073aa;
                    color: white;
                    border: none;
                    padding: 10px 15px;
                    border-radius: 3px;
                    cursor: pointer;
					font-size: 16px;
                }
                button:hover {
                    background-color: #005177;
                }
                .error {
                    color: #d63638;
                    background-color: #fbeaea;
                    padding: 15px;
                    border-radius: 3px;
                    margin-bottom: 15px;
                }
                .success {
                    color: #00a32a;
                    background-color: #edfaef;
                    padding: 15px;
                    border-radius: 3px;
                    margin-bottom: 15px;
                }
                .delete-button {
                    background-color: #d63638;
                    float: left;
                }
                .delete-button:hover {
                    background-color: #b32d2e;
                }
                .test-button {
                    background-color: #2271b1;
                }
                .test-button:hover {
                    background-color: #135e96;
                }
                .section {
					overflow:hidden;
                    margin-bottom: 25px;
                    padding: 15px;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                }
                .section h2 {
                    margin-top: 0;
                    color: #0073aa;
                }
                .info {
                    margin-bottom: 15px;
                    padding: 5px;
                    background-color: #f0f6fc;
                    border-radius: 3px;
                }
				
				hr {
					height: 1px;
					color: #ddd;
					background-color: #ddd;
					border: none;
					margin: 20px 0px 20px 0px;
				}
				
				.footer {
					text-align: center;
					font-size: 14px;
				}
            </style>
        </head>
        <body>
            <div class="container">
                <h1>WordPress URL Replacer</h1>
                <p>This script helps you replace URLs in your WordPress database.</p>';
    }

    /**
     * Render error and success messages
     */
    private function render_messages() {
        if (!empty($this->errors)) {
            echo '<div class="error">';
            foreach ($this->errors as $error) {
                echo $error;
            }
            echo '</div>';
        }

        if (!empty($this->messages)) {
            echo '<div class="success">';
            foreach ($this->messages as $message) {
                echo $message;
            }
            echo '</div>';
        }
    }

    /**
     * Render the main form
     */
    private function render_form() {
        // Database connection section
        echo '<div class="section">
            <h2>Database Connection</h2>';
        
        if ($this->is_connected) {
            echo '<div class="info">Connected to database: <b>' . $this->sanitize_input($this->db_name) . '</b></div>';
        } else {
            if (defined('ABSPATH') && function_exists('wp_load_alloptions')) {
                echo '<div class="success">✓ WordPress configuration detected (wp-config.php). You can use it to connect.</div>';
				echo '<div class="info"><ul>';
				echo '<li>DB Host: '. $this->db_host.'</li>';
				echo '<li>DB Name: '. $this->db_name.'</li>';
				echo '<li>DB User: '. $this->db_user.'</li>';
				echo '<li>DB Password: '. $this->db_password.'</li>';
				echo '<li>Table Prefix: '. $this->table_prefix.'</li>';
				echo '</ul></div>';
                echo '<form method="post">
                    <input type="hidden" name="action" value="connect">
                    <button type="submit">Connect using WordPress Configuration &#8594;</button>
                </form>';
            } else {
				echo '<div class="error">WordPress configuration not detected. Enter the connection details manually.</div>';
			}
            
			echo '<hr/>';
			echo 'To manually enter the connection details:';
			echo '<br><br>';
            echo '<form method="post">
                <input type="hidden" name="action" value="manual_connect">
                <label for="db_host">DB Host:</label>
                <input type="text" id="db_host" name="db_host" value="' . $this->db_host . '" required>
                
                <label for="db_name">DB Name:</label>
                <input type="text" id="db_name" name="db_name" value="' . $this->db_name . '" required>
                
                <label for="db_user">DB User:</label>
                <input type="text" id="db_user" name="db_user" value="' . $this->db_user . '" required>
                
                <label for="db_password">DB Password:</label>
                <input type="password" id="db_password" name="db_password" value="' . $this->db_password . '">
                
                <label for="table_prefix">Table Prefix:</label>
                <input type="text" id="table_prefix" name="table_prefix" value="' . $this->table_prefix . '" required>
                
                <button type="submit" name="action" value="test_connection" class="test-button">Connection and Next &#8594;</button>
            </form>';
        }
        
        echo '</div>';
		
		// Current URL section
		if ($this->is_connected) {
			echo '<div class="section">
				<h2>Current Site URLs</h2>';
			
				echo '<div class="url-info">';
				
				if (!empty($this->site_url) || !empty($this->home_url)) {
					if (!empty($this->site_url)) {
						echo '<p><strong>Site URL:</strong> <span class="url-display">' . $this->sanitize_input($this->site_url) . '</span></p>';
					}
					if (!empty($this->home_url)) {
						echo '<p><strong>Home URL:</strong> <span class="url-display">' . $this->sanitize_input($this->home_url) . '</span></p>';
					}
				} else {
					echo '<p>No URLs found in database (Click on the Refresh button).</p>';
				}
				
				echo '</div>';
				
				echo '<form method="post" class="button-group">
					<input type="hidden" name="action" value="get_current_url">
					<button type="submit" class="get-url-button">Refresh URLs from Database</button>
				</form>
				<i><small>This option works properly if the script has access to wp-config.php</small></i>';
			echo '</div>';
        } else {
            //echo '<div class="info">Connect to the database first to retrieve current URLs.</div>';
        }
        
        
        // URL replacement section
		if ($this->is_connected) {
        echo '<div class="section">
            <h2>URL Replacement</h2>';
            echo '<i><small>You can specify the domain with or without https://</small></i> <br><br>';  
            echo '<form method="post">
                <input type="hidden" name="action" value="change_url">
                
                <label for="old_url">Old URL:</label>
                <input type="text" id="old_url" name="old_url" placeholder="https://old-domain.com" value="'. $this->site_url . '" required>
                
                <label for="new_url">New URL:</label>
                <input type="text" id="new_url" name="new_url" placeholder="https://new-domain.com" required>
                
                <button type="submit">&#9888; Change URL</button>
            </form>';
			echo '</div>';
        } else {
            //echo '';
        }
        
		
        // Delete script section
        echo '<div class="section">
            <h2>Script Management</h2>
            <p>After you\'ve completed the URL replacement, you should delete this script for security reasons.</p>
            <form method="post" onsubmit="return confirm(\'Are you sure you want to delete this script?\');">
                <input type="hidden" name="action" value="delete_script">
                <button type="submit" class="delete-button">&#10005; Delete script</button>
            </form>
        </div>';
    }

    /**
     * Render the footer
     */
    private function render_footer() {
		echo '<div class="footer">
		WordPress URL Replacer v.1.0 &#8226;
		Development by <a href="https://inwebpress.com/" target="_blank">InwebPress</a> &#8226;
		Script on <a href="https://inwebpress.com/" target="_blank">GitHub</a>
		</div>';
        echo '</div>
        </body>
        </html>';
    }
}

// Initialize and run the script
$url_replacer = new WP_URL_Replacer();
$url_replacer->render();
?>