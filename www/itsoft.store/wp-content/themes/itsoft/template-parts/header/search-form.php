<form class="search header-main__search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
    <div class="search__mobile-close">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M13.2083 25L8 20M8 20L13.2083 15M8 20H33" stroke="#4B2FD7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </div>
    <label class="screen-reader-text" for="itsoft-header-search">Поиск по сайту</label>
    <input id="itsoft-header-search" type="search" name="s" class="search__input" placeholder="<?php echo esc_attr( itsoft_get_search_placeholder_text() ); ?>" required>
    <button type="submit" class="search__submit" aria-label="Найти">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M16.4997 16.5L12.1688 12.1692M12.1688 12.1692C13.341 10.997 13.9995 9.40725 13.9995 7.74958C13.9995 6.09192 13.341 4.50215 12.1688 3.33C10.9967 2.15785 9.40692 1.49935 7.74926 1.49935C6.09159 1.49935 4.50182 2.15785 3.32967 3.33C2.15753 4.50215 1.49902 6.09192 1.49902 7.74958C1.49902 9.40725 2.15753 10.997 3.32967 12.1692C4.50182 13.3413 6.09159 13.9998 7.74926 13.9998C9.40692 13.9998 10.9967 13.3413 12.1688 12.1692Z" stroke="#8A92A5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
    <button type="button" class="search__mobile-button">
        <svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22.5 22.5L16.7258 16.7258M16.7258 16.7258C18.2886 15.163 19.1666 13.0434 19.1666 10.8333C19.1666 8.62316 18.2886 6.50356 16.7258 4.94076C15.163 3.37797 13.0434 2.5 10.8333 2.5C8.62316 2.5 6.50356 3.37797 4.94076 4.94076C3.37797 6.50356 2.5 8.62316 2.5 10.8333C2.5 13.0434 3.37797 15.163 4.94076 16.7258C6.50356 18.2886 8.62316 19.1666 10.8333 19.1666C13.0434 19.1666 15.163 18.2886 16.7258 16.7258Z" stroke="#4B2FD7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</form>
