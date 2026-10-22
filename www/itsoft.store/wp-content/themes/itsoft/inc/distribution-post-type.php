<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'itsoft_register_distribution_content_types' );
function itsoft_register_distribution_content_types() {
    $post_type_labels = array(
        'name'                  => esc_html__( 'Дистрибутивы', 'itsoft' ),
        'singular_name'         => esc_html__( 'Дистрибутив', 'itsoft' ),
        'menu_name'             => esc_html__( 'Дистрибутивы', 'itsoft' ),
        'name_admin_bar'        => esc_html__( 'Дистрибутив', 'itsoft' ),
        'add_new'               => esc_html__( 'Добавить', 'itsoft' ),
        'add_new_item'          => esc_html__( 'Добавить дистрибутив', 'itsoft' ),
        'new_item'              => esc_html__( 'Новый дистрибутив', 'itsoft' ),
        'edit_item'             => esc_html__( 'Редактировать дистрибутив', 'itsoft' ),
        'view_item'             => esc_html__( 'Просмотреть дистрибутив', 'itsoft' ),
        'all_items'             => esc_html__( 'Все дистрибутивы', 'itsoft' ),
        'search_items'          => esc_html__( 'Искать дистрибутивы', 'itsoft' ),
        'not_found'             => esc_html__( 'Дистрибутивы не найдены', 'itsoft' ),
        'not_found_in_trash'    => esc_html__( 'В корзине дистрибутивы не найдены', 'itsoft' ),
        'featured_image'        => esc_html__( 'Изображение дистрибутива', 'itsoft' ),
        'set_featured_image'    => esc_html__( 'Задать изображение', 'itsoft' ),
        'remove_featured_image' => esc_html__( 'Удалить изображение', 'itsoft' ),
        'use_featured_image'    => esc_html__( 'Использовать как изображение', 'itsoft' ),
        'archives'              => esc_html__( 'Архив дистрибутивов', 'itsoft' ),
    );

    $post_type_args = array(
        'labels'             => $post_type_labels,
        'public'             => true,
        'show_in_rest'       => true,
        'has_archive'        => true,
        'rewrite'            => array( 'slug' => 'distribution' ),
        'menu_position'      => 21,
        'menu_icon'          => 'dashicons-download',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'comments' ),
        'hierarchical'       => false,
        'show_in_nav_menus'  => true,
        'publicly_queryable' => true,
        'query_var'          => true,
    );

    register_post_type( 'distribution', $post_type_args );

    $taxonomy_labels = array(
        'name'              => esc_html__( 'Категории дистрибутивов', 'itsoft' ),
        'singular_name'     => esc_html__( 'Категория дистрибутива', 'itsoft' ),
        'search_items'      => esc_html__( 'Искать категории', 'itsoft' ),
        'all_items'         => esc_html__( 'Все категории', 'itsoft' ),
        'parent_item'       => esc_html__( 'Родительская категория', 'itsoft' ),
        'parent_item_colon' => esc_html__( 'Родительская категория:', 'itsoft' ),
        'edit_item'         => esc_html__( 'Редактировать категорию', 'itsoft' ),
        'update_item'       => esc_html__( 'Обновить категорию', 'itsoft' ),
        'add_new_item'      => esc_html__( 'Добавить новую категорию', 'itsoft' ),
        'new_item_name'     => esc_html__( 'Название новой категории', 'itsoft' ),
        'menu_name'         => esc_html__( 'Категория дистрибутивов', 'itsoft' ),
    );

    $taxonomy_args = array(
        'labels'            => $taxonomy_labels,
        'public'            => true,
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'download' ),
        'query_var'         => true,
    );

    register_taxonomy( 'download', array( 'distribution' ), $taxonomy_args );
}

/**
 * Open comments for distribution post type by default.
 */
add_filter( 'comments_open', 'itsoft_distribution_comments_open', 10, 2 );
function itsoft_distribution_comments_open( $open, $post_id ) {
	$post = get_post( $post_id );
	if ( $post && 'distribution' === $post->post_type ) {
		return true;
	}
	return $open;
}

/**
 * Filter to allow comments for distribution post type.
 */
add_filter( 'get_default_comment_status', 'itsoft_distribution_default_comment_status', 10, 3 );
function itsoft_distribution_default_comment_status( $status, $post_type, $comment_type ) {
    if ( 'distribution' === $post_type ) {
        return 'open';
    }
    return $status;
}

/**
 * Add thumbnail field to Download taxonomy add form.
 */
