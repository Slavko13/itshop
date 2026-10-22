<nav class="catalog__nav" id="itsoft-catalog-navigation" aria-label="Каталог товаров">
    <?php
    // Get top-level WooCommerce product categories
    $categories = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => 0,
    ));
    ?>

    <!-- Mobile Header (Visible only on mobile) -->
    <div class="catalog-nav__mobile-header">
        <button class="catalog-nav__mobile-close" type="button" aria-label="Закрыть каталог">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 30L30 10M10 10L30 30" stroke="#4B2FD7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="catalog-nav__mobile-logo">
            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo.svg' ); ?>" alt="ITСофт">
        </a>
        <div class="catalog-nav__mobile-actions">
            <button class="catalog-nav__mobile-search-btn search__mobile-button" type="button" aria-label="Search">
                <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.5 22.5L16.7258 16.7258M16.7258 16.7258C18.2886 15.163 19.1666 13.0434 19.1666 10.8333C19.1666 8.62316 18.2886 6.50356 16.7258 4.94076C15.163 3.37797 13.0434 2.5 10.8333 2.5C8.62316 2.5 6.50356 3.37797 4.94076 4.94076C3.37797 6.50356 2.5 8.62316 2.5 10.8333C2.5 13.0434 3.37797 15.163 4.94076 16.7258C6.50356 18.2886 8.62316 19.1666 10.8333 19.1666C13.0434 19.1666 15.163 18.2886 16.7258 16.7258Z" stroke="#4B2FD7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
            <a href="<?php echo wc_get_cart_url(); ?>" class="catalog-nav__mobile-cart-btn" aria-label="Cart">
                <span class="catalog-nav__mobile-cart-icon">
                    <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2.34375 3.125H3.7875C4.31875 3.125 4.78229 3.48229 4.91979 3.99479L5.31875 5.49167M5.31875 5.49167C11.1215 5.32905 16.9187 5.97412 22.5438 7.40833C21.6854 9.96458 20.6656 12.4479 19.4979 14.8437H7.8125M5.31875 5.49167L7.8125 14.8437M7.8125 14.8437C6.9837 14.8437 6.18884 15.173 5.60279 15.759C5.01674 16.3451 4.6875 17.1399 4.6875 17.9687H21.0938M6.25 21.0937C6.25 21.3009 6.16769 21.4997 6.02118 21.6462C5.87466 21.7927 5.67595 21.875 5.46875 21.875C5.26155 21.875 5.06284 21.7927 4.91632 21.6462C4.76981 21.4997 4.6875 21.3009 4.6875 21.0937C4.6875 20.8865 4.76981 20.6878 4.91632 20.5413C5.06284 20.3948 5.26155 20.3125 5.46875 20.3125C5.67595 20.3125 5.87466 20.3948 6.02118 20.5413C6.16769 20.6878 6.25 20.8865 6.25 21.0937ZM19.5313 21.0937C19.5313 21.3009 19.4489 21.4997 19.3024 21.6462C19.1559 21.7927 18.9572 21.875 18.75 21.875C18.5428 21.875 18.3441 21.7927 18.1976 21.6462C18.0511 21.4997 17.9688 21.3009 17.9688 21.0937C17.9688 20.8865 18.0511 20.6878 18.1976 20.5413C18.3441 20.3948 18.5428 20.3125 18.75 20.3125C18.9572 20.3125 19.1559 20.3948 19.3024 20.5413C19.4489 20.6878 19.5313 20.8865 19.5313 21.0937Z" stroke="#4B2FD7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="catalog-nav__mobile-cart-count user-nav__cart-count" <?php echo WC()->cart->get_cart_contents_count() === 0 ? 'hidden' : ''; ?>>
                        <?php echo WC()->cart->get_cart_contents_count(); ?>
                    </span>
                </span>
            </a>
        </div>

        <!-- Mobile Menu Search Form -->
        <form class="catalog-nav__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <div class="catalog-nav__search-inner">
                <button type="button" class="catalog-nav__search-close">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.2083 25L8 20M8 20L13.2083 15M8 20H33" stroke="#4B2FD7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <input type="search" name="s" class="catalog-nav__search-input" placeholder="<?php echo esc_attr( itsoft_get_search_placeholder_text() ); ?>">
                <button type="submit" class="catalog-nav__search-submit" aria-label="Найти">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.4997 16.5L12.1688 12.1692M12.1688 12.1692C13.341 10.997 13.9995 9.40725 13.9995 7.74958C13.9995 6.09192 13.341 4.50215 12.1688 3.33C10.9967 2.15785 9.40692 1.49935 7.74926 1.49935C6.09159 1.49935 4.50182 2.15785 3.32967 3.33C2.15753 4.50215 1.49902 6.09192 1.49902 7.74958C1.49902 9.40725 2.15753 10.997 3.32967 12.1692C4.50182 13.3413 6.09159 13.9998 7.74926 13.9998C9.40692 13.9998 10.9967 13.3413 12.1688 12.1692Z" stroke="#8A92A5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <div class="catalog-nav__layout">
        <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
            <ul class="catalog-nav__parents">
                <?php foreach ($categories as $category) : 
                    $children = get_terms(array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
                        'parent'     => $category->term_id,
                    ));
                    $has_children = !empty($children) && !is_wp_error($children);
                ?>
                    <li class="catalog-nav__parent-item <?php echo $has_children ? 'has-children' : ''; ?>">
                        <div class="catalog-nav__parent-row">
                            <a href="<?php echo get_term_link($category); ?>" class="catalog-nav__parent-link">
                                <span class="catalog-nav__parent-text"><?php echo esc_html($category->name); ?></span>
                            </a>
                            <?php if ($has_children) : ?>
                                <button
                                    class="catalog-nav__toggle-btn"
                                    type="button"
                                    aria-label="<?php echo esc_attr( sprintf( 'Показать подкатегории: %s', $category->name ) ); ?>"
                                    aria-expanded="false"
                                >
                                    <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                                        <path d="M0.799805 0.599976L3.7998 4.59998L0.799805 8.59998" stroke="#6C4CFF" stroke-width="2" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_children) : ?>
                            <div class="catalog-nav__children-panel">
                                <div class="catalog-nav__children-title">
                                    <?php echo esc_html($category->name); ?>
                                </div>
                                <ul class="catalog-nav__children-list">
                                    <?php foreach ($children as $child) : ?>
                                        <li class="catalog-nav__child-item">
                                            <a href="<?php echo get_term_link($child); ?>" class="catalog-nav__child-link">
                                                <?php echo esc_html($child->name); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="catalog-nav__footer">
        <?php if ( ! is_user_logged_in() ) : ?>
            <div class="catalog-nav__login-text">
                Войдите чтобы видеть покупки, оставлять коментарии, и иметь доступ к скидкам
            </div>
        <?php endif; ?>

        <a class="catalog-nav__account-link" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
            <?php echo is_user_logged_in() ? 'Личный кабинет' : 'Войти'; ?>
        </a>
        <div class="catalog-nav__footer-divider"></div>

        <nav class="catalog-nav__footer-nav">
            <div class="catalog-nav__footer-title">О магазине</div>
            <ul class="catalog-nav__footer-list">
                <li class="catalog-nav__footer-item"><a class="catalog-nav__footer-link" href="<?php echo esc_url( home_url( '/reviews/' ) ); ?>">Отзывы</a></li>
                <li class="catalog-nav__footer-item"><a class="catalog-nav__footer-link" href="<?php echo esc_url( home_url( '/articles/' ) ); ?>">Статьи</a></li>
                <li class="catalog-nav__footer-item"><a class="catalog-nav__footer-link" href="<?php echo esc_url( home_url( '/download/' ) ); ?>">Скачать программы</a></li>
                <li class="catalog-nav__footer-item"><a class="catalog-nav__footer-link" href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">Ответы на вопросы</a></li>
            </ul>
        </nav>

        <div class="laicos catalog-nav__footer-laicos">
            <a href="tel:+79934796500" class="laicos__link" aria-label="Phone">
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="30" height="30" rx="5" fill="#6C4CFF" />
                    <path d="M16.8462 13.1538V9.46154M16.8462 13.1538H20.5385M16.8462 13.1538L21.7692 8.23077M19.3077 23C12.5106 23 7 17.4894 7 10.6923V8.84615C7 8.35652 7.1945 7.88695 7.54073 7.54073C7.88695 7.1945 8.35652 7 8.84615 7H9.9719C10.3953 7 10.7645 7.288 10.8671 7.69908L11.7746 11.3282C11.8648 11.6892 11.7303 12.0683 11.4324 12.2907L10.3715 13.0866C10.2196 13.1965 10.1073 13.3525 10.0511 13.5313C9.99494 13.7102 9.99795 13.9024 10.0597 14.0794C10.5535 15.4227 11.3335 16.6425 12.3455 17.6545C13.3575 18.6665 14.5773 19.4465 15.9206 19.9403C16.2825 20.0732 16.6821 19.937 16.9134 19.6285L17.7093 18.5676C17.8183 18.4222 17.9677 18.3121 18.1389 18.2513C18.3101 18.1904 18.4955 18.1814 18.6718 18.2254L22.3009 19.1329C22.7112 19.2355 23 19.6047 23 20.0281V21.1538C23 21.6435 22.8055 22.1131 22.4593 22.4593C22.1131 22.8055 21.6435 23 21.1538 23H19.3077Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </a>
            <a href="https://t.me/itsoft_help" class="laicos__link" target="_blank" rel="noopener" aria-label="Telegram">
                <svg width="30" height="30" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="30" height="30" rx="5" fill="#6C4CFF" />
                    <path d="M25.1543 3.98724C24.8292 4.00158 24.5264 4.09627 24.2598 4.20404C24.01 4.3045 23.0558 4.71378 21.5527 5.35834C20.0484 6.00346 18.0565 6.85865 15.9316 7.7724C11.682 9.5999 6.90421 11.657 4.45703 12.7099C4.36501 12.7495 4.04392 12.8521 3.70312 13.1181C3.36233 13.3842 3 13.9355 3 14.5497C3 15.0451 3.23607 15.5372 3.5332 15.831C3.83034 16.1248 4.14475 16.2699 4.41406 16.3798C5.39123 16.7785 8.42185 18.0187 8.99414 18.2529C9.19555 18.8699 10.2925 22.2254 10.5469 23.0478C10.7146 23.5905 10.8742 23.9304 11.082 24.2001C11.1859 24.335 11.3067 24.4532 11.4531 24.5458C11.5116 24.5829 11.5758 24.6114 11.6406 24.6376L11.6445 24.6396C11.6595 24.6456 11.6723 24.6558 11.6875 24.6611C11.7161 24.6711 11.7352 24.6723 11.7734 24.6806C11.9258 24.7298 12.0795 24.7607 12.2168 24.7607C12.802 24.7607 13.1602 24.4384 13.1602 24.4384L13.1816 24.4228L16.1914 21.8193L19.8418 25.2724C19.8932 25.3451 20.3725 26.0029 21.4297 26.0029C22.0574 26.0029 22.5553 25.6879 22.875 25.3525C23.1947 25.0171 23.3938 24.6647 23.4785 24.2216V24.2197C23.5577 23.801 26.9219 6.53021 26.9219 6.53021L26.916 6.55365C27.0145 6.10414 27.0403 5.68551 26.9316 5.2724C26.823 4.85929 26.5366 4.46376 26.1816 4.25092C25.8267 4.03812 25.4794 3.9729 25.1543 3.98724ZM24.9668 6.07709C24.962 6.10627 24.9704 6.0916 24.9629 6.12591L24.9609 6.13763L24.959 6.14935C24.959 6.14935 21.6365 23.1992 21.5137 23.8486C21.5227 23.7995 21.4816 23.897 21.4395 23.956C21.3799 23.9149 21.2578 23.8622 21.2578 23.8622L21.2383 23.8408L16.252 19.124L12.7266 22.1708L13.7754 17.9716C13.7754 17.9716 20.3316 11.185 20.7266 10.79C21.0446 10.474 21.1113 10.3638 21.1113 10.2548C21.1113 10.1088 21.0352 10.0029 20.8652 10.0029C20.7122 10.0029 20.5065 10.1516 20.3965 10.2216C18.9634 11.1352 12.6718 14.802 9.85156 16.4423C9.40338 16.2589 6.28966 14.9843 5.23437 14.5536C5.24236 14.5501 5.23973 14.5514 5.24805 14.5478C7.69587 13.4947 12.4716 11.4375 16.7207 9.61029C18.8452 8.69667 20.8387 7.84083 22.3418 7.19623C23.8218 6.56155 24.8501 6.12476 24.9668 6.07709Z" fill="white" />
                </svg>
            </a>
        </div>

        <div class="catalog-nav__footer-contacts">
            <a class="catalog-nav__footer-tel" href="tel:+79934796500">+7 993 479 6500</a>
            <div class="catalog-nav__footer-work">По будням с 11:00 до 18:00</div>
            <div class="catalog-nav__footer-work">По выходным с 11:00 до 16:00</div>
        </div>
    </div>
    </div>

    

    <style>
        /* Base Styles (Desktop First) */
        .catalog-nav__layout {
            display: flex;
            background: #fff;
            border-radius: 20px;
            overflow: visible;
            position: relative;
            min-height: 400px;
            overflow-x: hidden;
        }

        /* Mobile Header - Hidden on Desktop */
        .catalog-nav__mobile-header {
            display: none;
        }

        /* Column 1: Parents */
        .catalog-nav__parents {
            width: 33.333%;
            margin: 0;
            padding: 20px 0;
            list-style: none;
            border-right: 1px solid #eee;
            box-sizing: border-box;
            background: #fff;
            border-radius: 20px 0 0 20px;
        }

        .catalog-nav__parent-item {
            display: block;
        }

        .catalog-nav__parent-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            transition: background-color 0.2s;
            cursor: pointer;
            position: relative;
        }

        .catalog-nav__parent-link {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            font-size: 16px;
            transition: color 0.2s;
            flex-grow: 1;
            padding: 16px 24px;
            position: relative;
        }

        .catalog-nav__toggle-btn {
            background: none;
            border: none;
            padding: 0 0 0 10px;
            display: flex;
            align-items: center;
            cursor: inherit;
            position: relative;
            right: auto;
            top: auto;
            width: 50px;
            min-height: 50px;
            cursor: pointer;
            z-index: 3;
        }

        /* Desktop Hover States */
        @media (min-width: 1025px) {
            .catalog-nav__parent-item:hover .catalog-nav__parent-row {
                background: #e5deff;
            }
            .catalog-nav__parent-item:hover .catalog-nav__parent-link {
                color: var(--main-perple, #6C4CFF);
            }

            /* Children Panel */
            .catalog-nav__children-panel {
                display: none;
                position: absolute;
                top: 0;
                left: 33.333%;
                width: 66.666%;
                height: 100%;
                background: #fff;
                padding: 30px;
                box-sizing: border-box;
                border-radius: 0 20px 20px 0;
                overflow-y: auto;
            }

            .catalog-nav__parent-item:hover .catalog-nav__children-panel {
                display: block;
            }
            
            /* Ensure toggle button doesn't capture events on desktop */
            .catalog-nav__toggle-btn {
                pointer-events: none;
            }
        }

        /* Children Grid */
        .catalog-nav__children-title {
            font-weight: 600;
            font-size: 30px;
            line-height: 1.2;
            color: #0C082C;
            margin-bottom: 20px;
            display: none; /* Hidden by default, shown on desktop */
        }

        @media (min-width: 1025px) {
            .catalog-nav__children-title {
                display: block;
            }
        }

        .catalog-nav__children-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 30px;
            align-content: start;
        }

        .catalog-nav__child-link {
            text-decoration: none;
            color: #333;
            font-size: 15px;
            display: inline-block;
            transition: color 0.2s;
        }

        .catalog-nav__child-link:hover {
            color: var(--main-perple, #6C4CFF);
            text-decoration: underline;
            text-decoration-skip-ink: none;
            text-decoration-style: dotted;
        }

        /* Mobile Styles (< 1024px) */
        @media (max-width: 1024px) {

            .admin-bar .catalog__nav {
                /* padding-top: 32px;
                padding-bottom: 32px; */
            }
            .catalog__nav.active {
                position: fixed;
                top: 0;
                left: 0;
                right: auto !important; /* Override JS inline style */
                width: 100%;
                height: 100vh;
                background: #fff;
                z-index: 9999;
                display: flex !important;
                flex-direction: column;
                border-radius: 0;
                box-shadow: none;
                overflow: hidden;
            }

            .catalog-nav__mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 33px 20px 27px 20px;
                border-bottom: 1px solid #d9d9d9;
                background: #fff;
                flex-shrink: 0;
                gap: 15px;
                position: relative; /* Ensure search form absolute positioning works relative to this */
            }

            /* Mobile Search Form Styles */
            .catalog-nav__search {
                display: none;
                position: absolute;
                inset: 0;
                background: #fff;
                z-index: 10;
                padding: 0 20px;
                align-items: center;
            }

            .catalog-nav__search.active {
                display: flex;
            }

            .catalog-nav__search-inner {
                display: flex;
                align-items: center;
                width: 100%;
                gap: 10px;
                border: 1px solid #f0f0f0;
                border-radius: 12px;
                padding: 5px 10px;
                height: 40px;
                box-sizing: border-box;
            }

            .catalog-nav__search-close {
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #4B2FD7;
            }

            .catalog-nav__search-input {
                flex: 1;
                border: none;
                outline: none;
                font-size: 14px;
                color: #333;
            }

            .catalog-nav__search-submit {
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .catalog-nav__mobile-close {
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                order: 1;
            }

            .catalog-nav__mobile-logo {
                order: 2;
                margin-right: auto;
                display: flex;
                align-items: center;
            }
            
            .catalog-nav__mobile-logo img {
                height: 32px;
                width: auto;
            }
            
            .catalog-nav__mobile-actions {
                display: flex;
                align-items: center;
                gap: 15px;
                order: 3;
            }
            
            .catalog-nav__mobile-cart-icon {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .catalog-nav__mobile-cart-count {
                /* Inherits styles from user-nav__cart-count mostly, but positioned relative to this icon */
                position: absolute;
                top: -6px;
                right: -10px;
            }

            .catalog-nav__layout {
                display: block;
                border-radius: 0;
                height: auto;
                flex-grow: 1;
                overflow-y: auto;
                min-height: 0;
            }

            .catalog-nav__parents {
                width: 100%;
                border: none;
                border-radius: 0;
            }

            .catalog-nav__parent-item {
                border-bottom: 1px solid #f5f5f5;
            }

            .catalog-nav__parent-row {
                background: #fff;
            }

            .catalog-nav__parent-link {
                position: relative;
            }

            /* Active/Open state for accordion */
            .catalog-nav__parent-item.open .catalog-nav__parent-row {
                background: #f9f9f9;
            }
            
            .catalog-nav__parent-item.open .catalog-nav__parent-link {
                color: var(--main-perple, #6C4CFF);
                background: #e5deff;
                border-radius: 12px;
            }

            /* Rotate arrow when open */
            .catalog-nav__toggle-btn svg {
                transition: transform 0.3s ease;
                transform: rotate(90deg); /* Default points right? SVG path suggests right. Rotate 90 to point down */
            }
            
            /* Adjust rotation if default SVG points right (looks like right >) */
            /* Open state: arrow points Up or Down? Usually Up for open accordion. */
            /* Let's assume default is Down (collapsed) and Up (open). But SVG is right. */
            /* Let's set default collapsed to rotate(90deg) [Down], and open to rotate(-90deg) [Up]? */
            /* Or collapsed: Right, Open: Down. */
            /* Screenshot shows Collapsed: Down. Open: Up. */
            /* So, default state needs to look Down. SVG is Right. */
            
            .catalog-nav__toggle-btn svg {
                 transform: rotate(90deg); /* Initial state: Down */
            }

            .catalog-nav__parent-item.open .catalog-nav__toggle-btn svg {
                transform: rotate(-90deg); /* Open state: Up */
            }

            /* Children Panel Mobile */
            .catalog-nav__children-panel {
                display: none; /* Hidden by default */
                position: static;
                width: 100%;
                height: auto;
                background: #f9f9f9;
                padding: 0 24px 20px 24px;
                border-radius: 0;
                border-top: none;
            }
            
            .catalog-nav__parent-item.open .catalog-nav__children-panel {
                display: block;
            }

            .catalog-nav__children-list {
                grid-template-columns: 1fr; /* Single column */
                gap: 12px;
                padding: 20px 0 0 40px;
            }
        }
        @media (max-width: 782px) {
            .admin-bar .catalog__nav {
                padding-top: 46px;
                padding-bottom: 46px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nav = document.querySelector('.catalog__nav');
            const closeBtn = document.querySelector('.catalog-nav__mobile-close');
            const catalogBtn = document.querySelector('.catalog-btn');
            const mobileButton = document.querySelector('.mobile-nav__burger');
            const toggleBtns = document.querySelectorAll('.catalog-nav__toggle-btn');
            
            // Mobile Close Button
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    nav.classList.remove('active');
                    if(catalogBtn) catalogBtn.classList.remove('active');
                    if (mobileButton) {
                        mobileButton.setAttribute('aria-expanded', 'false');
                        mobileButton.setAttribute('aria-label', 'Открыть каталог');
                    }
                    document.body.classList.remove('no-scroll');
                    document.body.style.overflowY = '';
                });
            }

            // Accordion Toggles
            toggleBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Only enable click toggle on mobile
                    if (window.innerWidth > 1024) return;

                    e.preventDefault();
                    e.stopPropagation();
                    
                    const parentItem = this.closest('.catalog-nav__parent-item');
                    const isOpen = parentItem.classList.toggle('open');
                    this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            });
            
            // Toggle Body Scroll Observer
            // Watch for class changes on nav to set overflow
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === "class") {
                        const isMobile = window.innerWidth <= 1024;
                        if (nav.classList.contains('active') && isMobile) {
                            document.body.style.overflowY = 'hidden';
                        } else {
                            document.body.style.overflowY = '';
                        }
                    }
                });
            });
            
            observer.observe(nav, { attributes: true });

            // Also handle window resize to reset styles/classes if needed
            window.addEventListener('resize', () => {
                 if(window.innerWidth > 1024) {
                     document.body.style.overflowY = '';
                     // Optional: collapse accordions?
                 } else {
                     if(nav.classList.contains('active')) {
                         document.body.style.overflowY = 'hidden';
                     }
                 }
            });
        });
    </script>
</nav>
