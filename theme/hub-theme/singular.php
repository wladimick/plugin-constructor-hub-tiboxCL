<?php
/**
 * Entradas y páginas. WordPress usa esta plantilla para single y page cuando no
 * existe una más específica, que es exactamente lo que queremos: una sola.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="hub-theme-content" class="hub-theme-main">
    <?php while (have_posts()) : ?>
        <?php the_post(); ?>
        <article <?php post_class('hub-theme-entry'); ?>>
            <?php if (!is_front_page()) : ?>
                <h1 class="hub-theme-entry__title"><?php the_title(); ?></h1>
            <?php endif; ?>

            <?php if (is_singular('post')) : ?>
                <p class="hub-theme-entry__meta">
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                </p>
            <?php endif; ?>

            <?php the_content(); ?>

            <?php
            wp_link_pages([
                'before' => '<nav class="hub-theme-pagination">',
                'after' => '</nav>',
            ]);
            ?>
        </article>

        <?php
        if (comments_open() || get_comments_number()) {
            comments_template();
        }
        ?>
    <?php endwhile; ?>
</main>

<?php
get_footer();
