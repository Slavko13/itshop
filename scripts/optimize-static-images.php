<?php
/**
 * Build WebP copies of the theme's large static raster images.
 *
 * Run inside the WordPress Docker image with the assets directory mounted at
 * /work. Source files remain in place as editable masters.
 */

declare(strict_types=1);

$files = [
	'/work/home-hero-office-suite-v3.png',
	'/work/article-covers/license-security-v1.jpg',
	'/work/article-covers/office-activation-v1.jpg',
	'/work/article-covers/windows-setup-v1.jpg',
];

foreach ($files as $source) {
	if (!is_file($source)) {
		fwrite(STDERR, "Missing source: {$source}\n");
		exit(1);
	}

	$extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
	$image = 'png' === $extension ? imagecreatefrompng($source) : imagecreatefromjpeg($source);

	if (!$image) {
		fwrite(STDERR, "Unable to decode: {$source}\n");
		exit(1);
	}

	if ('png' === $extension) {
		imagepalettetotruecolor($image);
		imagealphablending($image, true);
		imagesavealpha($image, true);
	}

	$target = preg_replace('/\.(?:png|jpe?g)$/i', '.webp', $source);
	if (!imagewebp($image, $target, 82)) {
		imagedestroy($image);
		fwrite(STDERR, "Unable to encode: {$target}\n");
		exit(1);
	}

	imagedestroy($image);
	printf("%s: %d -> %d bytes\n", basename($target), filesize($source), filesize($target));
}
