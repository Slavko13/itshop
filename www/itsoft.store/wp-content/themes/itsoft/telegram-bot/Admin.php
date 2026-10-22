<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Itsoft_Tg_Admin {
    private $logger;

    public function __construct( $logger ) {
        $this->logger = $logger;
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
    }

    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'Telegram Logs',
            'Telegram Logs',
            'manage_options',
            'itsoft-tg-logs',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $logs = $this->logger->get_logs();
        ?>
        <div class="wrap">
            <h1>Telegram Bot Logs</h1>
            <p>Логи ошибок и важных предупреждений (wp-content/uploads/telegram/debug.log)</p>
            <textarea style="width: 100%; height: 600px; font-family: monospace; background: #f0f0f1;" readonly><?php echo esc_textarea( $logs ); ?></textarea>
            
            <p>
                <form method="post" action="">
                    <?php // Future: Add clear log button if needed ?>
                </form>
            </p>
        </div>
        <?php
    }
}
