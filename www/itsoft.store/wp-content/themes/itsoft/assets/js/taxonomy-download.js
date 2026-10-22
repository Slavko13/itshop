/* Taxonomy Download Scripts */

document.addEventListener('DOMContentLoaded', () => {
    const tabsNav = document.querySelector('.download-tabs__nav');
    const tabsItems = document.querySelectorAll('.download-tabs__nav-item');
    const posts = document.querySelectorAll('.download-tabs__post');

    if (!tabsNav || !tabsItems.length || !posts.length) return;

    tabsItems.forEach(tab => {
        tab.addEventListener('click', () => {
            const filter = tab.dataset.filter;

            // Update active tab
            tabsItems.forEach(item => item.classList.remove('download-tabs__nav-item--active'));
            tab.classList.add('download-tabs__nav-item--active');

            // Filter posts
            posts.forEach(post => {
                const postCategories = post.dataset.category ? post.dataset.category.split(' ') : [];
                if (filter === 'all' || postCategories.includes(filter)) {
                    post.classList.remove('download-tabs__post--hidden');
                } else {
                    post.classList.add('download-tabs__post--hidden');
                }
            });
        });
    });
});
