<?php
/**
 * What the plugin can do to an image.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress;

use PhotosSh\BlurFacesResult;
use PhotosSh\FixResult;
use PhotosSh\ImageResult;
use PhotosSh\Photos;
use PhotosSh\RemoveTextResult;
use PhotosSh\RestoreFacesResult;

defined( 'ABSPATH' ) || exit;

/**
 * One operation with its parameters: remove the background, upscale 2× or
 * 4×, remove text, fix light and colour, remove noise, colourise, restore
 * faces, hide faces, fix for a marketplace, or write alt text.
 *
 * An operation has a key (`remove-background`, `upscale-2`, `remove-text`,
 * `fix-amazon`) that names it in URLs, bulk actions and queued tasks.
 *
 * All of them make a new image, except "write alt text": it makes no image
 * and is done by AltText, not by run().
 */
final class Operation {

	public const REMOVE_BACKGROUND = 'remove-background';
	public const UPSCALE           = 'upscale';
	public const REMOVE_TEXT       = 'remove-text';
	public const ENHANCE           = 'enhance';
	public const DENOISE           = 'denoise';
	public const COLORIZE          = 'colorize';
	public const RESTORE_FACES     = 'restore-faces';
	public const BLUR_FACES        = 'blur-faces';
	public const FIX               = 'fix';
	public const ALT_TEXT          = 'alt-text';

	public const SCALES = [ 2, 4 ];

	/**
	 * The marketplaces of the API's `/v1/fix`, by name.
	 */
	public const MARKETPLACES = [
		'amazon'  => 'Amazon',
		'ebay'    => 'eBay',
		'etsy'    => 'Etsy',
		'shopify' => 'Shopify',
		'google'  => 'Google Merchant Center',
		'allegro' => 'Allegro',
		'zalando' => 'Zalando',
	];

	/**
	 * The models remove background can find the subject with, by name.
	 * Each costs the same.
	 */
	public const MODELS = [
		'atlas' => 'Atlas',
		'lumen' => 'Lumen',
		'muse'  => 'Muse',
	];

	/**
	 * The API's default model. It is not sent: the API uses it when no
	 * model is given.
	 */
	public const DEFAULT_MODEL = 'atlas';

	/**
	 * The edge settings of remove background, each with the lowest and the
	 * highest value the API takes. Each is 0 by default. A 0 is not sent:
	 * the API then leaves the edge as it is.
	 */
	public const EDGES = [
		'shift'    => [ -10, 10 ],
		'hardness' => [ 0, 100 ],
		'smooth'   => [ 0, 10 ],
	];

	/**
	 * One of the operation constants.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * The upscale factor, for an upscale.
	 *
	 * @var int|null
	 */
	public ?int $scale;

	/**
	 * The marketplace's name, for a fix.
	 *
	 * @var string|null
	 */
	public ?string $marketplace;

	/**
	 * For alt text: whether text that is already there is replaced.
	 *
	 * @var bool
	 */
	public bool $replace;

	/**
	 * For remove background: whether the photo is a studio shot, so that
	 * the box, case or lid shown with the product is kept too. Null leaves
	 * it to the "Studio shots" setting.
	 *
	 * @var bool|null
	 */
	public ?bool $studio;

	/**
	 * For remove background: the model that finds the subject, a key of
	 * MODELS. Null leaves it to the "Model" setting.
	 *
	 * @var string|null
	 */
	public ?string $model;

	/**
	 * For remove background: how far the cutout's edge is moved, in pixels,
	 * from -10 (inward) to 10 (outward). Null leaves it to the "Edge shift"
	 * setting.
	 *
	 * @var int|float|null
	 */
	public int|float|null $shift;

	/**
	 * For remove background: how crisp the edge is, from 0 to 100 (about a
	 * pixel wide). Null leaves it to the "Edge hardness" setting.
	 *
	 * @var int|float|null
	 */
	public int|float|null $hardness;

