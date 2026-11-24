<?php
/**
 * WP-CLI commands.
 *
 * @package PhotosSh\WordPress
 */

namespace PhotosSh\WordPress\Cli;

use PhotosSh\WordPress\AltText;
use PhotosSh\WordPress\ClientFactory;
use PhotosSh\WordPress\ErrorMessages;
use PhotosSh\WordPress\Operation;
use PhotosSh\WordPress\Processor;
use PhotosSh\WordPress\SkippedException;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Processes media library images with Photos.sh.
 *
 * Each result is saved as a new attachment; the original is not changed.
 * `alt-text` makes no image: it writes the attachment's alternative text.
 */
final class Command {

	/**
	 * Processes attachments.
	 *
	 * @var Processor
	 */
	private Processor $processor;

	/**
	 * Writes alternative text.
	 *
	 * @var AltText
	 */
	private AltText $alt_text;

	/**
	 * Makes the API clients.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $clients;

	/**
	 * Sets the command up.
	 *
	 * @param Processor     $processor Processes attachments.
	 * @param AltText       $alt_text  Writes alternative text.
	 * @param ClientFactory $clients   Makes the API clients.
	 */
	public function __construct( Processor $processor, AltText $alt_text, ClientFactory $clients ) {
		$this->processor = $processor;
		$this->alt_text  = $alt_text;
		$this->clients   = $clients;
	}

