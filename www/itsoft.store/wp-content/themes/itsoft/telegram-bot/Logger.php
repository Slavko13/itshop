<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Itsoft_Tg_Logger {
    private $log_dir;
    private $log_file;

    public function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/uploads/telegram';
        $this->log_file = $this->log_dir . '/debug.log';

        if ( ! file_exists( $this->log_dir ) ) {
            wp_mkdir_p( $this->log_dir );
            // Secure the directory
            file_put_contents( $this->log_dir . '/.htaccess', 'deny from all' );
            file_put_contents( $this->log_dir . '/index.html', '' );
        }
    }

    public function log( $message, $level = 'INFO' ) {
        // Filter: only log ERROR and CRITICAL/WARNING, unless it's strictly required otherwise.
        // The user asked for "only errors and serious warnings".
        // Let's accept 'ERROR', 'CRITICAL', 'WARNING'. 
        // We can also allow 'INFO' for specific debug flows if needed, but per requirements:
        // "only errors and serious warnings".
        
        $allowed_levels = [ 'ERROR', 'CRITICAL', 'WARNING' ];
        
        if ( ! in_array( strtoupper( $level ), $allowed_levels ) ) {
            return;
        }

        $timestamp = date( 'Y-m-d H:i:s' );
        $formatted_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        file_put_contents( $this->log_file, $formatted_message, FILE_APPEND );
    }

    public function get_logs() {
        if ( file_exists( $this->log_file ) ) {
            return file_get_contents( $this->log_file );
        }
        return '';
    }
}