	/**
	 * For remove background: how much the ripples of the outline are ironed
	 * out, from 0 to 10. Null leaves it to the "Edge smoothing" setting.
	 *
	 * @var int|float|null
	 */
	public int|float|null $smooth;

	/**
	 * Use the named constructors.
	 *
	 * @param string         $name        One of the operation constants.
	 * @param int|null       $scale       The upscale factor.
	 * @param string|null    $marketplace The marketplace's name.
	 * @param bool           $replace     For alt text: whether text that is already there is replaced.
	 * @param bool|null      $studio      For remove background: whether the photo is a studio shot.
	 * @param string|null    $model       For remove background: the model, a key of MODELS.
	 * @param int|float|null $shift       For remove background: how far the edge is moved, in pixels.
	 * @param int|float|null $hardness    For remove background: how crisp the edge is.
	 * @param int|float|null $smooth      For remove background: how much the outline is smoothed.
	 */
	private function __construct( string $name, ?int $scale = null, ?string $marketplace = null, bool $replace = false, ?bool $studio = null, ?string $model = null, int|float|null $shift = null, int|float|null $hardness = null, int|float|null $smooth = null ) {
		$this->name        = $name;
		$this->scale       = $scale;
		$this->marketplace = $marketplace;
		$this->replace     = $replace;
		$this->studio      = $studio;
		$this->model       = $model;
		$this->shift       = $shift;
		$this->hardness    = $hardness;
		$this->smooth      = $smooth;
	}

	/**
	 * Remove the background.
	 *
	 * @param bool|null      $studio   Whether the photo is a studio shot (a product on a plain light
	 *                                 backdrop), so that the box, case or lid shown with the product
	 *                                 is kept too. Null leaves it to the "Studio shots" setting.
	 * @param string|null    $model    The model that finds the subject, a key of MODELS. Null leaves
	 *                                 it to the "Model" setting.
	 * @param int|float|null $shift    Moves the cutout's edge, in pixels, from -10 to 10: inward when
	 *                                 negative, outward when positive. Null leaves it to the "Edge
	 *                                 shift" setting.
	 * @param int|float|null $hardness Makes the edge crisper, from 0 to 100. Null leaves it to the
	 *                                 "Edge hardness" setting.
	 * @param int|float|null $smooth   Irons out ripples in the outline, from 0 to 10. Null leaves it
	 *                                 to the "Edge smoothing" setting.
	 * @throws \InvalidArgumentException For an unknown model, or an edge setting outside its range.
	 */
	public static function remove_background( ?bool $studio = null, ?string $model = null, int|float|null $shift = null, int|float|null $hardness = null, int|float|null $smooth = null ): self {
		if ( null !== $model ) {
			$model = strtolower( trim( $model ) );
			if ( ! isset( self::MODELS[ $model ] ) ) {
				throw new \InvalidArgumentException( 'Unknown model.' );
			}
		}

		$edges = [
			'shift'    => $shift,
			'hardness' => $hardness,
			'smooth'   => $smooth,
		];
		foreach ( $edges as $name => $value ) {
			if ( null === $value ) {
				continue;
			}
			$edges[ $name ] = self::edge_value( $name, $value );
			if ( null === $edges[ $name ] ) {
				throw new \InvalidArgumentException( 'An edge setting is outside its range.' );
			}
		}

		return new self( self::REMOVE_BACKGROUND, null, null, false, $studio, $model, $edges['shift'], $edges['hardness'], $edges['smooth'] );
	}

	/**
	 * What a remove background model is recommended for, in a sentence or
	 * two for the settings page. Empty for an unknown model.
	 *
	 * @param string $model A key of MODELS.
	 */
	public static function model_description( string $model ): string {
		switch ( $model ) {
			case 'atlas':
				return __( 'Everyday product photos: a product alone or with its box, on any backdrop.', 'photos-sh' );
			case 'lumen':
				return __( 'Pale packaging on a light backdrop, such as a cream or wooden box beside a bottle, and close-ups where the product fills the photo. On those, Atlas can keep the bottle and drop the box. It may keep a dark backdrop at the photo\'s edge, and can cut away part of an open frame or wire stand.', 'photos-sh' );
			case 'muse':
				return __( 'People: portraits, and models wearing the product.', 'photos-sh' );
			default:
				return '';
		}
	}

