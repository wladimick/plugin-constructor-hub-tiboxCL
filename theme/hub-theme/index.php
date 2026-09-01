<?php
/**
 * Listado por defecto. Cubre home, archivos y búsqueda.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="hub-theme-content" class="hub-theme-main">
    <?php if (is_archive() || is_search()) : ?>
        <header>
            <h1 class="hub-theme-entry__title">
                <?php
                if (is_search()) {
                    /* translators: %s: search terms. */
                    printf(esc_html__('Resultados para “%s”', 'hub-theme'), esc_html(get_search_query()));
                } else {
                    the_archive_title();
                }
                ?>
            </h1>
            <?php the_archive_description(); ?>
        </header>
    <?php endif; ?>

    <?php if (!have_posts()) : ?>
        <p><?php esc_html_e('No hay contenido que mostrar.', 'hub-theme'); ?></p>
        <?php get_search_form(); ?>
    <?php endif; ?>

    <?php while (have_posts()) : ?>
        <?php the_post(); ?>
        <article <?php post_class('hub-theme-entry'); ?>>
            <h2 class="hub-theme-entry__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h2>
            <p class="hub-theme-entry__meta">
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
            </p>
            <?php the_excerpt(); ?>
        </article>
    <?php endwhile; ?>

    <div class="hub-theme-pagination">
        <?php the_posts_pagination(['mid_size' => 2]); ?>
    </div>
</main>

<?php
get_footer();
