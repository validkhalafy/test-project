<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Per-entry SEO fields and lightweight metadata when no SEO plugin is active. */
final class Artiey_AET_SEO_Meta {
	const TYPES = array( 'artiey_gallery', 'artiey_artist', 'artiey_artwork', 'artiey_exhibition' );

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'output' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'breadcrumbs' ), 31 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
	}

	public static function add_box() {
		foreach ( self::TYPES as $type ) {
			add_meta_box( 'artiey_aet_seo', 'سئوی این صفحه', array( __CLASS__, 'box' ), $type, 'normal', 'default' );
		}
	}

	public static function box( $post ) {
		wp_nonce_field( 'artiey_aet_seo_save', 'artiey_aet_seo_nonce' );
		$title = get_post_meta( $post->ID, '_aet_seo_title', true );
		$desc  = get_post_meta( $post->ID, '_aet_seo_description', true );
		echo '<p><label for="aet-seo-title"><strong>عنوان سئو</strong></label><br><input id="aet-seo-title" class="widefat" type="text" name="_aet_seo_title" maxlength="70" value="' . esc_attr( $title ) . '"><span class="description">خالی بماند تا عنوان صفحه از عنوان نوشته ساخته شود.</span></p>';
		$noindex = '1' === (string) get_post_meta( $post->ID, '_aet_seo_noindex', true );
		echo '<p><label><input type="checkbox" name="_aet_seo_noindex" value="1"' . checked( $noindex, true, false ) . '> <strong>این صفحه در گوگل نمایش داده نشود (noindex)</strong></label></p>';
		echo '<p><label for="aet-seo-description"><strong>توضیح متا</strong></label><br><textarea id="aet-seo-description" class="widefat" rows="3" maxlength="320" name="_aet_seo_description">' . esc_textarea( $desc ) . '</textarea><span class="description">خالی بماند تا خلاصهٔ نوشته یا معرفی پروفایل استفاده شود.</span></p>';
	}

	public static function save( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::TYPES, true ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $_POST['artiey_aet_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['artiey_aet_seo_nonce'] ) ), 'artiey_aet_seo_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['_aet_seo_noindex'] ) ) {
			update_post_meta( $post_id, '_aet_seo_noindex', '1' );
		} else {
			delete_post_meta( $post_id, '_aet_seo_noindex' );
		}
		foreach ( array( '_aet_seo_title' => 70, '_aet_seo_description' => 320 ) as $key => $length ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$value = Artiey_AET_Text::substr( $value, 0, $length );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	private static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || function_exists( 'the_seo_framework' ) || class_exists( 'SlimSEO\\Core', false );
	}

	private static function description( $id ) {
		$description = trim( (string) get_post_meta( $id, '_aet_seo_description', true ) );
		if ( '' === $description ) {
			$description = trim( (string) get_the_excerpt( $id ) );
		}
		if ( '' === $description ) {
			$type = get_post_type( $id );
			if ( 'artiey_gallery' === $type ) {
				$data = Artiey_AET_Gallery::get( $id );
				$description = $data['short_bio'];
			} elseif ( 'artiey_artist' === $type ) {
				$data = Artiey_AET_Artist::get( $id );
				$description = $data['short_bio'];
			} elseif ( 'artiey_artwork' === $type ) {
				$data = Artiey_AET_Artwork::get( $id );
				$description = implode( ' — ', array_filter( array( $data['title'], $data['year'], $data['medium'], $data['material'] ) ) );
			} elseif ( 'artiey_exhibition' === $type ) {
				$gid   = (int) get_post_meta( $id, '_ae_gallery', true );
				$parts = array( get_the_title( $id ), Artiey_AET_Exhibition::dates_text( $id ), $gid ? get_the_title( $gid ) : '' );
				$description = implode( ' — ', array_filter( $parts ) );
			}
			if ( '' === trim( (string) $description ) ) {
				$post        = get_post( $id );
				$description = $post ? wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 40, '…' ) : '';
			}
		}
		$description = trim( wp_strip_all_tags( (string) $description ) );
		return Artiey_AET_Text::substr( $description, 0, 160 );
	}

	public static function document_title( $title ) {
		if ( self::has_seo_plugin() || ! is_singular( self::TYPES ) ) {
			return $title;
		}
		$custom = trim( (string) get_post_meta( get_queried_object_id(), '_aet_seo_title', true ) );
		return '' !== $custom ? $custom : $title;
	}

	public static function output() {
		if ( self::has_seo_plugin() || ! is_singular( self::TYPES ) ) {
			return;
		}
		$id          = get_queried_object_id();
		$title       = trim( (string) get_post_meta( $id, '_aet_seo_title', true ) );
		$title       = '' !== $title ? $title : get_the_title( $id );
		$description = self::description( $id );
		$image       = get_the_post_thumbnail_url( $id, 'large' );
		$type        = 'artiey_exhibition' === get_post_type( $id ) ? 'event' : 'article';
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( get_permalink( $id ) ) . '">' . "\n";
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_locale() ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_post_modified_time( 'c', true, $id ) ) . '">' . "\n";
		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
		}
	}

	/** noindex for editor-flagged pages and for filtered/paged directory views (duplicate content). */
	public static function robots( $robots ) {
		$filtered = false;
		foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, keys only.
			if ( is_string( $key ) && 0 === strpos( $key, 'aet_' ) ) {
				$filtered = true;
				break;
			}
		}
		$flagged = is_singular( self::TYPES ) && '1' === (string) get_post_meta( get_queried_object_id(), '_aet_seo_noindex', true );
		if ( $filtered || $flagged ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/** BreadcrumbList for single galleries, artists, artworks and exhibitions. */
	public static function breadcrumbs() {
		if ( ! is_singular( self::TYPES ) || ! Artiey_AET_Settings::get( 'schema' ) ) {
			return;
		}
		$id    = get_queried_object_id();
		$items = array( array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ) );
		$obj   = get_post_type_object( get_post_type( $id ) );
		$arch  = get_post_type_archive_link( get_post_type( $id ) );
		if ( $obj && $arch ) {
			$items[] = array( 'name' => $obj->labels->name, 'url' => $arch );
		}
		$items[] = array( 'name' => get_the_title( $id ), 'url' => get_permalink( $id ) );
		$list    = array();
		foreach ( $items as $i => $item ) {
			$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $item['name'] ), 'item' => $item['url'] );
		}
		$json = wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( $json ) {
			echo "\n" . '<script type="application/ld+json">' . str_replace( '</', '<\/', $json ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, '</' escaped.
		}
	}
}
