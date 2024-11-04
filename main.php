<?php
/*
Plugin Name: Centralized Payment Reminder Control
Description: Control popup visibility and payment redirects for client websites.
Version: 1.2
Author: Irosh Wijesiri
*/

// Ensure WordPress environment is loaded.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Register the admin menu to manage client sites.
function payment_reminder_menu() {
    add_menu_page(
        'Payment Reminder Control',
        'Payment Reminder',
        'manage_options',
        'payment-reminder',
        'render_payment_reminder_page',
        '', // Optional icon URL
        5 // Priority in the menu.
    );
}
add_action('admin_menu', 'payment_reminder_menu');

// Render the Payment Reminder settings page.
function render_payment_reminder_page() {
    if (isset($_POST['add_new_client'])) {
        $new_client = sanitize_text_field($_POST['new_client']);
        
        // Validate the format of the new client website.
        if (filter_var('https://' . $new_client, FILTER_VALIDATE_URL) && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $new_client)) {
            $clients = get_option('payment_reminder_status', []);
            if (!array_key_exists($new_client, $clients)) {
                $clients[$new_client] = ['show_popup' => '', 'redirect_url' => ''];
                update_option('payment_reminder_status', $clients);
                echo '<div class="updated notice"><p>New client added successfully!</p></div>';
            } else {
                echo '<div class="error notice"><p>This client already exists.</p></div>';
            }
        } else {
            echo '<div class="error notice"><p>Invalid website address. Please enter a valid domain (e.g., example.bojun.lk).</p></div>';
        }
    }
	// Delete a client.
    if (isset($_POST['delete_client'])) {
        $client_to_delete = sanitize_text_field($_POST['client_to_delete']);
        $clients = get_option('payment_reminder_status', []);

        if (isset($clients[$client_to_delete])) {
            unset($clients[$client_to_delete]);
            update_option('payment_reminder_status', $clients);
            echo '<div class="updated notice"><p>Client deleted successfully!</p></div>';
        }
    }

    if (isset($_POST['update_popup_status'])) {
        $clients = get_option('payment_reminder_status', []);
        $redirects = $_POST['redirects'] ?? [];

        foreach ($clients as $client => $data) {
            $clients[$client]['show_popup'] = isset($_POST['clients'][$client]) ? '1' : '';
            $clients[$client]['redirect_url'] = esc_url_raw($redirects[$client] ?? '');
        }
        update_option('payment_reminder_status', $clients);
        echo '<div class="updated notice"><p>Popup statuses and redirect URLs updated.</p></div>';
    }

    $clients = get_option('payment_reminder_status', []);
    ?>
    <form method="post">
        <h2>Manage Client Popups and Redirect URLs</h2>
        <table class="form-table">
            <thead>
                <tr>
                    <th>Client Website</th>
                    <th>Show Popup</th>
                    <th>Payment Redirect URL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client => $data) { ?>
                    <tr>
                        <td><?php echo esc_html($client); ?></td>
                        <td>
                            <input type="checkbox" name="clients[<?php echo esc_attr($client); ?>]" 
                                value="1" <?php checked($data['show_popup'], '1'); ?> />
                        </td>
                        <td>
                            <input type="url" name="redirects[<?php echo esc_attr($client); ?>]" 
                                value="<?php echo esc_attr($data['redirect_url']); ?>" 
                                placeholder="https://example.com/payment" style="width: 100%;" />
                        </td>
						<td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="client_to_delete" value="<?php echo esc_attr($client); ?>" />
                                <button type="submit" name="delete_client" class="button button-secondary" 
                                        onclick="return confirm('Are you sure you want to delete this client?');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <p class="submit">
            <button type="submit" name="update_popup_status" class="button button-primary">Save Changes</button>
        </p>
    </form>

    <h2>Add New Client Website</h2>
    <form method="post">
        <input type="text" name="new_client" placeholder="Enter website (e.g., example.bojun.lk)" required 
               style="width: 100%; max-width: 400px; margin-bottom: 10px;" />
        <p class="submit">
            <button type="submit" name="add_new_client" class="button button-secondary">Add Client</button>
        </p>
    </form>
    <?php
}

// Register the REST API endpoint to expose popup status and redirect URL.
function register_payment_reminder_endpoint() {
    register_rest_route('payment-reminder/v1', '/status', [
        'methods'  => 'GET',
        'callback' => 'get_payment_reminder_status',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'register_payment_reminder_endpoint');

// Callback for the REST API endpoint.
function get_payment_reminder_status(WP_REST_Request $request) {
    $domain = sanitize_text_field($request->get_param('domain'));
    $clients = get_option('payment_reminder_status', []);
    $client_data = $clients[$domain] ?? ['show_popup' => '', 'redirect_url' => ''];

    if (!headers_sent()) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowed_origins = ['https://c1.designbrief.com.au/', 'https://fencingquotesonline.bojun.lk'];

        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET');
            header('Access-Control-Allow-Headers: Content-Type');
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    return rest_ensure_response($client_data);
}
