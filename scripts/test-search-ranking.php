<?php
/**
 * Local search ranking and normalization smoke test.
 */

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Search ranking smoke test is local-only.' );
}

$search = new ITSoft_LF_Search();
$cases  = array(
	array( 'Windows 11 Pro', '/Windows 11 Pro/i', 1 ),
	array( 'Windows 11 Professional', '/Windows 11 Pro/i', 1 ),
	array( 'Windows 11 Home', '/Windows 11 Home/i', 1 ),
	array( 'Windows 10 Pro', '/Windows 10 Pro/i', 1 ),
	array( 'Microsoft Office', '/Microsoft Office/i', 5 ),
	array( 'Office 2021', '/Office.*2021|2021.*Office/i', 5 ),
	array( 'Office 2024', '/Office.*2024|2024.*Office/i', 5, true ),
	array( 'Microsoft 365', '/Microsoft 365/i', 5, true ),
	array( 'Visio', '/Visio/i', 5 ),
	array( 'Project', '/Project/i', 5 ),
	array( 'SQL Server', '/SQL Server/i', 5, true ),
	array( 'Server 2022', '/Server 2022/i', 5 ),
	array( 'Виндовс 11 Про', '/Windows 11 Pro/i', 3 ),
	array( 'Виндоус 11', '/Windows 11/i', 5 ),
	array( 'Винда 11', '/Windows 11/i', 5 ),
	array( 'Майкрософт офис', '/Microsoft Office/i', 5 ),
	array( 'Офис 2021', '/Office.*2021|2021.*Office/i', 5 ),
	array( 'Домашняя виндовс', '/Windows.*Home|Home.*Windows/i', 5 ),
	array( 'Профессиональная виндовс', '/Windows.*Pro/i', 5 ),
	array( 'windows11', '/Windows 11/i', 5 ),
	array( 'win11', '/Windows 11/i', 5 ),
	array( 'w11 pro', '/Windows 11 Pro/i', 3 ),
	array( 'win 10 pro', '/Windows 10 Pro/i', 3 ),
	array( 'ms office', '/Microsoft Office/i', 5 ),
	array( 'msoffice', '/Microsoft Office/i', 5 ),
	array( 'vindovs 11', '/Windows 11/i', 5 ),
	array( 'vindous 11 pro', '/Windows 11 Pro/i', 3 ),
	array( 'ofis 2021', '/Office.*2021|2021.*Office/i', 5 ),
	array( 'windos 11', '/Windows 11/i', 5 ),
	array( 'wndows 11', '/Windows 11/i', 5 ),
	array( 'windwos 11', '/Windows 11/i', 5 ),
	array( 'microsft office', '/Microsoft Office/i', 5 ),
	array( 'offce 2021', '/Office.*2021|2021.*Office/i', 5 ),
	array( 'window', '/Windows/i', 10 ),
	array( 'micro', '/Microsoft/i', 10 ),
	array( 'profess', '/Professional|Pro \(/i', 10 ),
	array( 'home', '/Home/i', 10 ),
	array( 'ключ windows 11', '/Windows 11/i', 5 ),
	array( 'лицензия office', '/Office/i', 5 ),
	array( 'активация windows', '/Windows/i', 10 ),
	array( 'цштвщцы 11 зкщ', '/Windows 11 Pro/i', 1 ),
);

foreach ( $cases as $case ) {
	[ $query, $expected_pattern, $max_position ] = $case;
	$optional = ! empty( $case[3] );
	if ( $optional ) {
		$catalog_match = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => preg_replace( '/^.*?(Office 2024|Microsoft 365).*$/i', '$1', $query ),
				'fields'         => 'ids',
			)
		);
		if ( empty( $catalog_match ) ) {
			continue;
		}
	}
	$ids = $search->rank_site_content( $query );
	if ( empty( $ids ) ) {
		WP_CLI::error( sprintf( 'No result for "%s".', $query ) );
	}
	$matched = false;
	foreach ( array_slice( $ids, 0, $max_position ) as $id ) {
		if ( 'product' === get_post_type( $id ) && preg_match( $expected_pattern, get_the_title( $id ) ) ) {
			$matched = true;
			break;
		}
	}
	if ( ! $matched ) {
		WP_CLI::error( sprintf( 'Expected product was not in top %d for "%s".', $max_position, $query ) );
	}
}

if ( $search->rank_site_content( 'Office 2099' ) ) {
	WP_CLI::error( 'Non-existent numeric version returned a knowingly wrong product.' );
}

$product_id = 325;
$old_meta   = get_post_meta( $product_id, '_itsoft_search_synonyms', true );
$post_id    = 0;
try {
	update_post_meta( $product_id, '_itsoft_search_synonyms', 'eleven-pro-control' );
	$ids = $search->rank_site_content( 'eleven-pro-control' );
	if ( $product_id !== (int) ( $ids[0] ?? 0 ) ) {
		WP_CLI::error( 'Custom product synonym was not indexed.' );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Windows 11 Pro activation control article',
			'post_content' => 'Windows 11 Pro',
		)
	);
	$ids = $search->rank_site_content( 'Windows 11 Pro' );
	if ( 'product' !== get_post_type( (int) ( $ids[0] ?? 0 ) ) ) {
		WP_CLI::error( 'An article displaced a relevant product.' );
	}
	if ( ! in_array( (int) $post_id, $ids, true ) ) {
		WP_CLI::error( 'Relevant article disappeared instead of ranking after products.' );
	}

	WP_CLI::success( 'Search smoke passed: S01-S40 families plus keyboard layout, numeric precision, custom synonyms and product-first ranking.' );
} finally {
	if ( '' === $old_meta ) {
		delete_post_meta( $product_id, '_itsoft_search_synonyms' );
	} else {
		update_post_meta( $product_id, '_itsoft_search_synonyms', $old_meta );
	}
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		wp_delete_post( $post_id, true );
	}
}