	/**
	 * The model to send to the API for remove background, or null to send
	 * none: when no model is set, or it is Atlas, the API's default.
	 */
	public function api_model(): ?string {
		return null !== $this->model && self::DEFAULT_MODEL !== $this->model ? $this->model : null;
	}

	/**
	 * The number a value gives for an edge setting of remove background,
	 * or null when it is not a number in the setting's range. A whole
	 * number comes back as an integer.
	 *
	 * @param string $name  A key of EDGES.
	 * @param mixed  $value A number, or a string that holds one.
	 */
	public static function edge_value( string $name, $value ): int|float|null {
		if ( ! isset( self::EDGES[ $name ] ) || ! is_numeric( $value ) ) {
			return null;
		}
		$number        = (float) $value;
		[ $min, $max ] = self::EDGES[ $name ];
		if ( ! is_finite( $number ) || $number < $min || $number > $max ) {
			return null;
		}

		return floor( $number ) === $number ? (int) $number : $number;
	}

	/**
	 * The edge settings to send to the API for remove background, by name:
	 * those that are set and are not 0, the API's default.
	 *
	 * @return array<string, int|float>
	 */
	public function api_edges(): array {
		$set = [
			'shift'    => $this->shift,
			'hardness' => $this->hardness,
			'smooth'   => $this->smooth,
		];

		$edges = [];
		foreach ( $set as $name => $value ) {
			if ( null !== $value && 0.0 !== (float) $value ) {
				$edges[ $name ] = $value;
			}
		}

		return $edges;
	}

	/**
	 * Upscale.
	 *
	 * @param int $scale 2 or 4.
	 * @throws \InvalidArgumentException For another scale.
	 */
	public static function upscale( int $scale ): self {
		if ( ! in_array( $scale, self::SCALES, true ) ) {
			throw new \InvalidArgumentException( 'The scale must be 2 or 4.' );
		}

		return new self( self::UPSCALE, $scale );
	}

	/**
	 * Remove text, watermarks and captions.
	 */
	public static function remove_text(): self {
		return new self( self::REMOVE_TEXT );
	}

	/**
	 * Fix light and colour, automatically.
	 */
	public static function enhance(): self {
		return new self( self::ENHANCE );
	}

	/**
	 * Remove noise and JPEG artefacts.
	 */
	public static function denoise(): self {
		return new self( self::DENOISE );
	}

	/**
	 * Colour a black-and-white photo.
	 */
	public static function colorize(): self {
		return new self( self::COLORIZE );
	}

	/**
	 * Redraw the faces of an old, blurred or noisy photo sharp.
	 */
	public static function restore_faces(): self {
		return new self( self::RESTORE_FACES );
	}

	/**
	 * Hide every face, blurred.
	 */
	public static function blur_faces(): self {
		return new self( self::BLUR_FACES );
	}

	/**
	 * Fix for a marketplace.
	 *
	 * @param string $marketplace A key of MARKETPLACES.
	 * @throws \InvalidArgumentException For an unknown marketplace.
	 */
	public static function fix( string $marketplace ): self {
		$marketplace = strtolower( trim( $marketplace ) );
		if ( ! isset( self::MARKETPLACES[ $marketplace ] ) ) {
			throw new \InvalidArgumentException( 'Unknown marketplace.' );
		}

		return new self( self::FIX, null, $marketplace );
	}

	/**
	 * Write the image's alternative text. It makes no image.
	 *
	 * @param bool $replace Whether to replace text that is already there.
	 */
	public static function alt_text( bool $replace = false ): self {
		return new self( self::ALT_TEXT, null, null, $replace );
	}