	/**
	 * Removes the background of images.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--studio]
	 * : The photos are studio shots (a product on a plain light backdrop, as in a light tent): also keep the box, case or lid shown with the product. Overrides the "Studio shots" setting for this run; --no-studio turns it off for this run.
	 *
	 * [--model=<model>]
	 * : The model that finds the subject: atlas (everyday product photos), lumen (pale packaging on a light backdrop, and close-ups where the product fills the photo) or muse (people: portraits, and models wearing the product). Each costs the same. Overrides the "Model" setting for this run.
	 * ---
	 * options:
	 *   - atlas
	 *   - lumen
	 *   - muse
	 * ---
	 *
	 * [--shift=<px>]
	 * : Moves the cutout's edge, in pixels, from -10 to 10: inward when negative, outward when positive. A shift of -1 to -2 trims the light rim a dark product shows on a dark page. Overrides the "Edge shift" setting for this run.
	 *
	 * [--hardness=<amount>]
	 * : Makes the edge crisper, from 0 (as it is) to 100 (about a pixel wide). On a dark page, pair it with a small negative --shift. Overrides the "Edge hardness" setting for this run.
	 *
	 * [--smooth=<amount>]
	 * : Irons out ripples in the outline, from 0 (as it is) to 10. From about 4 it rounds small corners and can break thin parts such as a wire stand. Overrides the "Edge smoothing" setting for this run.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh remove-background 12 13
	 *     wp photos-sh remove-background 14 --studio
	 *     wp photos-sh remove-background 15 --model=lumen
	 *     wp photos-sh remove-background 16 --shift=-1.5 --hardness=50
	 *
	 * @subcommand remove-background
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function remove_background( array $args, array $assoc_args ): void {
		$studio = isset( $assoc_args['studio'] ) ? filter_var( $assoc_args['studio'], FILTER_VALIDATE_BOOLEAN ) : null;
		$model  = isset( $assoc_args['model'] ) && is_string( $assoc_args['model'] ) ? $assoc_args['model'] : null;

		$edges = [
			'shift'    => null,
			'hardness' => null,
			'smooth'   => null,
		];
		foreach ( Operation::EDGES as $name => [ $min, $max ] ) {
			if ( ! isset( $assoc_args[ $name ] ) ) {
				continue;
			}
			$edges[ $name ] = Operation::edge_value( $name, $assoc_args[ $name ] );
			if ( null === $edges[ $name ] ) {
				WP_CLI::error( sprintf( '--%s must be a number from %d to %d.', $name, $min, $max ) );

				return;
			}
		}

		try {
			$operation = Operation::remove_background( $studio, $model, $edges['shift'], $edges['hardness'], $edges['smooth'] );
		} catch ( \InvalidArgumentException $error ) {
			WP_CLI::error( 'Unknown model. Use one of: ' . implode( ', ', array_keys( Operation::MODELS ) ) . '.' );

			return;
		}

		$this->run( $operation, $args, $assoc_args );
	}

	/**
	 * Upscales images with AI.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--scale=<scale>]
	 * : How much larger.
	 * ---
	 * default: 2
	 * options:
	 *   - 2
	 *   - 4
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh upscale 12 --scale=4
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function upscale( array $args, array $assoc_args ): void {
		$scale = $assoc_args['scale'] ?? 2;
		try {
			$operation = Operation::upscale( is_numeric( $scale ) ? (int) $scale : 0 );
		} catch ( \InvalidArgumentException $error ) {
			WP_CLI::error( 'The scale must be 2 or 4.' );

			return;
		}

		$this->run( $operation, $args, $assoc_args );
	}

	/**
	 * Removes text, watermarks and captions from images.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh remove-text 12
	 *
	 * @subcommand remove-text
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function remove_text( array $args, array $assoc_args ): void {
		$this->run( Operation::remove_text(), $args, $assoc_args );
	}

	/**
	 * Fixes the light and colour of images, automatically.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh enhance 12 13
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function enhance( array $args, array $assoc_args ): void {
		$this->run( Operation::enhance(), $args, $assoc_args );
	}

	/**
	 * Removes noise and JPEG artefacts from images.
	 *
	 * It is slow, and takes photos up to 12 megapixels.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh denoise 12
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function denoise( array $args, array $assoc_args ): void {
		$this->run( Operation::denoise(), $args, $assoc_args );
	}

	/**
	 * Colours black-and-white images.
	 *
	 * The colours are guesses, so some of them can be wrong.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh colorize 12
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function colorize( array $args, array $assoc_args ): void {
		$this->run( Operation::colorize(), $args, $assoc_args );
	}

	/**
	 * Redraws the faces in old, blurred or noisy images sharp.
	 *
	 * The detail is invented: do not use it for identity photos or evidence.
	 * An image in which no face is restored is skipped: no attachment is made.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh restore-faces 12
	 *
	 * @subcommand restore-faces
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function restore_faces( array $args, array $assoc_args ): void {
		$this->run( Operation::restore_faces(), $args, $assoc_args );
	}

	/**
	 * Hides the faces in images by blurring them.
	 *
	 * A face that is not found still shows: the number of faces hidden is
	 * printed for each image. An image in which no face is found is skipped:
	 * no attachment is made.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh blur-faces 12
	 *
	 * @subcommand blur-faces
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function blur_faces( array $args, array $assoc_args ): void {
		$this->run( Operation::blur_faces(), $args, $assoc_args );
	}

	/**
	 * Writes the alternative text of images with AI.
	 *
	 * No image is made. The text is in English and can be wrong: read it
	 * before publishing. An image that already has alternative text is
	 * skipped, unless --replace is given.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * [--replace]
	 * : Replace alternative text that is already there.
	 *
	 * [--porcelain]
	 * : Print only the IDs of the attachments whose text was written.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh alt-text 12 13
	 *     wp photos-sh alt-text 12 --replace
	 *
	 * @subcommand alt-text
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function alt_text( array $args, array $assoc_args ): void {
		$this->run( Operation::alt_text( ! empty( $assoc_args['replace'] ) ), $args, $assoc_args );
	}

	/**
	 * Fixes images for a marketplace's image rules.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more image attachment IDs.
	 *
	 * --marketplace=<marketplace>
	 * : The marketplace.
	 * ---
	 * options:
	 *   - amazon
	 *   - ebay
	 *   - etsy
	 *   - shopify
	 *   - google
	 *   - allegro
	 *   - zalando
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the new attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh fix 12 --marketplace=amazon
	 *
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	public function fix( array $args, array $assoc_args ): void {
		$marketplace = $assoc_args['marketplace'] ?? '';
		try {
			$operation = Operation::fix( is_string( $marketplace ) ? $marketplace : '' );
		} catch ( \InvalidArgumentException $error ) {
			WP_CLI::error( 'Unknown marketplace. Use one of: ' . implode( ', ', array_keys( Operation::MARKETPLACES ) ) . '.' );

			return;
		}

		$this->run( $operation, $args, $assoc_args );
	}

	/**
	 * Shows the connected account and the credits left.
	 *
	 * ## EXAMPLES
	 *
	 *     wp photos-sh account
	 *
	 * @param list<string>         $args       Not used.
	 * @param array<string, mixed> $assoc_args Not used.
	 */
	public function account( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		try {
			$account = $this->clients->create( 2 )->account();
		} catch ( \Throwable $error ) {
			WP_CLI::error( ErrorMessages::describe( $error ) );

			return;
		}

		if ( isset( $account['account'] ) ) {
			WP_CLI::line( 'Account: ' . $account['account'] );
		}
		if ( ! empty( $account['unlimited'] ) ) {
			WP_CLI::line( 'Credits: unlimited' );
		} elseif ( isset( $account['credits'] ) ) {
			WP_CLI::line( 'Credits: ' . $account['credits'] );
		}
		foreach ( $account['prices'] ?? [] as $operation => $price ) {
			WP_CLI::line( sprintf( 'Price of %s: %d', $operation, $price ) );
		}
	}

