<footer class="footer" id="site-footer">

    <div class="footer__main">
        <div class="grid-container footer__grid">
            <div class="footer__col footer__col_info">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo footer__logo">
                    <?php get_template_part( 'template-parts/brand-logo', null, array( 'variant' => 'light' ) ); ?>
                </a>

                <p class="footer__description">Лицензионное программное обеспечение с быстрой электронной доставкой и поддержкой после покупки.</p>

                <div class="footer__contacts">
                    <a href="tel:+79934796500" class="footer__tel">+7 993 479 6500</a>
                    <a href="https://t.me/itsoft_help" class="footer__telegram" target="_blank" rel="noopener">Написать в Telegram <span aria-hidden="true">→</span></a>
                </div>

                <div class="footer__work work">
                    <div class="work__item">Будни: 11:00–18:00</div>
                    <div class="work__item">Выходные: 11:00–16:00</div>
                </div>
            </div>

            <?php
            $footer_menus = [
                'footer_catalog'  => 'Каталог',
                'footer_services' => 'Сервисы',
                'footer_about'    => 'О магазине',
            ];
            ?>

            <?php foreach ( $footer_menus as $location => $fallback_title ) : ?>
                <?php
                $menu_title = $fallback_title;
                ?>
                <div class="footer__col">
                    <nav class="extra-nav">
                        <div class="extra-nav__title"><?php echo esc_html( $menu_title ); ?></div>
                        <?php if ( has_nav_menu( $location ) ) : ?>
                            <?php
                            wp_nav_menu( [
                                'theme_location' => $location,
                                'container'      => false,
                                'menu_class'     => 'extra-nav__list',
                                'fallback_cb'    => false,
                                'depth'          => 1,
                                'item_spacing'   => 'discard',
                            ] );
                            ?>
                        <?php else : ?>
                            <ul class="extra-nav__list"></ul>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="footer__bar">
        <div class="grid-container footer__bar-inner">
            <div class="footer__copyright">
                ©<?php echo esc_html( wp_date( 'Y' ) ); ?> ITsoft. Цифровые ключи активации.
            </div>

            <nav class="footer__docs docs">
                <ul class="docs__list">
                    <li class="docs__item"><a class="docs__link" href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Пользовательское соглашение</a></li>
                    <li class="docs__item"><a class="docs__link" href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Политика конфиденциальности</a></li>
                    <li class="docs__item"><a class="docs__link" href="<?php echo esc_url( home_url( '/offer/' ) ); ?>">Оферта</a></li>
                </ul>
            </nav>

            <a class="footer__developer" href="https://dualweb.ru/" target="_blank" rel="noopener external">
                <span>Разработка сайта</span>
                <strong>DUALWEB</strong>
            </a>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
