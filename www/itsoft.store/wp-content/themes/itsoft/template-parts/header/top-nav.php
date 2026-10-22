<nav class="topnav topbar__nav">
    <?php
    wp_nav_menu( [
        'theme_location' => 'top',
        'container'      => false,
        'menu_class'     => 'topnav__list',
        'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
        'depth'          => 1,
    ] );
    ?>
</nav>
