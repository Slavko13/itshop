<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitize shortcode input stored in theme mods (Customizer).
 *
 * @param mixed $value Raw value from Customizer.
 * @return string
 */
function itsoft_sanitize_slider_shortcode_theme_mod( $value ) {
    if ( ! is_string( $value ) ) {
        return '';
    }

    return wp_strip_all_tags( trim( $value ) );
}

/**
 * Theme Customizer: hero-small slider shortcode.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 */
function itsoft_customize_register( $wp_customize ) {
    $wp_customize->add_section(
        'itsoft_hero_small',
        array(
            'title'       => __( 'Слайдер внутренних страниц', 'itsoft' ),
            'description' => __( 'Слайдер в hero-small на всех внутренних страницах (не главная).', 'itsoft' ),
            'priority'    => 86,
        )
    );

    $wp_customize->add_setting(
        'itsoft_hero_small_shortcode',
        array(
            'default'           => '',
            'type'              => 'theme_mod',
            'capability'        => 'edit_theme_options',
            'sanitize_callback' => 'itsoft_sanitize_slider_shortcode_theme_mod',
            'transport'         => 'refresh',
        )
    );

    $wp_customize->add_control(
        'itsoft_hero_small_shortcode',
        array(
            'label'       => __( 'Шорткод слайдера для внутренних страниц (Smart Slider)', 'itsoft' ),
            'description' => __( 'Например: [smartslider3 slider="123"]. Если пусто, в hero-small выводится стандартный текст.', 'itsoft' ),
            'section'     => 'itsoft_hero_small',
            'type'        => 'textarea',
            'input_attrs' => array(
                'rows'  => 2,
                'class' => 'code',
            ),
        )
    );
}

add_action( 'customize_register', 'itsoft_customize_register' );
