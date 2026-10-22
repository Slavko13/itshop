<?php

defined( 'ABSPATH' ) || exit;

final class ITSoft_LF_Crypto {
	public function is_configured(): bool {
		try {
			$this->master_key();
			return true;
		} catch ( RuntimeException $exception ) {
			return false;
		}
	}

	public function normalize( string $value ): string {
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $value ) );
	}

	public function fingerprint( string $plaintext ): string {
		$normalized = $this->normalize( $plaintext );
		return hash_hmac( 'sha256', $normalized, $this->derive_key( 'fingerprint' ) );
	}

	public function encrypt( string $plaintext ): string {
		$normalized = $this->normalize( $plaintext );
		if ( '' === $normalized ) {
			throw new InvalidArgumentException( 'License key must not be empty.' );
		}

		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $normalized, $nonce, $this->derive_key( 'encryption' ) );

		return base64_encode( $nonce . $ciphertext );
	}

	public function decrypt( string $payload ): string {
		$decoded = base64_decode( $payload, true );
		if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			throw new RuntimeException( 'Invalid encrypted license payload.' );
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->derive_key( 'encryption' ) );

		if ( false === $plaintext ) {
			throw new RuntimeException( 'Unable to decrypt license payload.' );
		}

		return $plaintext;
	}

	public function mask( string $plaintext ): string {
		$normalized = $this->normalize( $plaintext );
		$tail       = substr( $normalized, -4 );
		return '••••••••…' . $tail;
	}

	private function derive_key( string $context ): string {
		return sodium_crypto_generichash( 'itsoft-license-' . $context, $this->master_key(), SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	private function master_key(): string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			throw new RuntimeException( 'The Sodium PHP extension is required.' );
		}

		if ( ! defined( 'ITSOFT_LICENSE_ENCRYPTION_KEY' ) || ! is_string( ITSOFT_LICENSE_ENCRYPTION_KEY ) ) {
			throw new RuntimeException( 'ITSOFT_LICENSE_ENCRYPTION_KEY is not configured.' );
		}

		$key = base64_decode( ITSOFT_LICENSE_ENCRYPTION_KEY, true );
		if ( false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen( $key ) ) {
			throw new RuntimeException( 'ITSOFT_LICENSE_ENCRYPTION_KEY must be a base64 encoded 32-byte key.' );
		}

		return $key;
	}
}
