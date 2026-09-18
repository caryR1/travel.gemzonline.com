<?php
/**
 * Plugin Name: Travel Gemz SEO
 * Description: Per-page title, meta description, Open Graph, Twitter Card, and JSON-LD structured data, keyed by page — REST-editable (no Yoast dependency).
 * Version: 1.0.0
 * Author: Travel Gemz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TravelGemz_SEO {

	const META_TITLE       = '_tgz_meta_title';
	const META_DESCRIPTION = '_tgz_meta_description';
	const META_OG_IMAGE    = '_tgz_og_image';
	const META_SCHEMA_TYPE = '_tgz_schema_type';

	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ) );
		add_action( 'wp_head', array( $this, 'output_head_tags' ), 1 );
	}

	public function register_meta() {
		$fields = array(
			self::META_TITLE,
			self::META_DESCRIPTION,
			self::META_OG_IMAGE,
			self::META_SCHEMA_TYPE,
		);
		foreach ( $fields as $field ) {
			register_post_meta( 'page', $field, array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			) );
		}
	}

	private function get_meta( $key ) {
		if ( ! is_singular( 'page' ) ) {
			return '';
		}
		return get_post_meta( get_queried_object_id(), $key, true );
	}

	public function filter_title_parts( $parts ) {
		$custom = $this->get_meta( self::META_TITLE );
		if ( $custom ) {
			$parts = array( 'title' => $custom );
		}
		return $parts;
	}

	public function output_head_tags() {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$title       = $this->get_meta( self::META_TITLE );
		$description = $this->get_meta( self::META_DESCRIPTION );
		$og_image    = $this->get_meta( self::META_OG_IMAGE );
		$schema_type = $this->get_meta( self::META_SCHEMA_TYPE );
		$url         = get_permalink();
		$site_name   = get_bloginfo( 'name' );

		if ( ! $title ) {
			$title = wp_get_document_title();
		}

		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}

		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $description ) {
			echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:type" content="' . ( $schema_type === 'Article' ? 'article' : 'website' ) . '">' . "\n";
		if ( $og_image ) {
			echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
		}

		echo '<meta name="twitter:card" content="' . ( $og_image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $description ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		if ( $og_image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
		}

		$this->output_structured_data( $schema_type, $title, $description, $og_image, $url, $site_name );
	}

	private function output_structured_data( $schema_type, $title, $description, $og_image, $url, $site_name ) {
		if ( ! $schema_type ) {
			return;
		}

		if ( $schema_type === 'WebSite' ) {
			$data = array(
				'@context' => 'https://schema.org',
				'@type'    => 'WebSite',
				'name'     => $site_name,
				'url'      => home_url( '/' ),
			);
		} elseif ( $schema_type === 'TouristDestination' ) {
			$data = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'TouristDestination',
				'name'        => get_the_title(),
				'description' => $description,
				'url'         => $url,
			);
			if ( $og_image ) {
				$data['image'] = $og_image;
			}
		} elseif ( $schema_type === 'Article' ) {
			$data = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'Article',
				'headline'      => get_the_title(),
				'description'   => $description,
				'url'           => $url,
				'datePublished' => get_the_date( 'c' ),
				'dateModified'  => get_the_modified_date( 'c' ),
				'publisher'     => array(
					'@type' => 'Organization',
					'name'  => $site_name,
				),
			);
			if ( $og_image ) {
				$data['image'] = $og_image;
			}
		} elseif ( in_array( $schema_type, array( 'CollectionPage', 'AboutPage', 'ContactPage', 'WebPage' ), true ) ) {
			$data = array(
				'@context'    => 'https://schema.org',
				'@type'       => $schema_type,
				'name'        => get_the_title(),
				'description' => $description,
				'url'         => $url,
			);
		} else {
			return;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}

new TravelGemz_SEO();
