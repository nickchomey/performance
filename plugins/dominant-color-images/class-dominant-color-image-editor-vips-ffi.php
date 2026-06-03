<?php
/**
 * Dominant Color Image Editor for VIPS FFI
 *
 * Extends the VIPS image editor with dominant color detection, transparency
 * detection, and CSS-only LQIP gradient generation methods.
 *
 * This mirrors how Dominant_Color_Image_Editor_GD and
 * Dominant_Color_Image_Editor_Imagick extend their respective editors.
 *
 * @package dominant-color-images
 * @since 1.3.0
 */

declare( strict_types = 1 );

// Bail if the VIPS editor is not available.
if ( ! class_exists( 'NotGlossy\\VipsImageEditorFFI\\Image_Editor_Vips_FFI' ) ) {
	return;
}

/**
 * Class Dominant_Color_Image_Editor_Vips_FFI
 *
 * @since 1.3.0
 *
 * @see WP_Image_Editor
 */
class Dominant_Color_Image_Editor_Vips_FFI extends \NotGlossy\VipsImageEditorFFI\Image_Editor_Vips_FFI {

	/**
	 * Get the dominant colour of the image as raw RGB values.
	 *
	 * Resizes the image to 1×1 and returns the averaged pixel as an
	 * associative RGB array. The caller should convert to hex via
	 * dominant_color_rgb_to_hex() when storing in metadata.
	 *
	 * @since 1.3.0
	 *
	 * @return array{r: int, g: int, b: int}|\WP_Error RGB values (0-255), or WP_Error on failure.
	 */
	public function get_dominant_color() {
		if ( ! (bool) $this->image ) {
			return new \WP_Error(
				'image_editor_dominant_color_error_no_image',
				__( 'Dominant color detection no image found.', 'dominant-color-images' )
			);
		}

		try {
			$thumb = $this->image->thumbnail_image( 1, array( 'height' => 1 ) );
			$pixel = $thumb->getpoint( 0, 0 );

			return array(
				'r' => (int) $pixel[0],
				'g' => (int) $pixel[1],
				'b' => (int) $pixel[2],
			);


		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'image_editor_dominant_color_error',
				sprintf(
					__( 'Dominant color detection failed: %s', 'dominant-color-images' ),
					$e->getMessage()
				)
			);
		}
	}


	/**
	 * Looks for transparent pixels in the image.
	 *
	 * @since 1.3.0
	 *
	 * @return bool|\WP_Error True if transparency found, false otherwise, WP_Error on failure.
	 */
	public function has_transparency() {
		if ( ! (bool) $this->image ) {
			return new \WP_Error(
				'image_editor_has_transparency_error_no_image',
				__( 'Transparency detection no image found.', 'dominant-color-images' )
			);
		}

		try {
			if ( ! $this->image->hasAlpha() ) {
				return false;
			}

			$alpha = $this->image->extract_band( $this->image->bands - 1 );
			$min   = (float) $alpha->min();

			return $min < 255.0;
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'image_editor_has_transparency_error',
				sprintf(
					__( 'Transparency detection failed: %s', 'dominant-color-images' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get the 3×2 grid pixel values from the image.
	 *
	 * The grid is a 3-column × 2-row sampling of the image, resized to
	 * exactly 6 pixels. Each cell's raw RGB values are returned for use
	 * in LQIP generation.
	 *
	 * @since 1.3.0
	 *
	 * @return array<int, array{r: int, g: int, b: int}> 6 grid cells as ['r'=>R, 'g'=>G, 'b'=>B].
	 */
	public function get_lqip_grid_values(): array {

		// Skip LQIP generation for images with transparency (the gradient
		// placeholder would show through transparent areas).

		if ( $this->has_transparency()) {
			return array();
		}

		$image = $this->image;

		$scale_x = 3.0 / $image->width;
		$scale_y = 2.0 / $image->height;
		$small   = $image->resize( $scale_x, array( 'vscale' => $scale_y ) );
		$small   = $small->sharpen( array( 'sigma' => 0.5 ) );

		if ( $small->hasAlpha() ) {
			$small = $small->flatten();
		}

		// Cell positions: left-to-right, top-to-bottom.
		$cell_positions = array(
			array( 0, 0 ),
			array( 1, 0 ),
			array( 2, 0 ),
			array( 0, 1 ),
			array( 1, 1 ),
			array( 2, 1 ),
		);

		$values = array();

		foreach ( $cell_positions as $pos ) {
			$pixel = $small->getpoint( $pos[0], $pos[1] );
			$values[] = array(
				'r' => (int) $pixel[0],
				'g' => (int) $pixel[1],
				'b' => (int) $pixel[2],
			);
		}

		return $values;
	}
}