	/**
	 * Runs an operation on each attachment, one after the other.
	 *
	 * @param Operation            $operation  What to do.
	 * @param list<string>         $args       The attachment IDs.
	 * @param array<string, mixed> $assoc_args The flags.
	 */
	private function run( Operation $operation, array $args, array $assoc_args ): void {
		$porcelain = ! empty( $assoc_args['porcelain'] );
		$failed    = 0;
		$skipped   = 0;

		foreach ( $args as $arg ) {
			$attachment_id = is_numeric( $arg ) ? (int) $arg : 0;
			try {
				$line = $operation->makes_image()
					? $this->process( $attachment_id, $operation, $porcelain )
					: $this->write_alt_text( $attachment_id, $operation, $porcelain );
			} catch ( SkippedException $skip ) {
				++$skipped;
				$hint = Operation::ALT_TEXT === $operation->name ? ' Use --replace to write over it.' : '';
				WP_CLI::warning( sprintf( 'Attachment %s: %s%s', $arg, $skip->getMessage(), $hint ) );
				continue;
			} catch ( \Throwable $error ) {
				++$failed;
				WP_CLI::warning( sprintf( 'Attachment %s: %s', $arg, ErrorMessages::describe( $error ) ) );
				continue;
			}

			if ( $porcelain ) {
				WP_CLI::line( $line );
			} else {
				WP_CLI::log( $line );
			}
		}

		if ( $failed > 0 ) {
			WP_CLI::error( sprintf( '%d of %d failed.', $failed, count( $args ) ) );
		}
		if ( ! $porcelain ) {
			$done = count( $args ) - $skipped;
			WP_CLI::success(
				$skipped > 0
					? sprintf( '%s: %d done, %d skipped.', $operation->key(), $done, $skipped )
					: sprintf( '%s: %d done.', $operation->key(), $done )
			);
		}
	}

	/**
	 * Makes the new image of one attachment.
	 *
	 * @param int       $attachment_id The source attachment.
	 * @param Operation $operation     What to do.
	 * @param bool      $porcelain     Whether only the new attachment's ID is wanted.
	 * @return string The line to print.
	 * @throws \Throwable When it failed, or nothing was done.
	 */
	private function process( int $attachment_id, Operation $operation, bool $porcelain ): string {
		$new_id = $this->processor->process( $attachment_id, $operation, 2 );
		if ( $porcelain ) {
			return (string) $new_id;
		}

		$line = sprintf( 'Attachment %d: saved as attachment %d (%s).', $attachment_id, $new_id, wp_basename( (string) get_attached_file( $new_id ) ) );
		$note = Processor::result_note( $new_id, $operation );

		return '' !== $note ? $line . ' ' . $note : $line;
	}

	/**
	 * Writes the alternative text of one attachment.
	 *
	 * @param int       $attachment_id The attachment.
	 * @param Operation $operation     Write alt text, keeping or replacing what is there.
	 * @param bool      $porcelain     Whether only the attachment's ID is wanted.
	 * @return string The line to print.
	 * @throws \Throwable When it failed, or the image has text that is to be kept.
	 */
	private function write_alt_text( int $attachment_id, Operation $operation, bool $porcelain ): string {
		$text = $this->alt_text->write( $attachment_id, $operation->replace, 2 );

		return $porcelain ? (string) $attachment_id : sprintf( 'Attachment %d: the alternative text is now "%s"', $attachment_id, $text );
	}
}