add_action( 'download_add_form_fields', 'itsoft_download_add_thumbnail_field' );
function itsoft_download_add_thumbnail_field() {
	?>
	<div class="form-field term-thumbnail-wrap">
		<label for="term-thumbnail-id"><?php esc_html_e( 'Иконка категории', 'itsoft' ); ?></label>
		<div class="itsoft-term-thumbnail-field" data-itsoft-term-thumbnail>
			<div class="itsoft-term-thumbnail-preview" style="margin-bottom:10px;max-width:80px;"></div>
			<input type="hidden" id="term-thumbnail-id" name="term_thumbnail_id" value="">
			<button type="button" class="button itsoft-term-thumbnail-upload">
				<?php esc_html_e( 'Выбрать изображение', 'itsoft' ); ?>
			</button>
			<button type="button" class="button itsoft-term-thumbnail-remove" style="display:none;">
				<?php esc_html_e( 'Удалить', 'itsoft' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'Используется как иконка категории на странице дистрибутивов.', 'itsoft' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Add thumbnail field to Download taxonomy edit form.
 *
 * @param WP_Term $term Current term object.
 */
add_action( 'download_edit_form_fields', 'itsoft_download_edit_thumbnail_field' );
function itsoft_download_edit_thumbnail_field( $term ) {
	$thumbnail_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	$image_src    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
	?>
	<tr class="form-field term-thumbnail-wrap">
		<th scope="row">
			<label for="term-thumbnail-id"><?php esc_html_e( 'Иконка категории', 'itsoft' ); ?></label>
		</th>
		<td>
			<div class="itsoft-term-thumbnail-field" data-itsoft-term-thumbnail>
				<div class="itsoft-term-thumbnail-preview" style="margin-bottom:10px;max-width:80px;">
					<?php if ( $image_src ) : ?>
						<img src="<?php echo esc_url( $image_src ); ?>" alt="" style="max-width:100%;height:auto;">
					<?php endif; ?>
				</div>
				<input type="hidden" id="term-thumbnail-id" name="term_thumbnail_id" value="<?php echo esc_attr( $thumbnail_id ); ?>">
				<button type="button" class="button itsoft-term-thumbnail-upload">
					<?php esc_html_e( 'Выбрать изображение', 'itsoft' ); ?>
				</button>
				<button type="button" class="button itsoft-term-thumbnail-remove" <?php echo $image_src ? '' : 'style="display:none;"'; ?>>
					<?php esc_html_e( 'Удалить', 'itsoft' ); ?>
				</button>
			</div>
			<p class="description">
				<?php esc_html_e( 'Используется как иконка категории на странице дистрибутивов.', 'itsoft' ); ?>
			</p>
		</td>
	</tr>
	<?php
}

/**
 * Save Download taxonomy thumbnail.
 *
 * @param int $term_id Term ID.
 */
function itsoft_download_save_thumbnail( $term_id ) {
	if ( ! isset( $_POST['term_thumbnail_id'] ) ) {
		return;
	}

	$thumbnail_id = absint( wp_unslash( $_POST['term_thumbnail_id'] ) );

	if ( $thumbnail_id ) {
		update_term_meta( $term_id, 'thumbnail_id', $thumbnail_id );
	} else {
		delete_term_meta( $term_id, 'thumbnail_id' );
	}
}

add_action( 'created_download', 'itsoft_download_save_thumbnail' );
add_action( 'edited_download', 'itsoft_download_save_thumbnail' );

/**
 * Enqueue media scripts for Download taxonomy screens.
 */
add_action( 'admin_enqueue_scripts', 'itsoft_download_admin_assets' );
function itsoft_download_admin_assets( $hook ) {
	if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'download' !== $screen->taxonomy ) {
		return;
	}

	wp_enqueue_media();

	wp_add_inline_script(
		'jquery-core',
		"(function($){
			function initField(\$wrap){
				if(!\$wrap.length || \$wrap.data('itsoft-term-thumbnail-initialized')){return;}
				\$wrap.data('itsoft-term-thumbnail-initialized', true);

				var frame;
				var \$input   = \$wrap.find('input#term-thumbnail-id');
				var \$preview = \$wrap.find('.itsoft-term-thumbnail-preview');
				var \$btnAdd  = \$wrap.find('.itsoft-term-thumbnail-upload');
				var \$btnDel  = \$wrap.find('.itsoft-term-thumbnail-remove');

				\$btnAdd.on('click', function(e){
					e.preventDefault();

					if(frame){
						frame.open();
						return;
					}

					frame = wp.media({
						title: '". esc_js( __( 'Выберите изображение', 'itsoft' ) ) ."',
						button: { text: '". esc_js( __( 'Использовать', 'itsoft' ) ) ."' },
						multiple: false
					});

					frame.on('select', function(){
						var attachment = frame.state().get('selection').first().toJSON();
						if(!attachment){ return; }

						\$input.val(attachment.id);
						\$preview.html('<img src=\"'+ attachment.url +'\" alt=\"\" style=\"max-width:100%;height:auto;\" />');
						\$btnDel.show();
					});

					frame.open();
				});

				\$btnDel.on('click', function(e){
					e.preventDefault();
					\$input.val('');
					\$preview.empty();
					\$btnDel.hide();
				});
			}

			$(function(){
				$('[data-itsoft-term-thumbnail]').each(function(){
					initField($(this));
				});
			});
		})(jQuery);"
	);
}