	/**
	 * The operation a key names, or null when the key names none.
	 *
	 * @param string $key Such as `upscale-2` or `fix-amazon`.
	 */
	public static function from_key( string $key ): ?self {
		foreach ( self::all() as $operation ) {
			if ( $operation->key() === $key ) {
				return $operation;
			}
		}

		return null;
	}

	/**
	 * Every operation the plugin offers, in menu order.
	 *
	 * @return list<self>
	 */
	public static function all(): array {
		$all = [ self::remove_background() ];
		foreach ( self::SCALES as $scale ) {
			$all[] = self::upscale( $scale );
		}
		$all[] = self::remove_text();
		$all[] = self::enhance();
		$all[] = self::denoise();
		$all[] = self::colorize();
		$all[] = self::restore_faces();
		$all[] = self::blur_faces();
		foreach ( array_keys( self::MARKETPLACES ) as $marketplace ) {
			$all[] = self::fix( $marketplace );
		}
		$all[] = self::alt_text( false );
		$all[] = self::alt_text( true );

		return $all;
	}

	/**
	 * The key that names this operation.
	 */
	public function key(): string {
		if ( self::UPSCALE === $this->name ) {
			return self::UPSCALE . '-' . $this->scale;
		}
		if ( self::FIX === $this->name ) {
			return self::FIX . '-' . $this->marketplace;
		}
		if ( self::ALT_TEXT === $this->name && $this->replace ) {
			return self::ALT_TEXT . '-replace';
		}

		return $this->name;
	}

	/**
	 * Whether the operation makes a new image. "Write alt text" does not.
	 */
	public function makes_image(): bool {
		return self::ALT_TEXT !== $this->name;
	}

	/**
	 * The parameters, for the result attachment's meta.
	 *
	 * @return array<string, int|float|string|bool>
	 */
	public function params(): array {
		if ( self::REMOVE_BACKGROUND === $this->name ) {
			$params = [];
			if ( true === $this->studio ) {
				$params['studio'] = true;
			}
			$model = $this->api_model();
			if ( null !== $model ) {
				$params['model'] = $model;
			}

			return $params + $this->api_edges();
		}
		if ( null !== $this->scale ) {
			return [ 'scale' => $this->scale ];
		}
		if ( null !== $this->marketplace ) {
			return [ 'marketplace' => $this->marketplace ];
		}
		if ( self::ALT_TEXT === $this->name ) {
			return [ 'replace' => $this->replace ];
		}

		return [];
	}

	/**
	 * The end of a result's file name: `shoe-no-bg.png`. Empty for alt
	 * text, which makes no file.
	 */
	public function suffix(): string {
		switch ( $this->name ) {
			case self::REMOVE_BACKGROUND:
				return 'no-bg';
			case self::UPSCALE:
				return 'upscaled-' . $this->scale . 'x';
			case self::REMOVE_TEXT:
				return 'no-text';
			case self::ENHANCE:
				return 'enhanced';
			case self::DENOISE:
				return 'no-noise';
			case self::COLORIZE:
				return 'colourised';
			case self::RESTORE_FACES:
				return 'faces-restored';
			case self::BLUR_FACES:
				return 'faces-hidden';
			default:
				return (string) $this->marketplace;
		}
	}

	/**
	 * The action's name, for menus and links.
	 */
	public function label(): string {
		switch ( $this->name ) {
			case self::REMOVE_BACKGROUND:
				return __( 'Remove background', 'photos-sh' );
			case self::UPSCALE:
				/* translators: %d: the upscale factor, 2 or 4. */
				return sprintf( __( 'Upscale %d×', 'photos-sh' ), (int) $this->scale );
			case self::REMOVE_TEXT:
				return __( 'Remove text', 'photos-sh' );
			case self::ENHANCE:
				return __( 'Fix light and colour', 'photos-sh' );
			case self::DENOISE:
				return __( 'Remove noise', 'photos-sh' );
			case self::COLORIZE:
				return __( 'Colourise', 'photos-sh' );
			case self::RESTORE_FACES:
				return __( 'Restore faces', 'photos-sh' );
			case self::BLUR_FACES:
				return __( 'Hide faces', 'photos-sh' );
			case self::ALT_TEXT:
				return $this->replace ? __( 'Replace alt text', 'photos-sh' ) : __( 'Write alt text', 'photos-sh' );
			default:
				/* translators: %s: a marketplace's name, such as Amazon. */
				return sprintf( __( 'Fix for %s', 'photos-sh' ), $this->marketplace_title() );
		}
	}

