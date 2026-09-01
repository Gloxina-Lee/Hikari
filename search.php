<?php

/**
 * The template for displaying search results pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package Sakurairo
 */

get_header(); ?>
<section id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
    
    <?php
    global $wp_query;

    $search_query = get_search_query();
    $available_content_types = sakurairo_get_search_available_post_types();
    $content_types = sakurairo_get_search_post_types();
    $show_pages_filter = in_array('page', $available_content_types, true);

    // 搜索页标题
    if (!iro_opt('patternimg') || !get_random_bg_url()) : ?>
        <header class="page-header">
            <h1 class="page-title"><?php printf(esc_html__('Search result: %s', 'sakurairo'), '<span>' . esc_html($search_query) . '</span>'); ?></h1>
        </header><!-- .page-header -->
    <?php endif; ?>

    <?php if (iro_opt('search_filter')) : ?>
        <!-- 筛选器部分 -->
        <div id="filter-container">
            <div class="filter-count">
                <?php echo esc_html(number_format_i18n($wp_query->found_posts)); ?> <?php echo __('results found', 'sakurairo'); ?>
            </div>

            <form id="search-filter-form" action="" method="GET">
                <?php if ($search_query) : ?>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search_query); ?>">
                <?php endif; ?>

                <label>
                    <input type="checkbox" name="content_type[]" value="post" onchange="applyFilter()" <?php echo in_array('post', $content_types, true) ? 'checked' : ''; ?>> <?php echo __('Post', 'sakurairo'); ?>
                </label>

                <?php if (iro_opt('search_for_shuoshuo')) : ?>
                    <label>
                    <input type="checkbox" name="content_type[]" value="shuoshuo" onchange="applyFilter()" <?php echo in_array('shuoshuo', $content_types, true) ? 'checked' : ''; ?>> <?php echo __('shuoshuo', 'sakurairo'); ?>
                    </label>
                <?php endif; ?>

                <?php if ($show_pages_filter) : ?>
                    <label>
                    <input type="checkbox" name="content_type[]" value="page" onchange="applyFilter()" <?php echo in_array('page', $content_types, true) ? 'checked' : ''; ?>> <?php echo __('Page', 'sakurairo'); ?>
                    </label>
                <?php endif; ?>
            </form>

            <div id="filter-toggle" title="<?php echo __('If no option is selected, all results are retrieved by default', 'sakurairo'); ?>" onclick="applyFilter()">
            <a href="./" id="the_filter" style="color: white;"><i class="fas fa-filter"></i></a> <?php echo __('Click to filter', 'sakurairo'); ?></a>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function applyFilter() {
        var filterForm = document.getElementById('search-filter-form');
        var checkboxes = filterForm.querySelectorAll('input[name="content_type[]"]');
        var selected = [];
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) selected.push(checkbox.value);
        });

        var searchParams = new URLSearchParams(window.location.search);
        searchParams.set('content_type', selected.join(','));
        var newUrl = window.location.pathname + '?' + searchParams.toString();

        var the_filter = document.getElementById('the_filter');
        the_filter.href = newUrl;

        the_filter.click();
    }
    </script>

    <?php
    if (have_posts()) :
        while (have_posts()) :
            the_post();
            get_template_part('tpl/content', 'thumbcard');
        endwhile;

        the_posts_pagination(array(
            'mid_size' => 1,
            'prev_text' => __('Previous', 'sakurairo'),
            'next_text' => __('Next', 'sakurairo'),
        ));
    else :
        ?>
        <div class="search-box" style="margin-top: 15px;">
            <!-- search start -->
            <form class="s-search">
                <input class="text-input" type="search" name="s" placeholder="<?php esc_attr_e('Search...', 'sakurairo'); ?>" required>
            </form>
            <!-- search end -->
        </div>
        <?php get_template_part('tpl/content', 'none'); ?>
    <?php
    endif;
    ?>


		<style>
			.nav-previous,
			.nav-next {
				padding: 20px 0;
				text-align: center;
				margin: 40px 0 80px;
				display: inline-block;
				font-family: 'Fira Code', 'Noto Sans SC';
			}

			.nav-previous a,
			.nav-next a {
				padding: 13px 35px;
				border: 1px solid #D6D6D6;
				border-radius: 50px;
				color: #ADADAD;
				text-decoration: none;
			}

			.nav-previous span,
			.nav-next span {
				color: #989898;
				font-size: 15px;
			}

			.nav-previous a:hover,
			.nav-next a:hover {
				border: 1px solid #A0DAD0;
				color: #A0DAD0;
			}
		</style>
	</main><!-- #main -->
</section><!-- #primary -->

<?php get_footer(); ?>
