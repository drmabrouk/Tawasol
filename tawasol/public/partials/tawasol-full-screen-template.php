<?php
/**
 * Full-screen template for Tawasol Chat and Login pages.
 * Bypasses the theme's header and footer for an immersive experience.
 *
 * @package           Tawasol
 * @subpackage        Tawasol/public/partials
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        #wpadminbar {
            display: none !important;
        }
        html {
            margin-top: 0 !important;
        }
    </style>
</head>
<body <?php body_class(); ?>>
    <?php
    while ( have_posts() ) :
        the_post();
        the_content();
    endwhile;
    ?>
    <?php wp_footer(); ?>
</body>
</html>