	/**
	 * The action's name in the bulk actions menu. For alt text it says what
	 * happens to text that is already there, because the menu is the only
	 * place to say it.
	 */
	public function bulk_label(): string {
		if ( self::ALT_TEXT !== $this->name ) {
			return $this->label();
		}

		return $this->replace
			? __( 'Write alt text (replace existing text)', 'photos-sh' )
			: __( 'Write alt text (keep existing text)', 'photos-sh' );
	}

	/**
	 * What was done, for a result's title: "Shoe (background removed)".
	 * Empty for alt text, which makes no image.
	 */
	public function done_label(): string {
		switch ( $this->name ) {
			case self::REMOVE_BACKGROUND:
				return __( 'background removed', 'photos-sh' );
			case self::UPSCALE:
				/* translators: %d: the upscale factor, 2 or 4. */
				return sprintf( __( 'upscaled %d×', 'photos-sh' ), (int) $this->scale );
			case self::REMOVE_TEXT:
				return __( 'text removed', 'photos-sh' );
			case self::ENHANCE:
				return __( 'light and colour fixed', 'photos-sh' );
			case self::DENOISE:
				return __( 'noise removed', 'photos-sh' );
			case self::COLORIZE:
				return __( 'colourised', 'photos-sh' );
			case self::RESTORE_FACES:
				return __( 'faces restored', 'photos-sh' );
			case self::BLUR_FACES:
				return __( 'faces hidden', 'photos-sh' );
			case self::ALT_TEXT:
				return '';
			default:
				/* translators: %s: a marketplace's name, such as Amazon. */
				return sprintf( __( 'fixed for %s', 'photos-sh' ), $this->marketplace_title() );
		}
	}

	/**
	 * One sentence an editor should read before relying on the operation,
	 * or an empty string when there is nothing to warn about.
	 */
	public function caution(): string {
		switch ( $this->name ) {
			case self::DENOISE:
				return __( 'Remove noise is slow, and takes photos up to 12 megapixels.', 'photos-sh' );
			case self::COLORIZE:
				return __( 'Colourise guesses the colours, so some of them can be wrong.', 'photos-sh' );
			case self::RESTORE_FACES:
				return __( 'Restore faces invents the detail it adds: do not use it for identity photos or evidence.', 'photos-sh' );
			case self::BLUR_FACES:
				return __( 'Hide faces blurs the faces it finds; a face it does not find still shows.', 'photos-sh' );
			default:
				return '';
		}
	}

	/**
	 * The marketplace's display name, for a fix.
	 */
	public function marketplace_title(): string {
		return self::MARKETPLACES[ (string) $this->marketplace ] ?? (string) $this->marketplace;
	}

	/**
	 * Runs the operation on an image file through the API. Remove
	 * background sends `studio` only when the operation's is true, `model`
	 * only for a model other than Atlas, the API's default, and `shift`,
	 * `hardness` and `smooth` only when they are not 0.
	 *
	 * @param Photos $photos The API client.
	 * @param string $path   The image file.
	 * @throws \PhotosSh\Exception\PhotosException When the API or the connection fails.
	 * @throws \LogicException                     For alt text, which makes no image.
	 */
	public function run( Photos $photos, string $path ): ImageResult {
		switch ( $this->name ) {
			case self::REMOVE_BACKGROUND:
				$edges = $this->api_edges();

				return $photos->removeBackground(
					$path,
					studio: true === $this->studio ? true : null,
					model: $this->api_model(),
					shift: $edges['shift'] ?? null,
					hardness: $edges['hardness'] ?? null,
					smooth: $edges['smooth'] ?? null,
				);
			case self::UPSCALE:
				return $photos->upscale( $path, $this->scale );
			case self::REMOVE_TEXT:
				return $photos->removeText( $path );
			case self::ENHANCE:
				return $photos->enhance( $path, true );
			case self::DENOISE:
				return $photos->denoise( $path );
			case self::COLORIZE:
				return $photos->colorize( $path );
			case self::RESTORE_FACES:
				return $photos->restoreFaces( $path );
			case self::BLUR_FACES:
				return $photos->blurFaces( $path, 'blur' );
			case self::ALT_TEXT:
				throw new \LogicException( 'Writing alt text makes no image.' );
			default:
				return $photos->fix( $path, (string) $this->marketplace );
		}
	}

