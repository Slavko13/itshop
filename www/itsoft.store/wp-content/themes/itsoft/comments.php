<?php
if ( post_password_required() ) {
    return;
}
?>

<div id="comments" class="comments-area">
    <div class="comments__form">
            <?php comment_form(); ?>
    </div>
    <?php if ( have_comments() ) : ?>
        <div class="comments__count">
            <?php
            printf(
                _nx( 'One comment', '%1$s comments', get_comments_number(), 'comments title', 'textdomain' ),
                number_format_i18n( get_comments_number() )
            );
            ?>
        </div>

        <ol class="comment-list">
            <?php
            wp_list_comments( array(
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 50,
            ) );
            ?>
        </ol>

        <?php the_comments_navigation(); ?>

    <?php endif; ?>

</div>