	/**
	 * What the API said about a result beyond the image, for the result
	 * attachment's meta.
	 *
	 * The number of faces is left out when the API did not send it: the
	 * library reads a missing `X-Faces` as 0, which would look like "no
	 * face was found".
	 *
	 * @param ImageResult $result The API's result.
	 * @return array<string, mixed>
	 */
	public static function details( ImageResult $result ): array {
		if ( $result instanceof FixResult ) {
			return [
				'passed'   => $result->passed,
				'findings' => $result->findings,
			];
		}
		if ( $result instanceof RemoveTextResult ) {
			return [
				'removed' => $result->removed,
				'kept'    => $result->kept,
			];
		}
		if ( $result instanceof BlurFacesResult && null !== $result->header( 'X-Faces' ) ) {
			return [ 'faces' => $result->faces ];
		}
		if ( $result instanceof RestoreFacesResult && null !== $result->header( 'X-Faces' ) ) {
			return [
				'faces'   => $result->faces,
				'skipped' => $result->facesSkipped,
			];
		}

		return [];
	}

	/**
	 * Whether the API said it found nothing to work on: no face to hide or
	 * to restore. The image it returned is then the same as the source.
	 *
	 * @param array<string, mixed> $details What details() gave.
	 */
	public function found_nothing( array $details ): bool {
		return in_array( $this->name, [ self::BLUR_FACES, self::RESTORE_FACES ], true )
			&& isset( $details['faces'] )
			&& is_numeric( $details['faces'] )
			&& 0 === (int) $details['faces'];
	}

	/**
	 * What the API said was done, in words for the editor: how many faces
	 * were hidden or restored. Empty when it said nothing of the kind.
	 *
	 * @param array<string, mixed> $details What details() gave, as kept in the result attachment's meta.
	 */
	public function result_note( array $details ): string {
		if ( ! isset( $details['faces'] ) || ! is_numeric( $details['faces'] ) ) {
			return '';
		}
		$faces = (int) $details['faces'];

		if ( self::BLUR_FACES === $this->name ) {
			if ( 0 === $faces ) {
				return __( 'No face was found.', 'photos-sh' );
			}

			/* translators: %d: a number of faces. */
			return sprintf( _n( '%d face was hidden.', '%d faces were hidden.', $faces, 'photos-sh' ), $faces );
		}

		if ( self::RESTORE_FACES === $this->name ) {
			$skipped = isset( $details['skipped'] ) && is_numeric( $details['skipped'] ) ? (int) $details['skipped'] : 0;
			if ( 0 === $faces && 0 === $skipped ) {
				return __( 'No face was found.', 'photos-sh' );
			}

			$note = __( 'No face was restored.', 'photos-sh' );
			if ( $faces > 0 ) {
				/* translators: %d: a number of faces. */
				$note = sprintf( _n( '%d face was restored.', '%d faces were restored.', $faces, 'photos-sh' ), $faces );
			}
			if ( $skipped > 0 ) {
				/* translators: %d: a number of faces. */
				$note .= ' ' . sprintf( _n( '%d face is large enough already and was left as it is.', '%d faces are large enough already and were left as they are.', $skipped, 'photos-sh' ), $skipped );
			}

			return $note;
		}

		return '';
	}
}
